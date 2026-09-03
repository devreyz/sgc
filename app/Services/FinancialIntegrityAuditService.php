<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialIntegrityAuditService
{
    /** @return array{summary: array<string, int>, aggregates: list<array<string, mixed>>, issues: list<array<string, mixed>>} */
    public function audit(?int $tenantId = null, ?int $projectId = null): array
    {
        $issues = collect();
        $distributions = $this->rows('production_deliveries', $tenantId, $projectId)
            ->filter(fn ($row) => $row->parent_delivery_id !== null && $row->deleted_at === null)
            ->values();
        $allDeliveries = $this->rows('production_deliveries', $tenantId, $projectId)
            ->filter(fn ($row) => $row->deleted_at === null)
            ->keyBy('id');
        $associateReceipts = $this->rows('associate_receipts', $tenantId, $projectId)->keyBy('id');
        $customerReceipts = $this->customerReceiptRows($tenantId, $projectId)->keyBy('id');
        $customerReceiptProjects = Schema::hasTable('customer_billing_receipt_projects')
            ? DB::table('customer_billing_receipt_projects')
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->get(['customer_billing_receipt_id', 'sales_project_id'])
                ->groupBy('customer_billing_receipt_id')
                ->map(fn (Collection $rows): array => $rows->pluck('sales_project_id')->map(fn ($id): int => (int) $id)->all())
            : collect();

        foreach ($distributions as $distribution) {
            $base = [
                'tenant_id' => (int) $distribution->tenant_id,
                'project_id' => (int) $distribution->sales_project_id,
                'record_type' => 'production_delivery',
                'record_id' => (int) $distribution->id,
            ];
            $parent = $allDeliveries->get($distribution->parent_delivery_id);
            if (! $parent
                || $parent->parent_delivery_id !== null
                || (int) $parent->tenant_id !== (int) $distribution->tenant_id
                || (int) $parent->sales_project_id !== (int) $distribution->sales_project_id) {
                $issues->push($this->issue($base, 'critical', 'orphan_distribution', 'Distribuicao sem entrega-pai valida no mesmo tenant e projeto.'));
            }

            if ($distribution->customer_id === null) {
                $issues->push($this->issue($base, 'critical', 'missing_customer', 'Distribuicao financeira sem cliente.'));
            }
            if ((float) $distribution->quantity <= 0) {
                $issues->push($this->issue($base, 'critical', 'invalid_quantity', 'Distribuicao com quantidade invalida.'));
            }
            if ((float) $distribution->unit_price <= 0) {
                $issues->push($this->issue($base, 'critical', 'invalid_price', 'Distribuicao com preco unitario invalido.'));
            }

            $this->checkReceiptReference($issues, $base, $distribution, $associateReceipts, 'associate_receipt_id', 'associate_receipt');
            $this->checkReceiptReference(
                $issues,
                $base,
                $distribution,
                $customerReceipts,
                'billing_receipt_id',
                'customer_billing_receipt',
                $customerReceiptProjects,
            );

            if (($distribution->billing_status ?? null) === 'paid') {
                $associatePaid = $distribution->associate_receipt_id
                    && ($associateReceipts->get($distribution->associate_receipt_id)?->status ?? null) === 'paid';
                $customerPaid = $distribution->billing_receipt_id
                    && ($customerReceipts->get($distribution->billing_receipt_id)?->status ?? null) === 'paid';
                if (! $associatePaid && ! $customerPaid) {
                    $issues->push($this->issue($base, 'warning', 'legacy_billing_status_divergence', 'billing_status indica pago, mas nenhum documento especifico vinculado esta pago.'));
                }
            }
        }

        $this->auditReceipts(
            $issues,
            $associateReceipts,
            $distributions,
            'associate_receipt_id',
            'associate_receipt_payments',
            'associate_receipt_id',
            'associate_receipt',
        );
        $this->auditReceipts(
            $issues,
            $customerReceipts,
            $distributions,
            'billing_receipt_id',
            'customer_receipt_payments',
            'customer_billing_receipt_id',
            'customer_billing_receipt',
        );

        $this->auditDuplicateReferences($issues, 'associate_ledgers', $tenantId, 'associate_ledger');
        $this->auditDuplicateReferences($issues, 'cash_movements', $tenantId, 'cash_movement');

        $ordered = $issues->sortBy(function (array $issue): string {
            $rank = ['critical' => 0, 'warning' => 1, 'info' => 2][$issue['severity']] ?? 3;

            return sprintf(
                '%d:%010d:%010d:%s:%010d',
                $rank,
                $issue['tenant_id'] ?? 0,
                $issue['project_id'] ?? 0,
                $issue['record_type'],
                $issue['record_id'],
            );
        })->values();

        $aggregates = $ordered->groupBy('code')->map(function (Collection $group, string $code): array {
            $classification = $this->classification($code);

            return [
                'code' => $code,
                'severity' => $group->sortBy(fn (array $issue): int => ['critical' => 0, 'warning' => 1, 'info' => 2][$issue['severity']] ?? 3)->first()['severity'],
                'count' => $group->count(),
                'tenants' => $group->pluck('tenant_id')->filter()->unique()->sort()->values()->all(),
                'projects' => $group->pluck('project_id')->filter()->unique()->sort()->values()->all(),
                'classification' => $classification['classification'],
                'remediation' => $classification['remediation'],
            ];
        })->sortBy(fn (array $row): string => sprintf('%d:%s', ['critical' => 0, 'warning' => 1, 'info' => 2][$row['severity']] ?? 3, $row['code']))
            ->values();

        return [
            'summary' => [
                'critical' => $ordered->where('severity', 'critical')->count(),
                'warning' => $ordered->where('severity', 'warning')->count(),
                'info' => $ordered->where('severity', 'info')->count(),
                'total' => $ordered->count(),
            ],
            'aggregates' => $aggregates->all(),
            'issues' => $ordered->all(),
        ];
    }

    private function rows(string $table, ?int $tenantId, ?int $projectId = null): Collection
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $query = DB::table($table);
        if ($tenantId !== null && Schema::hasColumn($table, 'tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        if ($projectId !== null && Schema::hasColumn($table, 'sales_project_id')) {
            $query->where('sales_project_id', $projectId);
        }

        return $query->get();
    }

    private function checkReceiptReference(
        Collection $issues,
        array $base,
        object $distribution,
        Collection $receipts,
        string $foreignKey,
        string $type,
        ?Collection $receiptProjects = null,
    ): void {
        $receiptId = $distribution->{$foreignKey} ?? null;
        if (! $receiptId) {
            return;
        }

        $receipt = $receipts->get($receiptId);
        if (! $receipt) {
            $issues->push($this->issue($base, 'critical', "missing_{$type}", "Distribuicao aponta para {$type} inexistente."));

            return;
        }

        $projectIds = collect($receiptProjects?->get((int) $receipt->id, []))
            ->push((int) $receipt->sales_project_id)->unique();
        if ((int) $receipt->tenant_id !== (int) $distribution->tenant_id
            || ! $projectIds->contains((int) $distribution->sales_project_id)) {
            $issues->push($this->issue($base, 'critical', "cross_tenant_{$type}", "Vinculo com {$type} cruza tenant ou projeto."));
        }
    }

    private function customerReceiptRows(?int $tenantId, ?int $projectId): Collection
    {
        if (! Schema::hasTable('customer_billing_receipts')) {
            return collect();
        }

        $query = DB::table('customer_billing_receipts');
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }
        if ($projectId !== null) {
            $query->where(function ($nested) use ($projectId): void {
                $nested->where('sales_project_id', $projectId);
                if (Schema::hasTable('customer_billing_receipt_projects')) {
                    $nested->orWhereExists(fn ($pivot) => $pivot
                        ->selectRaw('1')
                        ->from('customer_billing_receipt_projects')
                        ->whereColumn('customer_billing_receipt_projects.customer_billing_receipt_id', 'customer_billing_receipts.id')
                        ->where('customer_billing_receipt_projects.sales_project_id', $projectId));
                }
            });
        }

        return $query->get();
    }

    private function auditReceipts(
        Collection $issues,
        Collection $receipts,
        Collection $distributions,
        string $distributionForeignKey,
        string $paymentsTable,
        string $paymentForeignKey,
        string $type,
    ): void {
        $payments = $this->rows($paymentsTable, null)->groupBy($paymentForeignKey);

        foreach ($receipts as $receipt) {
            $linked = $distributions
                ->filter(fn ($distribution) => (int) ($distribution->{$distributionForeignKey} ?? 0) === (int) $receipt->id)
                ->values();
            $fkIds = $linked->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $jsonIds = collect($this->decodeIds($receipt->delivery_ids ?? null))->sort()->values();
            $base = [
                'tenant_id' => (int) $receipt->tenant_id,
                'project_id' => (int) $receipt->sales_project_id,
                'record_type' => $type,
                'record_id' => (int) $receipt->id,
            ];

            if ($fkIds->all() !== $jsonIds->all()) {
                $issues->push($this->issue($base, 'warning', 'snapshot_fk_mismatch', 'delivery_ids diverge das FKs atuais; a FK permanece como verdade operacional.', [
                    'fk_ids' => $fkIds->all(),
                    'snapshot_ids' => $jsonIds->all(),
                ]));
            }
            if ($jsonIds->count() !== $jsonIds->unique()->count()) {
                $issues->push($this->issue($base, 'warning', 'duplicate_snapshot_id', 'O snapshot contem IDs de distribuicao duplicados.'));
            }

            $gross = $linked->sum(fn ($distribution) => (float) ($distribution->gross_value
                ?: ((float) $distribution->quantity * (float) $distribution->unit_price)));
            if ($linked->isNotEmpty() && abs($gross - (float) ($receipt->total_gross ?? 0)) > 0.01) {
                $issues->push($this->issue($base, 'warning', 'frozen_gross_mismatch', 'Total bruto congelado diverge das distribuicoes atualmente vinculadas.', [
                    'linked_gross' => round($gross, 4),
                    'frozen_gross' => (float) ($receipt->total_gross ?? 0),
                ]));
            }

            $paid = collect($payments->get($receipt->id, collect()))->sum(fn ($payment) => (float) $payment->amount);
            if (abs($paid - (float) ($receipt->amount_paid ?? 0)) > 0.01) {
                $issues->push($this->issue($base, 'critical', 'payment_total_mismatch', 'amount_paid diverge da soma das parcelas registradas.', [
                    'payments_total' => round($paid, 2),
                    'amount_paid' => (float) ($receipt->amount_paid ?? 0),
                ]));
            }
            if (($receipt->status ?? null) === 'paid' && $paid + 0.01 < (float) ($receipt->total_net ?? 0)) {
                $issues->push($this->issue($base, 'critical', 'paid_without_sufficient_payments', 'Documento marcado como pago sem parcelas suficientes.'));
            }
        }
    }

    private function auditDuplicateReferences(
        Collection $issues,
        string $table,
        ?int $tenantId,
        string $type,
    ): void {
        $rows = $this->rows($table, $tenantId)
            ->filter(fn ($row) => ! property_exists($row, 'deleted_at') || $row->deleted_at === null)
            ->filter(fn ($row) => filled($row->reference_type ?? null) && filled($row->reference_id ?? null));

        $rows->groupBy(fn ($row) => implode(':', [$row->tenant_id, $row->reference_type, $row->reference_id]))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->each(function (Collection $group) use ($issues, $type): void {
                $first = $group->first();
                $issues->push($this->issue([
                    'tenant_id' => (int) $first->tenant_id,
                    'project_id' => null,
                    'record_type' => $type,
                    'record_id' => (int) $first->id,
                ], 'warning', 'duplicate_reference', 'Mais de um lancamento usa a mesma referencia financeira.', [
                    'ids' => $group->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'reference_type' => $first->reference_type,
                    'reference_id' => (int) $first->reference_id,
                ]));
            });
    }

    private function decodeIds(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return collect(is_array($value) ? $value : [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }

    private function issue(
        array $base,
        string $severity,
        string $code,
        string $message,
        array $details = [],
    ): array {
        return array_merge($base, compact('severity', 'code', 'message', 'details'));
    }

    /** @return array{classification: string, remediation: string} */
    private function classification(string $code): array
    {
        return match ($code) {
            'orphan_distribution', 'missing_customer', 'invalid_quantity', 'invalid_price' => [
                'classification' => 'A/B/F',
                'remediation' => 'Bloqueado no fluxo atual; ocorrencias existentes exigem revisao humana.',
            ],
            'missing_associate_receipt', 'missing_customer_billing_receipt',
            'cross_tenant_associate_receipt', 'cross_tenant_customer_billing_receipt' => [
                'classification' => 'B/E/F',
                'remediation' => 'Divida historica sensivel; nao desvincular ou realocar automaticamente.',
            ],
            'payment_total_mismatch', 'paid_without_sufficient_payments' => [
                'classification' => 'B/E/F',
                'remediation' => 'Reconciliar parcelas, documento e caixa com aprovacao humana.',
            ],
            'snapshot_fk_mismatch', 'duplicate_snapshot_id', 'frozen_gross_mismatch' => [
                'classification' => 'B/C/E',
                'remediation' => 'FK e verdade corrente; preservar snapshot e revisar documento antes de regenerar.',
            ],
            'duplicate_reference' => [
                'classification' => 'B/E/F',
                'remediation' => 'Investigar duplicidade contabil antes de qualquer estorno ou consolidacao.',
            ],
            'legacy_billing_status_divergence' => [
                'classification' => 'B/C/F',
                'remediation' => 'Referencia legada; comparar com os dois comprovantes modernos.',
            ],
            default => [
                'classification' => 'F',
                'remediation' => 'Revisao humana obrigatoria.',
            ],
        };
    }
}
