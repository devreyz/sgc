<?php

namespace App\Services;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\ReceiptStatus;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\ProductionDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AssociateFinancialSummaryService
{
    public function distributionQuery(int $tenantId, int $associateId, ?int $projectId = null): Builder
    {
        return ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associateId)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->when($projectId, fn (Builder $query) => $query->where('sales_project_id', $projectId));
    }

    public function receiptQuery(int $tenantId, int $associateId, ?int $projectId = null): Builder
    {
        return AssociateReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associateId)
            ->when($projectId, fn (Builder $query) => $query->where('sales_project_id', $projectId));
    }

    public function summary(int $tenantId, int $associateId, ?int $projectId = null): array
    {
        $distributions = $this->distributionQuery($tenantId, $associateId, $projectId);
        $receipts = $this->receiptQuery($tenantId, $associateId, $projectId);

        $gross = (float) (clone $distributions)
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
            ->value('total');

        $fees = (float) (clone $distributions)
            ->selectRaw('COALESCE(SUM(COALESCE(admin_fee_amount, 0)), 0) as total')
            ->value('total');

        $net = (float) (clone $distributions)
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, (quantity * unit_price) - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $unbilled = (float) (clone $distributions)
            ->whereNull('associate_receipt_id')
            ->where(function (Builder $query) {
                $query->whereNull('billing_status')
                    ->orWhere('billing_status', BillingStatus::UNBILLED->value);
            })
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, (quantity * unit_price) - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $legacyBilled = (float) (clone $distributions)
            ->whereNull('associate_receipt_id')
            ->where('billing_status', BillingStatus::BILLED->value)
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, (quantity * unit_price) - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $legacyPaid = (float) (clone $distributions)
            ->whereNull('associate_receipt_id')
            ->where('billing_status', BillingStatus::PAID->value)
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, (quantity * unit_price) - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $receiptIssued = (float) (clone $receipts)
            ->whereIn('status', [
                ReceiptStatus::PENDING_PAYMENT->value,
                ReceiptStatus::PARTIALLY_PAID->value,
                ReceiptStatus::PAID->value,
            ])
            ->selectRaw('COALESCE(SUM(COALESCE(total_net, 0)), 0) as total')
            ->value('total');

        $receiptPaid = (float) (clone $receipts)
            ->whereIn('status', [
                ReceiptStatus::PARTIALLY_PAID->value,
                ReceiptStatus::PAID->value,
            ])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN status = ? AND COALESCE(amount_paid, 0) = 0 THEN COALESCE(total_net, 0) ELSE COALESCE(amount_paid, 0) END), 0) as total',
                [ReceiptStatus::PAID->value]
            )
            ->value('total');

        $receivable = max(0.0, ($receiptIssued - $receiptPaid) + $legacyBilled);
        $paid = $receiptPaid + $legacyPaid;

        $receiptIssuedThisMonth = (float) (clone $receipts)
            ->whereIn('status', [
                ReceiptStatus::PENDING_PAYMENT->value,
                ReceiptStatus::PARTIALLY_PAID->value,
                ReceiptStatus::PAID->value,
            ])
            ->whereMonth('issued_at', now()->month)
            ->whereYear('issued_at', now()->year)
            ->selectRaw('COALESCE(SUM(COALESCE(total_net, 0)), 0) as total')
            ->value('total');

        $paymentsThisMonth = (float) AssociateReceiptPayment::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('receipt', function (Builder $query) use ($associateId, $projectId) {
                $query->where('associate_id', $associateId)
                    ->when($projectId, fn (Builder $query) => $query->where('sales_project_id', $projectId));
            })
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        return [
            'distribution_count' => (clone $distributions)->count(),
            'total_gross' => $gross,
            'total_fees' => $fees,
            'total_net' => $net,
            'unbilled' => $unbilled,
            'billed' => $receivable,
            'paid' => $paid,
            'receipt_issued' => $receiptIssued,
            'receipt_paid' => $receiptPaid,
            'receivable' => $receivable,
            'issued_this_month' => $receiptIssuedThisMonth,
            'paid_this_month' => $paymentsThisMonth,
            'legacy_billed' => $legacyBilled,
            'legacy_paid' => $legacyPaid,
            'balance' => $receivable,
            'total' => $unbilled + $receivable + $paid,
        ];
    }

    public function receipts(int $tenantId, int $associateId, ?int $projectId = null, int $limit = 8): Collection
    {
        return $this->receiptQuery($tenantId, $associateId, $projectId)
            ->with(['project', 'payments'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function payments(int $tenantId, int $associateId, ?int $projectId = null, int $limit = 10): Collection
    {
        return AssociateReceiptPayment::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('receipt', function (Builder $query) use ($associateId, $projectId) {
                $query->where('associate_id', $associateId)
                    ->when($projectId, fn (Builder $query) => $query->where('sales_project_id', $projectId));
            })
            ->with('receipt.project')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function ledgerBalance(Associate $associate): float
    {
        return (float) (AssociateLedger::query()
            ->where('tenant_id', $associate->tenant_id)
            ->where('associate_id', $associate->id)
            ->latest('id')
            ->value('balance_after') ?? 0);
    }
}
