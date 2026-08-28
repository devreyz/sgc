<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantCloudStorageConnection;
use App\Services\GoogleDriveSyncDiagnostics;
use Illuminate\Console\Command;

class DiagnoseGoogleDriveSync extends Command
{
    protected $signature = 'drive:diagnose {--tenant= : ID da organização}';

    protected $description = 'Exibe o estado da conexão, fila e documentos sincronizados com o Google Drive';

    public function handle(GoogleDriveSyncDiagnostics $diagnostics): int
    {
        $tenantIds = $this->option('tenant')
            ? collect([(int) $this->option('tenant')])
            : TenantCloudStorageConnection::query()->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            $this->warn('Nenhuma conexão com o Google Drive foi encontrada.');

            return self::SUCCESS;
        }

        foreach ($tenantIds->unique() as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            $status = $diagnostics->forTenant((int) $tenantId);

            $this->newLine();
            $this->info(($tenant?->name ?? 'Organização')." (ID {$tenantId})");
            $this->table(['Item', 'Valor'], [
                ['Conexão', $status['connected'] ? 'ativa' : $status['connection_status']],
                ['Pasta raiz criada', $status['root_folder_ready'] ? 'sim' : 'não'],
                ['Último envio concluído', $status['last_sync_at']?->format('d/m/Y H:i:s') ?? 'nunca'],
                ['Erro da conexão', $status['last_error'] ?: 'nenhum'],
                ['Tarefas de documentos aguardando', $status['queued_documents']],
                ['Tarefas de documentos com falha', $status['failed_document_jobs']],
                ['Documentos sincronizados', $status['synced_documents']],
                ['Documentos rejeitados', $status['failed_documents']],
                ['Notificações aguardando', $status['queued_notifications']],
                ['Notificações com falha', $status['failed_notification_jobs']],
            ]);
        }

        return self::SUCCESS;
    }
}
