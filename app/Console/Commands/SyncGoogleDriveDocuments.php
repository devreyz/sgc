<?php

namespace App\Console\Commands;

use App\Models\TenantCloudStorageConnection;
use App\Services\GoogleDriveSyncDispatcher;
use Illuminate\Console\Command;

class SyncGoogleDriveDocuments extends Command
{
    protected $signature = 'drive:sync-documents {--tenant= : ID da organização a sincronizar}';

    protected $description = 'Agenda a sincronização dos documentos de organizações com Google Drive ativo';

    public function handle(GoogleDriveSyncDispatcher $dispatcher): int
    {
        $query = TenantCloudStorageConnection::query()->where('status', 'active')->select('tenant_id')->distinct();
        if ($tenant = $this->option('tenant')) {
            $query->where('tenant_id', (int) $tenant);
        }

        $tenants = 0;
        $documents = 0;
        foreach ($query->cursor() as $connection) {
            $documents += $dispatcher->dispatchForTenant((int) $connection->tenant_id);
            $tenants++;
        }

        $this->info("Sincronização solicitada para {$documents} documento(s) em {$tenants} organização(ões).");

        return self::SUCCESS;
    }
}
