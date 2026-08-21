<?php

namespace App\Services\Accounting;

use App\Enums\DeliveryStatus;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use Illuminate\Support\Collection;

class AccountingProcessIntegrityService
{
    /**
     * Focused receipt-level checks. This intentionally does not run the full
     * project auditor while a page is being rendered.
     *
     * @return array{critical_count: int, issues: array<int, array{code: string, message: string}>}
     */
    public function inspect(CustomerBillingReceipt $receipt): array
    {
        $issues = collect();
        $distributions = $receipt->relationLoaded('billingDistributions')
            ? $receipt->billingDistributions
            : $receipt->billingDistributions()->get([
                'id',
                'tenant_id',
                'sales_project_id',
                'parent_delivery_id',
                'customer_id',
                'quantity',
                'unit_price',
                'status',
            ]);

        if (! $receipt->sales_project_id) {
            $issues->push($this->issue('missing_project', 'A cobrança não possui projeto de venda válido.'));
        }

        if (($receipt->customer_id && $receipt->organization_id) || (! $receipt->customer_id && ! $receipt->organization_id)) {
            $issues->push($this->issue('invalid_recipient', 'A cobrança precisa possuir um único destinatário.'));
        }

        if ((float) ($receipt->total_net ?? 0) <= 0 && $receipt->status?->isLocked()) {
            $issues->push($this->issue('invalid_snapshot', 'O valor financeiro fechado não é válido.'));
        }

        if ($distributions->isEmpty()) {
            $issues->push($this->issue('missing_distributions', 'Nenhuma distribuição está vinculada à cobrança.'));
        }

        $this->inspectDistributions($receipt, $distributions, $issues);

        return [
            'critical_count' => $issues->count(),
            'issues' => $issues->values()->all(),
        ];
    }

    private function inspectDistributions(CustomerBillingReceipt $receipt, Collection $distributions, Collection $issues): void
    {
        foreach ($distributions as $distribution) {
            if (! $distribution instanceof ProductionDelivery) {
                continue;
            }

            $prefix = 'Distribuição #'.$distribution->id.': ';

            if (! $distribution->parent_delivery_id) {
                $issues->push($this->issue('parent_as_financial_line', $prefix.'a entrega física não pode ser uma linha financeira.'));
            }

            if ((int) $distribution->tenant_id !== (int) $receipt->tenant_id
                || (int) $distribution->sales_project_id !== (int) $receipt->sales_project_id) {
                $issues->push($this->issue('cross_context_distribution', $prefix.'tenant ou projeto incompatível.'));
            }

            if (! $distribution->customer_id) {
                $issues->push($this->issue('missing_customer', $prefix.'cliente não identificado.'));
            }

            if ((float) $distribution->quantity <= 0) {
                $issues->push($this->issue('invalid_quantity', $prefix.'quantidade inválida.'));
            }

            if ((float) $distribution->unit_price <= 0) {
                $issues->push($this->issue('invalid_price', $prefix.'preço unitário inválido.'));
            }

            if ($distribution->status !== DeliveryStatus::APPROVED) {
                $issues->push($this->issue('invalid_status', $prefix.'status incompatível com o fechamento financeiro.'));
            }
        }
    }

    /** @return array{code: string, message: string} */
    private function issue(string $code, string $message): array
    {
        return compact('code', 'message');
    }
}
