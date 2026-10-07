<?php

namespace App\Services;

use App\Jobs\SyncAssociateReceiptToDrive;
use App\Jobs\SyncCustomerBillingReceiptToDrive;
use App\Jobs\SyncTenantStoredFileToDrive;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
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
            ->where('provider', 'google_drive')
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

        $customerReceipts = 0;
        CustomerBillingReceipt::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('delivery_ids')
            ->where('total_net', '>', 0)
            ->chunkById(100, function ($items) use (&$customerReceipts): void {
                foreach ($items as $receipt) {
                    SyncCustomerBillingReceiptToDrive::dispatch((int) $receipt->id);
                    $customerReceipts++;
                }
            });

        return $receipts + $customerReceipts + SyncTenantStoredFileToDrive::dispatchExistingForTenant($tenantId);
    }
}
