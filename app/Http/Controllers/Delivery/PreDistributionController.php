<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\PriceTable;
use App\Models\PriceTableItem;
use App\Models\PreDistributionItem;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PreDistributions\PreDistributionAccess;
use App\Services\PreDistributions\PreDistributionQueryService;
use App\Services\PreDistributions\PreDistributionReconciliationService;
use App\Services\PreDistributions\PreDistributionReportService;
use App\Services\PreDistributions\PreDistributionSheetService;
use App\Services\ProjectDistributionCustomerService;
use App\Services\TenantIdentityService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PreDistributionController extends Controller
{
    public function __construct(private PreDistributionSheetService $sheets, private PreDistributionAccess $access) {}

    public function index(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (! $request->expectsJson()) {
            $this->sheets->visibleQuery($tenantId, $request->user());

            return response()->view('delivery.pre-distributions', [
                'tenant' => $request->route('tenant'), 'manager' => $this->access->manager($request->user(), $tenantId),
            ])->header('Cache-Control', 'no-store, private');
        }
        $filters = $request->validate(['status' => 'nullable|in:draft,completed,cancelled', 'date' => 'nullable|date_format:Y-m-d', 'operator' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100']);
        $query = $this->sheets->visibleQuery($tenantId, $request->user())
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('operational_date', $date))
            ->when($filters['q'] ?? null, function ($query, $term) {
                $query->where(function ($match) use ($term) {
                    $match->where('title', 'like', '%'.$term.'%');
                    if (preg_match('/^(?:PD-)?(\d{1,18})$/i', $term, $code)) {
                        $match->orWhere('id', (int) $code[1]);
                    }
                });
            })
            ->when($filters['operator'] ?? null, fn ($query, $id) => $query->where('assigned_to', $id));

        return $this->json($query->orderByDesc('operational_date')->orderByDesc('id')->paginate(20));
    }

    public function catalog(Request $request, ProjectDistributionCustomerService $customers)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->access->manager($request->user(), $tenantId), 403);
        $productsOnly = $request->boolean('products_only');
        $filters = $request->validate([
            'project' => [Rule::requiredIf($productsOnly), 'nullable', 'integer', 'min:1'],
            'customer_id' => [Rule::requiredIf($productsOnly), 'nullable', 'integer', 'min:1'],
            'q' => 'nullable|string|max:80', 'project_q' => 'nullable|string|max:80',
            'operator_q' => 'nullable|string|max:80', 'products_only' => 'nullable|boolean',
            'unpriced_only' => 'nullable|boolean',
        ]);
        if (empty($filters['project'])) {
            $operators = User::query()->where('status', true)->whereHas('tenants', fn ($query) => $query->where('tenants.id', $tenantId)->where('tenant_user.status', true)
                ->where(fn ($roles) => $roles->where('tenant_user.is_admin', true)->orWhereJsonContains('tenant_user.roles', 'entregador')->orWhereJsonContains('tenant_user.roles', 'registrador_entregas')->orWhereJsonContains('tenant_user.roles', 'admin'))
                ->when($filters['operator_q'] ?? null, fn ($q, $term) => $q->where('tenant_user.tenant_name', 'like', '%'.$term.'%')))
                ->orderBy('id')->limit(301)->get(['id']);
            $projects = SalesProject::query()->where('tenant_id', $tenantId)->whereIn('status', ['draft', 'active', 'deliveries_closed'])
                ->when($filters['project_q'] ?? null, fn ($q, $term) => $q->where('title', 'like', '%'.$term.'%'))->orderBy('title')->limit(301)->get(['id', 'title']);
            $names = app(TenantIdentityService::class)->namesForUsers($tenantId, $operators->pluck('id'));

            return $this->json(['projects' => $projects->take(300)->values(), 'projects_more' => $projects->count() > 300, 'operators_more' => $operators->count() > 300,
                'organizations' => Organization::query()->where('tenant_id', $tenantId)->orderBy('name')->limit(300)->get(['id', 'name']),
                'operators' => $operators->take(300)->map(fn ($user) => ['id' => $user->id, 'name' => $names[$user->id] ?? 'Usuário #'.$user->id])->values()]);
        }
        $project = SalesProject::query()->where('tenant_id', $tenantId)->findOrFail((int) $filters['project']);
        $products = Product::query()->where('tenant_id', $tenantId)->where('status', true)
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('name', 'like', '%'.$term.'%'));
        $destinations = $customers->customers($project);
        if ($productsOnly) {
            $customer = $destinations->firstWhere('id', (int) $filters['customer_id']);
            abort_unless($customer, 404);
            $pricedProducts = PriceTableItem::query()->select('product_id')
                ->where('price_table_id', $customer->price_table_id)->where('sale_price', '>', 0)
                ->whereHas('priceTable', fn ($table) => $table->where('tenant_id', $tenantId)->where('active', true));

            if ($request->boolean('unpriced_only')) {
                $products->whereNotIn('id', $pricedProducts);
            } else {
                $products->whereIn('id', $pricedProducts);
            }

            return $this->json(['products' => $products->orderBy('name')->limit(50)->get(['id', 'name', 'unit'])]);
        }

        $organizations = Organization::query()->where('tenant_id', $tenantId)->whereIn('id', $destinations->pluck('organization_id')->filter())->orderBy('name')->get(['id', 'name']);
        $tables = PriceTable::query()->where('tenant_id', $tenantId)->where('active', true)
            ->whereIn('id', $destinations->pluck('price_table_id')->filter())->get(['id', 'name'])->keyBy('id');

        return $this->json(['organizations' => $organizations, 'customers' => $destinations->map(fn ($customer) => [
            'id' => $customer->id, 'name' => $customer->trade_name ?: $customer->name, 'organization_id' => $customer->organization_id,
            'price_table' => $tables->get($customer->price_table_id),
        ])->values(),
            'products' => $products->orderBy('name')->limit(50)->get(['id', 'name', 'unit'])]);
    }

    public function referencePrice(Request $request, ProjectDistributionCustomerService $customers)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->access->manager($request->user(), $tenantId), 403);
        $data = $request->validate([
            'project_id' => 'required|integer|min:1', 'customer_id' => 'required|integer|min:1',
            'product_id' => 'required|integer|min:1', 'price_table_id' => 'nullable|integer|min:1',
        ]);
        $project = SalesProject::query()->where('tenant_id', $tenantId)->findOrFail($data['project_id']);
        $customers->assertAllowed($project, [$data['customer_id']]);
        $product = Product::query()->where('tenant_id', $tenantId)->findOrFail($data['product_id']);

        return $this->json($this->sheets->referenceQuote($project, $data['customer_id'], $product, $data['price_table_id'] ?? null));
    }

    public function show(Request $request)
    {
        $tenantId = $this->tenantId($request);
        $filters = $request->validate(['customer_id' => 'nullable|integer|min:1', 'pending' => 'nullable|boolean', 'q' => 'nullable|string|max:100']);
        $sheet = $this->sheets->visibleQuery($tenantId, $request->user())->findOrFail((int) $request->route('sheet'));
        $sheet->load(['projects' => fn ($query) => $query->where('sales_projects.tenant_id', $tenantId)->select('sales_projects.id', 'title')]);
        $columns = ['id', 'sheet_id', 'sales_project_id', 'customer_id', 'product_id', 'unit', 'planned_quantity',
            'reported_quantity', 'notes', 'delivered_at', 'logistics_mode', 'origin', 'cancelled_at', 'cancellation_reason'];
        $manager = $this->access->manager($request->user(), $tenantId);
        if ($manager) {
            array_push($columns, 'reference_price', 'reference_price_table_id');
        }
        $items = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sheet_id', $sheet->id)
            ->when($filters['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($filters['pending'] ?? false, fn ($q) => $q->whereNull('reported_quantity')->whereNull('cancelled_at'))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($match) => $match
                ->whereHas('product', fn ($product) => $product->withTrashed()->where('tenant_id', $tenantId)->where('name', 'like', '%'.$term.'%'))
                ->orWhereHas('customer', fn ($customer) => $customer->withTrashed()->where('tenant_id', $tenantId)->where('name', 'like', '%'.$term.'%'))))
            ->with(['project' => fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId)->select('id', 'title'),
                'customer' => fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId)->select($manager ? ['id', 'name', 'price_table_id'] : ['id', 'name']),
                'product' => fn ($q) => $q->withoutGlobalScopes()->where('tenant_id', $tenantId)->select('id', 'name', 'unit')])
            ->orderBy('customer_id')->orderBy('id')->paginate(100, $columns);
        $destinations = DB::table('pre_distribution_items as i')->join('customers as c', fn ($j) => $j->on('c.id', '=', 'i.customer_id')->on('c.tenant_id', '=', 'i.tenant_id'))
            ->where('i.tenant_id', $tenantId)->where('i.sheet_id', $sheet->id)->select('c.id', 'c.name')->distinct()->orderBy('c.name')->get();

        $data = ['sheet' => $sheet, 'items' => $items, 'destinations' => $destinations, 'can_manage' => $manager];
        if ($manager) {
            $tableIds = $items->getCollection()->pluck('reference_price_table_id')->merge($items->getCollection()->pluck('customer.price_table_id'))->filter();
            $data['price_tables'] = PriceTable::withTrashed()->where('tenant_id', $tenantId)->whereIn('id', $tableIds)->get(['id', 'name', 'active', 'deleted_at']);
            $data['reference_totals'] = $this->referenceTotals($tenantId, $sheet->id);
        }

        return $this->json($data);
    }

    private function referenceTotals(int $tenantId, int $sheetId): array
    {
        $totals = ['planned' => '0', 'reported' => '0', 'unpriced' => 0];
        $rows = PreDistributionItem::query()->where('tenant_id', $tenantId)->where('sheet_id', $sheetId)
            ->whereNull('cancelled_at')->get(['reference_price', 'planned_quantity', 'reported_quantity']);
        foreach ($rows as $row) {
            if ($row->reference_price === null) {
                $totals['unpriced']++;
                continue;
            }
            foreach (['planned', 'reported'] as $kind) {
                $quantity = $row->{$kind.'_quantity'};
                if ($quantity !== null) {
                    $totals[$kind] = bcadd($totals[$kind], bcmul($quantity, $row->reference_price, 8), 8);
                }
            }
        }

        return $totals;
    }

    public function store(Request $request)
    {
        return $this->json($this->sheets->create($this->tenantId($request), $request->user(), $this->key($request), $request->all()), 201);
    }

    public function update(Request $request)
    {
        $tenantId = $this->tenantId($request);
        $sheetId = (int) $request->route('sheet');
        $result = $this->sheets->save($tenantId, $request->user(), $sheetId, $this->key($request), $request->all());
        if ($this->access->manager($request->user(), $tenantId)) {
            $result['reference_totals'] = $this->referenceTotals($tenantId, $sheetId);
            $result['saved_references'] = PreDistributionItem::query()->where('tenant_id', $tenantId)->where('sheet_id', $sheetId)
                ->whereIn('id', collect($request->input('items'))->pluck('id')->filter()->all())
                ->get(['id', 'reference_price', 'reference_price_table_id'])->keyBy('id');
        }

        return $this->json($result);
    }

    public function duplicate(Request $request)
    {
        return $this->json($this->sheets->duplicate($this->tenantId($request), $request->user(), (int) $request->route('sheet'), $this->key($request), $request->all()), 201);
    }

    public function updateDetails(Request $request)
    {
        return $this->json($this->sheets->updateDetails($this->tenantId($request), $request->user(), (int) $request->route('sheet'), $this->key($request), $request->all()));
    }

    public function transition(Request $request)
    {
        return $this->json($this->sheets->transition($this->tenantId($request), $request->user(), (int) $request->route('sheet'), $this->key($request), $request->all()));
    }

    public function cancelItem(Request $request)
    {
        return $this->json($this->sheets->cancelItem($this->tenantId($request), $request->user(), (int) $request->route('sheet'), (int) $request->route('item'), $this->key($request), $request->all()));
    }

    public function consolidate(Request $request, PreDistributionQueryService $queries)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->access->manager($request->user(), $tenantId), 403);
        $ids = $request->validate(['sheet_ids' => 'required|array|min:1|max:100'])['sheet_ids'];

        return $this->json($queries->consolidate($tenantId, $request->user(), $ids, $request->all()));
    }

    public function origins(Request $request, PreDistributionQueryService $queries)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->access->manager($request->user(), $tenantId), 403);
        $ids = $request->validate(['sheet_ids' => 'required|array|min:1|max:100'])['sheet_ids'];

        return $this->json($queries->origins($tenantId, $request->user(), $ids, $request->all()));
    }

    public function history(Request $request)
    {
        $tenantId = $this->tenantId($request);
        $sheet = $this->sheets->visibleQuery($tenantId, $request->user())->findOrFail((int) $request->route('sheet'));
        $columns = ['id', 'event', 'version', 'reason', 'created_at'];
        if ($this->access->manager($request->user(), $tenantId)) {
            $columns = ['*'];
        }

        return $this->json($sheet->events()->where('tenant_id', $tenantId)->orderByDesc('id')->paginate(50, $columns));
    }

    public function snippets(Request $request, PreDistributionReconciliationService $reconciliation)
    {
        $tenantId = $this->tenantId($request);
        $reconciliation->authorize($tenantId, $request->user());
        $filters = $request->validate(['reception' => 'required|integer|min:1']);
        $reception = ProductionDelivery::query()->where('tenant_id', $tenantId)->whereNull('parent_delivery_id')
            ->where('status', 'approved')->with('product')->findOrFail($filters['reception']);
        $items = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNull('cancelled_at')
            ->where('sales_project_id', $reception->sales_project_id)->where('product_id', $reception->product_id)
            ->where('unit', trim((string) $reception->product?->unit))->where('reported_quantity', '>', 0)
            ->whereHas('sheet', fn ($q) => $q->where('tenant_id', $tenantId)->where('status', 'completed'))
            ->with(['sheet' => fn ($q) => $q->where('tenant_id', $tenantId)->select('id', 'title', 'version', 'operational_date'),
                'customer' => fn ($q) => $q->where('tenant_id', $tenantId)->select('id', 'name')])
            ->orderBy('sheet_id')->orderBy('id')->paginate(30, ['id', 'sheet_id', 'customer_id', 'unit', 'reported_quantity', 'reference_price']);
        $progress = $reconciliation->progressMany($tenantId, collect($items->items())->pluck('id')->all());
        $items->through(function ($item) use ($progress) {
            $item->setAttribute('progress', $progress[$item->id]);

            return $item;
        });

        return $this->json($items);
    }

    public function reconciliation(Request $request, PreDistributionReconciliationService $reconciliation)
    {
        $tenantId = $this->tenantId($request);
        $reconciliation->authorize($tenantId, $request->user());
        $sheet = $this->sheets->visibleQuery($tenantId, $request->user())->findOrFail((int) $request->route('sheet'));
        $items = $sheet->items()->where('tenant_id', $tenantId)->orderBy('id')->paginate(30, ['id', 'customer_id', 'product_id', 'unit']);
        $progress = $reconciliation->progressMany($tenantId, collect($items->items())->pluck('id')->all());

        return $this->json($items->through(fn ($item) => ['item' => $item, 'progress' => $progress[$item->id]]));
    }

    public function officialCandidates(Request $request, PreDistributionReconciliationService $reconciliation)
    {
        $tenantId = $this->tenantId($request);
        $reconciliation->authorize($tenantId, $request->user());
        $item = PreDistributionItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sheet_id', (int) $request->route('sheet'))->findOrFail((int) $request->route('item'));
        $rows = ProductionDelivery::query()->where('tenant_id', $tenantId)->where('sales_project_id', $item->sales_project_id)
            ->where('product_id', $item->product_id)->where('customer_id', $item->customer_id)->where('status', 'approved')->whereNotNull('parent_delivery_id')
            ->whereHas('parentDelivery', fn ($q) => $q->where('tenant_id', $tenantId)->whereNull('parent_delivery_id')->where('status', 'approved'))
            ->with(['associate' => fn ($q) => $q->where('tenant_id', $tenantId)->select('id', 'user_id')])->orderByDesc('delivery_date')->orderByDesc('id')
            ->paginate(30, ['id', 'associate_id', 'parent_delivery_id', 'quantity', 'unit_price', 'delivery_date']);
        $names = app(TenantIdentityService::class)->namesForUsers($tenantId, collect($rows->items())->pluck('associate.user_id')->filter());
        $used = DB::table('pre_distribution_links')->where('tenant_id', $tenantId)->whereIn('distribution_id', collect($rows->items())->pluck('id'))
            ->whereNull('reversed_at')->groupBy('distribution_id')->selectRaw('distribution_id, SUM(quantity) as used')->pluck('used', 'distribution_id');
        $rows->through(fn ($row) => ['id' => $row->id, 'reception_id' => $row->parent_delivery_id,
            'associate' => $names[$row->associate?->user_id] ?? 'Associado #'.$row->associate_id, 'quantity' => $row->quantity, 'unit_price' => $row->unit_price,
            'delivery_date' => $row->delivery_date?->format('Y-m-d'), 'available' => bcsub($row->quantity, (string) ($used[$row->id] ?? 0), 4)]);

        return $this->json($rows);
    }

    public function reverseLink(Request $request, PreDistributionReconciliationService $reconciliation)
    {
        return $this->json($reconciliation->reverse($this->tenantId($request), $request->user(), (int) $request->route('link'), $this->key($request), $request->all()));
    }

    public function linkExisting(Request $request, PreDistributionReconciliationService $reconciliation)
    {
        return $this->json($reconciliation->linkExisting($this->tenantId($request), $request->user(), $this->key($request), $request->all()));
    }

    public function createReport(Request $request, PreDistributionReportService $reports)
    {
        return $this->json($reports->create($this->tenantId($request), $request->user(), $this->key($request), $request->all()), 201);
    }

    public function reports(Request $request)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->access->manager($request->user(), $tenantId), 403);

        return $this->json(DB::table('pre_distribution_reports')->where('tenant_id', $tenantId)->orderByDesc('id')
            ->paginate(30, ['id', 'code', 'series_id', 'revision', 'created_at', 'sha256']));
    }

    public function sheetTable(Request $request, PreDistributionReportService $reports)
    {
        $tenantId = $this->tenantId($request);
        $sheet = $this->sheets->visibleQuery($tenantId, $request->user())->findOrFail((int) $request->route('sheet'));
        $showPrices = $this->access->manager($request->user(), $tenantId);

        return $this->json([
            'groups' => $reports->sheetGroups($request->route('tenant'), $sheet, $showPrices),
            'show_prices' => $showPrices,
        ]);
    }

    public function sheetPdf(Request $request, PreDistributionReportService $reports)
    {
        $tenantId = $this->tenantId($request);
        $sheet = $this->sheets->visibleQuery($tenantId, $request->user())->findOrFail((int) $request->route('sheet'));

        $response = $reports->printSheet($request->route('tenant'), $sheet, $this->access->manager($request->user(), $tenantId))
            ->stream($sheet->code.'.pdf');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function reportPdf(Request $request, PreDistributionReportService $reports)
    {
        $file = $reports->bytes($this->tenantId($request), $request->user(), (int) $request->route('report'));

        return response($file['bytes'], 200, ['Content-Type' => 'application/pdf', 'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'attachment; filename="'.$file['filename'].'"', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function reportSource(Request $request)
    {
        $tenantId = $this->tenantId($request);
        abort_unless($this->access->manager($request->user(), $tenantId), 403);
        $report = DB::table('pre_distribution_reports')->where('tenant_id', $tenantId)->find((int) $request->route('report'));
        abort_unless($report, 404);
        $snapshot = json_decode($report->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return $this->json(Arr::only($snapshot, ['sheet_ids', 'filters', 'include_prices']));
    }

    private function tenantId(Request $request): int
    {
        $tenant = $request->route('tenant');
        abort_unless($tenant instanceof Tenant, 404);
        $this->access->assertContext($request->user(), (int) $tenant->id);

        return (int) $tenant->id;
    }

    private function key(Request $request): string
    {
        return (string) ($request->header('Idempotency-Key') ?: $request->input('operation_key', ''));
    }

    private function json(mixed $value, int $status = 200)
    {
        return response()->json($value, $status, ['Cache-Control' => 'no-store, private']);
    }
}
