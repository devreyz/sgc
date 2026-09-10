<?php

namespace App\Services\Services;

use App\Models\Document;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
            $hash = hash('sha256', $bytes);
            $extension = match ($mime) {
                'image/jpeg' => 'jpg','image/png' => 'png','image/webp' => 'webp','application/pdf' => 'pdf'
            };
            $path = 'services/'.$execution->tenant_id.'/'.$execution->id.'/'.Str::uuid().'.'.$extension;
            Storage::disk('local')->put($path, $bytes);
            $document = new Document(['name' => $field['label'] ?? $file->getClientOriginalName(), 'original_name' => $file->getClientOriginalName(), 'path' => $path, 'disk' => 'local', 'mime_type' => $mime, 'size' => $file->getSize(), 'extension' => $extension, 'category' => $mime === 'application/pdf' ? 'comprovante' : 'foto', 'documentable_type' => ServiceExecution::class, 'documentable_id' => $execution->id, 'document_date' => now()->toDateString(), 'uploaded_by' => $actor->id]);
            $document->tenant_id = $execution->tenant_id;
            $document->save();
            $evidence = new ServiceExecutionEvidence(['service_execution_id' => $execution->id, 'document_id' => $document->id, 'field_key' => $fieldKey, 'evidence_type' => $field['evidence_type'] ?? 'other', 'phase' => $field['phase'] ?? 'execution', 'sha256' => $hash, 'metadata' => ['mime' => $mime, 'size' => $file->getSize()], 'uploaded_by' => $actor->id]);
            $evidence->tenant_id = $execution->tenant_id;
            $evidence->save();

            return $evidence->fresh('document');
        }, 3);
    }
}
