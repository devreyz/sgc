<?php

namespace App\Services;

use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryParentRecoveryService
{
    /** @return array{recoverable: int, unrecoverable: int} */
    public function diagnosisForCustomerReceipt(CustomerBillingReceipt $receipt): array
    {
        $distributions = $this->receiptDistributions($receipt);
        $expectedIds = $this->receiptDistributionIds($receipt);
        $parents = ProductionDelivery::withoutGlobalScopes()->withTrashed()
            ->where('tenant_id', $receipt->tenant_id)
            ->whereIn('id', $distributions->pluck('parent_delivery_id')->filter()->unique())
            ->get(['id', 'tenant_id', 'sales_project_id', 'parent_delivery_id', 'deleted_at'])
            ->keyBy('id');

        $recoverable = $distributions->filter(function (ProductionDelivery $distribution) use ($parents, $receipt): bool {
            $parent = $parents->get($distribution->parent_delivery_id);

            return $parent?->trashed()
                && $parent->parent_delivery_id === null
                && (int) $distribution->sales_project_id === (int) $receipt->sales_project_id
                && (int) $parent->sales_project_id === (int) $receipt->sales_project_id;
        })->count();
        $unrecoverable = $distributions->filter(function (ProductionDelivery $distribution) use ($parents, $receipt): bool {
            $parent = $parents->get($distribution->parent_delivery_id);

            return ! $parent
                || $parent->parent_delivery_id !== null
                || (int) $distribution->sales_project_id !== (int) $receipt->sales_project_id
                || (int) $parent->tenant_id !== (int) $receipt->tenant_id
                || (int) $parent->sales_project_id !== (int) $receipt->sales_project_id;
        })->count() + $expectedIds->diff($distributions->pluck('id')->map(fn ($id): int => (int) $id))->count();

        return compact('recoverable', 'unrecoverable');
    }

    /** @return array{restored: list<int>, unresolved: list<int>} */
    public function restoreForCustomerReceipt(CustomerBillingReceipt $receipt, User $actor): array
    {
        return DB::transaction(function () use ($receipt, $actor): array {
            $lockedReceipt = CustomerBillingReceipt::withoutGlobalScopes()
                ->where('tenant_id', $receipt->tenant_id)
                ->lockForUpdate()
                ->findOrFail($receipt->id);
            $distributions = $this->receiptDistributions($lockedReceipt, true);
            $missingIds = $this->receiptDistributionIds($lockedReceipt)
                ->diff($distributions->pluck('id')->map(fn ($id): int => (int) $id))
                ->values()->all();
            $invalidContextIds = $distributions
                ->where('sales_project_id', '!=', $lockedReceipt->sales_project_id)
                ->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
            $eligible = $distributions->where('sales_project_id', $lockedReceipt->sales_project_id);
            $result = $this->restoreParents($eligible, $actor, 'customer_billing_receipt', $lockedReceipt->id);
            $result['unresolved'] = collect($result['unresolved'])
                ->merge($missingIds)->merge($invalidContextIds)->unique()->values()->all();

            return $result;
        }, 5);
    }

    public function restoreForDistribution(ProductionDelivery $distribution, User $actor): ProductionDelivery
    {
        return DB::transaction(function () use ($distribution, $actor): ProductionDelivery {
            $locked = ProductionDelivery::withoutGlobalScopes()
                ->where('tenant_id', $distribution->tenant_id)
                ->whereNotNull('parent_delivery_id')
                ->lockForUpdate()
                ->findOrFail($distribution->id);
            $result = $this->restoreParents(collect([$locked]), $actor, 'production_delivery', $locked->id);
            if ($result['restored'] === []) {
                throw ValidationException::withMessages([
                    'distribution_id' => 'A entrega-pai não está disponível para restauração automática.',
                ]);
            }

            return ProductionDelivery::withoutGlobalScopes()->findOrFail($result['restored'][0]);
        }, 5);
    }

    /** @param Collection<int, ProductionDelivery> $distributions
     * @return array{restored: list<int>, unresolved: list<int>}
     */
    private function restoreParents(Collection $distributions, User $actor, string $sourceType, int $sourceId): array
    {
        $parents = ProductionDelivery::withoutGlobalScopes()->withTrashed()
            ->whereIn('id', $distributions->pluck('parent_delivery_id')->filter()->unique())
            ->lockForUpdate()->get()->keyBy('id');
        $restored = collect();
        $unresolved = collect();

        foreach ($distributions as $distribution) {
            $parent = $parents->get($distribution->parent_delivery_id);
            $compatible = $parent
                && $parent->parent_delivery_id === null
                && (int) $parent->tenant_id === (int) $distribution->tenant_id
                && (int) $parent->sales_project_id === (int) $distribution->sales_project_id
                && (int) $parent->associate_id === (int) $distribution->associate_id
                && (int) $parent->product_id === (int) $distribution->product_id;
            if (! $compatible) {
                $unresolved->push((int) $distribution->id);

                continue;
            }
            if ($parent->trashed()) {
                ProductionDelivery::withoutGlobalScopes()->withTrashed()
                    ->whereKey($parent->id)
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => now(),
                    ]);
                $restored->push((int) $parent->id);
            }
        }

        $restored = $restored->unique()->values();
        if ($restored->isNotEmpty()) {
            activity('delivery_integrity')->causedBy($actor)->withProperties([
                'tenant_id' => (int) $distributions->first()->tenant_id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'restored_parent_ids' => $restored->all(),
                'unresolved_distribution_ids' => $unresolved->unique()->values()->all(),
            ])->log('Entregas-pai restauradas para corrigir distribuições órfãs');
        }

        return ['restored' => $restored->all(), 'unresolved' => $unresolved->unique()->values()->all()];
    }

    /** @return Collection<int, ProductionDelivery> */
    private function receiptDistributions(CustomerBillingReceipt $receipt, bool $lock = false): Collection
    {
        $ids = collect($receipt->delivery_ids ?? [])->map(fn ($id): int => (int) $id)->filter();
        $query = ProductionDelivery::withoutGlobalScopes()
            ->where('tenant_id', $receipt->tenant_id)
            ->whereNotNull('parent_delivery_id')
            ->where(function ($query) use ($receipt, $ids): void {
                $query->where('billing_receipt_id', $receipt->id)
                    ->when($ids->isNotEmpty(), fn ($nested) => $nested->orWhereIn('id', $ids));
            });
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /** @return Collection<int, int> */
    private function receiptDistributionIds(CustomerBillingReceipt $receipt): Collection
    {
        return collect($receipt->delivery_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();
    }
}
