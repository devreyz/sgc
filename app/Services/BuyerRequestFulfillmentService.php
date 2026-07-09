<?php

namespace App\Services;

use App\Models\BuyerRequest;
use App\Models\BuyerRequestItem;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class BuyerRequestFulfillmentService
{
    public function summaryForRequest(BuyerRequest $request): array
    {
        $items = $request->items()->with(['product', 'customer'])->get();
        $summaries = $items->map(fn (BuyerRequestItem $item) => $this->summaryForItem($item));

        return [
            'items' => $summaries,
            'requested' => (float) $summaries->sum('requested_quantity'),
            'distributed' => (float) $summaries->sum('distributed_quantity'),
            'pending' => (float) $summaries->sum('pending_quantity'),
            'exceeded' => (float) $summaries->sum('exceeded_quantity'),
            'total_value' => (float) $summaries->sum('total_value'),
        ];
    }

    public function summaryForItem(BuyerRequestItem $item): array
    {
        $request = $item->buyerRequest;
        $customerId = $item->customer_id ?: $request->customer_id;
        $distributed = $this->distributedQuantity(
            (int) $request->tenant_id,
            (int) $request->sales_project_id,
            (int) $request->organization_id,
            (int) $item->product_id,
            $customerId ? (int) $customerId : null
        );

        $requested = (float) $item->requested_quantity;
        $pending = max(0, $requested - $distributed);
        $exceeded = max(0, $distributed - $requested);
        $unitPrice = (float) ($item->unit_price_snapshot ?? 0);

        return [
            'item' => $item,
            'product' => $item->product,
            'customer' => $item->customer ?: $request->customer,
            'requested_quantity' => $requested,
            'distributed_quantity' => $distributed,
            'pending_quantity' => $pending,
            'exceeded_quantity' => $exceeded,
            'unit_price' => $unitPrice,
            'total_value' => $distributed * $unitPrice,
        ];
    }

    public function updateStatus(BuyerRequest $request): BuyerRequest
    {
        if ($request->status === BuyerRequest::STATUS_CANCELLED) {
            return $request;
        }

        $summary = $this->summaryForRequest($request);
        $requested = (float) $summary['requested'];
        $distributed = (float) $summary['distributed'];
        $exceeded = (float) $summary['exceeded'];

        $status = match (true) {
            $distributed <= 0 => BuyerRequest::STATUS_OPEN,
            $exceeded > 0 => BuyerRequest::STATUS_EXCEEDED,
            $requested > 0 && $distributed >= $requested => BuyerRequest::STATUS_FULFILLED,
            default => BuyerRequest::STATUS_PARTIALLY_FULFILLED,
        };

        if ($request->status !== $status) {
            $request->forceFill(['status' => $status])->save();
        }

        return $request->refresh();
    }

    public function updateProjectOrganizationStatuses(int $tenantId, int $projectId, int $organizationId): void
    {
        BuyerRequest::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('organization_id', $organizationId)
            ->with('items')
            ->get()
            ->each(fn (BuyerRequest $request) => $this->updateStatus($request));
    }

    public function distributedQuantity(
        int $tenantId,
        int $projectId,
        int $organizationId,
        int $productId,
        ?int $customerId = null
    ): float {
        return (float) ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('product_id', $productId)
            ->whereNotNull('parent_delivery_id')
            ->where('status', 'approved')
            ->whereHas('customer', function ($query) use ($organizationId, $customerId) {
                $query->where('organization_id', $organizationId);

                if ($customerId) {
                    $query->whereKey($customerId);
                }
            })
            ->sum('quantity');
    }

    public function requestedQuantity(
        int $tenantId,
        int $projectId,
        int $organizationId,
        int $productId,
        ?int $customerId = null
    ): float {
        return (float) BuyerRequestItem::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->whereHas('buyerRequest', function ($query) use ($projectId, $organizationId, $customerId) {
                $query->where('sales_project_id', $projectId)
                    ->where('organization_id', $organizationId)
                    ->where('status', '!=', BuyerRequest::STATUS_CANCELLED);

                if ($customerId) {
                    $query->where(function ($nested) use ($customerId) {
                        $nested->where('customer_id', $customerId)
                            ->orWhereNull('customer_id');
                    });
                }
            })
            ->where(function ($query) use ($customerId) {
                if ($customerId) {
                    $query->where('customer_id', $customerId)->orWhereNull('customer_id');
                }
            })
            ->sum('requested_quantity');
    }

    public function remainingQuantity(
        int $tenantId,
        int $projectId,
        int $organizationId,
        int $productId,
        ?int $customerId = null
    ): float {
        return max(0, $this->requestedQuantity($tenantId, $projectId, $organizationId, $productId, $customerId)
            - $this->distributedQuantity($tenantId, $projectId, $organizationId, $productId, $customerId));
    }

    public function limitIsEnabled(SalesProject $project, Organization $organization): bool
    {
        if (Schema::hasColumn('buyer_requests', 'enforce_request_limits')) {
            if (BuyerRequest::where('tenant_id', $project->tenant_id)
                ->where('sales_project_id', $project->id)
                ->where('organization_id', $organization->id)
                ->where('enforce_request_limits', true)
                ->where('status', '!=', BuyerRequest::STATUS_CANCELLED)
                ->exists()) {
                return true;
            }
        }

        $pivot = $project->organizations()
            ->where('organizations.id', $organization->id)
            ->first()?->pivot;

        return (bool) ($pivot?->enforce_request_limits ?? false);
    }

    public function organizationReport(SalesProject $project, Organization $organization): Collection
    {
        $customerIds = Customer::where('tenant_id', $project->tenant_id)
            ->where('organization_id', $organization->id)
            ->pluck('id');

        $distributions = ProductionDelivery::where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', 'approved')
            ->whereIn('customer_id', $customerIds)
            ->with(['customer', 'product', 'associate.user'])
            ->orderBy('customer_id')
            ->orderBy('product_id')
            ->get();

        return $distributions
            ->groupBy(fn (ProductionDelivery $delivery) => $delivery->customer_id.'-'.$delivery->product_id)
            ->map(function (Collection $rows) use ($project, $organization) {
                $first = $rows->first();
                $requested = $this->requestedQuantity(
                    (int) $project->tenant_id,
                    (int) $project->id,
                    (int) $organization->id,
                    (int) $first->product_id,
                    (int) $first->customer_id
                );
                $distributed = (float) $rows->sum('quantity');
                $unitPrice = (float) $first->unit_price;

                return [
                    'customer' => $first->customer,
                    'product' => $first->product,
                    'requested_quantity' => $requested,
                    'distributed_quantity' => $distributed,
                    'pending_quantity' => max(0, $requested - $distributed),
                    'exceeded_quantity' => max(0, $distributed - $requested),
                    'unit_price' => $unitPrice,
                    'total_value' => (float) $rows->sum(fn ($row) => (float) $row->quantity * (float) $row->unit_price),
                    'distributions' => $rows,
                ];
            })
            ->values();
    }
}
