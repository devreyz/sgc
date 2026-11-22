<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Tenant;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SecretaryDocumentAssets
{
    public function __construct(private readonly DocumentContentSanitizer $sanitizer) {}

    public function images(Tenant $tenant)
    {
        return Document::query()->where('tenant_id', $tenant->id)
            ->where('documentable_type', $tenant->getMorphClass())->where('documentable_id', $tenant->id)
            ->where('disk', 'local')->where('path', 'like', $this->directory($tenant).'/%');
    }

    public function upload(Tenant $tenant, UploadedFile $file, int $userId): Document
    {
        $metadata = getimagesize($file->getPathname());
        $extension = match ($metadata['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };
        if (! $extension || $metadata[0] * $metadata[1] > 20_000_000) {
            throw ValidationException::withMessages(['image' => 'Envie uma imagem PNG, JPEG ou WebP de até 20 megapixels.']);
        }
        $name = (string) Str::ulid().'.'.$extension;
        $path = $file->storeAs($this->directory($tenant), $name, 'local');
        abort_unless($path, 507, 'Não foi possível guardar a imagem.');

        try {
            return Document::query()->forceCreate([
                'tenant_id' => $tenant->id, 'documentable_type' => $tenant->getMorphClass(),
                'documentable_id' => $tenant->id, 'path' => $path, 'disk' => 'local',
                'name' => Str::limit($file->getClientOriginalName(), 180, ''),
                'original_name' => Str::limit($file->getClientOriginalName(), 180, ''),
                'category' => 'foto', 'mime_type' => $metadata['mime'],
                'extension' => $extension, 'size' => $file->getSize(), 'uploaded_by' => $userId,
            ]);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
    }

    public function url(Tenant $tenant, int $imageId): string
    {
        return route('secretary.images.show', ['tenant' => $tenant->slug, 'image' => $imageId], false);
    }

    public function clean(Tenant $tenant, ?string $html, bool $forPdf = false, ?string $previousHtml = null): string
    {
        $sources = [];
        return $this->sanitizer->sanitize($html, function (string $source) use ($tenant, $forPdf, $previousHtml, &$sources): ?string {
            if (array_key_exists($source, $sources)) {
                return $sources[$source];
            }
            if (preg_match('~^/[^/]+/secretary/images/([1-9][0-9]*)$~D', $source, $matches)) {
                $image = $this->images($tenant)->find($matches[1]);
                return $sources[$source] = $image ? ($forPdf
                    ? $this->embedded(Storage::disk('local'), $image->path)
                    : $this->url($tenant, $image->id)) : null;
            }
            $prefix = 'tenants/'.$tenant->id.'/secretary/images/';
            if (preg_match('~^/storage/('.preg_quote($prefix, '~').'[A-Za-z0-9_-]+\.(?:png|jpe?g|webp))$~iD', $source, $matches)) {
                return $sources[$source] = $forPdf ? $this->embedded(Storage::disk('public'), $matches[1]) : $source;
            }
            if ($previousHtml && str_contains($previousHtml, $source)
                && preg_match('~^data:image/(?:png|jpe?g|webp);base64,[A-Za-z0-9+/=]+$~iD', $source)) {
                return $sources[$source] = $source;
            }

            return $sources[$source] = null;
        });
    }

    public function logo(Tenant $tenant): ?string
    {
        $path = ltrim((string) $tenant->logo, '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, ':') || str_contains($path, '\\')) {
            return null;
        }

        return $this->embedded(Storage::disk('public'), $path);
    }

    private function embedded(FilesystemAdapter $disk, string $path): ?string
    {
        $bytes = $disk->get($path);
        $mime = $bytes ? (getimagesizefromstring($bytes)['mime'] ?? null) : null;
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function directory(Tenant $tenant): string
    {
        return 'tenants/'.$tenant->id.'/secretary/images';
    }
}
