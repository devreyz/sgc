<?php

namespace App\Services;

use App\Models\CloudDocument;
use App\Models\Expense;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as DriveException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;

class DrivePortalFiles
{
    private const FOLDER = 'application/vnd.google-apps.folder';
    private const SHORTCUT = 'application/vnd.google-apps.shortcut';
    private const PDF_EXPORT = [
        'application/vnd.google-apps.document',
        'application/vnd.google-apps.spreadsheet',
        'application/vnd.google-apps.presentation',
        'application/vnd.google-apps.drawing',
    ];
    private const INLINE_TYPES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'text/plain', 'text/csv', 'audio/mpeg', 'audio/ogg', 'video/mp4', 'video/webm',
    ];
    private const FIELDS = 'id,name,mimeType,parents,trashed,explicitlyTrashed,size,modifiedTime,appProperties,capabilities(canAddChildren,canDownload,canRename,canTrash,canUntrash,canMoveItemWithinDrive)';

    private readonly string $rootId;

    private array $metadata;

    public function __construct(private readonly Drive $drive, DriveFile $root, private readonly int $tenantId)
    {
        $this->rootId = $root->getId();
        $this->metadata = [$this->rootId => $root];
    }

    public function listing(?string $folder, bool $trash, ?string $term, ?string $pageToken): array
    {
        [$target, $path] = $this->resolveFolder($folder);
        $query = "'{$target->getId()}' in parents and trashed = ".($trash ? 'true' : 'false');
        if (filled($term)) {
            $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $term);
            $query .= " and name contains '{$escaped}'";
        }

        $page = $this->drive->files->listFiles(array_filter([
            'q' => $query,
            'spaces' => 'drive',
            'fields' => 'nextPageToken,files('.self::FIELDS.')',
            'orderBy' => 'name_natural',
            'pageSize' => 60,
            'pageToken' => $pageToken,
        ]));

        $items = [];
        foreach ($page->getFiles() ?? [] as $file) {
            if ($this->parentId($file) === $target->getId() && (! $trash || $file->getExplicitlyTrashed())) {
                $items[] = $this->item($file);
            }
        }

        $legacyExpenses = [];
        foreach ([...$path, ...array_filter($items, fn ($item) => $item['folder'])] as $entry) {
            if (preg_match('/^Despesa (\d+)$/i', $entry['name'], $matches)) {
                $legacyExpenses[$entry['id']] = (int) $matches[1];
            }
        }
        if ($legacyExpenses) {
            $descriptions = Expense::query()->withTrashed()->where('tenant_id', $this->tenantId)
                ->whereIn('id', array_values($legacyExpenses))->pluck('description', 'id');
            $addLabel = function (array $entry) use ($descriptions, $legacyExpenses): array {
                $id = $legacyExpenses[$entry['id']] ?? null;
                if ($id && ($description = $descriptions->get($id))) {
                    $entry['label'] = 'DES-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT).' · '.$description;
                }

                return $entry;
            };
            $items = array_map($addLabel, $items);
            $path = array_map($addLabel, $path);
        }

        return ['items' => $items, 'path' => $path, 'nextPage' => $page->getNextPageToken(),
            'canAdd' => (bool) $target->getCapabilities()?->getCanAddChildren()];
    }

    public function createFolder(?string $parent, string $name): array
    {
        [$folder] = $this->resolveFolder($parent);
        abort_unless($folder->getCapabilities()?->getCanAddChildren(), 403);
        return $this->item($this->drive->files->create(new DriveFile([
            'name' => $name, 'mimeType' => self::FOLDER, 'parents' => [$folder->getId()],
        ]), ['fields' => self::FIELDS]));
    }

    public function upload(?string $parent, UploadedFile $upload): array
    {
        [$folder] = $this->resolveFolder($parent);
        abort_unless($folder->getCapabilities()?->getCanAddChildren(), 403);
        $name = $this->cleanName($upload->getClientOriginalName());
        $mime = $upload->getMimeType() ?: 'application/octet-stream';

        return $this->item($this->drive->files->create(
            new DriveFile(['name' => $name, 'parents' => [$folder->getId()]]),
            ['data' => $upload->getContent(), 'mimeType' => $mime, 'uploadType' => 'multipart', 'fields' => self::FIELDS],
        ));
    }

    public function change(string $id, array $data): array
    {
        abort_if($id === $this->rootId, 404);
        [$file, $previousPath] = $this->resolve($id, $data['action'] === 'restore');
        $options = ['fields' => self::FIELDS];
        $changes = new DriveFile;

        switch ($data['action']) {
            case 'rename':
                abort_unless($file->getCapabilities()?->getCanRename(), 403);
                $changes->setName($data['name']);
                $properties = $file->getAppProperties() ?? [];
                if ($file->getMimeType() === self::FOLDER && ! isset($properties['sgcCanonicalName'])
                    && strlen((string) $file->getName()) <= 100) {
                    $properties['sgcCanonicalName'] = $file->getName();
                    $changes->setAppProperties($properties);
                }
                break;
            case 'move':
                abort_unless($file->getCapabilities()?->getCanMoveItemWithinDrive(), 403);
                [$target, $destination] = $this->resolveFolder($data['destination'] ?? null);
                abort_unless($target->getCapabilities()?->getCanAddChildren(), 403);
                if (in_array($id, array_column($destination, 'id'), true)) {
                    throw ValidationException::withMessages(['destination' => 'Escolha uma pasta que esteja fora do item movido.']);
                }
                if ($this->parentId($file) === $target->getId()) {
                    return $this->item($file);
                }
                $options['addParents'] = $target->getId();
                $options['removeParents'] = $this->parentId($file);
                break;
            case 'trash':
                abort_unless($file->getCapabilities()?->getCanTrash(), 403);
                $changes->setTrashed(true);
                break;
            case 'restore':
                abort_unless($file->getExplicitlyTrashed() && $file->getCapabilities()?->getCanUntrash(), 403);
                $changes->setTrashed(false);
                break;
        }

        $changesPath = in_array($data['action'], ['rename', 'move'], true);
        $references = $changesPath ? $this->documentReferences($file, $previousPath) : [];
        $updated = $this->drive->files->update($id, $changes, $options);
        $this->metadata[$id] = $updated;
        if ($changesPath) {
            $path = $destination ?? array_slice($previousPath, 0, -1);
            $path[] = ['name' => $updated->getName()];
            $prefix = $this->relativePath($path);
            foreach ($references as [$document, $suffix]) {
                $metadata = $document->metadata ?? [];
                if ($file->getMimeType() === self::FOLDER || $data['action'] === 'move') {
                    $metadata['drive_custom_parent'] = true;
                }
                if ($file->getMimeType() !== self::FOLDER && $data['action'] === 'rename') {
                    $metadata['drive_custom_name'] = $updated->getName();
                }
                $document->forceFill([
                    'remote_path' => $prefix.$suffix,
                    'remote_folder_id' => $suffix === '' ? $this->parentId($updated) : $document->remote_folder_id,
                    'metadata' => $metadata,
                ])->save();
            }
        }

        return $this->item($updated);
    }

    private function documentReferences(DriveFile $file, array $path): array
    {
        $query = CloudDocument::query()->where('tenant_id', $this->tenantId)->where('provider', 'google_drive')
            ->whereNull('drive_archive_id')->whereNotNull('remote_file_id');
        if ($file->getMimeType() !== self::FOLDER) {
            return $query->where('remote_file_id', $file->getId())->get()->map(fn ($document) => [$document, ''])->all();
        }

        $prefix = $this->relativePath($path).'/';
        $references = [];
        foreach ($query->whereNotNull('remote_folder_id')->where('remote_path', 'like', $prefix.'%')->cursor() as $document) {
            if (! str_starts_with($document->remote_path, $prefix)) {
                continue;
            }
            try {
                [, $parents] = $this->resolveFolder($document->remote_folder_id);
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                continue;
            }
            if (in_array($file->getId(), array_column($parents, 'id'), true)) {
                $references[] = [$document, substr($document->remote_path, strlen($prefix) - 1)];
            }
        }

        return $references;
    }

    private function relativePath(array $path): string
    {
        return implode('/', array_column(array_slice($path, 1), 'name'));
    }

    public function download(string $id): array
    {
        abort_if($id === $this->rootId, 404);
        [$file] = $this->resolve($id);
        $mime = $file->getMimeType();
        abort_if(in_array($mime, [self::FOLDER, self::SHORTCUT], true), 404);
        abort_unless($file->getCapabilities()?->getCanDownload(), 403);
        $name = $this->cleanName($file->getName());

        if (in_array($mime, self::PDF_EXPORT, true)) {
            $mime = 'application/pdf';
            $name .= '.pdf';
            $response = $this->drive->files->export($id, $mime);
        } else {
            $response = $this->drive->files->get($id, ['alt' => 'media']);
        }

        abort_unless($response instanceof ResponseInterface, 502);

        return [
            'stream' => $response->getBody(),
            'name' => $name,
            'size' => (int) ($response->getHeaderLine('Content-Length') ?: 0),
            'mime' => in_array($mime, self::INLINE_TYPES, true) ? $mime : 'application/octet-stream',
        ];
    }

    private function resolveFolder(?string $id): array
    {
        [$folder, $path] = $this->resolve($id ?? $this->rootId);
        abort_unless($folder->getMimeType() === self::FOLDER, 404);

        return [$folder, $path];
    }

    /** The item's actual parent chain must terminate at this tenant's root. */
    private function resolve(string $id, bool $allowTrashedItem = false): array
    {
        $item = $node = $this->file($id);
        $path = [];
        $seen = [];
        while (true) {
            $nodeId = $node->getId();
            abort_if(! $nodeId || isset($seen[$nodeId]) || count($seen) >= 100, 404);
            abort_if($node->getTrashed() && (! $allowTrashedItem || $nodeId !== $id), 404);
            $seen[$nodeId] = true;
            $path[] = ['id' => $nodeId, 'name' => $node->getName() ?: 'Pasta sem nome'];
            if ($nodeId === $this->rootId) {
                return [$item, array_reverse($path)];
            }
            $parent = $this->parentId($node);
            abort_unless($parent, 404);
            $node = $this->file($parent);
            abort_unless($node->getMimeType() === self::FOLDER, 404);
        }
    }

    private function file(string $id): DriveFile
    {
        try {
            return $this->metadata[$id] ??= $this->drive->files->get($id, ['fields' => self::FIELDS]);
        } catch (DriveException $exception) {
            abort_if((int) $exception->getCode() === 404, 404);

            throw $exception;
        }
    }

    private function parentId(DriveFile $file): ?string
    {
        $parents = $file->getParents() ?? [];

        return count($parents) === 1 ? $parents[0] : null;
    }

    private function item(DriveFile $file): array
    {
        $mime = $file->getMimeType();
        $capabilities = $file->getCapabilities();
        $download = ! in_array($mime, [self::FOLDER, self::SHORTCUT], true) && $capabilities?->getCanDownload();
        $preview = in_array($mime, self::PDF_EXPORT, true) ? 'application/pdf' : (in_array($mime, self::INLINE_TYPES, true) ? $mime : null);

        return [
            'id' => $file->getId(), 'name' => $file->getName() ?: ($mime === self::FOLDER ? 'Pasta sem nome' : 'Arquivo sem nome'), 'folder' => $mime === self::FOLDER,
            'size' => $file->getSize(), 'modified' => $file->getModifiedTime(),
            'preview' => $download ? $preview : null, 'download' => (bool) $download,
            'rename' => (bool) $capabilities?->getCanRename(), 'move' => (bool) $capabilities?->getCanMoveItemWithinDrive(),
            'trash' => (bool) $capabilities?->getCanTrash(), 'restore' => (bool) $capabilities?->getCanUntrash(),
        ];
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('~[\x00-\x1f\x7f/\\\\]+~', '-', $name) ?? '');

        return in_array($name, ['', '.', '..'], true) ? 'arquivo' : $name;
    }
}
