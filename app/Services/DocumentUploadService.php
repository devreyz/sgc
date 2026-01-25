<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentUploadService
{
    protected string $disk = 'google';

    /**
     * Upload a document to Google Drive.
     * 
     * @param UploadedFile $file
     * @param string $module Module name (e.g., 'Associados', 'Despesas', 'Projetos')
     * @param int $referenceId Reference ID for folder organization
     * @param string $documentableType Model class
     * @param int $documentableId Model ID
     * @param array $metadata Additional metadata
     * @return Document
     */
    public function upload(
        UploadedFile $file,
        string $module,
        int $referenceId,
        string $documentableType,
        int $documentableId,
        array $metadata = []
    ): Document {
        // Generate the path: /{Year}/{Module}/{ID}/
        $year = date('Y');
        $basePath = "{$year}/{$module}/{$referenceId}";

        // Generate unique filename
        $filename = $this->generateFilename($file);
        $fullPath = "{$basePath}/{$filename}";

        // Upload to Google Drive
        Storage::disk($this->disk)->put($fullPath, file_get_contents($file));

        // Create document record
        return Document::create([
            'name' => $metadata['name'] ?? $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $fullPath,
            'disk' => $this->disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => $file->getClientOriginalExtension(),
            'category' => $metadata['category'] ?? 'outro',
            'documentable_type' => $documentableType,
            'documentable_id' => $documentableId,
            'document_date' => $metadata['document_date'] ?? null,
            'expiry_date' => $metadata['expiry_date'] ?? null,
            'uploaded_by' => auth()->id(),
            'notes' => $metadata['notes'] ?? null,
        ]);
    }

    /**
     * Upload expense receipt to specific folder.
     * Path: /Financeiro/Despesas/{Year}/{Month}/
     * 
     * @param UploadedFile $file
     * @param int $expenseId
     * @param array $metadata
     * @return Document
     */
    public function uploadExpenseReceipt(
        UploadedFile $file,
        int $expenseId,
        array $metadata = []
    ): Document {
        $year = date('Y');
        $month = date('m');
        $basePath = "Financeiro/Despesas/{$year}/{$month}";

        $filename = $this->generateFilename($file);
        $fullPath = "{$basePath}/{$filename}";

        Storage::disk($this->disk)->put($fullPath, file_get_contents($file));

        return Document::create([
            'name' => $metadata['name'] ?? "Comprovante - {$file->getClientOriginalName()}",
            'original_name' => $file->getClientOriginalName(),
            'path' => $fullPath,
            'disk' => $this->disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => $file->getClientOriginalExtension(),
            'category' => 'comprovante',
            'documentable_type' => \App\Models\Expense::class,
            'documentable_id' => $expenseId,
            'document_date' => $metadata['document_date'] ?? now(),
            'uploaded_by' => auth()->id(),
            'notes' => $metadata['notes'] ?? null,
        ]);
    }

    /**
     * Delete a document from storage.
     * 
     * @param Document $document
     * @return bool
     */
    public function delete(Document $document): bool
    {
        // Delete from storage
        if ($document->path) {
            Storage::disk($document->disk)->delete($document->path);
        }

        // Delete record
        return $document->delete();
    }

    /**
     * Generate a unique filename.
     * 
     * @param UploadedFile $file
     * @return string
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Ymd_His');
        $random = Str::random(8);
        
        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get the URL for a document.
     * 
     * @param Document $document
     * @return string|null
     */
    public function getUrl(Document $document): ?string
    {
        if (!$document->path) {
            return null;
        }

        return Storage::disk($document->disk)->url($document->path);
    }

    /**
     * Download a document.
     * 
     * @param Document $document
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(Document $document)
    {
        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name
        );
    }
}
