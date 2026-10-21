<?php

namespace App\Services\Accounting;

use App\Enums\CustomerReceiptStatus;
use App\Enums\DeliveryStatus;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\AssociateReceiptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingAssociateReceiptService
{
    public function distributions(CustomerBillingReceipt $billing): Builder
    {
        return ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $billing->tenant_id)->whereNull('deleted_at')
            ->whereNotNull('parent_delivery_id')
            ->whereIn('sales_project_id', $billing->projectIds())
            ->when($billing->status === CustomerReceiptStatus::DRAFT,
                fn (Builder $query) => $query->whereIn('id', $billing->delivery_ids ?? [])
                    ->where(fn (Builder $link) => $link->whereNull('billing_receipt_id')->orWhere('billing_receipt_id', $billing->id)),
                fn (Builder $query) => $query->where('billing_receipt_id', $billing->id));
    }

    public function unavailableReason(ProductionDelivery $row): ?string
    {
        if ($row->associate_receipt_id) {
            return 'Já pertence ao comprovante do membro '.($row->associateReceipt?->formatted_number ?? '#'.$row->associate_receipt_id).'.';
        }
        if ($row->paid || $row->distribution_billing_id || $row->project_payment_id) {
            return 'Já possui pagamento ou liquidação do membro.';
        }
        if ($row->status !== DeliveryStatus::APPROVED || ! $row->associate_id || ! $row->customer_id
            || ! $row->product_id || (float) $row->quantity <= 0 || (float) $row->unit_price <= 0) {
            return 'Confira aprovação, membro, cliente, produto, quantidade e preço.';
        }

        return null;
    }

    /** Cria um comprovante por membro/projeto, com rollback integral em caso de conflito. */
    public function create(CustomerBillingReceipt $billing, array $ids, string $issuedAt): Collection
    {
        return DB::transaction(function () use ($billing, $ids, $issuedAt): Collection {
            $billing = CustomerBillingReceipt::withoutGlobalScopes()->where('tenant_id', $billing->tenant_id)
                ->lockForUpdate()->findOrFail($billing->id);
            $ids = collect($ids)->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            if ($ids->isEmpty()) {
                throw new \RuntimeException('Selecione ao menos uma distribuição disponível.');
            }
            $rows = $this->distributions($billing)->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($rows->count() !== $ids->count()) {
                throw new \RuntimeException('A seleção do faturamento mudou. Atualize a página e confira novamente.');
            }
            foreach ($rows as $row) {
                if ($reason = $this->unavailableReason($row)) {
                    throw new \RuntimeException('Distribuição #'.$row->id.': '.$reason);
                }
            }
            $memberIds = $rows->pluck('associate_id')->unique();
            if (Associate::withoutGlobalScopes()->where('tenant_id', $billing->tenant_id)->whereIn('id', $memberIds)->count() !== $memberIds->count()) {
                throw new \RuntimeException('Um dos membros não está disponível nesta organização.');
            }
            $projects = SalesProject::withoutGlobalScopes()->where('tenant_id', $billing->tenant_id)
                ->whereNull('deleted_at')->whereIn('id', $rows->pluck('sales_project_id')->unique())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $created = collect();
            foreach ($rows->groupBy(fn ($row) => $row->sales_project_id.':'.$row->associate_id) as $group) {
                $project = $projects->get($group->first()->sales_project_id);
                if (! $project) {
                    throw new \RuntimeException('Um dos projetos não está disponível nesta organização.');
                }
                $receipt = AssociateReceipt::create([
                    'tenant_id' => $billing->tenant_id,
                    'sales_project_id' => $project->id,
                    'associate_id' => $group->first()->associate_id,
                    'status' => 'draft',
                    ...AssociateReceipt::numberingFor($project, $issuedAt),
                    'issued_at' => $issuedAt,
                    'from_date' => $group->min('delivery_date'),
                    'to_date' => $group->max('delivery_date'),
                    'delivery_ids' => $group->pluck('id')->all(),
                ]);
                app(AssociateReceiptService::class)->freezeReceipt($receipt, $group, $project);
                $created->push($receipt->refresh());
            }

            return $created;
        }, 5);
    }
}
