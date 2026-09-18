<?php

namespace App\Services\Services;

use App\Models\Document;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionEvidence;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\User;
use App\Services\TenantGoogleDriveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceEvidenceService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function upload(ServiceExecution $execution, string $fieldKey, UploadedFile $file, User $actor): ServiceExecutionEvidence
    {
        return DB::transaction(function () use ($execution, $fieldKey, $file, $actor) {
            $execution = ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
            if ($execution->isFrozen()) {
                throw ValidationException::withMessages(['file' => 'Execução validada não aceita novos anexos.']);
            }$field = collect(data_get($execution->catalog_snapshot, 'fields', []))->firstWhere('key', $fieldKey);
            if (! $field || ! in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)) {
                throw ValidationException::withMessages(['field_key' => 'Campo de evidência inválido.']);
            }$mime = $file->getMimeType();
            if (! in_array($mime, self::ALLOWED_MIMES, true)) {
                throw ValidationException::withMessages(['file' => 'Tipo de arquivo não permitido.']);
            }if ($file->getSize() > 12 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'O arquivo deve ter no máximo 12 MB.']);
            }$bytes = file_get_contents($file->getRealPath());
            $originalSize = strlen($bytes);
            [$bytes, $mime, $extension] = $this->optimize($bytes, $mime);
            $hash = hash('sha256', $bytes);
            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.'.$extension;
            $drive = Schema::hasTable('tenant_cloud_storage_connections')
                && TenantCloudStorageConnection::query()->where('tenant_id', $execution->tenant_id)->where('status', 'active')->exists();
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
                Storage::disk('local')->put($path, $bytes);
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

    private function optimize(string $bytes, string $mime): array
    {
        if ($mime === 'application/pdf') {
            return [$bytes, $mime, 'pdf'];
        }
        $image = @imagecreatefromstring($bytes);
        if (! $image) {
            throw ValidationException::withMessages(['file' => 'A imagem enviada não pôde ser lida.']);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $max = 2048;
        if (max($width, $height) > $max) {
            $ratio = $max / max($width, $height);
            $resized = imagescale($image, max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)), IMG_BICUBIC_FIXED);
            imagedestroy($image);
            $image = $resized;
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);
        ob_start();
        imagewebp($image, null, 82);
        $optimized = (string) ob_get_clean();
        imagedestroy($image);

        return [$optimized, 'image/webp', 'webp'];
    }
}
