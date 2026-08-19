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
use App\Models\SalesProject;
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
        $distributions = $this->distributionQuery($tenantId, $associateId, $projectId)
            ->with([
                'salesProject:id,tenant_id,admin_fee_percentage',
                'salesProject.fees',
                'associateReceipt',
            ])
            ->get([
                'id', 'tenant_id', 'sales_project_id', 'associate_id', 'parent_delivery_id',
                'quantity', 'unit_price', 'gross_value', 'admin_fee_amount', 'net_value', 'billing_status', 'paid',
                'associate_receipt_id',
            ]);
        $resolved = $this->resolveDistributions($distributions);
        $gross = $resolved['gross'];
        $fees = $resolved['fees'];
        $net = $resolved['net'];
        $activeReceiptStatuses = [
            ReceiptStatus::PENDING_PAYMENT->value,
            ReceiptStatus::PARTIALLY_PAID->value,
            ReceiptStatus::PAID->value,
        ];
        $receiptGroups = $distributions
            ->filter(fn (ProductionDelivery $distribution) => $distribution->associateReceipt
                && in_array($distribution->associateReceipt->status?->value, $activeReceiptStatuses, true))
            ->groupBy('associate_receipt_id');

        $paymentTotals = AssociateReceiptPayment::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('associate_receipt_id', $receiptGroups->keys())
            ->selectRaw('associate_receipt_id, COALESCE(SUM(amount), 0) as paid_total')
            ->groupBy('associate_receipt_id')
            ->pluck('paid_total', 'associate_receipt_id');

        $unbilled = 0.0;
        $legacyBilled = 0.0;
        $legacyPaid = 0.0;
        $receiptIssued = 0.0;
        $receiptPaid = 0.0;
        $receiptIssuedThisMonth = 0.0;
        $currentMonth = now()->format('Y-m');

        foreach ($receiptGroups as $receiptId => $items) {
            $receipt = $items->first()->associateReceipt;
            $receiptNet = (float) $items->sum(fn (ProductionDelivery $distribution) => $resolved['items'][$distribution->id]['net'] ?? 0);
            $paidAmount = (float) ($paymentTotals[$receiptId] ?? $receipt->amount_paid ?? 0);
            if ($receipt->status === ReceiptStatus::PAID && $paidAmount <= 0) {
                $paidAmount = $receiptNet;
            }

            $receiptIssued += $receiptNet;
            $receiptPaid += min($receiptNet, $paidAmount);
            if ($receipt->issued_at?->format('Y-m') === $currentMonth) {
                $receiptIssuedThisMonth += $receiptNet;
            }
        }

        foreach ($distributions as $distribution) {
            $receipt = $distribution->associateReceipt;
            if ($receipt && in_array($receipt->status?->value, $activeReceiptStatuses, true)) {
                continue;
            }

            $resolvedNet = (float) ($resolved['items'][$distribution->id]['net'] ?? 0);
            if ($distribution->paid || $distribution->billing_status === BillingStatus::PAID) {
                $legacyPaid += $resolvedNet;
            } elseif ($distribution->billing_status === BillingStatus::BILLED && ! $receipt) {
                $legacyBilled += $resolvedNet;
            } else {
                $unbilled += $resolvedNet;
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
            'distribution_count' => $distributions->count(),
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

    /**
     * Resolve valores pelo mesmo motor usado nos comprovantes. Grupos já
     * vinculados usam o snapshot congelado; os demais usam as taxas atuais.
     *
     * @return array{gross: float, fees: float, net: float, items: array<int, array{gross: float, fees: float, net: float}>}
     */
    public function resolveDistributions(Collection $distributions, ?SalesProject $project = null, ?AssociateReceipt $receipt = null): array
    {
        if ($distributions->isEmpty()) {
            return ['gross' => 0.0, 'fees' => 0.0, 'net' => 0.0, 'items' => []];
        }

        $relations = ['associateReceipt'];
        if ($project) {
            $project->loadMissing('fees');
        } else {
            $relations[] = 'salesProject:id,tenant_id,admin_fee_percentage';
            $relations[] = 'salesProject.fees';
        }
        if ($receipt) {
            $relations = array_values(array_diff($relations, ['associateReceipt']));
        }
        if ($relations !== []) {
            $distributions->loadMissing($relations);
        }
        $groups = $distributions->groupBy(function (ProductionDelivery $distribution) use ($project, $receipt): string {
            $projectId = $project?->id ?? $distribution->sales_project_id;
            $receiptId = $receipt?->id ?? $distribution->associate_receipt_id ?? 'live';

            return $projectId.':'.$receiptId;
        });
        $items = [];

        foreach ($groups as $group) {
            $first = $group->first();
            $groupProject = $project ?? $first->salesProject;
            $groupReceipt = $receipt ?? $first->associateReceipt;
            $data = ReceiptDataBuilder::fromDeliveries(
                $group,
                null,
                $groupProject,
                $groupReceipt?->fee_snapshot,
                true,
            );
            $items += $data['distributionFinancials'];
        }

        return [
            'gross' => (float) collect($items)->sum('gross'),
            'fees' => (float) collect($items)->sum('fees'),
            'net' => (float) collect($items)->sum('net'),
            'items' => $items,
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
            'project.fees',
            'distributions' => fn ($query) => $query
                ->whereNotNull('parent_delivery_id')
                ->where('status', DeliveryStatus::APPROVED->value),
            'payments',
        ]);

        $distributions = $receipt->distributions;
        $resolved = $this->resolveDistributions($distributions, $receipt->project, $receipt);
        $gross = $resolved['gross'];
        $fees = $resolved['fees'];
        $net = $resolved['net'];
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
