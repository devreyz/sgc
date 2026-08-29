<?php

namespace App\Jobs;

use App\Models\AssociateReceipt;
use App\Models\TenantCloudStorageConnection;
use App\Services\AssociateReceiptArchiveService;
use App\Services\AssociateReceiptDriveState;
use App\Services\TenantNotificationDispatcher;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncAssociateReceiptToDrive implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public int $tries = 3;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public function __construct(public readonly int $receiptId)
    {
        $this->onQueue('documents');
    }

    public function uniqueId(): string
    {
        return 'associate-receipt-'.$this->receiptId;
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(
        AssociateReceiptArchiveService $archive,
        AssociateReceiptDriveState $state,
        TenantNotificationDispatcher $notifications,
    ): void
    {
        $receipt = AssociateReceipt::withoutGlobalScopes()->find($this->receiptId);
        if (! $receipt) {
            Log::notice('Google Drive receipt synchronization skipped because the receipt no longer exists.', [
                'receipt_id' => $this->receiptId,
            ]);

            return;
        }

        if (! TenantCloudStorageConnection::query()
            ->where('tenant_id', $receipt->tenant_id)
            ->where('status', 'active')
            ->exists()) {
            Log::notice('Google Drive receipt synchronization skipped because the connection is inactive.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
            ]);

            return;
        }

        $fingerprint = $state->fingerprint($receipt);
        if ($state->alreadyHandled($receipt, $fingerprint)) {
            Log::debug('Google Drive receipt synchronization skipped because this exact version was already handled.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
            ]);

            return;
        }

        if (! $state->eligible($receipt)) {
            $state->recordRejected(
                $receipt,
                $fingerprint,
                'O comprovante não possui distribuições financeiras aprovadas. Uma nova sincronização ocorrerá somente após alteração do comprovante.',
            );
            Log::notice('Google Drive receipt synchronization discarded as permanently invalid for this version.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
            ]);

            return;
        }

        try {
            $archive->sync($receipt);
            $state->recordSynced($receipt, $fingerprint);
            try {
                $this->notifySynced($receipt, $notifications);
            } catch (Throwable $notificationError) {
                Log::warning('Google Drive receipt was synced but its administrative notification could not be created.', [
                    'tenant_id' => $receipt->tenant_id,
                    'receipt_id' => $receipt->id,
                    'error' => mb_substr($notificationError->getMessage(), 0, 300),
                ]);
            }

            // Se o comprovante mudou durante a geração, agenda somente a versão
            // atual. O lock foi liberado ao iniciar este job.
            $fresh = AssociateReceipt::withoutGlobalScopes()->find($receipt->id);
            if ($fresh && $state->fingerprint($fresh) !== $fingerprint) {
                self::dispatch($receipt->id);
            }
        } catch (Throwable $exception) {
            $message = mb_strtolower($exception->getMessage());
            if (str_contains($message, 'distribuicoes financeiras')
                || str_contains($message, 'distribuições financeiras')
                || str_contains($message, 'tenant, projeto ou associado')) {
                $state->recordRejected($receipt, $fingerprint, $this->safeFailureMessage($exception));
                Log::notice('Google Drive receipt synchronization discarded after permanent validation failure.', [
                    'tenant_id' => $receipt->tenant_id,
                    'receipt_id' => $receipt->id,
                ]);

                return;
            }

            activity('cloud_storage')->withProperties([
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
                'provider' => 'google_drive',
            ])->log('Falha ao sincronizar comprovante');

            Log::error('Google Drive receipt synchronization failed.', [
                'tenant_id' => $receipt->tenant_id,
                'receipt_id' => $receipt->id,
                'exception_class' => $exception::class,
                'exception_code' => $exception->getCode(),
                'error' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw new \RuntimeException(
                $this->safeFailureMessage($exception),
                0,
                $exception,
            );
        }
    }

    private function notifySynced(AssociateReceipt $receipt, TenantNotificationDispatcher $notifications): void
    {
        $tenant = Tenant::query()->find($receipt->tenant_id, ['id', 'slug']);
        if (! $tenant) {
            return;
        }

        $receipt->loadMissing(['associate', 'project']);
        $notifications->dispatchToConfiguredRoles('drive.receipt_synced', (int) $tenant->id, [
            'title' => 'Comprovante atualizado no Google Drive',
            'body' => 'O comprovante '.$receipt->formatted_number.' de '.($receipt->associate?->display_name ?: 'associado').' foi salvo na conta Google Drive da organização.',
            'url' => route('delivery.projects.producers', [
                'tenant' => $tenant->slug,
                'project' => $receipt->sales_project_id,
                'associate' => $receipt->associate_id,
                'name' => $receipt->associate?->display_name,
            ], false),
            'icon' => 'cloud-check',
            'action_label' => 'Abrir comprovante',
            'action_icon' => 'file-check-2',
        ]);
    }

    private function safeFailureMessage(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'distribuicoes financeiras') => 'O comprovante ainda não possui distribuições financeiras aprovadas para sincronizar.',
            str_contains($message, 'tenant, projeto ou associado') => 'O comprovante está incompleto e não pode ser sincronizado.',
            str_contains($message, 'reconect') || str_contains($message, 'token') => 'A conexão com o Google Drive precisa ser refeita.',
            default => 'Não foi possível sincronizar o comprovante com o Google Drive. Consulte o diagnóstico da conexão.',
        };
    }
}
