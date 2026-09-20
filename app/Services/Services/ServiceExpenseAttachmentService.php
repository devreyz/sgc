<?php

namespace App\Services\Services;

use App\Models\CloudDocument;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Models\User;
use App\Services\TenantGoogleDriveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceExpenseAttachmentService
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function upload(Tenant $tenant, Expense $expense, UploadedFile $file, User $actor): Document
    {
        abort_unless((int) $expense->tenant_id === (int) $tenant->id && $expense->origin_module === 'services', 403);
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['attachments' => 'Envie imagens JPG, PNG ou WebP, ou documentos PDF.']);
        }
        if ($file->getSize() > 12 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachments' => 'Cada anexo deve ter no máximo 12 MB.']);
        }

        $bytes = (string) file_get_contents($file->getRealPath());
        [$bytes, $mime, $extension] = $this->optimize($bytes, $mime);
        $baseName = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'comprovante';
        $filename = Str::slug($baseName).'-'.Str::lower(Str::random(8)).'.'.$extension;
        $drive = TenantCloudStorageConnection::query()
            ->where('tenant_id', $tenant->id)
            ->where('provider', 'google_drive')
            ->where('status', 'active')
            ->exists();

        if ($drive) {
            $cloud = app(TenantGoogleDriveService::class)->putDocument(
                $tenant,
                $expense,
                'service_expense_attachment_'.Str::uuid(),
                ['Serviços', 'Despesas', now()->format('Y'), 'Despesa '.$expense->id],
                $filename,
                $bytes,
                $mime,
            );
            $path = (string) $cloud->id;
            $disk = 'google_drive';
        } else {
            $path = 'services/'.$tenant->id.'/expenses/'.$expense->id.'/'.Str::uuid().'.'.$extension;
            Storage::disk('local')->put($path, $bytes);
            $disk = 'local';
        }

        $document = new Document([
            'name' => 'Comprovante — '.$baseName,
            'original_name' => $filename,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mime,
            'size' => strlen($bytes),
            'extension' => $extension,
            'category' => 'comprovante',
            'documentable_type' => Expense::class,
            'documentable_id' => $expense->id,
            'document_date' => $expense->date,
            'uploaded_by' => $actor->id,
        ]);
        $document->tenant_id = $tenant->id;
        $document->save();

        return $document;
    }

    public function contents(Document $document): string
    {
        if ($document->disk === 'google_drive') {
            $cloud = CloudDocument::query()->where('tenant_id', $document->tenant_id)->whereKey($document->path)->firstOrFail();
            $contents = app(TenantGoogleDriveService::class)->contents($cloud);
            if ($contents === null) {
                throw ValidationException::withMessages(['attachment' => 'O anexo não está disponível no Google Drive neste momento.']);
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
            throw ValidationException::withMessages(['attachments' => 'Uma das imagens não pôde ser lida.']);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        if (max($width, $height) > 2048) {
            $ratio = 2048 / max($width, $height);
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
