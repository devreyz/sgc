<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;
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
     * Rascunhos may be incomplete by design. Missing financial inputs are kept
     * as preparation items until the receipt is closed; structural corruption
     * remains critical at every stage.
     *
     * @return array{critical_count: int, preparation_count: int, blocking_count: int, snapshot_changed: bool, snapshot_changes: array{added_ids: list<int>, removed_ids: list<int>}, issues: array<int, array{code: string, message: string, severity: string}>}
     */
    public function inspect(CustomerBillingReceipt $receipt): array
    {
        $issues = collect();
        $projectIds = collect($receipt->projectIds());
        $snapshotIds = collect($receipt->delivery_ids)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $distributions = ProductionDelivery::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $receipt->tenant_id)
            ->where(function ($query) use ($receipt, $snapshotIds): void {
                $query->where('billing_receipt_id', $receipt->id)
                    ->when($snapshotIds->isNotEmpty(), fn ($nested) => $nested->orWhereIn('id', $snapshotIds));
            })
            ->get([
                'id', 'tenant_id', 'sales_project_id', 'parent_delivery_id', 'customer_id',
                'quantity', 'unit_price', 'status', 'billing_receipt_id', 'deleted_at',
            ]);

        $snapshotChanges = $this->snapshotChanges($receipt, $snapshotIds, $distributions);
        if ($snapshotChanges['added_ids'] !== []) {
            $issues->push($this->issue(
                'billing_snapshot_added_distributions',
                count($snapshotChanges['added_ids']).' distribuição(ões) foi(ram) adicionada(s) depois da geração deste faturamento. Atualize o faturamento e gere uma nova versão do comprovante.',
            ));
        }
        if ($snapshotChanges['removed_ids'] !== []) {
            $issues->push($this->issue(
                'billing_snapshot_removed_distributions',
                count($snapshotChanges['removed_ids']).' distribuição(ões) foi(ram) removida(s) depois da geração deste faturamento. Atualize o faturamento e gere uma nova versão do comprovante.',
            ));
        }

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
            $issues->push($this->issue(
                'missing_distributions',
                'Adicione os itens que formarão esta cobrança antes de enviá-la para emissão.',
                $this->completionSeverity($receipt),
            ));
        }

        $parents = ProductionDelivery::withoutGlobalScopes()->withTrashed()
            ->whereIn('id', $distributions->pluck('parent_delivery_id')->filter()->unique())
            ->get(['id', 'tenant_id', 'sales_project_id', 'parent_delivery_id', 'deleted_at'])
            ->keyBy('id');
        $this->inspectDistributions($receipt, $distributions, $parents, $issues, $projectIds);

        return [
            'critical_count' => $issues->where('severity', 'critical')->count(),
            'preparation_count' => $issues->where('severity', 'preparation')->count(),
            'blocking_count' => $issues->count(),
            'snapshot_changed' => $snapshotChanges['added_ids'] !== [] || $snapshotChanges['removed_ids'] !== [],
            'snapshot_changes' => $snapshotChanges,
            'issues' => $issues->values()->all(),
        ];
    }

    /**
     * Compara o documento congelado com os vínculos operacionais atuais. Em
     * rascunhos, delivery_ids ainda é a fonte de verdade e não há divergência.
     *
     * @param  Collection<int, int>  $snapshotIds
     * @param  Collection<int, ProductionDelivery>  $distributions
     * @return array{added_ids: list<int>, removed_ids: list<int>}
     */
    private function snapshotChanges(CustomerBillingReceipt $receipt, Collection $snapshotIds, Collection $distributions): array
    {
        if ($receipt->status === CustomerReceiptStatus::DRAFT) {
            return ['added_ids' => [], 'removed_ids' => []];
        }

        $currentIds = $distributions
            ->filter(fn (ProductionDelivery $distribution): bool =>
                ! $distribution->trashed()
                && (int) $distribution->billing_receipt_id === (int) $receipt->id
            )
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        return [
            'added_ids' => $currentIds->diff($snapshotIds)->sort()->values()->all(),
            'removed_ids' => $snapshotIds->diff($currentIds)->sort()->values()->all(),
        ];
    }

    private function inspectDistributions(CustomerBillingReceipt $receipt, Collection $distributions, Collection $parents, Collection $issues, Collection $projectIds): void
    {
        foreach ($distributions as $distribution) {
            if (! $distribution instanceof ProductionDelivery) {
                continue;
            }

            $prefix = 'Distribuição #'.$distribution->id.': ';

            if ($distribution->trashed()) {
                $issues->push($this->issue('deleted_distribution', $prefix.'o registro foi excluído e pode ser restaurado sem alterar o snapshot financeiro.'));
            }

            if (! $distribution->parent_delivery_id) {
                $issues->push($this->issue('parent_as_financial_line', $prefix.'a entrega física não pode ser uma linha financeira.'));
            } else {
                $parent = $parents->get($distribution->parent_delivery_id);
                if (! $parent) {
                    $issues->push($this->issue('missing_parent_delivery', $prefix.'a entrega-pai não existe mais.'));
                } elseif ($parent->deleted_at !== null) {
                    $issues->push($this->issue('deleted_parent_delivery', $prefix.'a entrega-pai foi excluída e pode ser restaurada.'));
                } elseif ($parent->parent_delivery_id !== null
                    || (int) $parent->tenant_id !== (int) $receipt->tenant_id
                    || (int) $parent->sales_project_id !== (int) $distribution->sales_project_id) {
                    $issues->push($this->issue('invalid_parent_delivery', $prefix.'a entrega-pai pertence a outro contexto.'));
                }
            }

            if ((int) $distribution->tenant_id !== (int) $receipt->tenant_id
                || ! $projectIds->contains((int) $distribution->sales_project_id)) {
                $issues->push($this->issue('cross_context_distribution', $prefix.'tenant ou projeto incompatível.'));
            }

            if (! $distribution->customer_id) {
                $issues->push($this->issue('missing_customer', $prefix.'informe o cliente de destino.', $this->completionSeverity($receipt)));
            }

            if ((float) $distribution->quantity <= 0) {
                $issues->push($this->issue('invalid_quantity', $prefix.'informe uma quantidade válida.', $this->completionSeverity($receipt)));
            }

            if ((float) $distribution->unit_price <= 0) {
                $issues->push($this->issue('invalid_price', $prefix.'informe o preço antes de fechar a cobrança.', $this->completionSeverity($receipt)));
            }

            if ($distribution->status !== DeliveryStatus::APPROVED) {
                $issues->push($this->issue('invalid_status', $prefix.'conclua a conferência do item antes de fechar a cobrança.', $this->completionSeverity($receipt)));
            }
        }
    }

    private function completionSeverity(CustomerBillingReceipt $receipt): string
    {
        return $receipt->status === CustomerReceiptStatus::DRAFT ? 'preparation' : 'critical';
    }

    /** @return array{code: string, message: string, severity: string} */
    private function issue(string $code, string $message, string $severity = 'critical'): array
    {
        return compact('code', 'message', 'severity');
    }
}
