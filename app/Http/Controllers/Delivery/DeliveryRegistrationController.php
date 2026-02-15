<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryRegistrationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'any.role:registrador_entregas']);
    }

    /**
     * Show delivery dashboard with all active projects
     */
    public function index()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        // Get active projects with statistics
        $projects = SalesProject::where('tenant_id', $tenantId)
            ->whereIn('status', [
                ProjectStatus::DRAFT->value,
                ProjectStatus::ACTIVE->value,
            ])
            ->with(['customer', 'demands.product', 'deliveries'])
            ->get()
            ->map(function ($project) {
                $totalTarget = $project->demands->sum('target_quantity');
                $totalDelivered = $project->deliveries->sum('quantity');
                $pendingDeliveries = $project->deliveries->where('status', DeliveryStatus::PENDING)->count();
                $progress = $totalTarget > 0 ? ($totalDelivered / $totalTarget) * 100 : 0;

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'customer_name' => $project->customer->name ?? '-',
                    'status' => $project->status->getLabel(),
                    'status_value' => $project->status->value,
                    'start_date' => $project->start_date?->format('d/m/Y'),
                    'end_date' => $project->end_date?->format('d/m/Y'),
                    'total_target' => $totalTarget,
                    'total_delivered' => $totalDelivered,
                    'remaining' => $totalTarget - $totalDelivered,
                    'progress' => $progress,
                    'pending_deliveries' => $pendingDeliveries,
                    'products_count' => $project->demands->count(),
                    'days_remaining' => $project->end_date ? (int) ceil(now()->diffInDays($project->end_date, false)) : null,
                ];
            });

        $stats = [
            'active_projects' => $projects->count(),
            'total_deliveries_today' => ProductionDelivery::where('tenant_id', $tenantId)
                ->whereDate('delivery_date', today())->count(),
            'pending_approvals' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('status', DeliveryStatus::PENDING)->count(),
            'total_delivered_this_week' => ProductionDelivery::where('tenant_id', $tenantId)
                ->whereBetween('delivery_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('quantity'),
        ];

        return view('delivery.dashboard', compact('projects', 'stats'));
    }

    /**
     * Show delivery registration page for specific project
     */
    public function register($projectId = null)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        // Get specific project or all active projects
        if ($projectId) {
            $project = SalesProject::where('tenant_id', $tenantId)
                ->whereIn('status', [
                    ProjectStatus::DRAFT->value,
                    ProjectStatus::ACTIVE->value,
                ])
                ->with(['customer', 'demands.product', 'deliveries'])
                ->findOrFail($projectId);

            $projects = collect([$project]);
        } else {
            $projects = SalesProject::where('tenant_id', $tenantId)
                ->whereIn('status', [
                    ProjectStatus::DRAFT->value,
                    ProjectStatus::ACTIVE->value,
                ])
                ->with(['customer', 'demands.product'])
                ->orderBy('title')
                ->get();
        }

        // Get all associates
        $associates = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->whereHas('user', function ($q) {
                $q->where('status', true);
            })
            ->orderBy('id')
            ->get();

        return view('delivery.register', compact('projects', 'associates'));
    }

    /**
     * Get project demands via AJAX
     */
    public function getProjectDemands($projectId)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $demands = ProjectDemand::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->with('product')
            ->get()
            ->map(function ($demand) use ($tenantId) {
                // Calculate remaining quantity
                $delivered = ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('project_demand_id', $demand->id)
                    ->where('status', '!=', DeliveryStatus::CANCELLED->value)
                    ->sum('quantity');

                return [
                    'id' => $demand->id,
                    'product_name' => $demand->product->name ?? '-',
                    'product_unit' => $demand->product->unit ?? 'un',
                    'target_quantity' => (float) $demand->target_quantity,
                    'delivered_quantity' => (float) $delivered,
                    'remaining_quantity' => (float) ($demand->target_quantity - $delivered),
                    'unit_price' => (float) $demand->unit_price,
                ];
            });

        return response()->json($demands);
    }

    /**
     * Get associate deliveries for a project
     */
    public function getAssociateDeliveries($projectId, $associateId)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->with('projectDemand.product')
            ->orderBy('delivery_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'product_name' => $delivery->projectDemand->product->name ?? '-',
                    'delivery_date' => $delivery->delivery_date?->format('d/m/Y') ?? '-',
                    'quantity' => (float) $delivery->quantity,
                    'unit' => $delivery->projectDemand->product->unit ?? 'un',
                    'net_value' => (float) $delivery->net_value,
                    'status' => $delivery->status->getLabel(),
                    'status_value' => $delivery->status->value,
                ];
            });

        return response()->json($deliveries);
    }

    /**
     * Store new delivery
     */
    public function store(Request $request)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'Selecione uma organização primeiro.',
            ], 403);
        }

        $validated = $request->validate([
            'sales_project_id' => 'required|exists:sales_projects,id',
            'project_demand_id' => 'required|exists:project_demands,id',
            'associate_id' => 'required|exists:associates,id',
            'delivery_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.001',
            'quality_grade' => 'nullable|string|max:50',
            'quality_notes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            // Prevenir submissões duplicadas rápidas (mesmo projeto, demanda, associado, data)
            $duplicate = ProductionDelivery::where('tenant_id', $tenantId)
                ->where('sales_project_id', $validated['sales_project_id'])
                ->where('project_demand_id', $validated['project_demand_id'])
                ->where('associate_id', $validated['associate_id'])
                ->where('delivery_date', $validated['delivery_date'])
                ->where('created_at', '>=', now()->subSeconds(30))
                ->first();

            if ($duplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Entrega já registrada recentemente.',
                    'existing' => ['id' => $duplicate->id],
                ], 409);
            }

            DB::beginTransaction();

            // Get project demand to calculate values
            $demand = ProjectDemand::where('tenant_id', $tenantId)
                ->findOrFail($validated['project_demand_id']);

            // Calculate gross value
            $quantity = (float) $validated['quantity'];
            $unitPrice = (float) $demand->unit_price;
            $grossValue = $quantity * $unitPrice;

            // Calculate admin fee (assuming 10%, adjust as needed)
            $adminFeePercent = 10; // 10%
            $adminFeeAmount = $grossValue * ($adminFeePercent / 100);
            $netValue = $grossValue - $adminFeeAmount;

            // Create delivery
            $delivery = ProductionDelivery::create([
                'tenant_id' => $tenantId,
                'sales_project_id' => $validated['sales_project_id'],
                'project_demand_id' => $validated['project_demand_id'],
                'associate_id' => $validated['associate_id'],
                'product_id' => $demand->product_id,
                'delivery_date' => $validated['delivery_date'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'admin_fee_amount' => $adminFeeAmount,
                'net_value' => $netValue,
                'status' => DeliveryStatus::PENDING,
                'quality_grade' => $validated['quality_grade'] ?? null,
                'quality_notes' => $validated['quality_notes'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_by' => Auth::id(),
                'paid' => false,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Entrega registrada com sucesso!',
                'delivery' => [
                    'id' => $delivery->id,
                    'quantity' => (float) $delivery->quantity,
                    'net_value' => (float) $delivery->net_value,
                    'status' => $delivery->status->getLabel(),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar entrega: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get project deliveries history
     */
    public function projectDeliveries($projectId)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $project = SalesProject::where('tenant_id', $tenantId)
            ->with('customer')
            ->findOrFail($projectId);

        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->with(['associate.user', 'projectDemand.product'])
            ->orderBy('delivery_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($delivery) {
                return [
                    'id' => $delivery->id,
                    'associate_name' => $delivery->associate->user->name ?? 'Associado #'.$delivery->associate_id,
                    'product_name' => $delivery->projectDemand->product->name ?? '-',
                    'delivery_date' => $delivery->delivery_date?->format('d/m/Y') ?? '-',
                    'quantity' => (float) $delivery->quantity,
                    'unit' => $delivery->projectDemand->product->unit ?? 'un',
                    'net_value' => (float) $delivery->net_value,
                    'quality_grade' => $delivery->quality_grade,
                    'status' => $delivery->status->getLabel(),
                    'status_value' => $delivery->status->value,
                ];
            });

        return view('delivery.project-deliveries', compact('project', 'deliveries'));
    }

    /**
     * Approve delivery
     */
    public function approveDelivery($deliveryId)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        try {
            $delivery = ProductionDelivery::where('tenant_id', $tenantId)
                ->findOrFail($deliveryId);

            if ($delivery->status !== DeliveryStatus::PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta entrega já foi processada.',
                ], 400);
            }

            $delivery->update([
                'status' => DeliveryStatus::APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entrega aprovada com sucesso!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao aprovar: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject delivery
     */
    public function rejectDelivery(Request $request, $deliveryId)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        try {
            $delivery = ProductionDelivery::where('tenant_id', $tenantId)
                ->findOrFail($deliveryId);

            if ($delivery->status !== DeliveryStatus::PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta entrega já foi processada.',
                ], 400);
            }

            $delivery->update([
                'status' => DeliveryStatus::REJECTED,
                'rejection_reason' => $request->input('reason'),
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Entrega rejeitada.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao rejeitar: '.$e->getMessage(),
            ], 500);
        }
    }
}
