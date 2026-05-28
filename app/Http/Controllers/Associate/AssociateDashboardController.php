<?php

namespace App\Http\Controllers\Associate;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\ProductionDelivery;
use App\Models\ProjectAssociate;
use App\Models\ProjectAssociateProductLimit;
use App\Models\SalesProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssociateDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Compute financial breakdown from distributions (unbilled/billed/paid).
     */
    private function computeFinancialStates(int $tenantId, int $projectId, int $associateId): array
    {
        $base = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->whereNotNull('parent_delivery_id')
            ->where('status', 'approved');

        $unbilled = (float) (clone $base)->where('billing_status', BillingStatus::UNBILLED->value)->sum('net_value');
        $billed   = (float) (clone $base)->where('billing_status', BillingStatus::BILLED->value)->sum('net_value');
        $paid     = (float) (clone $base)->where('billing_status', BillingStatus::PAID->value)->sum('net_value');

        return [
            'unbilled' => $unbilled,
            'billed'   => $billed,
            'paid'     => $paid,
            'total'    => $unbilled + $billed + $paid,
        ];
    }

    /**
     * Compute financial limit data for a project/associate pair.
     */
    private function computeFinancialLimit(int $tenantId, int $projectId, int $associateId, ?float $maxValue): array
    {
        $accumulated = (float) ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->selectRaw('SUM(quantity * unit_price) as total')
            ->value('total');

        $remaining  = $maxValue !== null ? max(0.0, $maxValue - $accumulated) : null;
        $percent    = ($maxValue && $maxValue > 0) ? min(100.0, ($accumulated / $maxValue) * 100) : null;

        return [
            'accumulated' => $accumulated,
            'max'         => $maxValue,
            'remaining'   => $remaining,
            'percent'     => $percent,
            'is_near'     => $percent !== null && $percent >= 80 && $percent < 100,
            'is_full'     => $percent !== null && $percent >= 100,
        ];
    }

    /**
     * Compute product limits for a project/associate pair.
     */
    private function computeProductLimits(int $tenantId, int $projectId, int $associateId): \Illuminate\Support\Collection
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

                $max     = (float) $limit->max_quantity;
                $percent = $max > 0 ? min(100.0, ($deliveredQty / $max) * 100) : 0.0;

                $limit->delivered_qty = $deliveredQty;
                $limit->remaining_qty = max(0.0, $max - $deliveredQty);
                $limit->percent_used  = $percent;
                $limit->is_near       = $percent >= 80 && $percent < 100;
                $limit->is_full       = $percent >= 100;
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
                  ->orWhereHas('projectAssociates', fn ($pa) => $pa->where('associate_id', $associateId));
            });
    }

    /**
     * Show associate dashboard
     */
    public function index()
    {
        $user     = Auth::user();
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $associate) {
            return view('associate.no-profile', ['user' => $user]);
        }

        // ─── Stats ───────────────────────────────────────────────────────────
        $baseDeliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id');

        // Distribuições (fónte da verdade financeira)
        $baseDistributions = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', 'approved');

        $stats = [
            'active_projects'     => $this->allowedProjectsQuery($tenantId, $associate->id)
                ->where('status', ProjectStatus::ACTIVE->value)
                ->count(),
            'pending_deliveries'  => (clone $baseDeliveries)->where('status', DeliveryStatus::PENDING->value)->count(),
            // Faturado este mês (billing_status = billed ou paid, criado no mês atual)
            'earnings_this_month' => (float) ((clone $baseDistributions)
                ->whereIn('billing_status', [BillingStatus::BILLED->value, BillingStatus::PAID->value])
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->sum('net_value') ?? 0),
            // Faturado mas ainda não pago ao associado
            'unpaid_value'        => (float) ((clone $baseDistributions)
                ->where('billing_status', BillingStatus::BILLED->value)
                ->sum('net_value') ?? 0),
            'current_balance'     => $associate->current_balance ?? 0,
        ];

        // ─── Active projects with limit data ─────────────────────────────────
        $recentProjects = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->whereIn('status', [ProjectStatus::ACTIVE->value])
            ->with(['customer', 'demands.product'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $projectLimitData = [];
        foreach ($recentProjects as $project) {
            $maxValue = $project->max_total_value_per_associate
                ? (float) $project->max_total_value_per_associate
                : null;
            $projectLimitData[$project->id] = $this->computeFinancialLimit(
                $tenantId, $project->id, $associate->id, $maxValue
            );
        }

        // ─── Alerts: projects near/at limit ──────────────────────────────────
        $limitAlerts = collect($projectLimitData)
            ->filter(fn ($d) => $d['is_near'] || $d['is_full']);

        // ─── Recent deliveries (card style) ──────────────────────────────────
        $recentDeliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with(['salesProject', 'product'])
            ->orderBy('delivery_date', 'desc')
            ->limit(6)
            ->get();

        return view('associate.dashboard', compact(
            'associate', 'stats', 'recentProjects', 'projectLimitData',
            'limitAlerts', 'recentDeliveries'
        ));
    }

    /**
     * Show all projects
     */
    public function projects(Request $request)
    {
        $user     = Auth::user();
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $associate) {
            return redirect()->route('associate.dashboard', ['tenant' => request()->route('tenant')->slug]);
        }

        $query = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->with(['customer', 'demands.product']);

        if ($request->status) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', [ProjectStatus::ACTIVE->value, ProjectStatus::DRAFT->value]);
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(18);

        // Limit data for each project
        $projectLimitData = [];
        $productLimitData = [];
        foreach ($projects as $project) {
            $maxValue = $project->max_total_value_per_associate
                ? (float) $project->max_total_value_per_associate
                : null;
            $projectLimitData[$project->id] = $this->computeFinancialLimit(
                $tenantId, $project->id, $associate->id, $maxValue
            );
            $productLimitData[$project->id] = $this->computeProductLimits(
                $tenantId, $project->id, $associate->id
            );
            $financialStateData[$project->id] = $this->computeFinancialStates(
                $tenantId, $project->id, $associate->id
            );
        }

        return view('associate.projects', compact(
            'associate', 'projects', 'projectLimitData', 'productLimitData', 'financialStateData'
        ));
    }

    /**
     * Show project details
     */
    public function showProject(Request $request)
    {
        $projectId = (int) request()->route('project');
        $user      = Auth::user();
        $tenantId  = session('tenant_id');

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
                ->where('associate_id', $associate->id)
                ->exists();
            if (! $allowed) {
                abort(403, 'Você não faz parte deste projeto.');
            }
        }

        // Financial limit
        $maxValue = $project->max_total_value_per_associate
            ? (float) $project->max_total_value_per_associate
            : null;
        $financialLimit  = $this->computeFinancialLimit($tenantId, $project->id, $associate->id, $maxValue);
        $financialStates = $this->computeFinancialStates($tenantId, $project->id, $associate->id);

        // Product limits
        $productLimits = $this->computeProductLimits($tenantId, $project->id, $associate->id);

        // My deliveries (paginated)
        $deliveryQuery = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with(['product', 'projectDemand.product']);

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

        // My delivery summary
        $myTotalQty   = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->sum('quantity');

        // Recibos de pagamento deste associado neste projeto
        $receipts = \App\Models\AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->orderByDesc('issued_at')
            ->get();

        return view('associate.project-details', compact(
            'associate', 'project', 'financialLimit', 'financialStates', 'productLimits',
            'myDeliveries', 'myTotalQty', 'receipts'
        ));
    }

    /**
     * Show deliveries
     */
    public function deliveries(Request $request)
    {
        $user     = Auth::user();
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

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with(['salesProject.customer', 'product']);

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->project_id) {
            $query->where('sales_project_id', $request->project_id);
        }
        if ($request->start_date) {
            $query->where('delivery_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('delivery_date', '<=', $request->end_date);
        }

        $deliveries = $query->orderBy('delivery_date', 'desc')->paginate(20);

        // Stats for the filtered set
        $allFiltered = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->project_id, fn ($q) => $q->where('sales_project_id', $request->project_id))
            ->when($request->start_date, fn ($q) => $q->where('delivery_date', '>=', $request->start_date))
            ->when($request->end_date, fn ($q) => $q->where('delivery_date', '<=', $request->end_date));

        $deliveryStats = [
            'total'        => (clone $allFiltered)->count(),
            'approved'     => (clone $allFiltered)->where('status', 'approved')->count(),
            'pending'      => (clone $allFiltered)->where('status', 'pending')->count(),
            'total_value'  => (clone $allFiltered)->whereNotIn('status', ['cancelled', 'rejected'])->selectRaw('SUM(quantity * unit_price) as t')->value('t') ?? 0,
        ];

        // Projects for filter dropdown
        $myProjects = $this->allowedProjectsQuery($tenantId, $associate->id)
            ->whereIn('status', [ProjectStatus::ACTIVE->value, ProjectStatus::DRAFT->value])
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('associate.deliveries', compact(
            'associate', 'deliveries', 'deliveryStats', 'myProjects'
        ));
    }

    /**
     * Show ledger/transactions
     */
    public function ledger(Request $request)
    {
        $user     = Auth::user();
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

        $query = AssociateLedger::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id);

        if ($request->start_date) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        $transactions   = $query->orderBy('transaction_date', 'desc')->paginate(20);
        $currentBalance = $associate->current_balance ?? 0;

        return view('associate.ledger', compact('associate', 'transactions', 'currentBalance'));
    }
}
