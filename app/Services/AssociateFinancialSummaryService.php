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
            ->where('production_deliveries.tenant_id', $tenantId)
            ->where('production_deliveries.associate_id', $associateId)
            ->whereNotNull('production_deliveries.parent_delivery_id')
            ->where('production_deliveries.status', DeliveryStatus::APPROVED->value)
            ->when($projectId, fn (Builder $query) => $query->where('production_deliveries.sales_project_id', $projectId));
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

        $gross = (float) (clone $distributions)
            ->selectRaw('COALESCE(SUM(gross_value), 0) as total')
            ->value('total');

        $fees = (float) (clone $distributions)
            ->selectRaw('COALESCE(SUM(COALESCE(admin_fee_amount, 0)), 0) as total')
            ->value('total');

        $net = (float) (clone $distributions)
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, gross_value - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $unbilled = (float) (clone $distributions)
            ->whereNull('associate_receipt_id')
            ->where(function (Builder $query) {
                $query->whereNull('billing_status')
                    ->orWhere('billing_status', BillingStatus::UNBILLED->value);
            })
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, gross_value - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $legacyBilled = (float) (clone $distributions)
            ->whereNull('associate_receipt_id')
            ->where('billing_status', BillingStatus::BILLED->value)
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, gross_value - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $legacyPaid = (float) (clone $distributions)
            ->whereNull('associate_receipt_id')
            ->where('billing_status', BillingStatus::PAID->value)
            ->selectRaw('COALESCE(SUM(COALESCE(net_value, gross_value - COALESCE(admin_fee_amount, 0))), 0) as total')
            ->value('total');

        $receiptFinancialRows = (clone $distributions)
            ->join('associate_receipts', function ($join) {
                $join->on('associate_receipts.id', '=', 'production_deliveries.associate_receipt_id')
                    ->on('associate_receipts.tenant_id', '=', 'production_deliveries.tenant_id')
                    ->on('associate_receipts.sales_project_id', '=', 'production_deliveries.sales_project_id')
                    ->on('associate_receipts.associate_id', '=', 'production_deliveries.associate_id');
            })
            ->whereIn('associate_receipts.status', [
                ReceiptStatus::PENDING_PAYMENT->value,
                ReceiptStatus::PARTIALLY_PAID->value,
                ReceiptStatus::PAID->value,
            ])
            ->selectRaw(
                'associate_receipts.id as receipt_id, associate_receipts.status as receipt_status, '
                .'associate_receipts.amount_paid as legacy_amount_paid, associate_receipts.issued_at, '
                .'COALESCE(SUM(COALESCE(production_deliveries.net_value, production_deliveries.gross_value - COALESCE(production_deliveries.admin_fee_amount, 0))), 0) as receipt_net'
            )
            ->groupBy([
                'associate_receipts.id',
                'associate_receipts.status',
                'associate_receipts.amount_paid',
                'associate_receipts.issued_at',
            ])
            ->get();

        $paymentTotals = AssociateReceiptPayment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('associate_receipt_id', $receiptFinancialRows->pluck('receipt_id'))
            ->selectRaw('associate_receipt_id, COALESCE(SUM(amount), 0) as paid_total')
            ->groupBy('associate_receipt_id')
            ->pluck('paid_total', 'associate_receipt_id');

        $receiptIssued = 0.0;
        $receiptPaid = 0.0;
        $receiptIssuedThisMonth = 0.0;
        $currentMonth = now()->format('Y-m');

        foreach ($receiptFinancialRows as $row) {
            $receiptNet = (float) $row->receipt_net;
            $paidAmount = (float) ($paymentTotals[$row->receipt_id] ?? $row->legacy_amount_paid ?? 0);
            if ($row->receipt_status === ReceiptStatus::PAID->value && $paidAmount <= 0) {
                $paidAmount = $receiptNet;
            }

            $receiptIssued += $receiptNet;
            $receiptPaid += min($receiptNet, $paidAmount);
            if ($row->issued_at && str_starts_with((string) $row->issued_at, $currentMonth)) {
                $receiptIssuedThisMonth += $receiptNet;
            }
        }

        $receivable = max(0.0, ($receiptIssued - $receiptPaid) + $legacyBilled);
        $paid = $receiptPaid + $legacyPaid;

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
            ->with([
                'project',
                'payments',
                'distributions' => fn ($query) => $query
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED->value),
            ])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve the financial snapshot displayed in the member portal from the
     * distributions currently linked to the receipt.
     *
     * @return array{gross: float, fees: float, net: float, paid: float, remaining: float, distribution_count: int}
     */
    public function receiptTotals(AssociateReceipt $receipt): array
    {
        $receipt->loadMissing([
            'distributions' => fn ($query) => $query
                ->whereNotNull('parent_delivery_id')
                ->where('status', DeliveryStatus::APPROVED->value),
            'payments',
        ]);

        $distributions = $receipt->distributions;
        $gross = (float) $distributions->sum(fn (ProductionDelivery $item) => (float) $item->gross_value);
        $fees = (float) $distributions->sum(fn (ProductionDelivery $item) => (float) ($item->admin_fee_amount ?? 0));
        $net = (float) $distributions->sum(function (ProductionDelivery $item): float {
            if ($item->net_value !== null) {
                return (float) $item->net_value;
            }

            return max(0.0, (float) $item->gross_value - (float) ($item->admin_fee_amount ?? 0));
        });
        $paymentSum = (float) $receipt->payments->sum('amount');
        $paid = $paymentSum > 0 ? $paymentSum : (float) ($receipt->amount_paid ?? 0);
        if ($receipt->status === ReceiptStatus::PAID && $paid <= 0) {
            $paid = $net;
        }

        return [
            'gross' => $gross,
            'fees' => $fees,
            'net' => $net,
            'paid' => min($net, $paid),
            'remaining' => max(0.0, $net - $paid),
            'distribution_count' => $distributions->count(),
        ];
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
