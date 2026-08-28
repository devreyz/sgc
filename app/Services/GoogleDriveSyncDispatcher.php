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
        AssociateReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->select('id')
            ->chunkById(100, function ($items) use (&$receipts): void {
                foreach ($items as $receipt) {
                    SyncAssociateReceiptToDrive::dispatch((int) $receipt->id);
                    $receipts++;
                }
            });

        return $receipts + SyncTenantStoredFileToDrive::dispatchExistingForTenant($tenantId);
    }
}
