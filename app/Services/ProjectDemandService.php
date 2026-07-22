<?php

namespace App\Services;

use App\Models\PriceTableItem;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ProjectDemandService
{
    public function catalog(SalesProject $project, ?int $customerId = null): Collection
    {
        $customers = app(ProjectDistributionCustomerService::class)->customers($project);

        if ($customerId) {
            $customers = $customers->where('id', $customerId)->values();
            if ($customers->isEmpty()) {
                throw ValidationException::withMessages([
                    'customer_id' => 'O cliente selecionado nao esta habilitado neste projeto.',
                ]);
            }
        }

        $tableCustomers = $customers
            ->filter(fn ($customer) => $customer->price_table_id)
            ->groupBy(fn ($customer) => (int) $customer->price_table_id);

        if ($tableCustomers->isEmpty()) {
            return collect();
        }

        $items = PriceTableItem::query()
            ->whereIn('price_table_id', $tableCustomers->keys())
            ->where('sale_price', '>', 0)
            ->whereHas('priceTable', fn ($query) => $query
                ->where('tenant_id', $project->tenant_id)
                ->where('active', true))
            ->whereHas('product', fn ($query) => $query
                ->where('tenant_id', $project->tenant_id)
                ->where('status', true))
            ->with('product:id,tenant_id,name,unit,status')
            ->get(['id', 'price_table_id', 'product_id', 'sale_price']);

        return $items->groupBy('product_id')->map(function (Collection $productItems) use ($tableCustomers) {
            $first = $productItems->first();
            $destinations = $productItems->flatMap(function (PriceTableItem $item) use ($tableCustomers) {
                return collect($tableCustomers->get((int) $item->price_table_id, []))->map(fn ($customer) => [
                    'customer_id' => (int) $customer->id,
                    'customer' => $customer->trade_name ?: $customer->name,
                    'price' => (float) $item->sale_price,
                ]);
            })->unique('customer_id')->values();
            $prices = $destinations->pluck('price')->unique()->sort()->values();
            $minimum = (float) ($prices->first() ?? 0);
            $maximum = (float) ($prices->last() ?? 0);
            $priceLabel = $prices->count() <= 1
                ? 'R$ '.number_format($minimum, 2, ',', '.')
                : 'R$ '.number_format($minimum, 2, ',', '.').' a R$ '.number_format($maximum, 2, ',', '.');

            return [
                'product_id' => (int) $first->product_id,
                'product_name' => $first->product?->name ?? 'Produto',
                'unit' => $first->product?->unit ?? 'un',
                'reference_price' => $prices->count() === 1 ? $minimum : 0.0,
                'price_label' => $priceLabel,
                'destination_count' => $destinations->count(),
                'destinations' => $destinations,
            ];
        })->sortBy('product_name')->values();
    }

    public function normalizedData(SalesProject $project, array $data): array
    {
        $customerId = filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null;
        $productId = (int) ($data['product_id'] ?? 0);
        $catalogItem = $this->catalog($project, $customerId)->firstWhere('product_id', $productId);

        if (! $catalogItem) {
            throw ValidationException::withMessages([
                'product_id' => 'O produto nao possui preco para o cliente ou destinos selecionados.',
            ]);
        }

        $data['customer_id'] = $customerId;
        // Compatibility snapshot only. Financial values always come from distributions.
        $data['unit_price'] = $catalogItem['reference_price'];

        return $data;
    }

    public function pricingSummary(SalesProject $project, ?int $customerId, ?int $productId): string
    {
        if (! $productId) {
            return 'Selecione um destino e um produto.';
        }

        $item = $this->catalog($project, $customerId)->firstWhere('product_id', $productId);
        if (! $item) {
            return 'Produto sem preco disponivel para o destino selecionado.';
        }

        $destinations = $item['destination_count'] === 1
            ? '1 destino habilitado'
            : $item['destination_count'].' destinos habilitados';

        return $item['price_label'].' · '.$destinations;
    }

    public function assertQuantity(ProjectDemand $demand, float $targetQuantity): void
    {
        if ($targetQuantity + 0.0005 < (float) $demand->delivered_quantity) {
            throw ValidationException::withMessages([
                'target_quantity' => 'A meta nao pode ser menor que a quantidade ja distribuida.',
            ]);
        }
    }
}
