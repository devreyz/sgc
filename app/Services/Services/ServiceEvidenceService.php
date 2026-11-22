<?php

namespace App\Services\Services;

use App\Models\Document;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceVersionField;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\User;
use App\Services\TenantGoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceEvidenceService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function upload(ServiceExecution $execution, string $fieldKey, UploadedFile $file, User $actor, ?callable $trackLocalFile = null): ServiceExecutionEvidence
    {
        return DB::transaction(function () use ($execution, $fieldKey, $file, $actor, $trackLocalFile) {
            $provider = $execution->service_provider_id;
            $version = $execution->service_version_id;
            $execution = ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
            if ((int) $execution->service_provider_id !== (int) $provider || (int) $execution->service_version_id !== (int) $version) {
                throw ValidationException::withMessages(['file' => 'A ordem foi corrigida. Reabra antes de anexar.']);
            }
            if ($execution->isFrozen() || $execution->status === 'cancelled') {
                throw ValidationException::withMessages(['file' => 'Esta execução não aceita novos anexos.']);
            }$field = collect(data_get($execution->catalog_snapshot, 'fields', []))->firstWhere('key', $fieldKey);
            if (! $field || ! in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)) {
                throw ValidationException::withMessages(['field_key' => 'Campo de evidência inválido.']);
            }$mime = $file->getMimeType();
            $allowed = $field['accepted_mime_types'] ?? null;
            if (! is_array($allowed) || $allowed === []) {
                $allowed = ($field['type'] ?? null) === 'file' ? self::ALLOWED_MIMES : array_values(array_filter(self::ALLOWED_MIMES, fn (string $type): bool => str_starts_with($type, 'image/')));
            }
            $allowed = array_values(array_intersect($allowed, ServiceVersionField::FILE_MIMES));
            if (($field['type'] ?? null) !== 'file') {
                $allowed = array_values(array_filter($allowed, fn (string $type): bool => str_starts_with($type, 'image/')));
            }
            if (! in_array($mime, $allowed, true)) {
                throw ValidationException::withMessages(['file' => 'Tipo de arquivo não permitido.']);
            }if ($file->getSize() > 12 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'O arquivo deve ter no máximo 12 MB.']);
            }$bytes = file_get_contents($file->getRealPath());
            $originalSize = strlen($bytes);
            [$bytes, $mime, $extension] = (new ServiceEvidenceImageOptimizer)->optimize($bytes, $mime, 'file');
            $hash = hash('sha256', $bytes);
            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.'.$extension;
            $drive = $trackLocalFile === null && Schema::hasTable('tenant_cloud_storage_connections')
                && TenantCloudStorageConnection::query()
                    ->where('tenant_id', $execution->tenant_id)
                    ->where('provider', 'google_drive')
                    ->where('status', 'active')
                    ->exists();
            if ($drive) {
                $tenant = Tenant::query()->findOrFail($execution->tenant_id);
                $cloud = app(TenantGoogleDriveService::class)->putDocument(
                    $tenant,
                    $execution,
                    'service_evidence_'.substr(hash('sha256', $fieldKey.Str::uuid()), 0, 40),
                    ['Serviços', $execution->order?->number ?? 'Execução '.$execution->id, 'Evidências'],
                    $filename,
                    $bytes,
                    $mime,
                );
                $path = $cloud->id;
                $disk = 'google_drive';
            } else {
                $path = 'services/'.$execution->tenant_id.'/'.$execution->id.'/'.Str::uuid().'.'.$extension;
                if (! Storage::disk('local')->put($path, $bytes)) {
                    throw new \RuntimeException('Não foi possível armazenar a evidência.');
                }
                $trackLocalFile?->__invoke($path);
                $disk = 'local';
            }
            $document = new Document(['name' => $field['label'] ?? $filename, 'original_name' => $filename, 'path' => $path, 'disk' => $disk, 'mime_type' => $mime, 'size' => strlen($bytes), 'extension' => $extension, 'category' => $mime === 'application/pdf' ? 'comprovante' : 'foto', 'documentable_type' => ServiceExecution::class, 'documentable_id' => $execution->id, 'document_date' => now()->toDateString(), 'uploaded_by' => $actor->id]);
            $document->tenant_id = $execution->tenant_id;
            $document->save();
            $evidence = new ServiceExecutionEvidence(['service_execution_id' => $execution->id, 'document_id' => $document->id, 'field_key' => $fieldKey, 'evidence_type' => $field['evidence_type'] ?? 'other', 'phase' => $field['phase'] ?? 'execution', 'sha256' => $hash, 'metadata' => ['mime' => $mime, 'size' => strlen($bytes), 'original_size' => $originalSize, 'optimized' => $mime === 'image/webp', 'storage' => $disk], 'uploaded_by' => $actor->id]);
            $evidence->tenant_id = $execution->tenant_id;
            $evidence->save();

            return $evidence->fresh('document');
        }, 3);
    }

    public function contents(Document $document): string
    {
        if ($document->disk === 'google_drive') {
            $cloud = \App\Models\CloudDocument::query()->where('tenant_id', $document->tenant_id)->whereKey($document->path)->firstOrFail();
            $contents = app(TenantGoogleDriveService::class)->contents($cloud);
            if ($contents === null) {
                throw ValidationException::withMessages(['file' => 'O arquivo não está disponível no Google Drive neste momento.']);
            }

            return $contents;
        }

        abort_unless($document->disk === 'local' && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->get($document->path);
    }

    public function view(Request $request, ServiceExecutionEvidence $evidence): Response
    {
        $document = $evidence->document;
        abort_unless($document && (int) $document->tenant_id === (int) $evidence->tenant_id, 404);
        $cacheImage = in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
        $response = response('', 200, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => $cacheImage ? 'private, no-cache' : 'private, no-store',
            'Vary' => 'Cookie',
        ]);
        $fallbackName = 'evidencia-'.$evidence->id.'.'.$document->extension;
        $filename = basename(str_replace('\\', '/', (string) $document->original_name)) ?: $fallbackName;
        $response->headers->set('Content-Disposition',
            $response->headers->makeDisposition('inline', $filename, $fallbackName));
        if ($cacheImage) {
            $version = hash('sha256', implode('|', [$document->tenant_id, $document->id, $document->disk,
                $document->path, $document->updated_at, $evidence->sha256]));
            $response->setEtag($version);
            if ($response->isNotModified($request)) {
                return $response;
            }
            if ((int) $document->size > 0 && (int) $document->size <= 2 * 1024 * 1024) {
                return $response->setContent(Cache::remember('service-evidence:'.$version, 90, fn () => $this->contents($document)));
            }
        }

        return $response->setContent($this->contents($document));
    }
}
