<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectAssociate;
use App\Models\ProjectAssociateProductLimit;
use App\Models\SalesProject;
use App\Services\AssociateFinancialSummaryService;
use App\Services\AssociateProjectLimitService;
use App\Services\TenantIdentityService;
use App\Services\TenantNotificationDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class AssociateProjectController extends Controller
{
    public function __construct(
        private readonly AssociateProjectLimitService $limits,
        private readonly AssociateFinancialSummaryService $financial,
        private readonly TenantIdentityService $identities,
        private readonly TenantNotificationDispatcher $notifications,
    ) {
        $this->middleware(['auth', 'any.role:registrador_entregas']);
    }

    public function index(Request $request)
    {
        $project = $this->projectContext($request);

        return view('delivery.project-associates', [
            'project' => $project,
            'canManage' => $this->canManageLimits($request),
        ]);
    }

    public function associatesData(Request $request): JsonResponse
    {
        $project = $this->projectContext($request);
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:active,blocked,unconfigured',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = Associate::query()
            ->where('associates.tenant_id', $project->tenant_id)
            ->leftJoin('tenant_user as project_member', function ($join) use ($project) {
                $join->on('project_member.user_id', '=', 'associates.user_id')
                    ->where('project_member.tenant_id', '=', $project->tenant_id);
            })
            ->leftJoin('project_associates as project_link', function ($join) use ($project) {
                $join->on('project_link.associate_id', '=', 'associates.id')
                    ->where('project_link.tenant_id', '=', $project->tenant_id)
                    ->where('project_link.sales_project_id', '=', $project->id);
            })
            ->select([
                'associates.id', 'associates.user_id', 'associates.tenant_id', 'associates.member_code',
                'associates.registration_number', 'associates.district', 'associates.city',
                'project_link.status as participation_status', 'project_link.financial_limit',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder, string $search) {
                $like = '%'.$search.'%';
                $builder->where(function (Builder $nested) use ($like) {
                    $nested->where('project_member.tenant_name', 'like', $like)
                        ->orWhere('associates.member_code', 'like', $like)
                        ->orWhere('associates.registration_number', 'like', $like)
                        ->orWhere('associates.district', 'like', $like)
                        ->orWhere('associates.city', 'like', $like);
                });
            })
            ->when(($validated['status'] ?? null) === 'active', fn (Builder $builder) => $builder->where('project_link.status', 'active'))
            ->when(($validated['status'] ?? null) === 'blocked', fn (Builder $builder) => $builder->where('project_link.status', 'blocked'))
            ->when(($validated['status'] ?? null) === 'unconfigured', fn (Builder $builder) => $builder->whereNull('project_link.id'))
            ->orderByRaw("COALESCE(NULLIF(TRIM(project_member.tenant_name), ''), 'zzzz')")
            ->orderBy('associates.id');

        $page = $query->paginate(15);
        $items = collect($page->items());
        $associateIds = $items->pluck('id');
        $names = $this->identities->namesForUsers($project->tenant_id, $items->pluck('user_id'));
        $productCounts = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereIn('associate_id', $associateIds)
            ->where('status', 'active')
            ->selectRaw('associate_id, COUNT(*) as total, SUM(max_quantity * COALESCE(reference_unit_price, 0)) as planned_value')
            ->groupBy('associate_id')
            ->get()
            ->keyBy('associate_id');
        $consumed = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereIn('associate_id', $associateIds)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->selectRaw('associate_id, SUM(gross_value) as total')
            ->groupBy('associate_id')
            ->pluck('total', 'associate_id');

        $page->setCollection($items->map(function (Associate $associate) use ($project, $names, $productCounts, $consumed) {
            $specificLimit = $associate->getAttribute('financial_limit');
            $financialLimit = $specificLimit !== null
                ? (float) $specificLimit
                : ($project->max_total_value_per_associate !== null ? (float) $project->max_total_value_per_associate : null);
            $used = (float) ($consumed[$associate->id] ?? 0);
            $limitStats = $productCounts->get($associate->id);
            $planned = (float) ($limitStats?->planned_value ?? 0);

            return [
                'id' => $associate->id,
                'name' => $names[$associate->user_id] ?? 'Membro nao identificado',
                'code' => $associate->member_code ?: $associate->registration_number,
                'location' => $associate->district ?: $associate->city,
                'participation_status' => $associate->getAttribute('participation_status') ?: 'unconfigured',
                'financial_limit' => $financialLimit,
                'financial_consumed' => $used,
                'financial_remaining' => $financialLimit === null ? null : max(0, $financialLimit - $used),
                'product_limits' => (int) ($limitStats?->total ?? 0),
                'simulated_limit_value' => $planned,
                'simulated_limit_remaining' => $financialLimit === null
                    ? null
                    : max(0, $financialLimit - $planned),
                'manage_url' => route('delivery.projects.associates.show', [
                    'tenant' => request()->route('tenant'),
                    'project' => $project->id,
                    'associate' => $associate->id,
                ]),
            ];
        }));

        return response()->json($page->toArray());
    }

    public function productLimitsIndex(Request $request)
    {
        $project = $this->projectContext($request);

        return view('delivery.project-product-limits', [
            'project' => $project,
            'canManage' => $this->canManageLimits($request),
        ]);
    }

    public function productLimitsProducts(Request $request): JsonResponse
    {
        $project = $this->projectContext($request);
        $mode = $this->limits->projectMode($project);
        $table = $mode['customer']?->priceTable;
        if (! $mode['allows_product_limits'] || ! $table) {
            return response()->json([
                'products' => [],
                'budget' => $this->limits->simulatedBudgetSummary($project),
                'message' => 'A gestao por produto exige exatamente um cliente ativo com tabela de precos.',
            ]);
        }

        $items = $table->items()
            ->with('product:id,name,unit')
            ->whereHas('product', fn (Builder $query) => $query
                ->where('tenant_id', $project->tenant_id)
                ->where('status', true))
            ->get(['product_id', 'sale_price']);
        $productIds = $items->pluck('product_id');
        $allocations = $this->limits->productAllocationSummaries($project, $productIds);

        return $this->privateJson([
            'products' => $items
                ->map(fn ($item) => [
                    'id' => (int) $item->product_id,
                    'name' => $item->product?->name ?? 'Produto',
                    'unit' => $item->product?->unit ?? 'un',
                    'price' => (float) $item->sale_price,
                    'project_maximum' => $allocations->get($item->product_id)['project_maximum'] ?? null,
                    'allocated' => $allocations->get($item->product_id)['allocated_to_others'] ?? 0,
                    'available' => $allocations->get($item->product_id)['available_for_associate'] ?? null,
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'budget' => $this->limits->simulatedBudgetSummary($project),
        ]);
    }

    public function productLimitsBoard(Request $request): JsonResponse
    {
        $project = $this->projectContext($request);
        $product = Product::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereKey((int) $request->route('product'))
            ->firstOrFail(['id', 'name', 'unit']);
        $mode = $this->limits->projectMode($project);
        abort_unless($mode['allows_product_limits'] && $mode['customer']?->priceTable, 422);
        $price = $mode['customer']->priceTable->priceFor($product->id);
        abort_if($price === null, 422, 'Produto sem preco na tabela do cliente.');
        $price = (float) $price;

        $limits = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('product_id', $product->id)
            ->where('status', 'active')
            ->get()
            ->keyBy('associate_id');
        $delivered = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('product_id', $product->id)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::CANCELLED->value, DeliveryStatus::REJECTED->value])
            ->groupBy('associate_id')
            ->selectRaw('associate_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'associate_id');
        $associateIds = $limits->keys()->merge($delivered->keys())->map(fn ($id) => (int) $id)->unique()->values();
        $requestedAssociateId = $request->integer('associate_id');
        if ($requestedAssociateId > 0) {
            $requestedAssociate = Associate::query()
                ->where('tenant_id', $project->tenant_id)
                ->findOrFail($requestedAssociateId);
            if ($project->restrict_participants) {
                abort_unless(ProjectAssociate::query()
                    ->where('tenant_id', $project->tenant_id)
                    ->where('sales_project_id', $project->id)
                    ->where('associate_id', $requestedAssociate->id)
                    ->where('status', 'active')
                    ->exists(), 403);
            }
            $associateIds->push($requestedAssociate->id);
            $associateIds = $associateIds->unique()->values();
        }

        $associates = Associate::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereIn('id', $associateIds)
            ->get(['id', 'tenant_id', 'user_id', 'nickname', 'registration_number']);
        $names = $this->identities->namesForUsers($project->tenant_id, $associates->pluck('user_id'));
        $links = ProjectAssociate::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->whereIn('associate_id', $associateIds)
            ->get(['associate_id', 'financial_limit', 'status'])
            ->keyBy('associate_id');
        $plannedByAssociate = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('status', 'active')
            ->whereIn('associate_id', $associateIds)
            ->groupBy('associate_id')
            ->selectRaw('associate_id, SUM(max_quantity * COALESCE(reference_unit_price, 0)) as total')
            ->pluck('total', 'associate_id');

        $allocation = $this->limits->productAllocationSummary($project, $product->id);
        $projectMaximum = $allocation['project_maximum'];
        $totalCommitted = (float) $allocation['allocated_to_others'];
        $rows = $associates->map(function (Associate $associate) use (
            $project,
            $product,
            $price,
            $limits,
            $delivered,
            $links,
            $plannedByAssociate,
            $projectMaximum,
            $totalCommitted,
            $names,
            $request,
        ) {
            $limit = $limits->get($associate->id);
            $current = (float) ($limit?->max_quantity ?? 0);
            $used = (float) ($delivered[$associate->id] ?? 0);
            $currentValue = $current * $price;
            $planned = (float) ($plannedByAssociate[$associate->id] ?? 0);
            $otherPlanned = max(0, $planned - $currentValue);
            $link = $links->get($associate->id);
            $financialCeiling = $link?->financial_limit !== null
                ? (float) $link->financial_limit
                : ($project->max_total_value_per_associate !== null
                    ? (float) $project->max_total_value_per_associate
                    : null);
            $availableByProject = $projectMaximum === null
                ? null
                : max(0, (float) $projectMaximum - max(0, $totalCommitted - $current));
            $availableByFinancial = $financialCeiling === null || $price <= 0
                ? null
                : max(0, ($financialCeiling - $otherPlanned) / $price);
            $caps = collect([$availableByProject, $availableByFinancial])
                ->filter(fn ($value) => $value !== null);
            $effectiveMaximum = $caps->isEmpty() ? max($current, $used, 1000) : (float) $caps->min();
            $sliderMaximum = max($current, $used, $effectiveMaximum);

            return [
                'associate_id' => $associate->id,
                'name' => $names[$associate->user_id] ?? 'Associado nao identificado',
                'nickname' => $associate->nickname,
                'registration' => $associate->registration_number,
                'participation' => $link?->status ?? ($project->restrict_participants ? 'unconfigured' : 'open'),
                'current_quantity' => $current,
                'delivered_quantity' => $used,
                'minimum_quantity' => $used,
                'maximum_quantity' => $sliderMaximum,
                'available_by_project' => $availableByProject,
                'available_by_financial' => $availableByFinancial,
                'unit_price' => $price,
                'simulated_value' => $currentValue,
                'associate_planned_value' => $planned,
                'financial_ceiling' => $financialCeiling,
                'update_url' => route('delivery.projects.associates.limits.product', [
                    'tenant' => $request->route('tenant'),
                    'project' => $project->id,
                    'associate' => $associate->id,
                ]),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $availableQuery = Associate::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereHas('user', fn (Builder $query) => $query->where('status', true))
            ->whereNotIn('id', $associateIds);
        if ($project->restrict_participants) {
            $availableQuery->whereIn('id', ProjectAssociate::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('sales_project_id', $project->id)
                ->where('status', 'active')
                ->select('associate_id'));
        }
        $available = $availableQuery
            ->limit(200)
            ->get(['id', 'tenant_id', 'user_id', 'nickname', 'registration_number']);
        $availableNames = $this->identities->namesForUsers($project->tenant_id, $available->pluck('user_id'));

        return $this->privateJson([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit ?? 'un',
                'price' => $price,
                'project_maximum' => $projectMaximum,
                'committed' => $totalCommitted,
                'available' => $projectMaximum === null ? null : max(0, (float) $projectMaximum - $totalCommitted),
                'simulated_value' => $totalCommitted * $price,
            ],
            'project_budget' => $this->limits->simulatedBudgetSummary($project),
            'rows' => $rows,
            'available_associates' => $available->map(fn (Associate $associate) => [
                'id' => $associate->id,
                'name' => $availableNames[$associate->user_id] ?? 'Associado nao identificado',
                'nickname' => $associate->nickname,
                'registration' => $associate->registration_number,
            ])->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values(),
            'can_manage' => $this->canManageLimits($request),
        ]);
    }

    public function updateParticipation(Request $request): JsonResponse
    {
        $this->authorizeLimitManagement($request);
        [$project, $associate] = $this->context($request);
        $validated = $request->validate(['status' => 'required|in:active,blocked']);

        $link = ProjectAssociate::query()->firstOrNew([
            'tenant_id' => $project->tenant_id,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
        ]);
        if (! $link->exists) {
            $link->created_by = $request->user()->id;
        }
        $link->fill([
            'status' => $validated['status'],
            'updated_by' => $request->user()->id,
        ])->save();

        activity('associate_project_limits')->performedOn($link)->withProperties([
            'tenant_id' => $project->tenant_id,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
            'status' => $validated['status'],
        ])->log('Participacao do associado atualizada');

        $this->notifyLimitChange($project, $associate, 'Participacao atualizada',
            $validated['status'] === 'active' ? 'Sua participacao foi liberada.' : 'Sua participacao foi bloqueada.');

        return response()->json(['message' => 'Participacao atualizada.', 'status' => $link->status]);
    }

    public function show(Request $request)
    {
        [$project, $associate] = $this->context($request);

        return view('delivery.associate-project', [
            'project' => $project,
            'associate' => $associate,
            'canManageLimits' => $this->canManageLimits($request),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$project, $associate] = $this->context($request);
        $section = (string) $request->route('section');

        return match ($section) {
            'summary' => response()->json($this->summary($project, $associate)),
            'limits' => response()->json($this->limitsData($project, $associate)),
            'products' => response()->json(['data' => $this->availableProducts($project, $associate)]),
            'deliveries' => response()->json($this->deliveries($request, $project, $associate)),
            'distributions' => response()->json($this->distributions($request, $project, $associate)),
            'receipts' => response()->json($this->receipts($request, $project, $associate)),
            'payments' => response()->json($this->payments($request, $project, $associate)),
            'history' => response()->json($this->history($request, $project, $associate)),
            default => response()->json(['message' => 'Secao nao encontrada.'], 404),
        };
    }

    public function updateFinancialLimit(Request $request): JsonResponse
    {
        $this->authorizeLimitManagement($request);
        [$project, $associate] = $this->context($request);
        $validated = $request->validate([
            'financial_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->limits->setFinancialLimit(
            $project,
            $associate,
            array_key_exists('financial_limit', $validated) && $validated['financial_limit'] !== null
                ? (float) $validated['financial_limit']
                : null,
            $validated['notes'] ?? null,
        );

        $this->notifyLimitChange($project, $associate, 'Limite financeiro atualizado', 'O limite financeiro do projeto foi atualizado.');

        return response()->json([
            'message' => 'Limite financeiro atualizado.',
            'summary' => $this->summary($project->fresh(), $associate),
        ]);
    }

    public function updateProductLimit(Request $request): JsonResponse
    {
        $this->authorizeLimitManagement($request);
        [$project, $associate] = $this->context($request);
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'max_quantity' => 'required|numeric|min:0.001',
            'notes' => 'nullable|string|max:1000',
        ]);

        $product = Product::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereKey($validated['product_id'])
            ->firstOrFail();

        $this->limits->setProductLimit(
            $project,
            $associate,
            (int) $validated['product_id'],
            (float) $validated['max_quantity'],
            $validated['notes'] ?? null,
        );

        $this->notifyLimitChange($project, $associate, 'Limite de produto atualizado',
            sprintf('%s: limite de %.3f %s.', $product->name, (float) $validated['max_quantity'], $product->unit));

        return response()->json([
            'message' => 'Limite do produto atualizado.',
            'data' => $this->limits->productLimits($project, $associate),
        ]);
    }

    private function context(Request $request): array
    {
        $project = $this->projectContext($request);
        $associate = Associate::query()
            ->where('tenant_id', $project->tenant_id)
            ->findOrFail((int) $request->route('associate'));

        return [$project, $associate];
    }

    private function notifyLimitChange(SalesProject $project, Associate $associate, string $title, string $body): void
    {
        $associate->loadMissing('user');
        $configuredRoles = $this->notifications->configuredRoles('associate.limit_updated', $project->tenant_id);
        $roles = array_values(array_diff($configuredRoles, ['associado']));
        $recipients = $this->notifications->usersForRoles($project->tenant_id, $roles);

        $this->notifications->dispatch('associate.limit_updated', $project->tenant_id, $recipients, [
            'title' => $title,
            'body' => $project->name.'. '.$body,
            'url' => route('delivery.projects.associates.show', [
                'tenant' => request()->route('tenant'),
                'project' => $project->id,
                'associate' => $associate->id,
            ], false),
            'icon' => 'gauge',
        ]);

        if (in_array('associado', $configuredRoles, true) && $associate->user) {
            $this->notifications->dispatch('associate.limit_updated', $project->tenant_id, [$associate->user], [
                'title' => $title,
                'body' => $project->name.'. '.$body,
                'url' => route('associate.projects', ['tenant' => request()->route('tenant')], false),
                'icon' => 'gauge',
            ]);
        }
    }

    private function projectContext(Request $request): SalesProject
    {
        $tenantId = (int) session('tenant_id');
        abort_unless($tenantId > 0, 403, 'Organizacao nao selecionada.');

        return SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer.priceTable', 'customers.priceTable'])
            ->findOrFail((int) $request->route('project'));
    }

    private function summary(SalesProject $project, Associate $associate): array
    {
        $summary = $this->limits->summary($project, $associate);
        $association = $this->limits->association($project, $associate);
        $financial = $this->financial->summary($project->tenant_id, $associate->id, $project->id);
        $receipts = AssociateReceipt::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id);

        return $summary + [
            'participation_status' => $association?->status ?? 'unconfigured',
            'restrict_participants' => (bool) $project->restrict_participants,
            'allow_any_product' => (bool) $project->allow_any_product,
            'can_deliver' => ! $project->restrict_participants || $association?->status === 'active',
            'total_net' => $financial['total_net'],
            'paid' => $financial['paid'],
            'receivable' => $financial['receivable'],
            'unbilled' => $financial['unbilled'],
            'receipt_count' => (clone $receipts)->count(),
            'obsolete_receipt_count' => (clone $receipts)->where('status', 'obsolete')->count(),
            'pending_distribution_receipts' => ProductionDelivery::query()
                ->where('tenant_id', $project->tenant_id)
                ->where('sales_project_id', $project->id)
                ->where('associate_id', $associate->id)
                ->whereNotNull('parent_delivery_id')
                ->whereNull('associate_receipt_id')
                ->where('status', DeliveryStatus::APPROVED->value)
                ->count(),
        ];
    }

    private function limitsData(SalesProject $project, Associate $associate): array
    {
        return [
            'summary' => $this->limits->summary($project, $associate),
            'products' => $this->limits->productLimits($project, $associate),
            'can_manage' => request()->user() ? $this->canManageLimits(request()) : false,
        ];
    }

    private function availableProducts(SalesProject $project, Associate $associate): array
    {
        $mode = $this->limits->projectMode($project);
        $table = $mode['customer']?->priceTable;
        if (! $mode['allows_product_limits'] || ! $table) {
            return [];
        }

        $items = $table->items()->with('product:id,name,unit')
            ->whereHas('product', fn (Builder $query) => $query
                ->where('tenant_id', $project->tenant_id)
                ->where('status', true))
            ->orderBy('product_id')
            ->get(['id', 'product_id', 'sale_price']);
        $allocations = $this->limits->productAllocationSummaries(
            $project,
            $items->pluck('product_id'),
            $associate->id,
        );

        return $items->map(fn ($item) => [
                'id' => $item->product_id,
                'name' => $item->product?->name,
                'unit' => $item->product?->unit,
                'price' => (float) $item->sale_price,
                'project_maximum' => $allocations->get($item->product_id)['project_maximum'] ?? null,
                'allocated_to_others' => $allocations->get($item->product_id)['allocated_to_others'] ?? 0,
                'available_for_associate' => $allocations->get($item->product_id)['available_for_associate'] ?? null,
            ])->values()->all();
    }

    private function deliveries(Request $request, SalesProject $project, Associate $associate): array
    {
        $query = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with(['product:id,name,unit', 'distributions:id,parent_delivery_id,quantity,associate_receipt_id,billing_status,paid']);

        $this->applyCommonFilters($query, $request);
        $page = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate($this->perPage($request));
        $names = $this->identities->namesForUsers($project->tenant_id, collect($page->items())->pluck('received_by'));

        $page->setCollection(collect($page->items())->map(function (ProductionDelivery $delivery) use ($names) {
            $distributed = (float) $delivery->distributions->sum('quantity');
            return [
                'id' => $delivery->id,
                'date' => $delivery->delivery_date?->format('d/m/Y'),
                'product' => $delivery->product?->name,
                'unit' => $delivery->product?->unit,
                'quantity' => (float) $delivery->quantity,
                'distributed' => $distributed,
                'remaining' => max(0, (float) $delivery->quantity - $distributed),
                'status' => $delivery->status?->value,
                'status_label' => $delivery->status?->getLabel(),
                'quality' => $delivery->quality_grade,
                'notes' => $delivery->notes,
                'registered_by' => $names[$delivery->received_by] ?? 'Membro nao identificado',
                'in_receipt' => $delivery->distributions->contains(fn ($item) => $item->associate_receipt_id !== null),
                'billed' => $delivery->distributions->contains(fn ($item) => ($item->billing_status?->value ?? $item->billing_status) !== 'unbilled'),
                'paid' => $delivery->distributions->contains(fn ($item) => (bool) $item->paid),
                'can_approve' => $delivery->status === DeliveryStatus::PENDING,
                'can_reject' => $delivery->status === DeliveryStatus::PENDING,
                'manage_url' => route('delivery.projects.deliveries', [
                    'tenant' => request()->route('tenant'),
                    'project' => $delivery->sales_project_id,
                    'delivery_id' => $delivery->id,
                ]),
            ];
        }));

        return $page->toArray();
    }

    private function distributions(Request $request, SalesProject $project, Associate $associate): array
    {
        $query = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->with(['product:id,name,unit', 'customer:id,name,trade_name', 'associateReceipt:id,receipt_year,receipt_number,status']);

        $this->applyCommonFilters($query, $request);
        $page = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(fn (ProductionDelivery $item) => [
            'id' => $item->id,
            'parent_delivery_id' => $item->parent_delivery_id,
            'date' => $item->delivery_date?->format('d/m/Y'),
            'product' => $item->product?->name,
            'unit' => $item->product?->unit,
            'customer' => $item->customer?->trade_name ?: $item->customer?->name,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'gross' => (float) $item->gross_value,
            'net' => (float) $item->net_value,
            'status' => $item->status?->value,
            'receipt' => $item->associateReceipt?->formatted_number,
            'receipt_status' => $item->associateReceipt?->status?->value,
            'billing_status' => $item->billing_status?->value,
            'paid' => (bool) $item->paid,
        ]));

        return $page->toArray();
    }

    private function receipts(Request $request, SalesProject $project, Associate $associate): array
    {
        $page = AssociateReceipt::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->withSum('payments', 'amount')
            ->orderByDesc('receipt_year')->orderByDesc('receipt_number')->orderByDesc('issued_at')
            ->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(fn (AssociateReceipt $receipt) => [
            'id' => $receipt->id,
            'number' => $receipt->formatted_number,
            'date' => $receipt->issued_at?->format('d/m/Y'),
            'gross' => (float) $receipt->total_gross,
            'fees' => (float) $receipt->total_fees,
            'net' => (float) $receipt->total_net,
            'paid' => (float) ($receipt->payments_sum_amount ?? $receipt->amount_paid ?? 0),
            'status' => $receipt->status?->value,
            'status_label' => $receipt->status?->getLabel(),
            'obsolete_reason' => $receipt->obsolete_reason,
            'locked' => $receipt->isLocked(),
            'reprint_url' => $receipt->status?->value === 'obsolete' ? null : route('delivery.projects.receipt-reprint', [
                'tenant' => request()->route('tenant'),
                'project' => $project->id,
                'receipt' => $receipt->id,
            ]),
        ]));

        return $page->toArray();
    }

    private function payments(Request $request, SalesProject $project, Associate $associate): array
    {
        $page = AssociateReceiptPayment::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereHas('receipt', fn (Builder $query) => $query
                ->where('sales_project_id', $project->id)
                ->where('associate_id', $associate->id))
            ->with('receipt:id,receipt_year,receipt_number')
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(fn ($payment) => [
            'id' => $payment->id,
            'receipt' => $payment->receipt?->formatted_number,
            'date' => $payment->payment_date?->format('d/m/Y'),
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
        ]));

        return $page->toArray();
    }

    private function history(Request $request, SalesProject $project, Associate $associate): array
    {
        $deliveryIds = ProductionDelivery::query()
            ->select('id')
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id);

        $page = Activity::query()
            ->where('tenant_id', $project->tenant_id)
            ->where(function (Builder $query) use ($project, $associate, $deliveryIds) {
                $query->where(function (Builder $properties) use ($project, $associate) {
                    $properties->where('properties->sales_project_id', $project->id)
                        ->where('properties->associate_id', $associate->id);
                })->orWhere(function (Builder $subject) use ($deliveryIds) {
                    $subject->where('subject_type', ProductionDelivery::class)
                        ->whereIn('subject_id', $deliveryIds);
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        $names = $this->identities->namesForUsers(
            $project->tenant_id,
            collect($page->items())->pluck('causer_id'),
        );
        $page->setCollection(collect($page->items())->map(fn (Activity $activity) => [
            'id' => $activity->id,
            'date' => $activity->created_at?->format('d/m/Y H:i'),
            'actor' => $activity->causer_id
                ? ($names[$activity->causer_id] ?? 'Membro nao identificado')
                : 'Sistema',
            'action' => $this->activityActionLabel($activity),
            'subject' => $this->activitySubjectLabel($activity),
        ]));

        return $page->toArray();
    }

    private function activityActionLabel(Activity $activity): string
    {
        $description = trim((string) $activity->description);
        if ($description !== '' && ! in_array($description, ['created', 'updated', 'deleted', 'restored'], true)) {
            return $description;
        }

        $subject = class_basename((string) $activity->subject_type);
        $event = $activity->event ?: $description;

        return match ([$subject, $event]) {
            ['ProductionDelivery', 'created'] => 'Entrega registrada',
            ['ProductionDelivery', 'updated'] => 'Entrega atualizada',
            ['ProductionDelivery', 'deleted'] => 'Entrega removida',
            ['ProjectAssociateProductLimit', 'created'] => 'Limite de produto criado',
            ['ProjectAssociateProductLimit', 'updated'] => 'Limite de produto atualizado',
            ['ProjectAssociate', 'created'] => 'Participacao configurada',
            ['ProjectAssociate', 'updated'] => 'Participacao atualizada',
            default => match ($event) {
                'created' => 'Registro criado',
                'updated' => 'Registro atualizado',
                'deleted' => 'Registro removido',
                'restored' => 'Registro restaurado',
                default => 'Alteracao registrada',
            },
        };
    }

    private function activitySubjectLabel(Activity $activity): string
    {
        if (! $activity->subject_id) {
            return 'Configuracao do associado';
        }

        $label = match (class_basename((string) $activity->subject_type)) {
            'ProductionDelivery' => 'Entrega',
            'ProjectAssociateProductLimit' => 'Limite de produto',
            'ProjectAssociate' => 'Participacao',
            'AssociateReceipt' => 'Comprovante',
            default => 'Registro',
        };

        return $label.' #'.$activity->subject_id;
    }

    private function applyCommonFilters(Builder $query, Request $request): void
    {
        $request->validate([
            'status' => 'nullable|string|max:30',
            'search' => 'nullable|string|max:100',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);
        $query->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('start_date'), fn (Builder $q) => $q->whereDate('delivery_date', '>=', $request->date('start_date')))
            ->when($request->filled('end_date'), fn (Builder $q) => $q->whereDate('delivery_date', '<=', $request->date('end_date')))
            ->when($request->filled('search'), fn (Builder $q) => $q->whereHas('product', fn (Builder $p) => $p->where('name', 'like', '%'.$request->string('search').'%')));
    }

    private function perPage(Request $request): int
    {
        return min(50, max(5, $request->integer('per_page', 15)));
    }

    private function privateJson(mixed $data, int $status = 200): JsonResponse
    {
        return response()
            ->json($data, $status)
            ->withHeaders([
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
            ]);
    }

    private function canManageLimits(Request $request): bool
    {
        return $request->user()->can('manage_associate_project_limits')
            || $request->user()->hasAnyRole(['admin', 'super_admin'])
            || $request->user()->hasRoleInTenant(['admin', 'registrador_entregas'], (int) session('tenant_id'));
    }

    private function authorizeLimitManagement(Request $request): void
    {
        abort_unless($this->canManageLimits($request), 403, 'Voce nao possui permissao para alterar limites.');
    }
}
