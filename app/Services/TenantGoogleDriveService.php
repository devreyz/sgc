<?php

namespace App\Services;

use App\Models\ArchivedGoogleDriveConnection;
use App\Models\CloudDocument;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as DriveException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TenantGoogleDriveService
{
    public function __construct(private readonly GoogleDriveClientFactory $clients)
    {
    }

    public function ensureRootFolder(TenantCloudStorageConnection $connection, ?Drive $drive = null): string
    {
        $drive ??= $this->clients->forConnection($connection);
        $folderId = $this->prepareRootFolder($drive, $connection->tenant, $connection->root_folder_id);
        if ($folderId !== $connection->root_folder_id) {
            $connection->forceFill(['root_folder_id' => $folderId])->save();
        }

        return $folderId;
    }

    public function prepareRootFolder(Drive $drive, Tenant $tenant, ?string $existingRoot = null, ?string $selectedFolder = null): string
    {
        if ($existingRoot) {
            if (! $this->ownsDestination($drive, $existingRoot, $tenant)) {
                throw new RuntimeException('A pasta vinculada não é uma pasta do SGC desta organização.');
            }

            return $existingRoot;
        }

        if ($selectedFolder) {
            $this->validateDestination($drive, $selectedFolder);
        }
        if ($root = $this->findOwnedRoot($drive, $tenant, $selectedFolder)) {
            return $root;
        }

        $metadata = [
            'name' => 'SGC - '.$this->safeSegment($tenant->name),
            'mimeType' => 'application/vnd.google-apps.folder',
            'appProperties' => [
                'sgc_tenant' => (string) $tenant->id,
                'sgc_type' => 'root',
            ],
        ];
        if ($selectedFolder) {
            $metadata['parents'] = [$selectedFolder];
        }

        $folder = $drive->files->create(new DriveFile($metadata), ['fields' => 'id,ownedByMe']);
        if (! $folder->getId() || ! $folder->getOwnedByMe()) {
            throw new RuntimeException('Não foi possível criar uma pasta própria na conta autorizada.');
        }

        return (string) $folder->id;
    }

    public function findOwnedRoot(Drive $drive, Tenant $tenant, ?string $parentId = null): ?string
    {
        $query = "mimeType = 'application/vnd.google-apps.folder' and trashed = false and 'me' in owners and appProperties has { key='sgc_tenant' and value='".(int) $tenant->id."' } and appProperties has { key='sgc_type' and value='root' }";
        if ($parentId !== null) {
            if (! preg_match('/\A[A-Za-z0-9_-]+\z/', $parentId)) {
                throw new RuntimeException('Identificador de pasta inválido.');
            }
            $query .= " and '{$parentId}' in parents";
        }

        $pageToken = null;
        do {
            $existing = $drive->files->listFiles(array_filter([
                'q' => $query,
                'spaces' => 'drive',
                'fields' => 'nextPageToken,files(id,ownedByMe,capabilities(canAddChildren))',
                'orderBy' => 'createdTime',
                'pageSize' => 25,
                'pageToken' => $pageToken,
            ]));
            foreach ($existing->getFiles() ?? [] as $folder) {
                if ($folder->getId() && $folder->getOwnedByMe() && $folder->getCapabilities()?->getCanAddChildren()) {
                    return $folder->getId();
                }
            }
            $pageToken = $existing->getNextPageToken();
        } while ($pageToken);

        return null;
    }

    private function validateDestination(Drive $drive, string $folderId): void
    {
        if (! $this->ownsDestination($drive, $folderId)) {
            throw new RuntimeException('Escolha uma pasta própria da conta autorizada.');
        }
    }

    public function ownsDestination(Drive $drive, ?string $folderId, ?Tenant $rootTenant = null): bool
    {
        if (! $folderId || ! preg_match('/\A[A-Za-z0-9_-]+\z/', $folderId)) {
            return false;
        }

        $folder = $this->getDriveFile($drive, $folderId, 'mimeType,trashed,driveId,ownedByMe,capabilities(canAddChildren),appProperties');
        $properties = $folder?->getAppProperties() ?? [];

        return $folder?->getMimeType() === 'application/vnd.google-apps.folder'
            && (! $rootTenant || (($properties['sgc_type'] ?? null) === 'root' && ($properties['sgc_tenant'] ?? null) === (string) $rootTenant->id))
            && ! $folder->getTrashed() && ! $folder->getDriveId()
            && $folder->getOwnedByMe() === true && $folder->getCapabilities()?->getCanAddChildren() === true;
    }

    public function putDocument(
        Tenant $tenant,
        Model $owner,
        string $documentType,
        array $folders,
        string $filename,
        string $contents,
        string $mimeType = 'application/pdf',
    ): CloudDocument {
        $result = DB::transaction(function () use ($tenant, $owner, $documentType, $folders, $filename, $contents, $mimeType) {
            $connection = TenantCloudStorageConnection::query()
                ->where('tenant_id', $tenant->id)
                ->where('provider', 'google_drive')
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $connection) {
                throw new RuntimeException('Esta organização não possui Google Drive conectado.');
            }

            return $this->writeDocument($connection, $owner, $documentType, $folders, $filename, $contents, $mimeType);
        });

        if ($result instanceof RuntimeException) {
            throw $result;
        }

        return $result;
    }

    private function writeDocument(
        TenantCloudStorageConnection $connection,
        Model $owner,
        string $documentType,
        array $folders,
        string $filename,
        string $contents,
        string $mimeType,
    ): CloudDocument|RuntimeException {
        $identity = [
            'tenant_id' => $connection->tenant_id,
            'provider' => 'google_drive',
            'document_type' => $documentType,
            'documentable_type' => $owner->getMorphClass(),
            'documentable_id' => $owner->getKey(),
        ];
        $document = CloudDocument::query()->where($identity)->first() ?? new CloudDocument();
        if (! $document->exists) {
            $document->forceFill($identity);
        }

        $checksum = hash('sha256', $contents);
        try {
            $drive = $this->clients->forConnection($connection);
            $rootId = $parentId = $this->ensureRootFolder($connection, $drive);

            foreach ($folders as $folder) {
                $parentId = $this->findOrCreateFolder($drive, $parentId, $this->safeSegment($folder));
            }

            $actualParent = $document->remote_file_id && ! $document->drive_archive_id
                ? $this->writableParentInRoot($drive, $document->remote_file_id, $rootId, $parentId)
                : null;

            if ($actualParent === $parentId && $document->checksum === $checksum && $document->status === 'synced') {
                if ($document->remote_folder_id !== $parentId) {
                    $document->forceFill(['remote_folder_id' => $parentId])->save();
                }

                return $document;
            }

            $safeFilename = $this->safeFilename($filename);
            $options = [
                'data' => $contents,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,modifiedTime',
            ];

            if ($actualParent !== null) {
                if ($actualParent !== $parentId) {
                    $options['addParents'] = $parentId;
                    $options['removeParents'] = $actualParent;
                }

                $file = $drive->files->update(
                    $document->remote_file_id,
                    new DriveFile(['name' => $safeFilename]),
                    $options,
                );
            } else {
                $file = $drive->files->create(
                    new DriveFile(['name' => $safeFilename, 'parents' => [$parentId]]),
                    $options,
                );
            }

            $document->forceFill([
                'drive_archive_id' => null,
                'remote_file_id' => $file->id,
                'remote_folder_id' => $parentId,
                'remote_path' => implode('/', [...$folders, $safeFilename]),
                'checksum' => $checksum,
                'version' => $document->exists ? ((int) $document->version + 1) : 1,
                'status' => 'synced',
                'synced_at' => now(),
                'last_error' => null,
            ])->save();

            $connection->forceFill(['last_sync_at' => now(), 'last_error' => null])->save();

            return $document;
        } catch (Throwable $exception) {
            $safeError = $this->safeConnectionError($exception);
            if (! $document->drive_archive_id) {
                $document->forceFill([
                    'remote_path' => implode('/', [...$folders, $filename]),
                    'status' => 'failed',
                    'last_error' => $safeError,
                ])->save();
            }
            $connection->forceFill([
                'last_error' => $safeError,
            ])->save();

            return new RuntimeException($safeError, 0, $exception);
        }
    }

    private function writableParentInRoot(Drive $drive, string $fileId, string $rootId, string $targetId): ?string
    {
        $file = $this->getDriveFile($drive, $fileId, 'mimeType,parents,trashed,ownedByMe,capabilities(canEdit)');
        $parents = $file?->getParents() ?? [];
        if (! $file || $file->getTrashed() || ! $file->getOwnedByMe() || ! $file->getCapabilities()?->getCanEdit()
            || in_array($file->getMimeType(), ['application/vnd.google-apps.folder', 'application/vnd.google-apps.shortcut'], true)
            || count($parents) !== 1) {
            return null;
        }

        $parentId = $parents[0];
        $ancestor = $parentId;
        $visited = [];
        while ($ancestor && ! isset($visited[$ancestor])) {
            if ($ancestor === $rootId || $ancestor === $targetId) {
                return $parentId;
            }

            $visited[$ancestor] = true;
            $folder = $this->getDriveFile($drive, $ancestor, 'mimeType,parents,trashed,ownedByMe');
            if ($folder?->getMimeType() !== 'application/vnd.google-apps.folder' || $folder->getTrashed() || ! $folder->getOwnedByMe()) {
                return null;
            }
            $parents = $folder->getParents() ?? [];
            $ancestor = count($parents) === 1 ? $parents[0] : null;
        }

        return null;
    }

    private function getDriveFile(Drive $drive, string $id, string $fields): ?DriveFile
    {
        try {
            return $drive->files->get($id, ['fields' => $fields, 'supportsAllDrives' => true]);
        } catch (DriveException $exception) {
            if ((int) $exception->getCode() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    public function archiveDocuments(TenantCloudStorageConnection $connection): void
    {
        $documents = CloudDocument::query()->where('tenant_id', $connection->tenant_id)
            ->where('provider', 'google_drive')->whereNotNull('remote_file_id')->whereNull('drive_archive_id');

        if (! $documents->exists()) {
            return;
        }

        $archive = new ArchivedGoogleDriveConnection;
        $archive->forceFill([
            'tenant_id' => $connection->tenant_id,
            'oauth_client_id' => $connection->oauth_client_id,
            'oauth_client_secret' => $connection->oauth_client_secret,
            'refresh_token' => $connection->refresh_token,
        ])->save();

        $documents->update(['drive_archive_id' => $archive->id]);
    }

    public function disconnect(TenantCloudStorageConnection $connection): void
    {
        // Revogar o projeto no Google também pode desligar outras organizações que usam a mesma conta.
        $connection->forceFill([
            'refresh_token' => null,
            'granted_scopes' => [],
            'status' => 'revoked',
            'last_error' => null,
        ])->save();
    }

    /**
     * Obtém um arquivo já sincronizado sem expor URL pública do Drive. A
     * autorização da aplicação continua sendo conferida pelo controlador.
     */
    public function contents(CloudDocument $document): ?string
    {
        if ($document->provider !== 'google_drive'
            || ($document->status !== 'synced' && ! $document->drive_archive_id)
            || ! $document->remote_file_id) {
            return null;
        }

        $connection = TenantCloudStorageConnection::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->first();
        if (! $connection) {
            return null;
        }

        if ($document->drive_archive_id) {
            $archive = ArchivedGoogleDriveConnection::query()->where('tenant_id', $document->tenant_id)->find($document->drive_archive_id);
            if ($archive && ($contents = $this->readDocument($archive, $document)) !== null) {
                return $contents;
            }
        }

        return $this->readDocument($connection, $document);
    }

    private function readDocument(TenantCloudStorageConnection $connection, CloudDocument $document): ?string
    {
        try {
            $response = $this->clients->forConnection($connection)
                ->files
                ->get($document->remote_file_id, ['alt' => 'media']);

            return method_exists($response, 'getBody')
                ? (string) $response->getBody()
                : null;
        } catch (Throwable) {
            // A visualização pode regenerar o PDF quando o Drive estiver
            // temporariamente indisponível; não vaza o diagnóstico ao membro.
            return null;
        }
    }

    private function findOrCreateFolder(Drive $drive, string $parentId, string $name): string
    {
        $escaped = str_replace(["\\", "'"], ["\\\\", "\\'"], $name);
        $files = $drive->files->listFiles([
            'q' => "name = '{$escaped}' and mimeType = 'application/vnd.google-apps.folder' and '{$parentId}' in parents and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        if ($files->getFiles() !== []) {
            return (string) $files->getFiles()[0]->id;
        }

        $folder = $drive->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]), ['fields' => 'id']);

        return (string) $folder->id;
    }

    private function safeSegment(string $value): string
    {
        $value = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', '-', $value) ?? '');

        return mb_substr($value !== '' ? $value : 'Sem nome', 0, 120);
    }

    private function safeFilename(string $value): string
    {
        return mb_substr($this->safeSegment($value), 0, 180);
    }

    private function safeConnectionError(Throwable $exception): string
    {
        $messages = [];
        for ($current = $exception; $current; $current = $current->getPrevious()) {
            $messages[] = mb_strtolower($current->getMessage());
        }
        $message = implode(' ', $messages);

        return match (true) {
            str_contains($message, 'invalid_grant'),
            str_contains($message, 'unauthorized'),
            str_contains($message, '401') => 'A autorização do Google Drive expirou ou foi revogada. Reconecte a conta.',
            str_contains($message, 'insufficientpermissions'),
            str_contains($message, 'insufficient permission'),
            str_contains($message, 'forbidden'),
            str_contains($message, '403') => 'A conta conectada não possui permissão suficiente no Google Drive. Reconecte e aceite o acesso solicitado.',
            str_contains($message, 'ratelimit'),
            str_contains($message, 'quota'),
            str_contains($message, '429') => 'O limite temporário do Google Drive foi atingido. A tarefa tentará novamente.',
            str_contains($message, 'timed out'),
            str_contains($message, 'timeout'),
            str_contains($message, 'connection') => 'O Google Drive não respondeu. A tarefa tentará novamente.',
            default => 'Não foi possível enviar o documento. Consulte o log de cloud_storage para o diagnóstico técnico.',
        };
    }
}
