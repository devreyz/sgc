<?php

namespace App\Observers;

use App\Jobs\SyncCustomerBillingReceiptToDrive;
use App\Models\CustomerBillingReceipt;
use App\Services\Accounting\BillingAuthorizationValidityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CustomerBillingReceiptObserver
{
    private const MATERIAL_FIELDS = [
        'sales_project_id', 'customer_id', 'organization_id', 'issued_at', 'from_date', 'to_date',
        'notes', 'report_annotations', 'report_annotations_position', 'delivery_ids', 'status', 'total_gross', 'total_fees', 'total_net', 'fee_snapshot',
    ];

    public function created(CustomerBillingReceipt $receipt): void
    {
        if (! $receipt->sales_project_id || ! Schema::hasTable('customer_billing_receipt_projects')) {
            return;
        }

        $receipt->projects()->syncWithoutDetaching([
            (int) $receipt->sales_project_id => ['tenant_id' => (int) $receipt->tenant_id],
        ]);

        $this->syncToDriveAfterCommit($receipt);
    }

    public function updated(CustomerBillingReceipt $receipt): void
    {
        if (! $receipt->wasChanged(self::MATERIAL_FIELDS)) {
            return;
        }

        $this->invalidateAfterCommit((int) $receipt->id, (int) $receipt->tenant_id);
        $this->syncToDriveAfterCommit($receipt);
    }

    private function syncToDriveAfterCommit(CustomerBillingReceipt $receipt): void
    {
        if (empty($receipt->delivery_ids) || (float) ($receipt->total_net ?? 0) <= 0) {
            return;
        }

        SyncCustomerBillingReceiptToDrive::dispatch((int) $receipt->id)->afterCommit();
    }

    public function deleting(CustomerBillingReceipt $receipt): void
    {
        if (! $receipt->isEditable()) {
            throw new \DomainException('Somente cobranças em rascunho podem ser excluídas.');
        }
    }

    private function invalidateAfterCommit(int $receiptId, int $tenantId): void
    {
        DB::afterCommit(function () use ($receiptId, $tenantId): void {
            try {
                $receipt = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($receiptId);
                if ($receipt) {
                    app(BillingAuthorizationValidityService::class)->invalidateIfChanged($receipt, auth()->user());
                }
            } catch (\Throwable $exception) {
                Log::error('Falha ao verificar autorização após alteração da cobrança.', [
                    'tenant_id' => $tenantId,
                    'receipt_id' => $receiptId,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }
}
