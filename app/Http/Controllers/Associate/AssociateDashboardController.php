<?php

namespace App\Http\Controllers\Associate;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\ProductionDelivery;
use App\Models\ProjectAssociate;
use App\Models\ProjectAssociateProductLimit;
use App\Models\SalesProject;
use App\Services\AssociateFinancialSummaryService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class AssociateDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function financial(): AssociateFinancialSummaryService
    {
        return app(AssociateFinancialSummaryService::class);
    }

    /**
     * Compute financial breakdown from approved distributions and receipts.
     */
    private function computeFinancialStates(int $tenantId, int $projectId, int $associateId): array
    {
        return $this->financial()->summary($tenantId, $associateId, $projectId);
    }

    /**
     * Compute financial limit data for a project/associate pair.
     */
    private function computeFinancialLimit(int $tenantId, int $projectId, int $associateId, ?float $maxValue): array
    {
        $accumulated = (float) ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->selectRaw('COALESCE(SUM(gross_value), 0) as total')
            ->value('total');

        $remaining = $maxValue !== null ? max(0.0, $maxValue - $accumulated) : null;
        $percent = ($maxValue && $maxValue > 0) ? min(100.0, ($accumulated / $maxValue) * 100) : null;

        return [
            'accumulated' => $accumulated,
            'max' => $maxValue,
            'remaining' => $remaining,
            'percent' => $percent,
            'is_near' => $percent !== null && $percent >= 80 && $percent < 100,
            'is_full' => $percent !== null && $percent >= 100,
        ];
    }

    /**
     * Compute product limits for a project/associate pair.
     */
    private function computeProductLimits(int $tenantId, int $projectId, int $associateId): Collection
    {
        return ProjectAssociateProductLimit::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->with('product')
            ->get()
            ->map(function ($limit) use ($tenantId, $projectId, $associateId) {
                $deliveredQty = (float) ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('sales_project_id', $projectId)
                    ->where('associate_id', $associateId)
                    ->where('product_id', $limit->product_id)
                    ->whereNull('parent_delivery_id')
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->sum('quantity');

                $max = (float) $limit->max_quantity;
                $percent = $max > 0 ? min(100.0, ($deliveredQty / $max) * 100) : 0.0;

                $limit->delivered_qty = $deliveredQty;
                $limit->remaining_qty = max(0.0, $max - $deliveredQty);
                $limit->percent_used = $percent;
                $limit->is_near = $percent >= 80 && $percent < 100;
                $limit->is_full = $percent >= 100;

                return $limit;
            });
    }

    /**
     * Build the project query respecting restrict_participants.
     */
    private function allowedProjectsQuery(int $tenantId, int $associateId)
    {
        return SalesProject::where('tenant_id', $tenantId)
            ->where(function ($q) use ($associateId) {
                $q->where('restrict_participants', false)
                    ->orWhereHas('projectAssociates', fn ($pa) => $pa
                        ->where('associate_id', $associateId)
                        ->where('status', 'active'));
            });
    }

    /**
     * Show associate dashboard
     */
    public function index()
    {
        $associate = $this->currentAssociate();

        if (! $associate) {
            return view('associate.no-profile', ['user' => Auth::user()]);
        }

        $stats = [
            'active_projects' => 0,
            'pending_deliveries' => 0,
            'earnings_this_month' => 0,
            'unpaid_value' => 0,
            'paid_this_month' => 0,
            'distributed_net' => 0,
            'current_balance' => 0,
        ];
        $recentProjects = collect();
        $projectLimitData = [];
        $limitAlerts = collect();
        $recentDeliveries = collect();
        $asyncPortal = true;

        return view('associate.dashboard', compact(
            'associate', 'stats', 'recentProjects', 'projectLimitData',
            'limitAlerts', 'recentDeliveries', 'asyncPortal'
        ));
    }

    /**
     * Show all projects
     */
    public function projects(Request $request)
    {
        $associate = $this->currentAssociate();

        if (! $associate) {
            return redirect()->route('associate.dashboard', ['tenant' => request()->route('tenant')->slug]);
        }
        $projects = $this->emptyPaginator($request, 8);
        $projectLimitData = [];
        $productLimitData = [];
        $financialStateData = [];
        $asyncPortal = true;

        return view('associate.projects', compact(
            'associate', 'projects', 'projectLimitData', 'productLimitData', 'financialStateData', 'asyncPortal'
        ));
    }

    /**
     * Show project details
     */
    public function showProject(Request $request)
    {
        $projectId = (int) request()->route('project');
        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $associate) {
            abort(403);
        }

        $project = SalesProject::where('id', $projectId)
            ->where('tenant_id', $tenantId)
            ->with(['customer', 'demands.product'])
            ->firstOrFail();

        // Respect restrict_participants
        if ($project->restrict_participants) {
            $allowed = ProjectAssociate::where('sales_project_id', $project->id)
                ->where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->where('status', 'active')
                ->exists();
            if (! $allowed) {
                abort(403, 'Você não faz parte deste projeto.');
            }
        }

        // Financial limit
        $maxValue = $project->max_total_value_per_associate
            ? (float) $project->max_total_value_per_associate
            : null;
        $financialLimit = $this->computeFinancialLimit($tenantId, $project->id, $associate->id, $maxValue);
        $financialStates = $this->computeFinancialStates($tenantId, $project->id, $associate->id);

        // Product limits
        $productLimits = $this->computeProductLimits($tenantId, $project->id, $associate->id);

        // My deliveries (paginated)
        $deliveryQuery = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with([
                'product',
                'projectDemand.product',
                'distributions' => fn ($query) => $query
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED->value),
            ]);

        if ($request->product_id) {
            $deliveryQuery->where('product_id', $request->product_id);
        }
        if ($request->start_date) {
            $deliveryQuery->where('delivery_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $deliveryQuery->where('delivery_date', '<=', $request->end_date);
        }
        if ($request->status) {
            $deliveryQuery->where('status', $request->status);
        }

        $myDeliveries = $deliveryQuery->orderBy('delivery_date', 'desc')->paginate(15);
        $this->attachDistributionFinancials($myDeliveries->getCollection());

        // My delivery summary
        $myTotalQty = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->sum('quantity');

        // Recibos de pagamento deste associado neste projeto
        $receipts = AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->orderByDesc('issued_at')
            ->get();

        // Distribuições agrupadas por organização/cliente para exibição resumida
        $distributionsByOrg = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', 'approved')
            ->with('customer.organization')
            ->get()
            ->groupBy(fn ($d) => $d->customer?->organization_id ?? 0)
            ->map(function ($items, $orgId) {
                $org = $items->first()?->customer?->organization;

                return [
                    'organization_name' => $org?->name ?? 'Sem organização',
                    'organization_id' => $orgId,
                    'total_gross' => $items->sum('gross_value'),
                    'total_net' => $items->sum('net_value'),
                    'count' => $items->count(),
                    'customers' => $items->groupBy('customer_id')
                        ->map(fn ($cItems) => [
                            'customer_name' => $cItems->first()?->customer?->trade_name
                                ?? $cItems->first()?->customer?->name ?? '?',
                            'total_gross' => $cItems->sum('gross_value'),
                            'total_net' => $cItems->sum('net_value'),
                            'count' => $cItems->count(),
                        ])->values()->all(),
                ];
            })
            ->sortBy('organization_name')
            ->values();

        return view('associate.project-details', compact(
            'associate', 'project', 'financialLimit', 'financialStates', 'productLimits',
            'myDeliveries', 'myTotalQty', 'receipts', 'distributionsByOrg'
        ));
    }

    /**
     * Show deliveries
     */
    public function deliveries(Request $request)
    {
        $associate = $this->currentAssociate();

        if (! $associate) {
            abort(403);
        }
        $deliveries = $this->emptyPaginator($request, 12);
        $deliveryStats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'total_value' => 0];
        $financialSummary = [
            'distribution_count' => 0, 'total_net' => 0, 'total_fees' => 0,
            'receivable' => 0, 'paid' => 0,
        ];
        $myProjects = collect();
        $asyncPortal = true;

        return view('associate.deliveries', compact(
            'associate', 'deliveries', 'deliveryStats', 'myProjects', 'financialSummary', 'asyncPortal'
        ));
    }

    /**
     * Show ledger/transactions
     */
    public function ledger(Request $request)
    {
        $associate = $this->currentAssociate();

        if (! $associate) {
            abort(403);
        }
        $transactions = $this->emptyPaginator($request, 12);
        $currentBalance = 0.0;
        $financialSummary = ['total_net' => 0, 'receivable' => 0, 'paid' => 0, 'total_fees' => 0];
        $receipts = collect();
        $receiptPayments = collect();
        $asyncPortal = true;

        return view('associate.ledger', compact(
            'associate',
            'transactions',
            'currentBalance',
            'financialSummary',
            'receipts',
            'receiptPayments',
            'asyncPortal'
        ));
    }

    private function currentAssociate(): ?Associate
    {
        $tenantId = (int) session('tenant_id');
        if ($tenantId <= 0) {
            return null;
        }

        return Associate::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', Auth::id())
            ->first();
    }

    private function emptyPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage, 1, ['path' => $request->url()]);
    }

    private function attachDistributionFinancials($deliveries): void
    {
        foreach ($deliveries as $delivery) {
            $distributions = $delivery->distributions;
            $gross = (float) $distributions->sum(fn (ProductionDelivery $item) => (float) $item->gross_value);
            $fees = (float) $distributions->sum(fn (ProductionDelivery $item) => (float) ($item->admin_fee_amount ?? 0));
            $net = (float) $distributions->sum(function (ProductionDelivery $item): float {
                if ($item->net_value !== null) {
                    return (float) $item->net_value;
                }

                return max(0.0, (float) $item->gross_value - (float) ($item->admin_fee_amount ?? 0));
            });

            $delivery->setAttribute('portal_distribution_count', $distributions->count());
            $delivery->setAttribute('portal_distributed_quantity', (float) $distributions->sum('quantity'));
            $delivery->setAttribute('portal_gross_value', $gross);
            $delivery->setAttribute('portal_fee_value', $fees);
            $delivery->setAttribute('portal_net_value', $net);
        }
    }
}
