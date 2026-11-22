<?php

namespace App\Jobs;

use App\Models\GeneratedDocument;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Services\TenantGoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SyncOfficialLetterToDrive implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, Queueable;

    public int $tries = 3;
    public int $uniqueFor = 1800;
    public array $backoff = [60, 300];

    public function __construct(public readonly int $tenantId, public readonly int $documentId)
    {
        $this->onQueue('documents');
    }

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->documentId;
    }

    public function handle(TenantGoogleDriveService $drive): void
    {
        $document = GeneratedDocument::withoutGlobalScopes()->where('tenant_id', $this->tenantId)
            ->whereNull('deleted_at')->whereIn('status', ['issued', 'cancelled'])->find($this->documentId);
        $tenant = Tenant::query()->find($this->tenantId);
        if (! $document || ! $tenant || ! TenantCloudStorageConnection::query()->where('tenant_id', $tenant->id)
            ->where('provider', 'google_drive')->where('status', 'active')->exists()) {
            return;
        }
        $settings = $document->document_settings ?? [];
        $path = $settings['pdf'] ?? '';
        if (! str_starts_with($path, 'tenants/'.$tenant->id.'/secretary/issued/')) {
            return;
        }
        $contents = Storage::disk('local')->get($path);
        if ($contents === null) {
            throw new \RuntimeException('O PDF arquivado do ofício não foi encontrado.');
        }
        $drive->putDocument($tenant, $document, 'secretary_official_letter', ['Secretaria', 'Ofícios', substr($settings['letter']['date'], 0, 7)],
            'OF-'.str_replace('/', '-', $settings['number']).' - '.Str::limit($document->title, 95, '').'.pdf', $contents);
    }
}
