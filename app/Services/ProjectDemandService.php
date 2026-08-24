<?php

namespace App\Services;

use App\Models\PriceTableItem;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
                    'price_table' => $customer->priceTable?->name,
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
        // Planning snapshot only. Financial values still come exclusively from distributions.
        $data['unit_price'] = $this->planningPrice($catalogItem);

        return $data;
    }

    public function createDemand(SalesProject $project, array $data): ProjectDemand
    {
        return DB::transaction(function () use ($project, $data): ProjectDemand {
            $lockedProject = SalesProject::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $project->tenant_id)
                ->whereKey($project->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->firstOrFail();
            $normalized = $this->normalizedData($lockedProject, $data);
            $this->assertWithinProjectBudget($lockedProject, $normalized);

            $demand = new ProjectDemand($normalized);
            $demand->forceFill([
                'tenant_id' => $lockedProject->tenant_id,
                'sales_project_id' => $lockedProject->id,
            ]);
            $demand->save();

            return $demand;
        });
    }

    public function updateDemand(SalesProject $project, ProjectDemand $demand, array $data): ProjectDemand
    {
        return DB::transaction(function () use ($project, $demand, $data): ProjectDemand {
            $lockedProject = SalesProject::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $project->tenant_id)
                ->whereKey($project->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDemand = ProjectDemand::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $lockedProject->tenant_id)
                ->where('sales_project_id', $lockedProject->id)
                ->whereKey($demand->id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->firstOrFail();

            $data['product_id'] ??= $lockedDemand->product_id;
            $data['customer_id'] ??= $lockedDemand->customer_id;
            $normalized = $this->normalizedData($lockedProject, $data);
            $this->assertQuantity($lockedDemand, (float) $normalized['target_quantity']);
            $this->assertWithinProjectBudget($lockedProject, $normalized, $lockedDemand->id);

            $lockedDemand->fill($normalized);
            $lockedDemand->save();

            return $lockedDemand;
        });
    }

    /**
     * @return array{
     *   planned_value: float,
     *   ceiling: float|null,
     *   remaining: float|null,
     *   percentage: float,
     *   has_unpriced: bool
     * }
     */
    public function budgetSummary(SalesProject $project, ?int $excludeDemandId = null): array
    {
        $demands = ProjectDemand::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereNull('deleted_at')
            ->when($excludeDemandId, fn ($query) => $query->where('id', '!=', $excludeDemandId))
            ->get(['id', 'customer_id', 'product_id', 'target_quantity', 'unit_price']);

        $catalogs = $demands
            ->pluck('customer_id')
            ->map(fn ($customerId) => $customerId ? (int) $customerId : null)
            ->unique()
            ->mapWithKeys(fn ($customerId): array => [
                $this->catalogKey($customerId) => $this->catalog($project, $customerId)->keyBy('product_id'),
            ]);

        $hasUnpriced = false;
        $planned = $demands->sum(function (ProjectDemand $demand) use ($catalogs, &$hasUnpriced): float {
            $customerId = $demand->customer_id ? (int) $demand->customer_id : null;
            $item = $catalogs->get($this->catalogKey($customerId))?->get((int) $demand->product_id);
            $price = $item ? $this->planningPrice($item) : (float) $demand->unit_price;
            if ($price <= 0) {
                $hasUnpriced = true;
            }

            return (float) $demand->target_quantity * max(0, $price);
        });

        $ceiling = (float) $project->total_value > 0 ? (float) $project->total_value : null;
        $remaining = $ceiling === null ? null : max(0, $ceiling - $planned);
        $percentage = $ceiling === null || $ceiling <= 0
            ? 0.0
            : min(100, ($planned / $ceiling) * 100);

        return [
            'planned_value' => $planned,
            'ceiling' => $ceiling,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'has_unpriced' => $hasUnpriced,
        ];
    }

    /**
     * @return array<string, float|bool|string|null>
     */
    public function budgetPreview(
        SalesProject $project,
        ?int $customerId,
        ?int $productId,
        float $quantity,
        ?int $excludeDemandId = null,
    ): array {
        $summary = $this->budgetSummary($project, $excludeDemandId);
        $item = $productId
            ? $this->catalog($project, $customerId)->firstWhere('product_id', $productId)
            : null;
        $price = $item ? $this->planningPrice($item) : null;
        $proposedValue = $price === null ? 0.0 : max(0, $quantity) * $price;
        $totalAfter = $summary['planned_value'] + $proposedValue;
        $remainingBefore = $summary['ceiling'] === null
            ? null
            : max(0, $summary['ceiling'] - $summary['planned_value']);
        $remainingAfter = $summary['ceiling'] === null
            ? null
            : max(0, $summary['ceiling'] - $totalAfter);
        $maximumQuantity = $price && $remainingBefore !== null
            ? floor(($remainingBefore / $price) * 1000) / 1000
            : null;
        $prices = collect($item['destinations'] ?? [])->pluck('price')->unique();

        return $summary + [
            'price' => $price,
            'price_label' => $item['price_label'] ?? null,
            'unit' => $item['unit'] ?? null,
            'uses_maximum_price' => $prices->count() > 1,
            'proposed_value' => $proposedValue,
            'total_after' => $totalAfter,
            'remaining_before' => $remainingBefore,
            'remaining_after' => $remainingAfter,
            'maximum_quantity' => $maximumQuantity,
            'exceeds' => $summary['ceiling'] !== null && $totalAfter > $summary['ceiling'] + 0.005,
        ];
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

    private function assertWithinProjectBudget(
        SalesProject $project,
        array $data,
        ?int $excludeDemandId = null,
    ): void {
        $preview = $this->budgetPreview(
            $project,
            filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
            (int) ($data['product_id'] ?? 0),
            (float) ($data['target_quantity'] ?? 0),
            $excludeDemandId,
        );

        if ($preview['price'] === null || $preview['price'] <= 0) {
            throw ValidationException::withMessages([
                'product_id' => 'Nao foi possivel determinar um preco valido para planejar esta meta.',
            ]);
        }

        if ($preview['exceeds']) {
            throw ValidationException::withMessages([
                'target_quantity' => sprintf(
                    'Esta meta ocupa R$ %s e faria o planejamento chegar a R$ %s, acima do teto de R$ %s. Quantidade maxima disponivel: %s.',
                    number_format((float) $preview['proposed_value'], 2, ',', '.'),
                    number_format((float) $preview['total_after'], 2, ',', '.'),
                    number_format((float) $preview['ceiling'], 2, ',', '.'),
                    number_format((float) $preview['maximum_quantity'], 3, ',', '.'),
                ),
            ]);
        }
    }

    private function planningPrice(array $catalogItem): float
    {
        return (float) collect($catalogItem['destinations'] ?? [])
            ->pluck('price')
            ->filter(fn ($price) => (float) $price > 0)
            ->max();
    }

    private function catalogKey(?int $customerId): string
    {
        return $customerId ? 'customer:'.$customerId : 'all';
    }
}
