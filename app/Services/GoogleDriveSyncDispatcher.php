<?php

namespace App\Services;

use App\Jobs\SyncAssociateReceiptToDrive;
use App\Jobs\SyncTenantStoredFileToDrive;
use App\Models\AssociateReceipt;
use App\Models\TenantCloudStorageConnection;

class GoogleDriveSyncDispatcher
{
    /**
     * Adds all eligible tenant documents to the documents queue.
     * Jobs themselves re-check the active connection before any upload.
     */
    public function dispatchForTenant(int $tenantId): int
    {
        if ($tenantId <= 0 || ! TenantCloudStorageConnection::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->exists()) {
            return 0;
        }

        $receipts = 0;
        $state = app(AssociateReceiptDriveState::class);
        AssociateReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('delivery_ids')
            ->where('total_net', '>', 0)
            ->chunkById(100, function ($items) use (&$receipts, $state): void {
                foreach ($items as $receipt) {
                    $fingerprint = $state->fingerprint($receipt);
                    if ($state->alreadyHandled($receipt, $fingerprint)) {
                        continue;
                    }

                    SyncAssociateReceiptToDrive::dispatch((int) $receipt->id);
                    $receipts++;
                }
            });

        return $receipts + SyncTenantStoredFileToDrive::dispatchExistingForTenant($tenantId);
    }
}
