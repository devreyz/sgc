<?php

namespace App\Http\Controllers\Associate;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\ProductionDelivery;
use App\Models\ProjectAssociate;
use App\Models\SalesProject;
use App\Services\AssociateFinancialSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AssociatePortalDataController extends Controller
{
    public function __construct(private readonly AssociateFinancialSummaryService $financial)
    {
        $this->middleware(['auth', 'role:associado']);
    }

    public function dashboard(Request $request): JsonResponse
    {
        [$tenantId, $associate] = $this->context($request);
        $financial = $this->financial->summary($tenantId, $associate->id);
        $projects = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->where('status', ProjectStatus::ACTIVE->value)
            ->with('customer:id,name')
            ->select(['id', 'tenant_id', 'title', 'customer_id', 'max_total_value_per_associate'])
            ->latest('created_at')
            ->limit(5)
            ->get();
        $limits = $this->financialLimits($tenantId, $associate->id, $projects->pluck('id')->all());

        $deliveries = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with(['product:id,name,unit', 'salesProject:id,title'])
            ->select(['id', 'tenant_id', 'sales_project_id', 'associate_id', 'product_id', 'delivery_date', 'quantity', 'status'])
            ->latest('delivery_date')
            ->latest('id')
            ->limit(6)
            ->get()
            ->map(fn (ProductionDelivery $delivery) => $this->deliveryItem($delivery));

        return response()->json([
            'summary' => [
                'receivable' => $financial['receivable'],
                'issued_this_month' => $financial['issued_this_month'],
                'paid_this_month' => $financial['paid_this_month'],
                'total_net' => $financial['total_net'],
                'active_projects' => $projects->count(),
                'pending_deliveries' => ProductionDelivery::query()
                    ->where('tenant_id', $tenantId)
                    ->where('associate_id', $associate->id)
                    ->whereNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::PENDING->value)
                    ->count(),
            ],
            'projects' => $projects->map(function (SalesProject $project) use ($limits, $request) {
                $limit = $limits[$project->id] ?? $this->emptyLimit($project);

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'customer' => $project->customer?->name,
                    'url' => route('associate.projects.show', [
                        'tenant' => $request->route('tenant'),
                        'project' => $project->id,
                    ]),
                    'limit' => $limit,
                ];
            })->values(),
            'deliveries' => $deliveries,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function projects(Request $request): JsonResponse
    {
        [$tenantId, $associate] = $this->context($request);
        $validated = $request->validate([
            'status' => 'nullable|in:active,suspended,deliveries_closed,completed,cancelled,archived,history,all',
            'search' => 'nullable|string|max:80',
            'page' => 'nullable|integer|min:1',
        ]);
        $status = $validated['status'] ?? 'active';
        $historyStatuses = [
            ProjectStatus::DELIVERIES_CLOSED->value,
            ProjectStatus::COMPLETED->value,
            ProjectStatus::CANCELLED->value,
            ProjectStatus::ARCHIVED->value,
        ];
        $query = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->where('status', '!=', ProjectStatus::DRAFT->value)
            ->with('customer:id,name')
            ->select([
                'id', 'tenant_id', 'title', 'type', 'customer_id', 'status', 'start_date', 'end_date',
                'max_total_value_per_associate', 'created_at',
            ])
            ->when($validated['search'] ?? null, function (Builder $builder, string $search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('type', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn (Builder $customerQuery) => $customerQuery
                            ->where('name', 'like', '%'.$search.'%'));
                });
            });

        if ($status === 'history') {
            $query->whereIn('status', $historyStatuses);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $page = $query->latest('created_at')->latest('id')->paginate(8);
        $projectIds = $page->getCollection()->pluck('id')->all();
        $limits = $this->financialLimits($tenantId, $associate->id, $projectIds);
        $financial = $this->projectFinancials($tenantId, $associate->id, $projectIds);
        $page->setCollection($page->getCollection()->map(function (SalesProject $project) use ($limits, $financial, $request) {
            return [
                'id' => $project->id,
                'title' => $project->title,
                'type' => $project->type,
                'customer' => $project->customer?->name,
                'status' => $project->status?->value,
                'status_label' => $project->status?->getLabel(),
                'period' => collect([$project->start_date?->format('d/m/Y'), $project->end_date?->format('d/m/Y')])->filter()->implode(' a '),
                'url' => route('associate.projects.show', [
                    'tenant' => $request->route('tenant'),
                    'project' => $project->id,
                ]),
                'limit' => $limits[$project->id] ?? $this->emptyLimit($project),
                'financial' => $financial[$project->id] ?? $this->emptyFinancial(),
            ];
        }));

        $statusCounts = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->where('status', '!=', ProjectStatus::DRAFT->value)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $response = $this->page($page);
        $response['filter'] = $status;
        $response['counts'] = [
            'active' => (int) ($statusCounts[ProjectStatus::ACTIVE->value] ?? 0),
            'history' => (int) $statusCounts->only($historyStatuses)->sum(),
            'all' => (int) $statusCounts->sum(),
        ];

        return response()->json($response)->header('Cache-Control', 'no-store, private');
    }

    public function deliveries(Request $request): JsonResponse
    {
        [$tenantId, $associate] = $this->context($request);
        $validated = $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,cancelled',
            'project_id' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
        ]);
        $base = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id');
        $this->deliveryFilters($base, $validated);

        $page = (clone $base)
            ->with([
                'product:id,name,unit',
                'salesProject:id,tenant_id,title,admin_fee_percentage',
                'salesProject.fees',
                'distributions' => fn ($query) => $query
                    ->where('status', DeliveryStatus::APPROVED->value)
                    ->select([
                        'id', 'tenant_id', 'sales_project_id', 'associate_id', 'parent_delivery_id', 'quantity', 'unit_price', 'gross_value', 'admin_fee_amount',
                        'net_value', 'billing_status', 'paid', 'associate_receipt_id',
                    ]),
                'distributions.associateReceipt',
            ])
            ->select(['id', 'tenant_id', 'sales_project_id', 'associate_id', 'product_id', 'delivery_date', 'quantity', 'status'])
            ->latest('delivery_date')
            ->latest('id')
            ->paginate(12);
        $page->setCollection($page->getCollection()->map(fn (ProductionDelivery $delivery) => $this->deliveryItem($delivery, true)));

        $parentIds = (clone $base)->select('id');
        $deliverySummary = (clone $base)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved', [DeliveryStatus::APPROVED->value])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending', [DeliveryStatus::PENDING->value])
            ->first();
        $summary = [
            'total' => (int) ($deliverySummary?->total ?? 0),
            'approved' => (int) ($deliverySummary?->approved ?? 0),
            'pending' => (int) ($deliverySummary?->pending ?? 0),
            'gross' => (float) ProductionDelivery::query()
                ->where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->whereNotNull('parent_delivery_id')
                ->where('status', DeliveryStatus::APPROVED->value)
                ->whereIn('parent_delivery_id', $parentIds)
                ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
                ->value('total'),
        ];
        $financial = $this->financial->summary($tenantId, $associate->id);
        $projects = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->where('status', ProjectStatus::ACTIVE->value)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (SalesProject $project) => ['id' => $project->id, 'title' => $project->title]);

        return response()->json([
            'items' => $this->page($page),
            'summary' => $summary,
            'financial' => [
                'distribution_count' => $financial['distribution_count'],
                'total_net' => $financial['total_net'],
                'total_fees' => $financial['total_fees'],
                'receivable' => $financial['receivable'],
                'paid' => $financial['paid'],
            ],
            'projects' => $projects,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function ledger(Request $request): JsonResponse
    {
        [$tenantId, $associate] = $this->context($request);
        $section = (string) $request->route('section');

        return match ($section) {
            'summary' => $this->ledgerSummary($tenantId, $associate),
            'receipts' => $this->ledgerReceipts($request, $tenantId, $associate),
            'payments' => $this->ledgerPayments($request, $tenantId, $associate),
            'transactions' => $this->ledgerTransactions($request, $tenantId, $associate),
            default => response()->json(['message' => 'Seção não encontrada.'], 404),
        };
    }

    private function ledgerSummary(int $tenantId, Associate $associate): JsonResponse
    {
        return response()->json($this->financial->summary($tenantId, $associate->id))
            ->header('Cache-Control', 'no-store, private');
    }

    private function ledgerReceipts(Request $request, int $tenantId, Associate $associate): JsonResponse
    {
        $page = AssociateReceipt::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->where('status', '!=', ReceiptStatus::DRAFT->value)
            ->with([
                'project:id,title',
                'payments:id,associate_receipt_id,amount',
                'distributions' => fn ($query) => $query
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED->value)
                    ->select(['id', 'associate_receipt_id', 'parent_delivery_id', 'quantity', 'unit_price', 'gross_value', 'admin_fee_amount', 'net_value']),
            ])
            ->latest('issued_at')
            ->latest('id')
            ->paginate(10);
        $page->setCollection($page->getCollection()->map(function (AssociateReceipt $receipt) use ($request) {
            $totals = $this->financial->receiptTotals($receipt);
            $isObsolete = $receipt->status === ReceiptStatus::OBSOLETE;

            return [
                'id' => $receipt->id,
                'number' => $receipt->formatted_number,
                'project' => $receipt->project?->title,
                'date' => $receipt->issued_at?->format('d/m/Y'),
                'status' => $receipt->status?->value,
                'status_label' => $receipt->status?->getLabel(),
                'net' => $totals['net'],
                'paid' => $totals['paid'],
                'remaining' => $totals['remaining'],
                'preview_url' => $isObsolete ? null : route('associate.projects.receipts.download', [
                    'tenant' => $request->route('tenant'),
                    'project' => $receipt->sales_project_id,
                    'receipt' => $receipt->id,
                ]),
            ];
        }));

        return response()->json($this->page($page))->header('Cache-Control', 'no-store, private');
    }

    private function ledgerPayments(Request $request, int $tenantId, Associate $associate): JsonResponse
    {
        $page = AssociateReceiptPayment::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('receipt', fn (Builder $query) => $query
                ->where('associate_id', $associate->id)
                ->where('status', '!=', ReceiptStatus::DRAFT->value))
            ->with('receipt.project:id,title')
            ->latest('payment_date')
            ->latest('id')
            ->paginate(10);
        $page->setCollection($page->getCollection()->map(fn (AssociateReceiptPayment $payment) => [
            'id' => $payment->id,
            'receipt' => $payment->receipt?->formatted_number,
            'project' => $payment->receipt?->project?->title,
            'date' => $payment->payment_date?->format('d/m/Y'),
            'amount' => (float) $payment->amount,
        ]));

        return response()->json($this->page($page))->header('Cache-Control', 'no-store, private');
    }

    private function ledgerTransactions(Request $request, int $tenantId, Associate $associate): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'page' => 'nullable|integer|min:1',
        ]);
        $page = AssociateLedger::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->when($validated['start_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '>=', $date))
            ->when($validated['end_date'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_date', '<=', $date))
            ->latest('transaction_date')
            ->latest('id')
            ->paginate(12);
        $page->setCollection($page->getCollection()->map(function (AssociateLedger $transaction) {
            $type = $transaction->type?->value;

            return [
                'id' => $transaction->id,
                'description' => $transaction->description ?: $transaction->category?->getLabel(),
                'category' => $transaction->category?->getLabel(),
                'type' => $type,
                'type_label' => $transaction->type?->getLabel(),
                'date' => $transaction->transaction_date?->format('d/m/Y'),
                'amount' => (float) $transaction->amount,
                'balance_after' => (float) $transaction->balance_after,
            ];
        }));

        return response()->json($this->page($page))->header('Cache-Control', 'no-store, private');
    }

    private function context(Request $request): array
    {
        $tenantId = (int) session('tenant_id');
        abort_unless($tenantId > 0, 403);
        $associate = Associate::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return [$tenantId, $associate];
    }

    private function allowedProjectsQuery(int $tenantId, int $associateId): Builder
    {
        return SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->where(function (Builder $query) use ($associateId) {
                $query->where('restrict_participants', false)
                    ->orWhereHas('projectAssociates', fn (Builder $participants) => $participants
                        ->where('associate_id', $associateId)
                        ->where('status', 'active'));
            });
    }

    private function financialLimits(int $tenantId, int $associateId, array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }
        $used = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associateId)
            ->whereIn('sales_project_id', $projectIds)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->selectRaw('sales_project_id, COALESCE(SUM(quantity * unit_price), 0) as used_total')
            ->groupBy('sales_project_id')
            ->pluck('used_total', 'sales_project_id');
        $projectMaximums = SalesProject::query()->where('tenant_id', $tenantId)->whereIn('id', $projectIds)
            ->pluck('max_total_value_per_associate', 'id');
        $associateMaximums = ProjectAssociate::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associateId)
            ->whereIn('sales_project_id', $projectIds)
            ->where('status', 'active')
            ->whereNotNull('financial_limit')
            ->pluck('financial_limit', 'sales_project_id');

        return collect($projectIds)->mapWithKeys(function (int $projectId) use ($used, $projectMaximums, $associateMaximums) {
            $configuredMaximum = $associateMaximums->get($projectId, $projectMaximums->get($projectId));
            $maximum = $configuredMaximum !== null ? (float) $configuredMaximum : null;
            $accumulated = (float) ($used[$projectId] ?? 0);
            $percent = $maximum && $maximum > 0 ? min(100, $accumulated / $maximum * 100) : null;

            return [$projectId => [
                'max' => $maximum,
                'accumulated' => $accumulated,
                'remaining' => $maximum !== null ? max(0, $maximum - $accumulated) : null,
                'percent' => $percent,
                'is_near' => $percent !== null && $percent >= 80 && $percent < 100,
                'is_full' => $percent !== null && $percent >= 100,
            ]];
        })->all();
    }

    private function projectFinancials(int $tenantId, int $associateId, array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }
        $distributions = ProductionDelivery::query()
            ->where('tenant_id', $tenantId)
            ->where('associate_id', $associateId)
            ->whereIn('sales_project_id', $projectIds)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->with([
                'salesProject:id,tenant_id,admin_fee_percentage',
                'salesProject.fees',
                'associateReceipt',
            ])
            ->get([
                'id', 'tenant_id', 'sales_project_id', 'associate_id', 'parent_delivery_id',
                'quantity', 'unit_price', 'gross_value', 'admin_fee_amount', 'net_value', 'billing_status', 'paid',
                'associate_receipt_id',
            ]);
        $resolved = $this->financial->resolveDistributions($distributions);
        $result = collect($projectIds)->mapWithKeys(fn (int $projectId) => [$projectId => $this->emptyFinancial()])->all();

        foreach ($distributions as $distribution) {
            $projectId = (int) $distribution->sales_project_id;
            $net = (float) ($resolved['items'][$distribution->id]['net'] ?? 0);
            $result[$projectId]['total'] += $net;

            if ($distribution->paid || $distribution->billing_status?->value === 'paid') {
                $result[$projectId]['paid'] += $net;
            } elseif ($distribution->associate_receipt_id || $distribution->billing_status?->value === 'billed') {
                $result[$projectId]['billed'] += $net;
            } else {
                $result[$projectId]['unbilled'] += $net;
            }
        }

        return $result;
    }

    private function deliveryItem(ProductionDelivery $delivery, bool $withFinancial = false): array
    {
        $item = [
            'id' => $delivery->id,
            'date' => $delivery->delivery_date?->format('d/m/Y'),
            'day' => $delivery->delivery_date?->format('d'),
            'month' => $delivery->delivery_date?->locale('pt_BR')->translatedFormat('M'),
            'product' => $delivery->product?->name,
            'unit' => $delivery->product?->unit,
            'project' => $delivery->salesProject?->title,
            'quantity' => (float) $delivery->quantity,
            'status' => $delivery->status?->value,
            'status_label' => $delivery->status?->getLabel(),
        ];
        if (! $withFinancial) {
            return $item;
        }
        $distributions = $delivery->distributions;
        $financial = $this->financial->resolveDistributions($distributions, $delivery->salesProject);
        $allPaid = $distributions->isNotEmpty() && $distributions->every(fn (ProductionDelivery $row) => $row->paid || $row->billing_status?->value === 'paid');
        $inReceipt = $distributions->contains(fn (ProductionDelivery $row) => $row->associate_receipt_id !== null || $row->billing_status?->value === 'billed');

        return $item + [
            'distribution_count' => $distributions->count(),
            'distributed_quantity' => (float) $distributions->sum('quantity'),
            'gross' => $financial['gross'],
            'fees' => $financial['fees'],
            'net' => $financial['net'],
            'billing_status' => $distributions->isEmpty() ? 'waiting' : ($allPaid ? 'paid' : ($inReceipt ? 'billed' : 'unbilled')),
            'billing_label' => $distributions->isEmpty() ? 'Aguardando distribuição' : ($allPaid ? 'Pago' : ($inReceipt ? 'Em comprovante' : 'A faturar')),
        ];
    }

    private function deliveryFilters(Builder $query, array $filters): void
    {
        $query->when($filters['status'] ?? null, fn (Builder $builder, string $status) => $builder->where('status', $status))
            ->when($filters['project_id'] ?? null, fn (Builder $builder, int $projectId) => $builder->where('sales_project_id', $projectId))
            ->when($filters['start_date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('delivery_date', '>=', $date))
            ->when($filters['end_date'] ?? null, fn (Builder $builder, string $date) => $builder->whereDate('delivery_date', '<=', $date));
    }

    private function page(LengthAwarePaginator $page): array
    {
        return [
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }

    private function emptyLimit(SalesProject $project): array
    {
        return [
            'max' => $project->max_total_value_per_associate ? (float) $project->max_total_value_per_associate : null,
            'accumulated' => 0.0,
            'remaining' => $project->max_total_value_per_associate ? (float) $project->max_total_value_per_associate : null,
            'percent' => null,
            'is_near' => false,
            'is_full' => false,
        ];
    }

    private function emptyFinancial(): array
    {
        return ['total' => 0.0, 'unbilled' => 0.0, 'billed' => 0.0, 'paid' => 0.0];
    }
}
