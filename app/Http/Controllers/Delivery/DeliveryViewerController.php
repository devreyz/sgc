<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\DeliveryProjectNote;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectAssociate;
use App\Models\ProjectAssociateProductLimit;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use App\Services\AssociateProjectLimitService;
use App\Services\TenantIdentityService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class DeliveryViewerController extends Controller
{
    public function index(Request $request): View
    {
        $this->tenantId();
        $tenant = $request->route('tenant');

        return view('delivery-viewer.index', compact('tenant'));
    }

    public function projectsData(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $projects = SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->with('customer:id,name,trade_name')
            ->withCount([
                'deliveries as receptions_count' => fn ($query) => $query->whereNull('parent_delivery_id'),
                'deliveries as distributions_count' => fn ($query) => $query->whereNotNull('parent_delivery_id'),
                'deliveries as pending_count' => fn ($query) => $query
                    ->whereNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::PENDING->value),
                'projectAssociates as participants_count' => fn ($query) => $query->where('status', 'active'),
                'associateProductLimits as product_limits_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->withSum([
                'deliveries as received_quantity' => fn ($query) => $query
                    ->whereNull('parent_delivery_id')
                    ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value]),
            ], 'quantity')
            ->withSum([
                'deliveries as distributed_quantity' => fn ($query) => $query
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED->value),
            ], 'quantity')
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('trade_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("CASE status WHEN 'active' THEN 0 WHEN 'deliveries_closed' THEN 1 ELSE 2 END")
            ->orderByDesc('reference_year')
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();
        $projects->getCollection()->transform(fn (SalesProject $project) => [
            'id' => $project->id,
            'title' => $project->title,
            'client' => $project->customer?->trade_name ?: $project->customer?->name,
            'status' => $project->status->value,
            'status_label' => $project->status->getLabel(),
            'received' => (float) ($project->received_quantity ?? 0),
            'distributed' => (float) ($project->distributed_quantity ?? 0),
            'associates' => (int) ($project->participants_count ?: $project->receptions_count),
            'pending' => (int) $project->pending_count,
            'url' => route('delivery-viewer.projects.show', [
                'tenant' => $request->route('tenant')->slug,
                'project' => $project->id,
            ]),
        ]);

        return $this->privateJson($projects);
    }

    public function show(Request $request): View
    {
        $project = $this->project($request);
        $tenant = $request->route('tenant');

        return view('delivery-viewer.show', compact('project', 'tenant'));
    }

    public function projectData(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $project = $this->project($request);
        $receivedByProduct = $this->quantityByProduct($tenantId, $project->id, false);
        $distributedByProduct = $this->quantityByProduct($tenantId, $project->id, true);
        $demandTotals = ProjectDemand::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(target_quantity) AS target_quantity')
            ->pluck('target_quantity', 'product_id');
        $productIds = $demandTotals->keys()
            ->merge($receivedByProduct->keys())
            ->merge($distributedByProduct->keys())
            ->unique()
            ->values();
        $products = Product::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->get(['id', 'name', 'unit'])
            ->keyBy('id');
        $productSummary = $productIds->map(function ($productId) use ($products, $demandTotals, $receivedByProduct, $distributedByProduct) {
            $target = $demandTotals->has($productId) ? (float) $demandTotals[$productId] : null;
            $received = (float) ($receivedByProduct[$productId] ?? 0);
            $distributed = (float) ($distributedByProduct[$productId] ?? 0);

            return [
                'id' => (int) $productId,
                'name' => $products->get($productId)?->name ?? 'Produto nao identificado',
                'unit' => $products->get($productId)?->unit ?? 'un',
                'target' => $target,
                'received' => $received,
                'distributed' => $distributed,
                'remaining_target' => $target === null ? null : max(0, $target - $received),
                'physical_balance' => max(0, $received - $distributed),
                'progress' => $target && $target > 0 ? min(100, ($received / $target) * 100) : 0,
            ];
        })->sortBy('name')->values();

        $associateIds = $this->projectAssociateIds($tenantId, $project->id);
        $associates = Associate::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $associateIds)
            ->get(['id', 'tenant_id', 'user_id', 'nickname', 'registration_number']);
        $names = app(TenantIdentityService::class)->namesForUsers($tenantId, $associates->pluck('user_id'));
        $associateStats = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->whereIn('associate_id', $associateIds)
            ->whereNull('deleted_at')
            ->groupBy('associate_id')
            ->selectRaw(
                'associate_id,
                SUM(CASE WHEN parent_delivery_id IS NULL AND status NOT IN (?, ?) THEN quantity ELSE 0 END) AS received,
                SUM(CASE WHEN parent_delivery_id IS NOT NULL AND status = ? THEN quantity ELSE 0 END) AS distributed,
                SUM(CASE WHEN parent_delivery_id IS NULL THEN 1 ELSE 0 END) AS deliveries_count',
                [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value, DeliveryStatus::APPROVED->value]
            )
            ->get()
            ->keyBy('associate_id');
        $limitTotals = ProjectAssociateProductLimit::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('status', 'active')
            ->groupBy('associate_id')
            ->selectRaw('associate_id, COUNT(*) AS products_count, SUM(max_quantity) AS maximum')
            ->get()
            ->keyBy('associate_id');
        $associateSummary = $associates->map(function (Associate $associate) use ($names, $associateStats, $limitTotals, $request, $project) {
            $stats = $associateStats->get($associate->id);
            $limits = $limitTotals->get($associate->id);
            $maximum = (float) ($limits?->maximum ?? 0);
            $received = (float) ($stats?->received ?? 0);
            $token = $this->associateToken($associate->id);

            return [
                'name' => $names[$associate->user_id] ?? 'Associado nao identificado',
                'nickname' => $associate->nickname,
                'registration' => $associate->registration_number,
                'received' => $received,
                'distributed' => (float) ($stats?->distributed ?? 0),
                'deliveries_count' => (int) ($stats?->deliveries_count ?? 0),
                'limited_products' => (int) ($limits?->products_count ?? 0),
                'maximum' => $maximum,
                'remaining' => max(0, $maximum - $received),
                'progress' => $maximum > 0 ? min(100, ($received / $maximum) * 100) : 0,
                'url' => route('delivery-viewer.associates.show', [
                    'tenant' => $request->route('tenant')->slug,
                    'project' => $project->id,
                    'associateToken' => $token,
                ]),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $stats = DB::table('production_deliveries')
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->whereNull('deleted_at')
            ->selectRaw(
                'SUM(CASE WHEN parent_delivery_id IS NULL AND status NOT IN (?, ?) THEN quantity ELSE 0 END) AS received,
                SUM(CASE WHEN parent_delivery_id IS NOT NULL AND status = ? THEN quantity ELSE 0 END) AS distributed,
                SUM(CASE WHEN parent_delivery_id IS NULL AND status = ? THEN 1 ELSE 0 END) AS pending',
                [
                    DeliveryStatus::REJECTED->value,
                    DeliveryStatus::CANCELLED->value,
                    DeliveryStatus::APPROVED->value,
                    DeliveryStatus::PENDING->value,
                ]
            )
            ->first();
        $customers = ProductionDelivery::query()
            ->where('production_deliveries.tenant_id', $tenantId)
            ->where('production_deliveries.sales_project_id', $project->id)
            ->whereNotNull('production_deliveries.parent_delivery_id')
            ->where('production_deliveries.status', DeliveryStatus::APPROVED->value)
            ->join('customers', 'customers.id', '=', 'production_deliveries.customer_id')
            ->groupBy('customers.id', 'customers.name', 'customers.trade_name')
            ->selectRaw('customers.name, customers.trade_name, SUM(production_deliveries.quantity) AS quantity')
            ->orderByDesc('quantity')
            ->get()
            ->map(fn ($customer) => [
                'name' => $customer->trade_name ?: $customer->name,
                'quantity' => (float) $customer->quantity,
            ]);
        $budget = app(AssociateProjectLimitService::class)->simulatedBudgetSummary($project);

        return $this->privateJson([
            'project' => [
                'title' => $project->title,
                'code' => $project->code,
                'status' => $project->status->getLabel(),
                'start_date' => $project->start_date?->format('d/m/Y'),
                'end_date' => $project->end_date?->format('d/m/Y'),
                'restricted' => (bool) $project->restrict_participants,
            ],
            'summary' => [
                'received' => (float) ($stats->received ?? 0),
                'distributed' => (float) ($stats->distributed ?? 0),
                'physical_balance' => max(0, (float) ($stats->received ?? 0) - (float) ($stats->distributed ?? 0)),
                'pending' => (int) ($stats->pending ?? 0),
                'associates' => $associateSummary->count(),
                'products' => $productSummary->count(),
                'planned_limit_value' => $budget['planned_value'],
                'project_ceiling' => $budget['ceiling'],
                'project_budget_remaining' => $budget['remaining'],
            ],
            'products' => $productSummary,
            'associates' => $associateSummary,
            'customers' => $customers,
        ]);
    }

    public function deliveriesData(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $project = $this->project($request);
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $associateToken = trim((string) $request->query('associate'));
        $associateId = $associateToken !== ''
            ? $this->associateIdFromToken($associateToken)
            : null;
        if ($associateId !== null) {
            abort_unless($this->projectAssociateIds($tenantId, $project->id)->contains($associateId), 404);
        }

        $deliveries = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->whereNull('parent_delivery_id')
            ->with([
                'associate:id,tenant_id,user_id,nickname,registration_number',
                'product:id,name,unit',
                'distributions' => fn ($query) => $query
                    ->with('customer:id,name,trade_name')
                    ->orderBy('customer_id'),
            ])
            ->when($associateId, fn ($query) => $query->where('associate_id', $associateId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->whereHas(
                'product',
                fn ($product) => $product->where('name', 'like', "%{$search}%")
            ))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->paginate(15);
        $names = app(TenantIdentityService::class)->namesForUsers(
            $tenantId,
            $deliveries->getCollection()->pluck('associate.user_id')
        );
        $deliveries->getCollection()->transform(function (ProductionDelivery $delivery) use ($names) {
            $distributed = $delivery->distributions
                ->whereNotIn('status', [DeliveryStatus::REJECTED, DeliveryStatus::CANCELLED])
                ->sum(fn ($item) => (float) $item->quantity);

            return [
                'id' => $delivery->id,
                'associate' => $names[$delivery->associate?->user_id] ?? 'Associado nao identificado',
                'product' => $delivery->product?->name ?? 'Produto nao identificado',
                'unit' => $delivery->product?->unit ?? 'un',
                'date' => $delivery->delivery_date?->format('d/m/Y'),
                'quantity' => (float) $delivery->quantity,
                'distributed' => (float) $distributed,
                'balance' => max(0, (float) $delivery->quantity - (float) $distributed),
                'status' => $delivery->status->value,
                'status_label' => $delivery->status->getLabel(),
                'destinations' => $delivery->distributions->map(fn (ProductionDelivery $distribution) => [
                    'customer' => $distribution->customer?->trade_name
                        ?: $distribution->customer?->name
                        ?: 'Cliente nao identificado',
                    'quantity' => (float) $distribution->quantity,
                ])->values(),
            ];
        });

        return $this->privateJson($deliveries);
    }

    public function associate(Request $request): View
    {
        $project = $this->project($request);
        $associate = $this->associateForProject($request, $project);
        $tenant = $request->route('tenant');

        return view('delivery-viewer.associate', compact('project', 'associate', 'tenant'));
    }

    public function associateData(Request $request, AssociateProjectLimitService $limitsService): JsonResponse
    {
        $tenantId = $this->tenantId();
        $project = $this->project($request);
        $associate = $this->associateForProject($request, $project);
        $limits = $limitsService->productLimits($project, $associate);
        $received = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->sum('quantity');
        $distributed = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->sum('quantity');
        $participation = ProjectAssociate::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->first(['status', 'financial_limit']);
        $distributionValue = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->sum('gross_value');
        $budget = $limitsService->simulatedBudgetSummary($project, $associate);

        return $this->privateJson([
            'associate' => [
                'name' => app(TenantIdentityService::class)->displayNameForAssociate($associate),
                'nickname' => $associate->nickname,
                'registration' => $associate->registration_number,
                'participation' => $participation?->status ?? ($project->restrict_participants ? 'not_configured' : 'open'),
            ],
            'summary' => [
                'received' => (float) $received,
                'distributed' => (float) $distributed,
                'physical_balance' => max(0, (float) $received - (float) $distributed),
                'products' => $limits->count(),
                'financial_limit' => $participation?->financial_limit !== null ? (float) $participation->financial_limit : null,
                'distributed_value' => (float) $distributionValue,
                'planned_limit_value' => $budget['planned_value'],
                'planned_limit_ceiling' => $budget['ceiling'],
                'planned_limit_remaining' => $budget['remaining'],
            ],
            'limits' => $limits->map(fn (array $limit) => [
                'product' => $limit['product'],
                'unit' => $limit['unit'],
                'maximum' => (float) $limit['maximum_quantity'],
                'received' => (float) $limit['delivered_quantity'],
                'distributed' => (float) $limit['distributed_quantity'],
                'remaining' => (float) $limit['remaining_quantity'],
                'progress' => min(100, (float) $limit['percent']),
                'unit_price' => (float) $limit['reference_unit_price'],
                'simulated_value' => (float) $limit['estimated_maximum_value'],
                'notes' => $limit['notes'],
            ])->values(),
            'deliveries_url' => route('delivery-viewer.projects.deliveries', [
                'tenant' => $request->route('tenant')->slug,
                'project' => $project->id,
                'associate' => $request->route('associateToken'),
            ]),
        ]);
    }

    public function notesData(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $project = $this->project($request);
        $notes = DeliveryProjectNote::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->latest()
            ->limit(30)
            ->get(['id', 'production_delivery_id', 'created_by', 'content', 'created_at']);
        $names = app(TenantIdentityService::class)->namesForUsers($tenantId, $notes->pluck('created_by'));
        $user = $request->user();

        return $this->privateJson($notes->map(fn (DeliveryProjectNote $note) => [
            'id' => $note->id,
            'delivery_id' => $note->production_delivery_id,
            'content' => $note->content,
            'author' => $names[$note->created_by] ?? 'Membro nao identificado',
            'created_at' => $note->created_at->format('d/m/Y H:i'),
            'can_delete' => (int) $note->created_by === (int) $user->id
                || $user->hasRoleInTenant('admin', $tenantId)
                || $user->isSuperAdmin(),
        ])->values());
    }

    public function storeNote(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = $this->tenantId();
        $project = SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $request->route('project'));
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1500'],
            'production_delivery_id' => ['nullable', 'integer'],
        ]);

        $deliveryId = $validated['production_delivery_id'] ?? null;
        if ($deliveryId !== null) {
            $deliveryExists = ProductionDelivery::query()
                ->where('tenant_id', $tenantId)
                ->where('sales_project_id', $project->id)
                ->whereKey($deliveryId)
                ->exists();
            abort_unless($deliveryExists, 404);
        }

        $note = new DeliveryProjectNote([
            'sales_project_id' => $project->id,
            'production_delivery_id' => $deliveryId,
            'created_by' => $request->user()->id,
            'content' => trim($validated['content']),
        ]);
        $note->setAttribute('tenant_id', $tenantId);
        $note->save();

        activity('delivery_viewer_notes')
            ->performedOn($note)
            ->causedBy($request->user())
            ->withProperties([
                'tenant_id' => $tenantId,
                'sales_project_id' => $project->id,
                'production_delivery_id' => $deliveryId,
            ])
            ->log('Anotacao de acompanhamento criada');

        if ($request->expectsJson()) {
            return $this->privateJson(['message' => 'Anotacao adicionada.'], 201);
        }

        return back()->with('success', 'Anotacao adicionada.');
    }

    public function destroyNote(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = $this->tenantId();
        $projectId = (int) $request->route('project');
        $note = DeliveryProjectNote::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->findOrFail((int) $request->route('note'));

        $canManage = (int) $note->created_by === (int) $request->user()->id
            || $request->user()->hasRoleInTenant('admin', $tenantId)
            || $request->user()->isSuperAdmin();
        abort_unless($canManage, 403);

        $note->delete();

        activity('delivery_viewer_notes')
            ->causedBy($request->user())
            ->withProperties([
                'tenant_id' => $tenantId,
                'sales_project_id' => $projectId,
                'note_id' => $note->id,
            ])
            ->log('Anotacao de acompanhamento removida');

        if ($request->expectsJson()) {
            return $this->privateJson(['message' => 'Anotacao removida.']);
        }

        return back()->with('success', 'Anotacao removida.');
    }

    private function project(Request $request): SalesProject
    {
        return SalesProject::query()
            ->where('tenant_id', $this->tenantId())
            ->findOrFail((int) $request->route('project'));
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

    private function projectAssociateIds(int $tenantId, int $projectId)
    {
        return ProjectAssociate::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('status', 'active')
            ->distinct()
            ->pluck('associate_id')
            ->merge(ProjectAssociateProductLimit::query()
                ->where('tenant_id', $tenantId)
                ->where('sales_project_id', $projectId)
                ->where('status', 'active')
                ->distinct()
                ->pluck('associate_id'))
            ->merge(ProductionDelivery::query()
                ->where('tenant_id', $tenantId)
                ->where('sales_project_id', $projectId)
                ->whereNotNull('associate_id')
                ->distinct()
                ->pluck('associate_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function associateForProject(Request $request, SalesProject $project): Associate
    {
        $associateId = $this->associateIdFromToken((string) $request->route('associateToken'));
        abort_unless($this->projectAssociateIds($project->tenant_id, $project->id)->contains($associateId), 404);

        return Associate::query()
            ->where('tenant_id', $project->tenant_id)
            ->findOrFail($associateId);
    }

    private function associateIdFromToken(string $token): int
    {
        try {
            $base64 = strtr($token, '-_', '+/');
            $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
            $id = (int) Crypt::decryptString($base64);
        } catch (DecryptException) {
            abort(404);
        }

        abort_if($id < 1, 404);

        return $id;
    }

    private function associateToken(int $associateId): string
    {
        return rtrim(strtr(Crypt::encryptString((string) $associateId), '+/', '-_'), '=');
    }

    private function quantityByProduct(int $tenantId, int $projectId, bool $distributions)
    {
        return ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->when(
                $distributions,
                fn ($query) => $query
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED->value),
                fn ($query) => $query
                    ->whereNull('parent_delivery_id')
                    ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value]),
            )
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) AS quantity')
            ->pluck('quantity', 'product_id');
    }

    private function tenantId(): int
    {
        $tenantId = (int) session('tenant_id');
        abort_if($tenantId < 1, 403, 'Selecione uma organizacao.');

        return $tenantId;
    }
}
