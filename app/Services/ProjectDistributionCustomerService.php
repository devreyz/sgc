<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PriceTableItem;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectDistributionCustomerService
{
    public function customers(SalesProject $project): Collection
    {
        $project->loadMissing(['customer:id,tenant_id,status', 'customers:id', 'organizations:id']);

        $customerIds = $project->customers->pluck('id')
            ->when($project->customer_id, fn (Collection $ids) => $ids->push((int) $project->customer_id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $organizationIds = $project->organizations->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($customerIds->isEmpty() && $organizationIds->isEmpty()) {
            return collect();
        }

        return Customer::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('status', true)
            ->where(function ($allowed) use ($customerIds, $organizationIds) {
                if ($customerIds->isNotEmpty()) {
                    $allowed->whereIn('id', $customerIds);
                }
                if ($organizationIds->isNotEmpty()) {
                    $method = $customerIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $allowed->{$method}('organization_id', $organizationIds);
                }
            })
            ->with('organization:id,name,short_name')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'trade_name', 'organization_id', 'price_table_id']);
    }

    public function ids(SalesProject $project): Collection
    {
        return $this->customers($project)->pluck('id')->map(fn ($id) => (int) $id)->values();
    }

    public function pricedProductIds(SalesProject $project): Collection
    {
        $priceTableIds = $this->customers($project)
            ->pluck('price_table_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($priceTableIds->isEmpty()) {
            return collect();
        }

        return PriceTableItem::query()
            ->whereIn('price_table_id', $priceTableIds)
            ->where('sale_price', '>', 0)
            ->whereHas('priceTable', fn ($query) => $query
                ->where('tenant_id', $project->tenant_id)
                ->where('active', true))
            ->distinct()
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    public function assertProductPriced(SalesProject $project, int $productId): void
    {
        if (! $this->pricedProductIds($project)->contains($productId)) {
            throw ValidationException::withMessages([
                'product_id' => 'Este produto nao possui preco cadastrado para nenhum cliente habilitado no projeto.',
            ]);
        }
    }

    public function assertAllowed(SalesProject $project, iterable $customerIds): void
    {
        $requested = collect($customerIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($requested->diff($this->ids($project))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'customer_id' => 'O cliente selecionado nao esta habilitado para distribuicoes deste projeto.',
            ]);
        }
    }
}
