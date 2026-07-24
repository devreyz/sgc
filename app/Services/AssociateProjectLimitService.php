<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Models\Associate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\ProjectAssociate;
use App\Models\ProjectAssociateProductLimit;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssociateProjectLimitService
{
    private const QUANTITY_TOLERANCE = 0.0005;

    public function projectMode(SalesProject $project): array
    {
        $project->loadMissing(['customer.priceTable', 'customers.priceTable']);

        $customers = $project->customers
            ->when($project->customer, fn (Collection $items) => $items->push($project->customer))
            ->filter(fn (Customer $customer) => $customer->status && (int) $customer->tenant_id === (int) $project->tenant_id)
            ->unique('id')
            ->values();

        $mode = match (true) {
            $customers->count() === 1 => 'single_customer',
            $customers->count() > 1 => 'multiple_customers',
            default => 'no_customer',
        };

        return [
            'mode' => $mode,
            'customer_count' => $customers->count(),
            'customer' => $customers->first(),
            'customers' => $customers,
            'allows_product_limits' => $mode === 'single_customer',
        ];
    }

    public function assertContext(SalesProject $project, Associate $associate): void
    {
        if ((int) $project->tenant_id !== (int) $associate->tenant_id) {
            abort(404);
        }

        if ($project->restrict_participants && ! ProjectAssociate::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('status', 'active')
            ->exists()) {
            abort(403, 'Este associado nao participa do projeto.');
        }
    }

    public function association(SalesProject $project, Associate $associate, bool $create = false): ?ProjectAssociate
    {
        $query = ProjectAssociate::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id);

        if (! $create) {
            return $query->first();
        }

        return ProjectAssociate::query()->firstOrCreate([
            'tenant_id' => $project->tenant_id,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
        ], [
            'status' => 'active',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    public function financialLimit(SalesProject $project, Associate $associate): ?float
    {
        $specific = $this->association($project, $associate)?->financial_limit;

        return $specific !== null
            ? (float) $specific
            : ($project->max_total_value_per_associate !== null ? (float) $project->max_total_value_per_associate : null);
    }

    public function simulatedLimitValue(
        SalesProject $project,
        ?int $associateId = null,
        ?int $exceptLimitId = null
    ): float {
        return (float) ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('status', 'active')
            ->when($associateId, fn ($query) => $query->where('associate_id', $associateId))
            ->when($exceptLimitId, fn ($query) => $query->whereKeyNot($exceptLimitId))
            ->selectRaw('COALESCE(SUM(max_quantity * COALESCE(reference_unit_price, 0)), 0) as total')
            ->value('total');
    }

    public function simulatedBudgetSummary(SalesProject $project, ?Associate $associate = null): array
    {
        $planned = $this->simulatedLimitValue($project, $associate?->id);
        $ceiling = $associate
            ? $this->financialLimit($project, $associate)
            : ((float) $project->total_value > 0 ? (float) $project->total_value : null);

        return [
            'planned_value' => $planned,
            'ceiling' => $ceiling,
            'remaining' => $ceiling === null ? null : max(0, $ceiling - $planned),
            'percent' => $ceiling && $ceiling > 0 ? min(100, ($planned / $ceiling) * 100) : null,
        ];
    }

    public function consumedFinancialValue(SalesProject $project, Associate $associate, ?int $exceptDistributionId = null): float
    {
        return (float) ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->when($exceptDistributionId, fn ($query) => $query->whereKeyNot($exceptDistributionId))
            ->sum('gross_value');
    }

    public function deliveredQuantity(SalesProject $project, Associate $associate, int $productId, ?int $exceptDeliveryId = null): float
    {
        return (float) ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('product_id', $productId)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->when($exceptDeliveryId, fn ($query) => $query->whereKeyNot($exceptDeliveryId))
            ->sum('quantity');
    }

    public function summary(SalesProject $project, Associate $associate): array
    {
        $mode = $this->projectMode($project);
        $financialLimit = $this->financialLimit($project, $associate);
        $consumed = $this->consumedFinancialValue($project, $associate);
        $simulated = $this->simulatedLimitValue($project, $associate->id);
        $base = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id);

        $received = (float) (clone $base)->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->sum('quantity');
        $distributed = (float) (clone $base)->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)->sum('quantity');

        return [
            'mode' => $mode['mode'],
            'customer_count' => $mode['customer_count'],
            'customer_name' => $mode['customer']?->trade_name ?: $mode['customer']?->name,
            'price_table_name' => $mode['customer']?->priceTable?->name,
            'allows_product_limits' => $mode['allows_product_limits'],
            'financial_limit' => $financialLimit,
            'financial_consumed' => $consumed,
            'financial_remaining' => $financialLimit === null ? null : max(0, $financialLimit - $consumed),
            'financial_percent' => $financialLimit && $financialLimit > 0 ? ($consumed / $financialLimit) * 100 : null,
            'received_quantity' => $received,
            'distributed_quantity' => $distributed,
            'undistributed_quantity' => max(0, $received - $distributed),
            'delivery_count' => (clone $base)->whereNull('parent_delivery_id')->count(),
            'distribution_count' => (clone $base)->whereNotNull('parent_delivery_id')->count(),
            'simulated_limit_value' => $simulated,
            'simulated_limit_remaining' => $financialLimit === null
                ? null
                : max(0, $financialLimit - $simulated),
        ];
    }

    public function productLimits(SalesProject $project, Associate $associate): Collection
    {
        if (! $this->projectMode($project)['allows_product_limits']) {
            return collect();
        }

        $delivered = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as delivered_quantity')
            ->pluck('delivered_quantity', 'product_id');

        $distributed = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as distributed_quantity')
            ->pluck('distributed_quantity', 'product_id');

        return ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('status', 'active')
            ->with('product:id,name,unit')
            ->orderBy('product_id')
            ->get()
            ->map(function (ProjectAssociateProductLimit $limit) use ($delivered, $distributed) {
                $maximum = (float) $limit->max_quantity;
                $used = (float) ($delivered[$limit->product_id] ?? 0);
                $distributedQuantity = (float) ($distributed[$limit->product_id] ?? 0);
                $price = (float) ($limit->reference_unit_price ?? 0);

                return [
                    'id' => $limit->id,
                    'product_id' => $limit->product_id,
                    'product' => $limit->product?->name ?? 'Produto',
                    'unit' => $limit->product?->unit ?? 'un',
                    'maximum_quantity' => $maximum,
                    'delivered_quantity' => $used,
                    'distributed_quantity' => $distributedQuantity,
                    'remaining_quantity' => max(0, $maximum - $used),
                    'percent' => $maximum > 0 ? ($used / $maximum) * 100 : 0,
                    'reference_unit_price' => $price,
                    'estimated_maximum_value' => $maximum * $price,
                    'estimated_delivered_value' => $used * $price,
                    'notes' => $limit->notes,
                    'status' => $limit->status,
                ];
            });
    }

    public function eligibleProducts(SalesProject $project, Associate $associate): Collection
    {
        $this->assertContext($project, $associate);

        $demands = ProjectDemand::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->with('product:id,name,unit,status')
            ->get()
            ->groupBy('product_id');
        $limits = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('product_id');

        $demandIds = $demands->keys()->map(fn ($id) => (int) $id);
        $limitIds = $limits->keys()->map(fn ($id) => (int) $id);
        $productIds = match (true) {
            $project->allow_any_product => Product::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('status', true)
                ->pluck('id'),
            $limitIds->isNotEmpty() && $demandIds->isNotEmpty() => $limitIds->intersect($demandIds)->values(),
            $limitIds->isNotEmpty() => $limitIds,
            default => $demandIds,
        };
        $productIds = $productIds
            ->intersect(app(ProjectDistributionCustomerService::class)->pricedProductIds($project))
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $products = Product::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('status', true)
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);
        $projectDelivered = $this->deliveredByProduct($project);
        $associateDelivered = $this->deliveredByProduct($project, $associate);

        return $products->map(function (Product $product) use ($demands, $limits, $projectDelivered, $associateDelivered) {
            $productDemands = $demands->get($product->id, collect());
            $projectMaximum = $productDemands->isEmpty() ? null : (float) $productDemands->sum('target_quantity');
            $projectUsed = (float) ($projectDelivered[$product->id] ?? 0);
            $limit = $limits->get($product->id);
            $associateMaximum = $limit ? (float) $limit->max_quantity : null;
            $associateUsed = (float) ($associateDelivered[$product->id] ?? 0);
            $projectRemaining = $projectMaximum === null ? null : max(0, $projectMaximum - $projectUsed);
            $associateRemaining = $associateMaximum === null ? null : max(0, $associateMaximum - $associateUsed);
            $applicable = collect([$projectRemaining, $associateRemaining])->filter(fn ($value) => $value !== null);

            return [
                'id' => $productDemands->first()?->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_unit' => $product->unit ?? 'un',
                'target_quantity' => $projectMaximum,
                'delivered_quantity' => $associateUsed,
                'remaining_quantity' => $applicable->isEmpty() ? null : (float) $applicable->min(),
                'project_limit' => $projectMaximum,
                'project_delivered' => $projectUsed,
                'project_remaining' => $projectRemaining,
                'associate_limit' => $associateMaximum,
                'associate_delivered' => $associateUsed,
                'associate_remaining' => $associateRemaining,
                'limit_percent' => $associateMaximum && $associateMaximum > 0
                    ? min(100, ($associateUsed / $associateMaximum) * 100)
                    : null,
                'unit_price' => (float) ($productDemands->first()?->unit_price ?? $limit?->reference_unit_price ?? 0),
                'is_free' => $projectMaximum === null,
            ];
        })->values();
    }

    private function deliveredByProduct(SalesProject $project, ?Associate $associate = null, ?int $exceptDeliveryId = null): Collection
    {
        return ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->when($associate, fn ($query) => $query->where('associate_id', $associate->id))
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->when($exceptDeliveryId, fn ($query) => $query->whereKeyNot($exceptDeliveryId))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as delivered_quantity')
            ->pluck('delivered_quantity', 'product_id');
    }

    public function setFinancialLimit(SalesProject $project, Associate $associate, ?float $limit, ?string $notes = null): ProjectAssociate
    {
        return DB::transaction(function () use ($project, $associate, $limit, $notes) {
            $association = $this->association($project, $associate, true);
            $association = ProjectAssociate::query()->whereKey($association->id)->lockForUpdate()->firstOrFail();
            $consumed = $this->consumedFinancialValue($project, $associate);

            if ($limit !== null && $limit + 0.005 < $consumed) {
                throw ValidationException::withMessages([
                    'financial_limit' => 'O limite financeiro nao pode ser inferior ao valor ja utilizado pelo associado.',
                ]);
            }

            $planned = $this->simulatedLimitValue($project, $associate->id);
            if ($limit !== null && $limit + 0.005 < $planned) {
                throw ValidationException::withMessages([
                    'financial_limit' => sprintf(
                        'O limite financeiro nao pode ser inferior ao valor simulado dos limites de produtos (R$ %s).',
                        number_format($planned, 2, ',', '.')
                    ),
                ]);
            }

            $old = $association->only(['financial_limit', 'notes']);
            $association->update([
                'financial_limit' => $limit,
                'notes' => $notes,
                'updated_by' => Auth::id(),
            ]);

            activity('associate_project_limits')->performedOn($association)->withProperties([
                'tenant_id' => $project->tenant_id,
                'sales_project_id' => $project->id,
                'associate_id' => $associate->id,
                'old' => $old,
                'new' => $association->only(['financial_limit', 'notes']),
            ])->log('Limite financeiro do associado atualizado');

            return $association;
        });
    }

    public function setProductLimit(SalesProject $project, Associate $associate, int $productId, float $maximum, ?string $notes = null): ProjectAssociateProductLimit
    {
        return DB::transaction(function () use ($project, $associate, $productId, $maximum, $notes) {
            $this->assertContext($project, $associate);
            SalesProject::query()
                ->where('tenant_id', $project->tenant_id)
                ->whereKey($project->id)
                ->lockForUpdate()
                ->firstOrFail();

            $mode = $this->projectMode($project);
            if (! $mode['allows_product_limits']) {
                throw ValidationException::withMessages(['product_id' => 'Limites por produto exigem exatamente um cliente ativo no projeto.']);
            }

            $used = $this->deliveredQuantity($project, $associate, $productId);
            if ($maximum + self::QUANTITY_TOLERANCE < $used) {
                throw ValidationException::withMessages(['max_quantity' => 'A quantidade maxima nao pode ser inferior ao total ja entregue.']);
            }

            $allocation = $this->productAllocationSummary($project, $productId, $associate->id, true);
            if ($allocation['project_maximum'] !== null
                && $maximum > $allocation['available_for_associate'] + self::QUANTITY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'max_quantity' => sprintf(
                        'A soma dos limites excede a meta do produto. Meta: %s | Comprometido com outros associados: %s | Disponivel: %s.',
                        number_format($allocation['project_maximum'], 3, ',', '.'),
                        number_format($allocation['allocated_to_others'], 3, ',', '.'),
                        number_format($allocation['available_for_associate'], 3, ',', '.')
                    ),
                ]);
            }

            $price = $mode['customer']?->priceTable?->priceFor($productId);
            if ($price === null) {
                throw ValidationException::withMessages(['product_id' => 'O produto nao possui preco na tabela do cliente padrao.']);
            }

            $limit = ProjectAssociateProductLimit::query()->firstOrNew([
                'tenant_id' => $project->tenant_id,
                'sales_project_id' => $project->id,
                'associate_id' => $associate->id,
                'product_id' => $productId,
            ]);
            if ($limit->exists) {
                $limit = ProjectAssociateProductLimit::query()
                    ->whereKey($limit->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $proposedValue = $maximum * (float) $price;
            $associatePlanned = $this->simulatedLimitValue(
                $project,
                $associate->id,
                $limit->exists ? $limit->id : null,
            ) + $proposedValue;
            $associateCeiling = $this->financialLimit($project, $associate);
            if ($associateCeiling !== null && $associatePlanned > $associateCeiling + 0.005) {
                throw ValidationException::withMessages([
                    'max_quantity' => sprintf(
                        'O valor simulado dos limites deste associado seria R$ %s e ultrapassaria seu teto de R$ %s.',
                        number_format($associatePlanned, 2, ',', '.'),
                        number_format($associateCeiling, 2, ',', '.')
                    ),
                ]);
            }

            $projectCeiling = (float) $project->total_value > 0 ? (float) $project->total_value : null;
            $projectPlanned = $this->simulatedLimitValue(
                $project,
                null,
                $limit->exists ? $limit->id : null,
            ) + $proposedValue;
            if ($projectCeiling !== null && $projectPlanned > $projectCeiling + 0.005) {
                throw ValidationException::withMessages([
                    'max_quantity' => sprintf(
                        'O valor simulado de todos os limites seria R$ %s e ultrapassaria o teto do projeto de R$ %s.',
                        number_format($projectPlanned, 2, ',', '.'),
                        number_format($projectCeiling, 2, ',', '.')
                    ),
                ]);
            }

            if (! $limit->exists) {
                $limit->created_by = Auth::id();
            }
            $limit->fill([
                'max_quantity' => $maximum,
                'reference_unit_price' => $price,
                'status' => 'active',
                'notes' => $notes,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
                'updated_by' => Auth::id(),
            ])->save();

            activity('associate_project_limits')->performedOn($limit)->withProperties([
                'tenant_id' => $project->tenant_id,
                'sales_project_id' => $project->id,
                'associate_id' => $associate->id,
                'product_id' => $productId,
                'maximum_quantity' => $maximum,
            ])->log('Limite de produto do associado atualizado');

            return $limit;
        });
    }

    public function productAllocationSummary(
        SalesProject $project,
        int $productId,
        ?int $exceptAssociateId = null,
        bool $lock = false
    ): array {
        $demands = ProjectDemand::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('product_id', $productId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'target_quantity']);

        $limits = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->when($exceptAssociateId, fn ($query) => $query->where('associate_id', '!=', $exceptAssociateId))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'associate_id', 'max_quantity']);

        $limitedAssociateIds = $limits->pluck('associate_id')->map(fn ($id) => (int) $id);
        $unallocatedDeliveries = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('product_id', $productId)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->when($exceptAssociateId, fn ($query) => $query->where('associate_id', '!=', $exceptAssociateId))
            ->when($limitedAssociateIds->isNotEmpty(), fn ($query) => $query->whereNotIn('associate_id', $limitedAssociateIds))
            ->sum('quantity');

        $projectMaximum = $demands->isEmpty() ? null : (float) $demands->sum('target_quantity');
        $allocatedToOthers = (float) $limits->sum('max_quantity') + (float) $unallocatedDeliveries;

        return [
            'project_maximum' => $projectMaximum,
            'allocated_to_others' => $allocatedToOthers,
            'unallocated_delivered_to_others' => (float) $unallocatedDeliveries,
            'available_for_associate' => $projectMaximum === null
                ? null
                : max(0, $projectMaximum - $allocatedToOthers),
        ];
    }

    public function productAllocationSummaries(
        SalesProject $project,
        Collection $productIds,
        ?int $exceptAssociateId = null
    ): Collection {
        $productIds = $productIds->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($productIds->isEmpty()) {
            return collect();
        }

        $maximums = ProjectDemand::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereIn('product_id', $productIds)
            ->selectRaw('product_id, SUM(target_quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $allocated = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereIn('product_id', $productIds)
            ->where('status', 'active')
            ->when($exceptAssociateId, fn ($query) => $query->where('associate_id', '!=', $exceptAssociateId))
            ->selectRaw('product_id, SUM(max_quantity) as total')
            ->groupBy('product_id')
            ->pluck('total', 'product_id');

        $unallocated = ProductionDelivery::query()
            ->leftJoin('project_associate_product_limits as allocation_limits', function ($join) use ($project) {
                $join->on('allocation_limits.associate_id', '=', 'production_deliveries.associate_id')
                    ->on('allocation_limits.product_id', '=', 'production_deliveries.product_id')
                    ->where('allocation_limits.tenant_id', '=', $project->tenant_id)
                    ->where('allocation_limits.sales_project_id', '=', $project->id)
                    ->where('allocation_limits.status', '=', 'active');
            })
            ->where('production_deliveries.tenant_id', $project->tenant_id)
            ->where('production_deliveries.sales_project_id', $project->id)
            ->whereIn('production_deliveries.product_id', $productIds)
            ->whereNull('production_deliveries.parent_delivery_id')
            ->whereNull('production_deliveries.deleted_at')
            ->whereNull('allocation_limits.id')
            ->whereNotIn('production_deliveries.status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->when($exceptAssociateId, fn ($query) => $query->where('production_deliveries.associate_id', '!=', $exceptAssociateId))
            ->selectRaw('production_deliveries.product_id, SUM(production_deliveries.quantity) as total')
            ->groupBy('production_deliveries.product_id')
            ->pluck('total', 'production_deliveries.product_id');

        return $productIds->mapWithKeys(function (int $productId) use ($maximums, $allocated, $unallocated): array {
            $projectMaximum = $maximums->has($productId) ? (float) $maximums[$productId] : null;
            $unallocatedDelivered = (float) ($unallocated[$productId] ?? 0);
            $allocatedToOthers = (float) ($allocated[$productId] ?? 0) + $unallocatedDelivered;

            return [$productId => [
                'project_maximum' => $projectMaximum,
                'allocated_to_others' => $allocatedToOthers,
                'unallocated_delivered_to_others' => $unallocatedDelivered,
                'available_for_associate' => $projectMaximum === null
                    ? null
                    : max(0, $projectMaximum - $allocatedToOthers),
            ]];
        });
    }

    public function validateDelivery(SalesProject $project, Associate $associate, int $productId, float $quantity, ?int $exceptDeliveryId = null): void
    {
        app(ProjectDistributionCustomerService::class)->assertProductPriced($project, $productId);

        $limit = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        $hasAnyAssociateLimits = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('status', 'active')
            ->exists();
        $demand = ProjectDemand::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->get();

        if (! $project->allow_any_product) {
            if ($hasAnyAssociateLimits && ! $limit) {
                throw ValidationException::withMessages(['product_id' => 'Este produto nao esta autorizado nos limites do associado.']);
            }
            if ($hasAnyAssociateLimits && $demand->isNotEmpty() && ! $limit) {
                throw ValidationException::withMessages(['product_id' => 'O produto precisa estar autorizado para o projeto e para o associado.']);
            }
            if (! $hasAnyAssociateLimits && $demand->isEmpty()) {
                throw ValidationException::withMessages(['product_id' => 'Configure uma demanda do projeto ou um limite de produto para este associado.']);
            }
            if ($hasAnyAssociateLimits && ProjectDemand::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('sales_project_id', $project->id)
                ->exists() && $demand->isEmpty()) {
                throw ValidationException::withMessages(['product_id' => 'Este produto nao faz parte das demandas do projeto.']);
            }
        }

        if ($demand->isNotEmpty()) {
            $maximumProject = (float) $demand->sum('target_quantity');
            $usedProject = (float) ($this->deliveredByProduct($project, null, $exceptDeliveryId)[$productId] ?? 0);
            if ($usedProject + $quantity > $maximumProject + self::QUANTITY_TOLERANCE) {
                throw ValidationException::withMessages([
                    'quantity' => sprintf(
                        'Limite geral do projeto excedido. Limite: %s | Entregue: %s | Disponivel: %s.',
                        number_format($maximumProject, 3, ',', '.'),
                        number_format($usedProject, 3, ',', '.'),
                        number_format(max(0, $maximumProject - $usedProject), 3, ',', '.')
                    ),
                ]);
            }
        }

        if (! $limit) {
            return;
        }

        $used = $this->deliveredQuantity($project, $associate, $productId, $exceptDeliveryId);
        $maximum = (float) $limit->max_quantity;
        if ($used + $quantity > $maximum + self::QUANTITY_TOLERANCE) {
            throw ValidationException::withMessages([
                'quantity' => sprintf(
                    'Limite do produto excedido. Limite: %s | Ja entregue: %s | Disponivel: %s.',
                    number_format($maximum, 3, ',', '.'),
                    number_format($used, 3, ',', '.'),
                    number_format(max(0, $maximum - $used), 3, ',', '.')
                ),
            ]);
        }
    }

    public function validateDistribution(SalesProject $project, Associate $associate, float $newGross, ?int $exceptDistributionId = null): void
    {
        $association = $this->association($project, $associate);
        if ($association) {
            ProjectAssociate::query()->whereKey($association->id)->lockForUpdate()->first();
        } else {
            SalesProject::query()->whereKey($project->id)->lockForUpdate()->first();
        }

        $limit = $this->financialLimit($project, $associate);
        if ($limit === null) {
            return;
        }

        $used = $this->consumedFinancialValue($project, $associate, $exceptDistributionId);
        if ($used + $newGross > $limit + 0.005) {
            throw ValidationException::withMessages([
                'distributions' => sprintf(
                    'Limite financeiro excedido. Limite: R$ %s | Utilizado: R$ %s | Disponivel: R$ %s | Nova distribuicao: R$ %s.',
                    number_format($limit, 2, ',', '.'),
                    number_format($used, 2, ',', '.'),
                    number_format(max(0, $limit - $used), 2, ',', '.'),
                    number_format($newGross, 2, ',', '.')
                ),
            ]);
        }
    }
}
