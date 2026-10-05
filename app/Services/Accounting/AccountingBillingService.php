<?php

namespace App\Services\Accounting;

use App\Enums\BillingAuthorizationStatus;
use App\Enums\CustomerReceiptStatus;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use App\Services\CustomerBillingProjectContextService;
use App\Services\CustomerBillingReceiptService;
use App\Services\CustomerBillingSelectionService;
use App\Services\FinancialDocumentIdentityService;
use App\Services\TenantIdentityService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AccountingBillingService
{
    public function __construct(
        private readonly CustomerBillingSelectionService $selection,
        private readonly CustomerBillingProjectContextService $projects,
        private readonly CustomerBillingReceiptService $receipts,
        private readonly TenantIdentityService $identities,
    ) {}

    /** @return array<string,mixed> */
    public function preview(int $tenantId, array $data, ?CustomerBillingReceipt $draft = null): array
    {
        $projectIds = $this->projectIds($data, $draft);
        $customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
        $organizationId = filled($data['organization_id'] ?? null) ? (int) $data['organization_id'] : null;
        $from = filled($data['from_date'] ?? null) ? (string) $data['from_date'] : null;
        $to = filled($data['to_date'] ?? null) ? (string) $data['to_date'] : null;
        $projectModels = $this->projects->projects($tenantId, $projectIds);
        $selection = $this->selection->selectDistributionIds(
            $tenantId,
            (array) ($data['distribution_ids'] ?? []),
            $projectIds,
            $customerId,
            $organizationId,
            $from,
            $to,
            $draft?->id,
        );
        $rows = $this->distributionRows($tenantId, $selection['selected_ids']);
        $snapshot = $this->receipts->computeDraftSnapshotForIds(
            $tenantId,
            $selection['selected_ids'],
            $projectModels,
        );

        $names = $this->identities->namesForUsers($tenantId, $rows->pluck('associate.user_id')->filter());
        $details = $rows->map(fn (ProductionDelivery $row): array => [
            'id' => (int) $row->id,
            'date' => $row->delivery_date?->format('d/m/Y'),
            'product' => $row->product?->name ?? 'Produto não identificado',
            'unit' => $row->product?->unit ?: 'un',
            'producer' => $names[$row->associate?->user_id] ?? $row->associate?->nickname ?? 'Produtor não identificado',
            'recipient' => $row->customer?->trade_name ?: $row->customer?->name ?: 'Destino não identificado',
            'quantity' => (string) $row->quantity,
            'unit_price' => (string) $row->unit_price,
            'associate_receipt_id' => $row->associate_receipt_id ? (int) $row->associate_receipt_id : null,
        ])->values();

        return [
            'selected_ids' => $selection['selected_ids'],
            'candidate_count' => $selection['candidate_count'],
            'excluded_count' => $selection['excluded_count'],
            'exclusion_reasons' => $selection['reasons'],
            'summary' => [
                'distributions' => $rows->count(),
                'producers' => $rows->pluck('associate_id')->unique()->count(),
                'products' => $rows->pluck('product_id')->unique()->count(),
                'recipient_units' => $rows->pluck('customer_id')->unique()->count(),
                'source_receipts' => $rows->pluck('associate_receipt_id')->filter()->unique()->count(),
            ],
            'distributions' => $details,
            'lines' => array_values((array) data_get($snapshot, 'fee_snapshot.document_lines', [])),
            'fees' => array_values((array) data_get($snapshot, 'fee_snapshot.fees', [])),
            'fee_snapshot' => (array) ($snapshot['fee_snapshot'] ?? []),
            'totals' => [
                'gross' => (string) $snapshot['total_gross'],
                'fees' => (string) $snapshot['total_fees'],
                'net' => (string) $snapshot['total_net'],
            ],
        ];
    }

    public function saveDraft(int $tenantId, array $data, User $actor, ?CustomerBillingReceipt $draft = null): CustomerBillingReceipt
    {
        $preview = $this->preview($tenantId, $data, $draft);
        if ($preview['selected_ids'] === [] && ! $draft) {
            throw new \RuntimeException('Selecione ao menos uma distribuição elegível para salvar o faturamento.');
        }
        if ($preview['excluded_count'] > 0) {
            throw new \RuntimeException('A seleção contém distribuições incompatíveis. Revise os itens ignorados antes de salvar.');
        }

        return DB::transaction(function () use ($tenantId, $data, $actor, $draft, $preview): CustomerBillingReceipt {
            $projectModels = $this->projects->projects($tenantId, $this->projectIds($data, $draft));
            $project = $projectModels->first();
            $attributes = [
                'tenant_id' => $tenantId,
                'sales_project_id' => $project->id,
                'customer_id' => filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
                'organization_id' => filled($data['organization_id'] ?? null) ? (int) $data['organization_id'] : null,
                'issued_at' => $data['issued_at'] ?? now()->toDateString(),
                'from_date' => $data['from_date'] ?? null,
                'to_date' => $data['to_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'delivery_ids' => $preview['selected_ids'],
                'status' => CustomerReceiptStatus::DRAFT->value,
                'total_gross' => $preview['totals']['gross'],
                'total_fees' => $preview['totals']['fees'],
                'total_net' => $preview['totals']['net'],
                'fee_snapshot' => array_merge($preview['fee_snapshot'], ['draft_preview' => true]),
            ];

            if ($draft) {
                $locked = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)
                    ->lockForUpdate()->findOrFail($draft->id);
                if ($locked->status !== CustomerReceiptStatus::DRAFT) {
                    throw new \RuntimeException('Somente faturamentos em rascunho podem ser editados.');
                }
                $locked->fill($attributes)->save();
                $receipt = $locked;
                $event = 'Faturamento em rascunho atualizado no Portal Contábil';
            } else {
                $attributes += CustomerBillingReceipt::numberingFor($project, $attributes['issued_at']);
                $attributes['created_by'] = $actor->id;
                if (Schema::hasColumn('customer_billing_receipts', 'operation_key')) {
                    $operationKey = strtolower((string) ($data['operation_key'] ?? ''));
                    $existing = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $tenantId)
                        ->where('operation_key', $operationKey)->first();
                    if ($existing) {
                        return $existing;
                    }
                    $attributes['operation_key'] = $operationKey;
                }
                $receipt = CustomerBillingReceipt::withoutGlobalScopes()->create($attributes);
                $event = 'Faturamento criado em rascunho no Portal Contábil';
            }
            if (Schema::hasTable('customer_billing_receipt_projects')) {
                $receipt->projects()->sync($projectModels->mapWithKeys(fn ($item): array => [
                    $item->id => ['tenant_id' => $tenantId],
                ])->all());
            }
            activity()->performedOn($receipt)->causedBy($actor)->withProperties([
                'tenant_id' => $tenantId,
                'distribution_count' => count($preview['selected_ids']),
            ])->log($event);

            return $receipt->fresh();
        }, 5);
    }

    public function freeze(CustomerBillingReceipt $draft, User $actor): CustomerBillingReceipt
    {
        if ($draft->status !== CustomerReceiptStatus::DRAFT) {
            throw new \RuntimeException('Somente um rascunho pode ser emitido.');
        }
        $ids = collect($draft->delivery_ids)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();
        $projects = $this->projects->projectsForReceipt($draft);
        $validated = $this->selection->selectDistributionIds(
            (int) $draft->tenant_id, $ids, $draft->projectIds(),
            $draft->customer_id ? (int) $draft->customer_id : null,
            $draft->organization_id ? (int) $draft->organization_id : null,
            $draft->from_date?->format('Y-m-d'), $draft->to_date?->format('Y-m-d'), $draft->id,
        );
        if ($validated['excluded_count'] > 0 || count($validated['selected_ids']) !== count($ids)) {
            throw new \RuntimeException('A seleção mudou desde a última conferência. Reabra o rascunho e revise as distribuições.');
        }
        $rows = $this->distributionRows((int) $draft->tenant_id, $ids);
        $this->receipts->freezeReceipt($draft, $rows, $projects->first());
        $frozen = $draft->fresh();
        app(FinancialDocumentIdentityService::class)->ensure($frozen, $actor);
        activity()->performedOn($frozen)->causedBy($actor)->withProperties([
            'tenant_id' => $frozen->tenant_id,
            'distribution_count' => count($ids),
        ])->log('Faturamento emitido no Portal Contábil');

        return $frozen;
    }

    public function reopenForCorrection(CustomerBillingReceipt $receipt, User $actor): CustomerBillingReceipt
    {
        return DB::transaction(function () use ($receipt, $actor): CustomerBillingReceipt {
            $locked = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()->findOrFail($receipt->id);
            $round = $locked->latestAuthorizationRound()->lockForUpdate()->first();
            if (! $round || ! in_array($round->status, [
                BillingAuthorizationStatus::CORRECTION_REQUESTED,
                BillingAuthorizationStatus::INVALIDATED,
                BillingAuthorizationStatus::CANCELLED,
            ], true)) {
                throw new \RuntimeException('Este faturamento não possui uma solicitação de correção editável.');
            }
            if ((float) $locked->amount_paid > 0) {
                throw new \RuntimeException('Um faturamento com recebimentos não pode ser reaberto.');
            }
            $locked->forceFill(['status' => CustomerReceiptStatus::DRAFT])->save();
            activity()->performedOn($locked)->causedBy($actor)->withProperties([
                'tenant_id' => $locked->tenant_id,
                'authorization_id' => $round->id,
                'authorization_sequence' => $round->sequence,
            ])->log('Faturamento reaberto para correção');

            return $locked->fresh();
        }, 5);
    }

    /** @return Collection<int,ProductionDelivery> */
    private function distributionRows(int $tenantId, array $ids): Collection
    {
        return ProductionDelivery::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->whereIn('id', collect($ids)->map(fn ($id): int => (int) $id)->unique())
            ->with(['product:id,tenant_id,name,unit,ncm', 'customer:id,tenant_id,organization_id,name,trade_name',
                'associate:id,tenant_id,user_id,nickname', 'salesProject:id,tenant_id,title,type'])
            ->orderBy('id')->get();
    }

    private function projectIds(array $data, ?CustomerBillingReceipt $draft): array
    {
        $ids = (array) ($data['project_ids'] ?? []);
        if ($ids === [] && filled($data['project_id'] ?? null)) {
            $ids = [(int) $data['project_id']];
        }

        return $ids !== [] ? $ids : ($draft?->projectIds() ?? []);
    }
}
