<?php

namespace App\Http\Controllers\Associate;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateLedger;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssociateDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show associate dashboard
     */
    public function index()
    {
        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        // Get associate record linked to user in current tenant
        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $associate) {
            // Show message that no associate profile exists
            return view('associate.no-profile', ['user' => $user]);
        }

        $stats = [
            'active_projects' => SalesProject::where('tenant_id', $tenantId)
                ->whereIn('status', [ProjectStatus::DRAFT->value, ProjectStatus::ACTIVE->value])
                ->count(),
            'my_deliveries' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->where('status', DeliveryStatus::APPROVED->value)
                ->count(),
            'pending_deliveries' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->where('status', DeliveryStatus::PENDING->value)
                ->count(),
            'total_delivered_this_month' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->where('status', DeliveryStatus::APPROVED->value)
                ->whereMonth('delivery_date', now()->month)
                ->sum('quantity'),
            'earnings_this_month' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->where('status', DeliveryStatus::APPROVED->value)
                ->whereMonth('delivery_date', now()->month)
                ->sum('net_value'),
            'unpaid_earnings' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('associate_id', $associate->id)
                ->where('status', DeliveryStatus::APPROVED->value)
                ->where('paid', false)
                ->sum('net_value'),
            'current_balance' => $associate->current_balance ?? 0,
        ];

        // Mostrar TODOS os projetos ativos, não apenas os que ele já entregou
        $recentProjects = SalesProject::where('tenant_id', $tenantId)
            ->whereIn('status', [ProjectStatus::DRAFT->value, ProjectStatus::ACTIVE->value])
            ->with([
                'customer',
                'demands.product',
                'deliveries' => function ($q) use ($associate, $tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->where('associate_id', $associate->id)
                      ->with('projectDemand.product');
                }
            ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentTransactions = AssociateLedger::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->orderBy('transaction_date', 'desc')
            ->limit(10)
            ->get();

        return view('associate.dashboard', compact('associate', 'stats', 'recentProjects', 'recentTransactions'));
    }

    /**
     * Show all projects
     */
    public function projects(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $associate) {
            return redirect()->route('associate.dashboard', ['tenant' => request()->route('tenant')->slug])
                ->with('error', 'Perfil de associado não encontrado.');
        }

        // Mostrar TODOS os projetos ativos para o associado poder ver o que precisa entregar
        $query = SalesProject::where('tenant_id', $tenantId)
            ->whereIn('status', [ProjectStatus::DRAFT->value, ProjectStatus::ACTIVE->value])
            ->with([
                'customer',
                'demands.product',
                'deliveries' => function ($q) use ($associate, $tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->where('associate_id', $associate->id)
                      ->with('projectDemand.product');
                }
            ]);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('associate.projects', compact('associate', 'projects'));
    }

    /**
     * Show project details
     */
    public function showProject($id)
    {
        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        // Carregar QUALQUER projeto ativo, não apenas os que ele já entregou
        $project = SalesProject::where('id', $id)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [ProjectStatus::DRAFT->value, ProjectStatus::ACTIVE->value])
            ->with([
                'customer',
                'demands.product',
                'deliveries' => function ($q) use ($associate, $tenantId) {
                    $q->where('tenant_id', $tenantId)
                      ->where('associate_id', $associate->id)
                      ->with('projectDemand.product');
                },
                'payments'
            ])
            ->firstOrFail();

        return view('associate.project-details', compact('associate', 'project'));
    }

    /**
     * Show deliveries
     */
    public function deliveries(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id)
            ->with(['salesProject.customer', 'product']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->where('delivery_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('delivery_date', '<=', $request->end_date);
        }

        $deliveries = $query->orderBy('delivery_date', 'desc')->paginate(20);

        return view('associate.deliveries', compact('associate', 'deliveries'));
    }

    /**
     * Show ledger/transactions
     */
    public function ledger(Request $request)
    {
        $user = Auth::user();
        $tenantId = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $associate = Associate::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        $query = AssociateLedger::where('tenant_id', $tenantId)
            ->where('associate_id', $associate->id);

        // Filter by date range
        if ($request->start_date) {
            $query->where('transaction_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('transaction_date', '<=', $request->end_date);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->paginate(20);
        $currentBalance = $associate->current_balance ?? 0;

        return view('associate.ledger', compact('associate', 'transactions', 'currentBalance'));
    }
}
