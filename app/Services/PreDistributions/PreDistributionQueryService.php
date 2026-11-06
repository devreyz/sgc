<?php

namespace App\Services\PreDistributions;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PreDistributionQueryService
{
    public function __construct(private PreDistributionAccess $access, private PreDistributionSheetService $sheets) {}

    public function selection(int $tenantId, User $actor, array $ids): array
    {
        $this->access->assertContext($actor, $tenantId);
        abort_unless($this->access->manager($actor, $tenantId), 403);
        $ids = Validator::make(['sheet_ids' => $ids], ['sheet_ids' => 'required|array|min:1|max:100', 'sheet_ids.*' => 'required|integer|distinct|min:1'])->validate()['sheet_ids'];
        abort_unless($this->sheets->visibleQuery($tenantId, $actor)->whereIn('id', $ids)->count() === count($ids), 404);

        return array_map('intval', $ids);
    }

    public function consolidate(int $tenantId, User $actor, array $ids, array $filters = [])
    {
        $ids = $this->selection($tenantId, $actor, $ids);
        $query = $this->items($tenantId, $ids, $filters);

        return $query->join('sales_projects as p', fn ($join) => $join->on('p.id', '=', 'i.sales_project_id')->on('p.tenant_id', '=', 'i.tenant_id'))
            ->join('customers as c', fn ($join) => $join->on('c.id', '=', 'i.customer_id')->on('c.tenant_id', '=', 'i.tenant_id'))
            ->join('products as pr', fn ($join) => $join->on('pr.id', '=', 'i.product_id')->on('pr.tenant_id', '=', 'i.tenant_id'))
            ->groupBy('i.tenant_id', 'i.sales_project_id', 'i.customer_id', 'i.product_id', 'i.unit')
            ->select('i.tenant_id', 'i.sales_project_id', 'i.customer_id', 'i.product_id', 'i.unit')
            ->selectRaw('MAX(p.title) as project, MAX(c.name) as customer, MAX(pr.name) as product, SUM(i.planned_quantity) as planned_quantity, SUM(i.reported_quantity) as reported_quantity')
            ->selectRaw('COUNT(*) as source_items, COUNT(DISTINCT i.sheet_id) as source_sheets, COUNT(DISTINCT s.operational_date) as source_days, SUM(CASE WHEN i.reported_quantity IS NULL THEN 1 ELSE 0 END) as pending_items')
            ->orderBy('customer')->orderBy('product')->orderBy('i.sales_project_id')->paginate(50)
            ->through(function ($row) {
                $row->possible_duplicate = (int) $row->source_items > (int) $row->source_days;

                return $row;
            });
    }

    public function origins(int $tenantId, User $actor, array $ids, array $filters = [])
    {
        $ids = $this->selection($tenantId, $actor, $ids);

        return $this->items($tenantId, $ids, $filters)->select('i.id', 'i.sheet_id', 'i.sales_project_id', 'i.customer_id', 'i.product_id',
            'i.unit', 'i.planned_quantity', 'i.reported_quantity', 'i.origin', 's.operational_date', 's.assigned_to', 's.status')
            ->orderBy('i.sheet_id')->orderBy('i.id')->paginate(100);
    }

    private function items(int $tenantId, array $ids, array $filters): Builder
    {
        $filters = Validator::make($filters, ['sales_project_id' => 'nullable|integer|min:1', 'customer_id' => 'nullable|integer|min:1',
            'product_id' => 'nullable|integer|min:1', 'organization_id' => 'nullable|integer|min:1', 'assigned_to' => 'nullable|integer|min:1',
            'unit' => 'nullable|string|max:32', 'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d'.(! empty($filters['date_from']) ? '|after_or_equal:date_from' : '')])->validate();

        return DB::table('pre_distribution_items as i')
            ->join('pre_distribution_sheets as s', fn ($join) => $join->on('s.id', '=', 'i.sheet_id')->on('s.tenant_id', '=', 'i.tenant_id'))
            ->where('i.tenant_id', $tenantId)->whereIn('i.sheet_id', $ids)->whereNull('i.cancelled_at')->where('s.status', '!=', 'cancelled')
            ->when($filters['sales_project_id'] ?? null, fn ($query, $id) => $query->where('i.sales_project_id', $id))
            ->when($filters['customer_id'] ?? null, fn ($query, $id) => $query->where('i.customer_id', $id))
            ->when($filters['product_id'] ?? null, fn ($query, $id) => $query->where('i.product_id', $id))
            ->when($filters['unit'] ?? null, fn ($query, $unit) => $query->where('i.unit', $unit))
            ->when($filters['assigned_to'] ?? null, fn ($query, $id) => $query->where('s.assigned_to', $id))
            ->when($filters['organization_id'] ?? null, fn ($query, $id) => $query->whereExists(fn ($q) => $q->selectRaw('1')->from('customers as destination')->whereColumn('destination.id', 'i.customer_id')->whereColumn('destination.tenant_id', 'i.tenant_id')->where('destination.organization_id', $id)))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('s.operational_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('s.operational_date', '<=', $date));
    }

    public function reportSources(int $tenantId, User $actor, array $ids, array $filters): Collection
    {
        $ids = $this->selection($tenantId, $actor, $ids);
        $limit = max(1, min(5000, (int) config('pre_distributions.max_report_items', 500)));
        $rows = $this->items($tenantId, $ids, $filters)
            ->join('sales_projects as p', fn ($j) => $j->on('p.id', '=', 'i.sales_project_id')->on('p.tenant_id', '=', 'i.tenant_id'))
            ->join('customers as c', fn ($j) => $j->on('c.id', '=', 'i.customer_id')->on('c.tenant_id', '=', 'i.tenant_id'))
            ->join('products as pr', fn ($j) => $j->on('pr.id', '=', 'i.product_id')->on('pr.tenant_id', '=', 'i.tenant_id'))
            ->select('i.*', 's.version as sheet_version', 's.operational_date', 's.assigned_to', 's.status as sheet_status', 'p.title as project_name', 'pr.name as product_name')
            ->selectRaw("COALESCE(NULLIF(TRIM(c.trade_name), ''), c.name) as customer_name")
            ->orderBy('p.title')->orderBy('customer_name')->orderBy('pr.name')->orderBy('i.id')->limit($limit + 1)->get();
        if ($rows->count() > $limit) {
            throw ValidationException::withMessages(['sheet_ids' => 'Selecione até '.$limit.' linhas por relatório; reduza o período ou as planilhas.']);
        }

        return $rows;
    }
}
