<?php

namespace App\Observers;

use App\Models\CustomerBillingReceipt;
use App\Services\Accounting\BillingAuthorizationValidityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerBillingReceiptObserver
{
    private const MATERIAL_FIELDS = [
        'sales_project_id', 'customer_id', 'organization_id', 'issued_at', 'from_date', 'to_date',
        'notes', 'delivery_ids', 'status', 'total_gross', 'total_fees', 'total_net', 'fee_snapshot',
    ];

    public function updated(CustomerBillingReceipt $receipt): void
    {
        if (! $receipt->wasChanged(self::MATERIAL_FIELDS)) {
            return;
        }

        $this->invalidateAfterCommit((int) $receipt->id, (int) $receipt->tenant_id);
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
