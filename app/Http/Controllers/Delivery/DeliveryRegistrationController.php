<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeliveryRegistrationController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'any.role:registrador_entregas']);
    }

    private function currentTenant(): ?Tenant
    {
        $tenant = request()->route('tenant');
        if ($tenant instanceof Tenant) {
            return $tenant;
        }
        $tenantId = session('tenant_id');

        return $tenantId ? Tenant::find($tenantId) : null;
    }

    /**
     * Show delivery dashboard with all active/draft projects
     */
    public function index()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $projects = SalesProject::where('tenant_id', $tenantId)
            ->whereIn('status', [ProjectStatus::DRAFT->value, ProjectStatus::ACTIVE->value, ProjectStatus::AWAITING_DELIVERY->value])
            ->with(['customer', 'demands.product', 'deliveries'])
            ->orderByRaw("FIELD(status, 'active', 'awaiting_delivery', 'draft')")
            ->orderBy('title')
            ->get()
            ->map(function ($project) {
                $totalTarget = $project->demands->sum('target_quantity');
                $totalDelivered = $project->deliveries->sum('quantity');
                $approvedDeliveries = $project->deliveries->where('status', DeliveryStatus::APPROVED->value)->count();
                $pendingDeliveries = $project->deliveries->where('status', DeliveryStatus::PENDING->value)->count();
                $rejectedDeliveries = $project->deliveries->where('status', DeliveryStatus::REJECTED->value)->count();
                $progress = $totalTarget > 0 ? min(100, ($totalDelivered / $totalTarget) * 100) : 0;

                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'customer_name' => $project->customer->name ?? '-',
                    'status' => $project->status->getLabel(),
                    'status_value' => $project->status->value,
                    'allow_any_product' => (bool) $project->allow_any_product,
                    'start_date' => $project->start_date?->format('d/m/Y'),
                    'end_date' => $project->end_date?->format('d/m/Y'),
                    'total_target' => $totalTarget,
                    'total_delivered' => $totalDelivered,
                    'remaining' => max(0, $totalTarget - $totalDelivered),
                    'progress' => $progress,
                    'approved_deliveries' => $approvedDeliveries,
                    'pending_deliveries' => $pendingDeliveries,
                    'rejected_deliveries' => $rejectedDeliveries,
                    'total_deliveries' => $project->deliveries->count(),
                    'products_count' => $project->allow_any_product ? '∞' : $project->demands->count(),
                    'days_remaining' => $project->end_date ? (int) ceil(now()->diffInDays($project->end_date, false)) : null,
                ];
            });

        $stats = [
            'active_projects' => $projects->where('status_value', ProjectStatus::ACTIVE->value)->count(),
            'draft_projects' => $projects->where('status_value', ProjectStatus::DRAFT->value)->count(),
            'total_deliveries_today' => ProductionDelivery::where('tenant_id', $tenantId)
                ->whereDate('delivery_date', today())->count(),
            // backward-compatibility: some views expect 'deliveries_today'
            'deliveries_today' => ProductionDelivery::where('tenant_id', $tenantId)
                ->whereDate('delivery_date', today())->count(),
            'pending_approvals' => ProductionDelivery::where('tenant_id', $tenantId)
                ->where('status', DeliveryStatus::PENDING)->count(),
            'total_delivered_this_week' => ProductionDelivery::where('tenant_id', $tenantId)
                ->whereBetween('delivery_date', [now()->startOfWeek(), now()->endOfWeek()])->sum('quantity'),
        ];

        $currentTenant = $this->currentTenant();

        return view('delivery.dashboard', compact('projects', 'stats', 'currentTenant'));
    }

    /**
     * Show delivery registration page for specific project
     */
    public function register()
    {
        $projectRoute = request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $isStandalone = ! $projectRoute;
        $standaloneProducts = collect();

        if ($projectRoute) {
            $project = SalesProject::where('tenant_id', $tenantId)
                ->with(['customer', 'demands.product', 'deliveries'])
                ->find($projectRoute);

            if (! $project) {
                return redirect()->route('delivery.dashboard', ['tenant' => request()->route('tenant')])
                    ->with('error', 'Projeto não encontrado.');
            }

            // Bloquear acesso a projetos em rascunho
            if ($project->status === ProjectStatus::DRAFT) {
                return redirect()->route('delivery.dashboard', ['tenant' => request()->route('tenant')])
                    ->with('error', 'Este projeto está em rascunho. Inicie o projeto antes de registrar entregas.');
            }

            $projects = collect([$project]);
        } else {
            // Modo avulso: sem projeto vinculado — carregar produtos ativos
            $projects = collect();
            $standaloneProducts = Product::where('tenant_id', $tenantId)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'unit', 'cost_price', 'sale_price']);
        }

        $associates = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->whereHas('user', function ($q) {
                $q->where('status', true);
            })
            ->orderBy('id')
            ->get();

        $currentTenant = $this->currentTenant();

        return view('delivery.register', compact('projects', 'associates', 'currentTenant', 'isStandalone', 'standaloneProducts'));
    }

    /**
     * Get project demands (or all products for allow_any_product projects) via AJAX
     */
    public function getProjectDemands()
    {
        $projectId = (int) request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)->find($projectId);
        if (! $project) {
            return response()->json(['error' => 'Projeto não encontrado'], 404);
        }

        // Projeto livre: retorna todos os produtos cadastrados
        if ($project->allow_any_product) {
            $products = Product::where('tenant_id', $tenantId)
                ->where('status', true)
                ->orderBy('name')
                ->get()
                ->map(function ($product) use ($tenantId, $projectId) {
                    $delivered = ProductionDelivery::where('tenant_id', $tenantId)
                        ->where('sales_project_id', $projectId)
                        ->where('product_id', $product->id)
                        ->where('status', '!=', DeliveryStatus::CANCELLED->value)
                        ->sum('quantity');

                    return [
                        'id' => null,                         // sem demanda específica
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_unit' => $product->unit ?? 'un',
                        'target_quantity' => null,            // sem meta, projeto livre
                        'delivered_quantity' => (float) $delivered,
                        'remaining_quantity' => null,         // ilimitado
                        'unit_price' => (float) ($product->cost_price ?? 0),
                        'is_free' => true,
                    ];
                });

            return response()->json($products);
        }

        // Projeto com demandas específicas
        $demands = ProjectDemand::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->with('product')
            ->get()
            ->map(function ($demand) use ($tenantId) {
                $delivered = ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('project_demand_id', $demand->id)
                    ->where('status', '!=', DeliveryStatus::CANCELLED->value)
                    ->sum('quantity');

                return [
                    'id' => $demand->id,
                    'product_id' => $demand->product_id,
                    'product_name' => $demand->product->name ?? '-',
                    'product_unit' => $demand->product->unit ?? 'un',
                    'target_quantity' => (float) $demand->target_quantity,
                    'delivered_quantity' => (float) $delivered,
                    'remaining_quantity' => (float) ($demand->target_quantity - $delivered),
                    'unit_price' => (float) $demand->unit_price,
                    'is_free' => false,
                ];
            });

        return response()->json($demands);
    }

    /**
     * Get associate deliveries for a project
     */
    public function getAssociateDeliveries()
    {
        $projectId = (int) request()->route('project');
        $associateId = (int) request()->route('associate');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->with(['projectDemand.product', 'product'])
            ->orderBy('delivery_date', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($delivery) {
                // Suporta entregas com ou sem demanda específica
                $productName = $delivery->projectDemand?->product?->name
                    ?? $delivery->product?->name
                    ?? '-';
                $unit = $delivery->projectDemand?->product?->unit
                    ?? $delivery->product?->unit
                    ?? 'un';

                return [
                    'id' => $delivery->id,
                    'product_name' => $productName,
                    'delivery_date' => $delivery->delivery_date?->format('d/m/Y') ?? '-',
                    'quantity' => (float) $delivery->quantity,
                    'unit' => $unit,
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
            return response()->json(['success' => false, 'message' => 'Selecione uma organização primeiro.'], 403);
        }

        $isStandalone = (bool) $request->input('is_standalone', false);

        $validated = $request->validate([
            'sales_project_id' => $isStandalone ? 'nullable|exists:sales_projects,id' : 'required|exists:sales_projects,id',
            'project_demand_id' => 'nullable|exists:project_demands,id',
            'product_id' => 'nullable|exists:products,id',
            'associate_id' => 'required|exists:associates,id',
            'delivery_date' => 'required|date',
            'quantity' => 'required|numeric|min:0.001',
            'quality_grade' => 'nullable|string|max:50',
            'quality_notes' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
        ]);

        // Verificar projeto (não aplicável no modo avulso)
        $project = null;
        if (! $isStandalone) {
            $project = SalesProject::where('tenant_id', $tenantId)
                ->find($validated['sales_project_id']);

            if (! $project) {
                return response()->json(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
            }

            if ($project->status !== ProjectStatus::ACTIVE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível registrar entregas para este projeto. Status atual: '.$project->status->getLabel().'. O projeto precisa estar "Em Execução".',
                ], 422);
            }

            // Projetos com demanda específica precisam de project_demand_id
            if (! $project->allow_any_product && empty($validated['project_demand_id'])) {
                return response()->json(['success' => false, 'message' => 'Selecione o produto da demanda do projeto.'], 422);
            }

            // Projetos livres precisam de product_id
            if ($project->allow_any_product && empty($validated['product_id'])) {
                return response()->json(['success' => false, 'message' => 'Selecione o produto a ser entregue.'], 422);
            }
        } else {
            // Modo avulso: product_id é obrigatório
            if (empty($validated['product_id'])) {
                return response()->json(['success' => false, 'message' => 'Selecione o produto a ser entregue.'], 422);
            }
        }

        try {
            DB::beginTransaction();

            $quantity = (float) $validated['quantity'];
            $unitPrice = 0;
            $productId = null;
            $demandId = null;

            if ($isStandalone) {
                // Entrega avulsa: sem projeto
                $product = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
                $unitPrice = (float) ($product->cost_price ?? 0);
                $productId = $product->id;
                $demandId = null;

                $duplicate = ProductionDelivery::where('tenant_id', $tenantId)
                    ->whereNull('sales_project_id')
                    ->where('product_id', $productId)
                    ->where('associate_id', $validated['associate_id'])
                    ->where('delivery_date', $validated['delivery_date'])
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->first();
            } elseif (! $project->allow_any_product) {
                // Projeto com demandas
                $demand = ProjectDemand::where('tenant_id', $tenantId)->findOrFail($validated['project_demand_id']);
                $unitPrice = (float) $demand->unit_price;
                $productId = $demand->product_id;
                $demandId = $demand->id;

                // Prevenir duplicatas
                $duplicate = ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('sales_project_id', $validated['sales_project_id'])
                    ->where('project_demand_id', $demandId)
                    ->where('associate_id', $validated['associate_id'])
                    ->where('delivery_date', $validated['delivery_date'])
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->first();
            } else {
                // Projeto livre
                $product = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
                $unitPrice = (float) ($product->cost_price ?? 0);
                $productId = $product->id;
                $demandId = null;

                $duplicate = ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('sales_project_id', $validated['sales_project_id'])
                    ->where('product_id', $productId)
                    ->where('associate_id', $validated['associate_id'])
                    ->where('delivery_date', $validated['delivery_date'])
                    ->where('created_at', '>=', now()->subSeconds(30))
                    ->first();
            }

            if (isset($duplicate) && $duplicate) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Entrega já registrada recentemente para este produto/associado/data.',
                    'existing' => ['id' => $duplicate->id],
                ], 409);
            }

            $grossValue = $quantity * $unitPrice;
            $adminFeePercent = $isStandalone ? 0.0 : (float) ($project->admin_fee_percentage ?? 10);
            $adminFeeAmount = $grossValue * ($adminFeePercent / 100);
            $netValue = $grossValue - $adminFeeAmount;

            $delivery = ProductionDelivery::create([
                'tenant_id' => $tenantId,
                'sales_project_id' => $isStandalone ? null : $validated['sales_project_id'],
                'project_demand_id' => $demandId,
                'associate_id' => $validated['associate_id'],
                'product_id' => $productId,
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
     * Iniciar projeto (DRAFT → ACTIVE) via painel público
     */
    public function startProject()
    {
        $projectId = (int) request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)->find($projectId);
        if (! $project) {
            return response()->json(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }

        if ($project->status !== ProjectStatus::DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas projetos em rascunho podem ser iniciados. Status atual: '.$project->status->getLabel(),
            ], 400);
        }

        $project->update(['status' => ProjectStatus::ACTIVE]);

        return response()->json([
            'success' => true,
            'message' => 'Projeto iniciado com sucesso! Agora é possível registrar entregas.',
        ]);
    }

    /**
     * Get project deliveries history
     */
    public function projectDeliveries()
    {
        $projectId = (int) request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $project = SalesProject::where('tenant_id', $tenantId)
            ->with('customer')
            ->findOrFail($projectId);

        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->with(['associate.user', 'projectDemand.product', 'product'])
            ->orderBy('delivery_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($delivery) {
                $productName = $delivery->projectDemand?->product?->name
                    ?? $delivery->product?->name
                    ?? '-';
                $unit = $delivery->projectDemand?->product?->unit
                    ?? $delivery->product?->unit
                    ?? 'un';

                return [
                    'id' => $delivery->id,
                    'associate_name' => $delivery->associate?->user?->name ?? 'Associado #'.$delivery->associate_id,
                    'product_name' => $productName,
                    'delivery_date' => $delivery->delivery_date?->format('d/m/Y') ?? '-',
                    'quantity' => (float) $delivery->quantity,
                    'unit' => $unit,
                    'net_value' => (float) $delivery->net_value,
                    'quality_grade' => $delivery->quality_grade,
                    'status' => $delivery->status->getLabel(),
                    'status_value' => $delivery->status->value,
                ];
            });

        $currentTenant = $this->currentTenant();

        return view('delivery.project-deliveries', compact('project', 'deliveries', 'currentTenant'));
    }

    /**
     * Approve delivery
     */
    public function approveDelivery()
    {
        $deliveryId = (int) request()->route('delivery');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        try {
            $delivery = ProductionDelivery::where('tenant_id', $tenantId)->findOrFail($deliveryId);

            if ($delivery->status !== DeliveryStatus::PENDING) {
                return response()->json(['success' => false, 'message' => 'Esta entrega já foi processada.'], 400);
            }

            $delivery->update([
                'status' => DeliveryStatus::APPROVED,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Entrega aprovada com sucesso!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao aprovar: '.$e->getMessage()], 500);
        }
    }

    /**
     * Reject delivery
     */
    public function rejectDelivery(Request $request)
    {
        $deliveryId = (int) request()->route('delivery');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        try {
            $delivery = ProductionDelivery::where('tenant_id', $tenantId)->findOrFail($deliveryId);

            if ($delivery->status !== DeliveryStatus::PENDING) {
                return response()->json(['success' => false, 'message' => 'Esta entrega já foi processada.'], 400);
            }

            $delivery->update([
                'status' => DeliveryStatus::REJECTED,
            ]);

            return response()->json(['success' => true, 'message' => 'Entrega rejeitada.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao rejeitar: '.$e->getMessage()], 500);
        }
    }

    public function finalizeProject()
    {
        $projectId = (int) request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)->find($projectId);
        if (! $project) {
            return response()->json(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }

        if ($project->status !== ProjectStatus::ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Apenas projetos ativos podem ter suas entregas finalizadas. Status atual: '.$project->status->getLabel(),
            ], 400);
        }

        $pendingCount = $project->deliveries()->where('status', DeliveryStatus::PENDING)->count();
        if ($pendingCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Existem {$pendingCount} entrega(s) ainda pendentes de aprovação. Aprove ou rejeite-as antes de finalizar.",
            ], 400);
        }

        $project->update(['status' => ProjectStatus::AWAITING_DELIVERY]);

        return response()->json([
            'success' => true,
            'message' => 'Entregas finalizadas! O projeto agora aguarda a entrega ao cliente.',
        ]);
    }

    public function deliverToClient(Request $request)
    {
        $projectId = (int) request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Tenant não encontrado'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)->find($projectId);
        if (! $project) {
            return response()->json(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }

        if ($project->status !== ProjectStatus::AWAITING_DELIVERY) {
            return response()->json([
                'success' => false,
                'message' => 'O projeto precisa ter as entregas finalizadas antes de marcar como entregue ao cliente. Status atual: '.$project->status->getLabel(),
            ], 400);
        }

        $validated = $request->validate([
            'delivery_date' => 'nullable|date',
            'quantities'    => 'required|array|min:1',
            'quantities.*'  => 'numeric|min:0',
            'notes'         => 'nullable|string|max:500',
        ]);

        // Verifica se ao menos uma quantidade é > 0
        $quantities = collect($validated['quantities'])->filter(fn ($q) => (float) $q > 0);
        if ($quantities->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Informe ao menos uma quantidade maior que zero.'], 422);
        }

        try {
            DB::beginTransaction();

            $stockService = app(StockService::class);
            $deliveryDate = $validated['delivery_date'] ?? now()->toDateString();
            $notes = $validated['notes'] ?? null;

            foreach ($quantities as $productId => $qty) {
                $product = Product::where('tenant_id', $tenantId)->find((int) $productId);
                if (! $product || (float) $qty <= 0) {
                    continue;
                }

                $stockService->exit(
                    $product,
                    (float) $qty,
                    StockMovementReason::ENTREGA_CLIENTE,
                    $project,
                    [
                        'movement_date' => $deliveryDate,
                        'notes'         => trim("Entrega ao cliente - Projeto: {$project->title}" . ($notes ? " | {$notes}" : '')),
                    ]
                );
            }

            $project->update([
                'status'         => ProjectStatus::DELIVERED,
                'delivered_date' => $deliveryDate,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Projeto marcado como entregue ao cliente com sucesso! Baixa no estoque registrada.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar entrega ao cliente: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna o resumo de estoque disponível por produto para um projeto (aprovado)
     */
    public function getProjectStockSummary()
    {
        $projectId = (int) request()->route('project');
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)->find($projectId);
        if (! $project) {
            return response()->json(['error' => 'Projeto não encontrado'], 404);
        }

        // Agrupa entregas aprovadas por produto para saber o que entrou no estoque
        $approvedByProduct = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('status', DeliveryStatus::APPROVED)
            ->with('product')
            ->selectRaw('product_id, SUM(quantity) as total_qty')
            ->groupBy('product_id')
            ->get();

        $result = $approvedByProduct->map(function ($item) {
            $product = $item->product;
            return [
                'product_id'      => $item->product_id,
                'product_name'    => $product?->name ?? 'Produto #'.$item->product_id,
                'product_unit'    => $product?->unit ?? 'un',
                'approved_qty'    => (float) $item->total_qty,
                'current_stock'   => (float) ($product?->current_stock ?? 0),
                'max_deliverable' => min((float) $item->total_qty, (float) ($product?->current_stock ?? 0)),
            ];
        })->values();

        return response()->json($result);
    }

    public function allDeliveries()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $currentTenant = $this->currentTenant();

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->with(['salesProject', 'associate.user', 'product', 'receiver', 'approver']);

        // Permanecer com filtros via query string
        $statusFilter = request('status');
        $projectFilter = request('project_id');
        $dateFrom = request('date_from');
        $dateTo = request('date_to');
        $search = request('search');

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }
        if ($projectFilter) {
            $query->where('sales_project_id', $projectFilter);
        }
        if ($dateFrom) {
            $query->whereDate('delivery_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('delivery_date', '<=', $dateTo);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('associate.user', fn ($aq) => $aq->where('name', 'like', "%{$search}%"));
            });
        }

        $deliveries = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate(25)->appends(request()->query());

        $projects = SalesProject::where('tenant_id', $tenantId)
            ->orderBy('title')
            ->pluck('title', 'id');

        // Resumo de estoque por produto (entregas aprovadas)
        $stockSummary = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('status', DeliveryStatus::APPROVED)
            ->with('product')
            ->select('product_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(*) as total_deliveries'))
            ->groupBy('product_id')
            ->get()
            ->map(fn ($item) => [
                'product_name' => optional($item->product)->name ?? 'Desconhecido',
                'total_quantity' => $item->total_quantity,
                'total_deliveries' => $item->total_deliveries,
            ]);

        $stats = [
            'total' => ProductionDelivery::where('tenant_id', $tenantId)->count(),
            'pending' => ProductionDelivery::where('tenant_id', $tenantId)->where('status', DeliveryStatus::PENDING)->count(),
            'approved' => ProductionDelivery::where('tenant_id', $tenantId)->where('status', DeliveryStatus::APPROVED)->count(),
            'rejected' => ProductionDelivery::where('tenant_id', $tenantId)->where('status', DeliveryStatus::REJECTED)->count(),
        ];

        $associates = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->whereHas('user', fn ($q) => $q->where('status', true))
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->user->name ?? "#{$a->id}"]);

        return view('delivery.all-deliveries', compact('deliveries', 'projects', 'stockSummary', 'stats', 'currentTenant', 'statusFilter', 'projectFilter', 'dateFrom', 'dateTo', 'search', 'associates'));
    }

    // ────────────────────────────────────────────────────────
    //  RELATÓRIOS PDF
    // ────────────────────────────────────────────────────────

    /**
     * Build a filtered query reusing the same filters from allDeliveries.
     */
    private function buildFilteredDeliveriesQuery(int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->with(['salesProject', 'associate.user', 'product']);

        // Relatórios nunca exibem entregas rejeitadas ou canceladas
        $query->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value]);

        if ($status = request('status')) {
            if (! in_array($status, [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])) {
                $query->where('status', $status);
            }
        }
        if ($projectId = request('project_id')) {
            $query->where('sales_project_id', $projectId);
        }
        if ($dateFrom = request('date_from')) {
            $query->whereDate('delivery_date', '>=', $dateFrom);
        }
        if ($dateTo = request('date_to')) {
            $query->whereDate('delivery_date', '<=', $dateTo);
        }
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('associate.user', fn ($aq) => $aq->where('name', 'like', "%{$search}%"));
            });
        }

        return $query->orderByDesc('delivery_date')->orderByDesc('id');
    }

    /**
     * Resolve human-readable filter labels for the PDF header.
     */
    private function resolveFilterLabels(int $tenantId): array
    {
        $filters = [];
        if ($pid = request('project_id')) {
            $filters['project'] = SalesProject::where('tenant_id', $tenantId)->find($pid)?->title ?? '';
        }
        if ($s = request('status')) {
            $filters['status'] = match ($s) {
                'pending' => 'Pendente', 'approved' => 'Aprovada',
                'rejected' => 'Rejeitada', 'cancelled' => 'Cancelada',
                default => $s,
            };
        }
        $filters['date_from'] = request('date_from') ? \Carbon\Carbon::parse(request('date_from'))->format('d/m/Y') : null;
        $filters['date_to'] = request('date_to') ? \Carbon\Carbon::parse(request('date_to'))->format('d/m/Y') : null;

        return $filters;
    }

    /**
     * Map a delivery model to a flat array for report views.
     */
    private function mapDeliveryRow(ProductionDelivery $d): array
    {
        return [
            'delivery_date' => $d->delivery_date?->format('d/m/Y') ?? '—',
            'project' => $d->salesProject->title ?? 'Avulsa',
            'associate' => $d->associate?->user?->name ?? '—',
            'product' => $d->product?->name ?? '—',
            'unit' => $d->product?->unit ?? 'un',
            'quantity' => (float) $d->quantity,
            'unit_price' => (float) $d->unit_price,
            'gross_value' => (float) $d->gross_value,
            'admin_fee' => (float) ($d->admin_fee_amount ?? 0),
            'net_value' => (float) ($d->net_value ?? 0),
            'status' => $d->status->getLabel(),
            'status_value' => $d->status->value,
            'quality_grade' => $d->quality_grade,
        ];
    }

    /**
     * PDF: Relatório agrupado por associado
     */
    public function reportByAssociate()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(403);
        }

        $tenant = $this->currentTenant();
        $deliveries = $this->buildFilteredDeliveriesQuery($tenantId)->get();
        $filters = $this->resolveFilterLabels($tenantId);

        $grouped = $deliveries->groupBy('associate_id');
        $groups = [];

        foreach ($grouped as $associateId => $items) {
            $assoc = $items->first()->associate;
            $groups[] = [
                'associate_name' => $assoc?->user?->name ?? 'Desconhecido',
                'cpf' => $assoc?->cpf_cnpj ?? '',
                'deliveries_count' => $items->count(),
                'total_quantity' => $items->sum('quantity'),
                'gross_value' => $items->sum('gross_value'),
                'admin_fee' => $items->sum('admin_fee_amount'),
                'net_value' => $items->sum('net_value'),
                'deliveries' => $items->map(fn ($d) => $this->mapDeliveryRow($d))->values()->all(),
            ];
        }

        usort($groups, fn ($a, $b) => strcasecmp($a['associate_name'], $b['associate_name']));

        $totals = [
            'associates_count' => count($groups),
            'deliveries_count' => $deliveries->count(),
            'total_quantity' => $deliveries->sum('quantity'),
            'total_gross' => $deliveries->sum('gross_value'),
            'total_admin_fee' => $deliveries->sum('admin_fee_amount'),
            'total_net' => $deliveries->sum('net_value'),
        ];

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.deliveries-by-associate', [
            'tenant' => $tenant,
            'title' => 'Relatório de Entregas por Associado',
            'subtitle' => request('project_id') ? ($filters['project'] ?? '') : null,
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters' => $filters,
            'groups' => $groups,
            'totals' => $totals,
        ], array_merge(
            $svc->systemPdfOptions('pdf.deliveries-by-associate', 'Relatório de Entregas por Associado'),
            ['paper' => 'a4', 'orientation' => 'landscape']
        ));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'entregas-por-associado-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * PDF: Relatório agrupado por produto/item
     */
    public function reportByProduct()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(403);
        }

        $tenant = $this->currentTenant();
        $deliveries = $this->buildFilteredDeliveriesQuery($tenantId)->get();
        $filters = $this->resolveFilterLabels($tenantId);

        $grouped = $deliveries->groupBy('product_id');
        $groups = [];

        foreach ($grouped as $productId => $items) {
            $product = $items->first()->product;
            $groups[] = [
                'product_name' => $product?->name ?? 'Desconhecido',
                'unit' => $product?->unit ?? 'un',
                'deliveries_count' => $items->count(),
                'total_quantity' => $items->sum('quantity'),
                'gross_value' => $items->sum('gross_value'),
                'admin_fee' => $items->sum('admin_fee_amount'),
                'net_value' => $items->sum('net_value'),
                'deliveries' => $items->map(fn ($d) => $this->mapDeliveryRow($d))->values()->all(),
            ];
        }

        usort($groups, fn ($a, $b) => strcasecmp($a['product_name'], $b['product_name']));

        $totals = [
            'products_count' => count($groups),
            'deliveries_count' => $deliveries->count(),
            'total_quantity' => $deliveries->sum('quantity'),
            'total_gross' => $deliveries->sum('gross_value'),
            'total_admin_fee' => $deliveries->sum('admin_fee_amount'),
            'total_net' => $deliveries->sum('net_value'),
        ];

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.deliveries-by-product', [
            'tenant' => $tenant,
            'title' => 'Relatório de Entregas por Produto',
            'subtitle' => request('project_id') ? ($filters['project'] ?? '') : null,
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters' => $filters,
            'groups' => $groups,
            'totals' => $totals,
        ], array_merge(
            $svc->systemPdfOptions('pdf.deliveries-by-product', 'Relatório de Entregas por Produto'),
            ['paper' => 'a4', 'orientation' => 'landscape']
        ));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'entregas-por-produto-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Lista pública (autenticada) dos produtores que entregaram em um projeto.
     * Requer auth + role registrador_entregas ou acima.
     */
    public function projectProducers()
    {
        $projectId = (int) request()->route('project');
        $tenantId  = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $project = SalesProject::where('tenant_id', $tenantId)->findOrFail($projectId);

        $tenant = $this->currentTenant();

        $producers = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('status', DeliveryStatus::APPROVED)
            ->with('associate.user')
            ->get()
            ->groupBy('associate_id')
            ->map(function ($items) {
                $assoc = $items->first()->associate;
                return [
                    'associate'    => $assoc,
                    'name'         => $assoc?->user?->name ?? '—',
                    'cpf'          => $assoc?->cpf_cnpj ?? '—',
                    'registration' => $assoc?->registration_number ?? '—',
                    'deliveries'   => $items->count(),
                    'quantity'     => $items->sum('quantity'),
                    'gross_value'  => $items->sum('gross_value'),
                    'admin_fee'    => $items->sum('admin_fee_amount'),
                    'net_value'    => $items->sum('net_value'),
                ];
            })
            ->sortBy('name')
            ->values();

        return view('delivery.project-producers', compact('project', 'tenant', 'producers'));
    }

    /**
     * PDF: Comprovante de entrega de um projeto filtrado por associado — com assinatura
     */
    public function reportProjectAssociate()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(403);
        }

        $tenant = $this->currentTenant();
        $projectId = request('project_id');
        $associateId = request('associate_id');

        if (! $projectId || ! $associateId) {
            return back()->with('error', 'Selecione o projeto e o associado para gerar o comprovante.');
        }

        $project = SalesProject::where('tenant_id', $tenantId)
            ->with('customer')
            ->findOrFail($projectId);

        $associate = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->findOrFail($associateId);

        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->where('status', DeliveryStatus::APPROVED)
            ->with('product')
            ->orderBy('delivery_date')
            ->get();

        if ($deliveries->isEmpty()) {
            return back()->with('error', 'Nenhuma entrega aprovada encontrada para este associado neste projeto.');
        }

        $summary = [
            'deliveries_count' => $deliveries->count(),
            'total_quantity' => $deliveries->sum('quantity'),
            'gross_value' => $deliveries->sum('gross_value'),
            'admin_fee' => $deliveries->sum('admin_fee_amount'),
            'net_value' => $deliveries->sum('net_value'),
        ];

        // Resumo por produto
        $productsSummary = $deliveries->groupBy('product_id')->map(function ($items) {
            $product = $items->first()->product;

            return [
                'product_name' => $product?->name ?? '—',
                'unit' => $product?->unit ?? 'un',
                'count' => $items->count(),
                'quantity' => $items->sum('quantity'),
                'gross' => $items->sum('gross_value'),
                'admin_fee' => $items->sum('admin_fee_amount'),
                'net' => $items->sum('net_value'),
            ];
        })->values()->all();

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'title' => 'Comprovante de Entrega',
            'subtitle' => $project->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'project' => $project,
            'associate' => $associate,
            'deliveries' => $deliveries,
            'summary' => $summary,
            'productsSummary' => $productsSummary,
        ], array_merge(
            $svc->systemPdfOptions('pdf.project-associate-receipt', 'Comprovante de Entrega'),
            ['paper' => 'a4', 'orientation' => 'portrait']
        ));

        $safeName = str_replace(' ', '-', mb_strtolower($associate->user->name ?? 'associado'));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            "comprovante-entrega-{$safeName}-projeto-{$project->id}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }
}
