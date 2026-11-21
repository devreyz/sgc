<?php

namespace App\Console\Commands;

use App\Models\CloudDocument;
use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Services\DriveDocumentLayout;
use App\Services\TenantGoogleDriveService;
use Illuminate\Console\Command;
use Throwable;

class OrganizeTenantDrive extends Command
{
    protected $signature = 'drive:organize {tenant? : Slug da organização} {--all : Organizações com Drive conectado} {--apply : Aplicar as mudanças no Drive}';

    protected $description = 'Renomeia e agrupa por mês os documentos sincronizados da organização';

    public function handle(TenantGoogleDriveService $drive): int
    {
        if ($this->option('all') && ! $this->argument('tenant')) {
            $activeTenants = TenantCloudStorageConnection::query()->select('tenant_id')->where('provider', 'google_drive')
                ->where('status', 'active')->whereNotNull('root_folder_id');
            $exitCode = self::SUCCESS;
            foreach (Tenant::query()->whereIn('id', $activeTenants)->lazyById() as $tenant) {
                if ($this->organizeTenant($tenant, $drive) !== self::SUCCESS) {
                    $exitCode = self::FAILURE;
                }
            }

            return $exitCode;
        }

        $tenant = $this->argument('tenant') && ! $this->option('all')
            ? Tenant::query()->where('slug', $this->argument('tenant'))->first() : null;
        if (! $tenant) {
            $this->error('Informe o slug de uma organização existente ou use --all.');

            return self::FAILURE;
        }

        return $this->organizeTenant($tenant, $drive);
    }

    private function organizeTenant(Tenant $tenant, TenantGoogleDriveService $drive): int
    {
        $apply = $this->option('apply');
        $changed = $skipped = $failed = 0;
        CloudDocument::query()->where('tenant_id', $tenant->id)->where('provider', 'google_drive')
            ->where('status', 'synced')->whereNull('drive_archive_id')->whereNotNull('remote_file_id')
            ->with('documentable')->chunkById(70, function ($documents) use ($drive, $tenant, $apply, &$changed, &$skipped, &$failed): void {
                foreach ($documents as $document) {
                    $owner = $document->documentable;
                    if (! $owner || (int) $owner->tenant_id !== (int) $tenant->id) {
                        continue;
                    }
                    if (($document->metadata['drive_custom_parent'] ?? false) || ($document->metadata['drive_custom_name'] ?? false)) {
                        continue;
                    }
                    try {
                        $layout = DriveDocumentLayout::resolve($document->document_type, $owner, $document->remote_path);
                        if (! $layout || implode('/', [...$layout['folders'], $layout['filename']]) === $document->remote_path) {
                            continue;
                        }
                        if (! $apply) {
                            $this->line($document->remote_path.' → '.implode('/', [...$layout['folders'], $layout['filename']]));
                        }
                        if (! $apply || $drive->reorganizeDocument($document, $layout)) {
                            $changed++;
                        } else {
                            $skipped++;
                        }
                    } catch (Throwable) {
                        $this->warn('Não foi possível organizar o documento '.$document->id.'.');
                        $failed++;
                    }
                }
            });

        $this->info($tenant->slug.' · '.($apply ? 'Reorganizados' : 'Previstos').": {$changed} · Ignorados: {$skipped} · Falhas: {$failed}");

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
