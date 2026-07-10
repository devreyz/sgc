<?php

namespace App\Http\Controllers\Delivery;

use App\Enums\BillingStatus;
use App\Enums\DeliveryStatus;
use App\Enums\ProjectStatus;
use App\Enums\ReceiptStatus;
use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\BuyerRequestFulfillmentService;
use App\Services\DeliveryProjectIntegrityService;
use App\Services\PricingService;
use App\Services\ProjectFinancialCalculator;
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

    private function deliveryViewData(ProductionDelivery $delivery): array
    {
        $productName = $delivery->projectDemand?->product?->name
            ?? $delivery->product?->name
            ?? '-';
        $unit = $delivery->projectDemand?->product?->unit
            ?? $delivery->product?->unit
            ?? 'un';

        $distributions = $delivery->distributions->map(fn ($d) => [
            'id'          => $d->id,
            'customer_id' => $d->customer_id,
            'customer'    => optional($d->customer)->trade_name ?? optional($d->customer)->name ?? '?',
            'qty'         => (float) $d->quantity,
            'net'         => (float) $d->net_value,
            'billed'      => $d->billing_status instanceof BillingStatus && $d->billing_status !== BillingStatus::UNBILLED,
            'paid'        => (bool) $d->paid || $d->billing_status === BillingStatus::PAID,
            'billing_status' => $d->billing_status?->value,
            'in_receipt'  => (bool) $d->associate_receipt_id,
            'receipt_id'  => $d->associate_receipt_id,
            'receipt_number' => $d->associateReceipt?->formatted_number,
            'billing_receipt_id' => $d->billing_receipt_id,
            'locked'      => (bool) $d->paid
                || $d->billing_status !== BillingStatus::UNBILLED
                || (bool) $d->billing_receipt_id
                || ($d->associateReceipt?->isLocked() ?? false),
        ]);

        $distributedQty = (float) $distributions->sum('qty');
        $issueCount = 0;
        $issueSeverity = null;

        if ($delivery->status === DeliveryStatus::APPROVED) {
            if ($distributedQty <= 0 || $distributedQty + 0.0005 < (float) $delivery->quantity) {
                $issueCount++;
                $issueSeverity = 'warning';
            }

            if ($distributedQty > (float) $delivery->quantity + 0.0005) {
                $issueCount++;
                $issueSeverity = 'critical';
            }
        }

        foreach ($delivery->distributions as $distribution) {
            if (! $distribution->customer_id || (float) ($distribution->unit_price ?? 0) <= 0 || (float) ($distribution->gross_value ?? 0) <= 0) {
                $issueCount++;
                $issueSeverity = 'critical';
            }

            if ($distribution->associate_receipt_id && ! $distribution->associateReceipt) {
                $issueCount++;
                $issueSeverity = 'critical';
            }
        }

        return [
            'id'                => $delivery->id,
            'associate_name'    => $delivery->associate?->user?->name ?? 'Associado #'.$delivery->associate_id,
            'product_name'      => $productName,
            'delivery_date'     => $delivery->delivery_date?->format('d/m/Y') ?? '-',
            'delivery_date_raw' => $delivery->delivery_date?->format('Y-m-d') ?? '',
            'quantity'          => (float) $delivery->quantity,
            'unit'              => $unit,
            'unit_price'        => (float) $delivery->unit_price,
            'net_value'         => (float) $delivery->net_value,
            'dist_net_value'    => (float) $distributions->sum('net'),
            'quality_grade'     => $delivery->quality_grade ?? '',
            'notes'             => $delivery->notes ?? '',
            'status'            => $delivery->status->getLabel(),
            'status_value'      => $delivery->status->value,
            'distributions'     => $distributions->toArray(),
            'distributed_qty'   => $distributedQty,
            'has_billed'        => $distributions->contains('billed', true),
            'issue_count'       => $issueCount,
            'issue_severity'    => $issueSeverity,
        ];
    }

    private function receiptDistributionIntegrityMessage($distributions, int $tenantId, int $projectId, ?int $associateId = null, ?int $receiptId = null): ?string
    {
        $distributions = collect($distributions)->values();

        if ($distributions->isEmpty()) {
            return 'Nenhuma distribuicao valida encontrada para gerar o comprovante.';
        }

        $ids = $distributions->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($distributions as $distribution) {
            if ((int) $distribution->tenant_id !== $tenantId || (int) $distribution->sales_project_id !== $projectId) {
                return "A distribuicao #{$distribution->id} pertence a outro tenant ou projeto.";
            }

            if ($associateId && (int) $distribution->associate_id !== $associateId) {
                return "A distribuicao #{$distribution->id} pertence a outro associado.";
            }

            if (is_null($distribution->parent_delivery_id)) {
                return "A entrega #{$distribution->id} e uma recepcao-pai e nao pode entrar em comprovante financeiro.";
            }

            if (empty($distribution->customer_id)) {
                return "A distribuicao #{$distribution->id} esta sem cliente/destino.";
            }

            if ((float) ($distribution->quantity ?? 0) <= 0) {
                return "A distribuicao #{$distribution->id} esta com quantidade invalida.";
            }

            if ((float) ($distribution->unit_price ?? 0) <= 0) {
                return "A distribuicao #{$distribution->id} esta sem preco valido.";
            }

            if ((float) ($distribution->gross_value ?? 0) <= 0) {
                return "A distribuicao #{$distribution->id} esta com valor bruto zerado.";
            }

            if ($distribution->paid || $distribution->billing_status === BillingStatus::PAID) {
                return "A distribuicao #{$distribution->id} ja foi paga e nao pode entrar em novo comprovante.";
            }

            if (! is_null($distribution->associate_receipt_id) && (int) $distribution->associate_receipt_id !== (int) $receiptId) {
                return "A distribuicao #{$distribution->id} ja esta vinculada ao comprovante #{$distribution->associate_receipt_id}.";
            }
        }

        $parentIds = $distributions->pluck('parent_delivery_id')->filter()->unique()->map(fn ($id) => (int) $id)->all();
        $parents = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereIn('id', $parentIds)
            ->get()
            ->keyBy('id');

        foreach ($distributions as $distribution) {
            $parent = $parents->get((int) $distribution->parent_delivery_id);

            if (! $parent) {
                return "A distribuicao #{$distribution->id} esta sem entrega-pai valida.";
            }

            if ((int) $parent->sales_project_id !== $projectId || (int) $parent->associate_id !== (int) $distribution->associate_id) {
                return "A distribuicao #{$distribution->id} tem tenant, projeto ou associado incompatível com a entrega-pai.";
            }
        }

        foreach ($parents as $parent) {
            $distributed = (float) ProductionDelivery::where('tenant_id', $tenantId)
                ->where('parent_delivery_id', $parent->id)
                ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
                ->sum('quantity');

            if ($distributed - (float) $parent->quantity > 0.0001) {
                return sprintf(
                    'A entrega #%d possui %.4f distribuidos para apenas %.4f recebidos.',
                    $parent->id,
                    $distributed,
                    (float) $parent->quantity
                );
            }
        }

        $legacyReceipt = AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->when($associateId, fn ($query) => $query->where('associate_id', $associateId))
            ->when($receiptId, fn ($query) => $query->where('id', '!=', $receiptId))
            ->get()
            ->first(function (AssociateReceipt $receipt) use ($ids) {
                $receiptIds = collect($receipt->delivery_ids ?? [])->map(fn ($id) => (int) $id)->all();

                return ! empty(array_intersect($ids, $receiptIds));
            });

        if ($legacyReceipt) {
            return "Uma ou mais distribuicoes selecionadas ja constam no comprovante {$legacyReceipt->formatted_number}.";
        }

        return null;
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
            ->whereIn('status', [ProjectStatus::DRAFT->value, ProjectStatus::ACTIVE->value, ProjectStatus::DELIVERIES_CLOSED->value])
            ->with(['customer', 'demands.product', 'deliveries'])
            ->orderByRaw("FIELD(status, 'active', 'deliveries_closed', 'draft')")
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

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->with('organization:id,name,short_name')
            ->get(['id', 'name', 'trade_name', 'organization_id']);

        return view('delivery.dashboard', compact('projects', 'stats', 'currentTenant', 'customers'));
    }

    /**
     * Show delivery registration page for specific project
     */
    public function register()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $projectRoute = request()->route('project');

        $projects = SalesProject::where('tenant_id', $tenantId)
            ->where('status', ProjectStatus::ACTIVE->value)
            ->with(['customer', 'customers'])
            ->orderBy('title')
            ->get()
            ->map(fn ($p) => [
                'id'                  => $p->id,
                'title'               => $p->title,
                'customer_name'       => $p->customer->name ?? '-',
                'allow_any_product'   => (bool) $p->allow_any_product,
                'admin_fee_percentage' => (float) ($p->admin_fee_percentage ?? 10),
                'customer_ids'        => $p->customers->pluck('id')->values()->all(),
            ]);

        // Pre-select project if provided via URL
        $selectedProject = null;
        if ($projectRoute) {
            $selectedProject = $projects->firstWhere('id', (int) $projectRoute);
            if (! $selectedProject) {
                return redirect()
                    ->route('delivery.dashboard', ['tenant' => request()->route('tenant')])
                    ->with('error', 'Projeto não encontrado ou não está em execução.');
            }
        }

        $associates = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->whereHas('user', fn ($q) => $q->where('status', true))
            ->orderBy('id')
            ->get()
            ->map(fn ($a) => [
                'id'                  => $a->id,
                'name'                => $a->user->name ?? "Associado #{$a->id}",
                'nickname'            => $a->nickname ?? null,
                'registration_number' => $a->registration_number,
            ]);

        $standaloneProducts = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->with('organization:id,name,short_name')
            ->get(['id', 'name', 'trade_name', 'organization_id']);

        $currentTenant = $this->currentTenant();

        return view('delivery.register', compact(
            'projects', 'associates', 'currentTenant', 'standaloneProducts', 'selectedProject', 'customers'
        ));
    }

    /**
     * Return active customers list (JSON) for distribution selectors
     */
    public function getCustomers()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', true)
            ->with('organization:id,name,short_name')
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name', 'organization_id', 'price_table_id'])
            ->map(fn ($c) => [
                'id'                => $c->id,
                'name'              => $c->name,
                'trade_name'        => $c->trade_name,
                'organization_id'   => $c->organization_id,
                'organization_name' => $c->organization?->short_name ?? $c->organization?->name,
                'price_table_id'    => $c->price_table_id,
            ]);

        return response()->json($customers);
    }

    /**
     * Distribute an approved reception delivery to one or more customers.
     * Creates child ProductionDelivery records (distribution records).
     */
    public function distribute(Request $request)
    {
        $deliveryId = (int) $request->route('delivery');
        $tenantId   = session('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $validated = $request->validate([
            'distributions'                  => 'required|array|min:1|max:50',
            'distributions.*.customer_id'    => 'required|integer|exists:customers,id',
            'distributions.*.quantity'       => 'required|numeric|min:0.001',
        ]);

        $reception = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNull('parent_delivery_id')
            ->findOrFail($deliveryId);

        if ($reception->status !== DeliveryStatus::APPROVED) {
            return response()->json(['success' => false, 'message' => 'Somente entregas aprovadas podem ser distribuídas.'], 422);
        }

        // Sum of already-distributed quantities for this reception
        $alreadyDistributed = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $deliveryId)
            ->sum('quantity');

        $newTotal = collect($validated['distributions'])->sum('quantity');

        if (($alreadyDistributed + $newTotal) > $reception->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'A soma das distribuições (' . number_format($alreadyDistributed + $newTotal, 3, ',', '.') .
                    ') excede a quantidade recebida (' . number_format($reception->quantity, 3, ',', '.') . ').',
            ], 422);
        }

        // Validate customers belong to this tenant
        $customerIds = collect($validated['distributions'])->pluck('customer_id')->unique();
        $validCount  = Customer::where('tenant_id', $tenantId)->whereIn('id', $customerIds)->count();
        if ($validCount !== $customerIds->count()) {
            return response()->json(['success' => false, 'message' => 'Cliente inválido.'], 422);
        }

        $project = $reception->salesProject;
        $project?->loadMissing('customers:id');
        $allowedCustomerIds = $project?->customers?->pluck('id') ?? collect();
        if ($allowedCustomerIds->isNotEmpty() && $customerIds->diff($allowedCustomerIds)->isNotEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Este projeto permite distribuicao apenas para os clientes vinculados a ele.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $pricingService = app(PricingService::class);
            $calculator     = app(ProjectFinancialCalculator::class);
            $requestFulfillment = app(BuyerRequestFulfillmentService::class);
            $product = $reception->product;

            $created = [];
            $affectedOrganizations = collect();
            $plannedAgainstRequests = [];
            foreach ($validated['distributions'] as $dist) {
                $customer = Customer::with('organization')->find((int) $dist['customer_id']);

                if (! $customer?->organization_id || ! $customer->organization) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'O cliente selecionado nao esta vinculado a uma organizacao compradora.',
                    ], 422);
                }

                if ($project && $requestFulfillment->limitIsEnabled($project, $customer->organization)) {
                    $remaining = $requestFulfillment->remainingQuantity(
                        (int) $tenantId,
                        (int) $project->id,
                        (int) $customer->organization_id,
                        (int) $reception->product_id,
                        (int) $customer->id
                    );
                    $limitKey = $customer->organization_id.'-'.$customer->id.'-'.$reception->product_id;
                    $alreadyPlanned = (float) ($plannedAgainstRequests[$limitKey] ?? 0);

                    if ($alreadyPlanned + (float) $dist['quantity'] > $remaining) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Esta organizacao limita a distribuicao ao solicitado. Saldo disponivel para '
                                . ($customer->trade_name ?: $customer->name) . ': '
                                . number_format(max(0, $remaining - $alreadyPlanned), 3, ',', '.') . '.',
                        ], 422);
                    }

                    $plannedAgainstRequests[$limitKey] = $alreadyPlanned + (float) $dist['quantity'];
                }

                // 1. Resolve o preço de venda pelo motor de preços
                $priceResult = $pricingService->resolvePrice($product, $customer, $project);

                // Bloquear distribuição se o produto não tem preço configurado
                if ($priceResult['source'] === 'unpriced' || bccomp((string) $priceResult['sale_price'], '0', 4) === 0) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'O produto "' . $product->name . '" não tem preço configurado para o cliente "' . ($customer->trade_name ?: $customer->name) . '". Configure a tabela de preços antes de distribuir.',
                    ], 422);
                }

                $unitPrice = $priceResult['sale_price'];
                $qty       = (string) $dist['quantity'];
                $gross     = bcmul($qty, $unitPrice, 8);

                // 2. Aplica taxas pelo motor financeiro central
                $financial = $project ? $calculator->calculate($project, $gross) : [
                    'total_fee'                => '0',
                    'net'                      => $gross,
                    'admin_fee_percentage_eff' => '0',
                ];

                $child = ProductionDelivery::create([
                    'tenant_id'            => $tenantId,
                    'sales_project_id'     => $reception->sales_project_id,
                    'project_demand_id'    => $reception->project_demand_id,
                    'associate_id'         => $reception->associate_id,
                    'product_id'           => $reception->product_id,
                    'customer_id'          => (int) $dist['customer_id'],
                    'parent_delivery_id'   => $reception->id,
                    'delivery_date'        => $reception->delivery_date,
                    'quantity'             => $qty,
                    'unit_price'           => $unitPrice,
                    'cost_price_used'      => $priceResult['cost_price'],
                    'admin_fee_percentage' => $financial['admin_fee_percentage_eff'],
                    'admin_fee_amount'     => $financial['total_fee'],
                    'net_value'            => $financial['net'],
                    'price_table_id'       => $priceResult['price_table_id'],
                    'price_source'         => $priceResult['source'],
                    // Distributions are auto-approved: the parent was already approved
                    // and distribution = the act of validating the sale.
                    'status'               => DeliveryStatus::APPROVED,
                    'quality_grade'        => $reception->quality_grade,
                    'received_by'          => Auth::id(),
                    'approved_by'          => Auth::id(),
                    'approved_at'          => now(),
                    'paid'                 => false,
                ]);
                $created[] = $child->id;
                $affectedOrganizations->push((int) $customer->organization_id);
            }

            DB::commit();

            $affectedOrganizations->unique()->each(function (int $organizationId) use ($requestFulfillment, $tenantId, $project) {
                if ($project) {
                    $requestFulfillment->updateProjectOrganizationStatuses((int) $tenantId, (int) $project->id, $organizationId);
                }
            });

            return response()->json([
                'success'      => true,
                'message'      => count($created) . ' distribuição(ões) criada(s).',
                'delivery_id'   => $reception->id,
                'created_ids'  => $created,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Erro: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete an individual distribution (child delivery — parent_delivery_id NOT NULL)
     * Only if project is not yet finalized/delivered.
     */
    public function deleteDistribution(Request $request)
    {
        $distributionId = (int) $request->route('distribution');
        $tenantId       = session('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $distribution = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNotNull('parent_delivery_id')
            ->findOrFail($distributionId);

        // Block if the parent project is completed or cancelled
        if ($distribution->salesProject && ! $distribution->salesProject->status?->allowsFinancial()) {
            return response()->json(['success' => false, 'message' => 'Não é possível remover distribuições de um projeto que não está em fase financeira ativa.'], 400);
        }

        if ($distribution->paid || $distribution->billing_status !== BillingStatus::UNBILLED) {
            return response()->json([
                'success' => false,
                'message' => 'Esta distribuicao nao pode ser excluida porque ja foi faturada ou paga.',
            ], 422);
        }

        if ($distribution->billing_receipt_id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta distribuicao ja esta vinculada a um comprovante de cobranca e nao pode ser excluida diretamente.',
            ], 422);
        }

        $projectId = $distribution->sales_project_id;
        $organizationId = $distribution->customer?->organization_id;
        $receipt = $distribution->associateReceipt;

        if ($receipt) {
            if ($receipt->isLocked()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta distribuicao nao pode ser excluida porque esta em um comprovante pago.',
                ], 422);
            }

            if (! $request->boolean('impact_confirmed') || (int) $request->input('math_answer') !== 2) {
                return response()->json([
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => 'Esta distribuicao ja esta em um comprovante. Confirme 1 + 1 para remover e recalcular o comprovante.',
                ], 409);
            }
        }

        DB::transaction(function () use ($distribution, $receipt, $projectId) {
            if ($receipt) {
                $nextIds = collect($receipt->delivery_ids ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => $id === (int) $distribution->id)
                    ->values()
                    ->all();

                $remaining = ProductionDelivery::where('tenant_id', $receipt->tenant_id)
                    ->where('sales_project_id', $receipt->sales_project_id)
                    ->where('associate_id', $receipt->associate_id)
                    ->where('associate_receipt_id', $receipt->id)
                    ->where('id', '!=', $distribution->id)
                    ->whereNotNull('parent_delivery_id')
                    ->get();

                if ($remaining->isNotEmpty() && $receipt->project) {
                    $snapshot = app(\App\Services\AssociateReceiptService::class)
                        ->computeSnapshot($remaining, $receipt->project);
                    $receipt->update([
                        'delivery_ids' => $nextIds,
                        'total_gross' => $snapshot['total_gross'],
                        'total_fees' => $snapshot['total_fees'],
                        'total_net' => $snapshot['total_net'],
                        'fee_snapshot' => $snapshot['fee_snapshot'],
                        'status' => ReceiptStatus::OBSOLETE->value,
                    ]);
                } else {
                    $receipt->update([
                        'delivery_ids' => [],
                        'total_gross' => 0,
                        'total_fees' => 0,
                        'total_net' => 0,
                        'fee_snapshot' => null,
                        'status' => ReceiptStatus::OBSOLETE->value,
                    ]);
                }
            }

            $distribution->delete();
        });

        if ($projectId && $organizationId) {
            app(BuyerRequestFulfillmentService::class)
                ->updateProjectOrganizationStatuses((int) $tenantId, (int) $projectId, (int) $organizationId);
        }

        // Return updated totals for the parent reception
        $parentId    = $distribution->parent_delivery_id;
        $distTotal   = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $parentId)
            ->sum('quantity');
        $distNetTotal = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $parentId)
            ->sum('net_value');

        return response()->json([
            'success'          => true,
            'message'          => 'Distribuição removida.',
            'parent_delivery_id' => $parentId,
            'deleted_id'       => $distributionId,
            'dist_total_qty'   => (float) $distTotal,
            'dist_total_net'   => (float) $distNetTotal,
        ]);
    }

    public function updateDistribution(Request $request)
    {
        $distributionId = (int) $request->route('distribution');
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessao expirada.'], 403);
        }

        $validated = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'quantity' => 'required|numeric|min:0.001',
        ]);

        $distribution = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNotNull('parent_delivery_id')
            ->with(['parentDelivery', 'salesProject', 'customer.organization', 'associateReceipt'])
            ->findOrFail($distributionId);

        if ($distribution->paid || $distribution->billing_status !== BillingStatus::UNBILLED || $distribution->billing_receipt_id) {
            return response()->json([
                'success' => false,
                'message' => 'Esta distribuicao nao pode ser editada porque ja foi faturada ou paga.',
            ], 422);
        }

        $receipt = $distribution->associateReceipt;
        if ($receipt?->isLocked()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta distribuicao ja esta em um comprovante pago e nao pode ser editada.',
            ], 422);
        }

        $parent = $distribution->parentDelivery;
        if (! $parent) {
            return response()->json(['success' => false, 'message' => 'Distribuicao sem entrega-pai valida.'], 422);
        }

        $customer = Customer::where('tenant_id', $tenantId)
            ->with('organization')
            ->findOrFail((int) $validated['customer_id']);

        if (! $customer->organization_id || ! $customer->organization) {
            return response()->json([
                'success' => false,
                'message' => 'O cliente selecionado nao esta vinculado a uma organizacao compradora.',
            ], 422);
        }

        $project = $parent->salesProject;
        $project?->loadMissing('customers:id');
        $allowedCustomerIds = $project?->customers?->pluck('id') ?? collect();
        if ($allowedCustomerIds->isNotEmpty() && ! $allowedCustomerIds->contains((int) $customer->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Este projeto permite distribuicao apenas para os clientes vinculados a ele.',
            ], 422);
        }

        $siblingsTotal = (float) ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $parent->id)
            ->where('id', '!=', $distribution->id)
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->sum('quantity');

        $newQuantity = (float) $validated['quantity'];
        if ($siblingsTotal + $newQuantity > (float) $parent->quantity + 0.0005) {
            return response()->json([
                'success' => false,
                'message' => 'A soma das distribuicoes excede a quantidade recebida.',
            ], 422);
        }

        $requestFulfillment = app(BuyerRequestFulfillmentService::class);
        if ($project && $requestFulfillment->limitIsEnabled($project, $customer->organization)) {
            $remaining = $requestFulfillment->remainingQuantity(
                (int) $tenantId,
                (int) $project->id,
                (int) $customer->organization_id,
                (int) $parent->product_id,
                (int) $customer->id
            );

            if ((int) $customer->id === (int) $distribution->customer_id) {
                $remaining += (float) $distribution->quantity;
            }

            if ($newQuantity > $remaining + 0.0005) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta organizacao limita a distribuicao ao solicitado. Saldo disponivel: '
                        . number_format(max(0, $remaining), 3, ',', '.') . '.',
                ], 422);
            }
        }

        $pricingService = app(PricingService::class);
        $calculator = app(ProjectFinancialCalculator::class);
        $product = $parent->product;
        $priceResult = $pricingService->resolvePrice($product, $customer, $project);

        if ($priceResult['source'] === 'unpriced' || bccomp((string) $priceResult['sale_price'], '0', 4) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'O produto "' . $product->name . '" nao tem preco configurado para o cliente "' . ($customer->trade_name ?: $customer->name) . '".',
            ], 422);
        }

        $qty = (string) $newQuantity;
        $unitPrice = $priceResult['sale_price'];
        $gross = bcmul($qty, $unitPrice, 8);
        $financial = $project ? $calculator->calculate($project, $gross) : [
            'total_fee' => '0',
            'net' => $gross,
            'admin_fee_percentage_eff' => '0',
        ];

        $oldOrganizationId = $distribution->customer?->organization_id;

        $distribution->update([
            'customer_id' => (int) $customer->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'cost_price_used' => $priceResult['cost_price'],
            'admin_fee_percentage' => $financial['admin_fee_percentage_eff'],
            'admin_fee_amount' => $financial['total_fee'],
            'net_value' => $financial['net'],
            'price_table_id' => $priceResult['price_table_id'],
            'price_source' => $priceResult['source'],
        ]);

        if ($receipt && ! $receipt->isLocked()) {
            $receiptDistributions = ProductionDelivery::where('tenant_id', $tenantId)
                ->where('associate_receipt_id', $receipt->id)
                ->whereNotNull('parent_delivery_id')
                ->get();

            if ($receiptDistributions->isNotEmpty() && $project) {
                $snapshot = app(\App\Services\AssociateReceiptService::class)
                    ->computeSnapshot($receiptDistributions, $project);

                $receipt->update([
                    'total_gross' => $snapshot['total_gross'],
                    'total_fees' => $snapshot['total_fees'],
                    'total_net' => $snapshot['total_net'],
                    'fee_snapshot' => $snapshot['fee_snapshot'],
                    'status' => ReceiptStatus::OBSOLETE->value,
                ]);
            } else {
                $receipt->update(['status' => ReceiptStatus::OBSOLETE->value]);
            }
        }

        collect([$oldOrganizationId, $customer->organization_id])
            ->filter()
            ->unique()
            ->each(function (int $organizationId) use ($requestFulfillment, $tenantId, $project) {
                if ($project) {
                    $requestFulfillment->updateProjectOrganizationStatuses((int) $tenantId, (int) $project->id, $organizationId);
                }
            });

        $distTotal = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $parent->id)
            ->sum('quantity');
        $distNetTotal = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $parent->id)
            ->sum('net_value');

        return response()->json([
            'success' => true,
            'message' => 'Distribuicao atualizada.',
            'delivery_id' => $parent->id,
            'parent_delivery_id' => $parent->id,
            'distribution' => [
                'id' => $distribution->id,
                'customer_id' => $customer->id,
                'customer' => $customer->trade_name ?: $customer->name,
                'qty' => (float) $distribution->quantity,
                'net' => (float) $distribution->net_value,
                'billed' => false,
                'paid' => false,
                'in_receipt' => (bool) $distribution->associate_receipt_id,
                'receipt_id' => $distribution->associate_receipt_id,
                'receipt_number' => $receipt?->formatted_number,
                'billing_receipt_id' => $distribution->billing_receipt_id,
                'billing_status' => $distribution->billing_status?->value,
                'locked' => false,
            ],
            'dist_total_qty' => (float) $distTotal,
            'dist_total_net' => (float) $distNetTotal,
        ]);
    }

    /**
     * Delete a pending delivery (only PENDING, only same session owner)
     */
    public function deleteDelivery(Request $request)
    {
        $deliveryId = (int) $request->route('delivery');
        $tenantId   = session('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $delivery = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('received_by', Auth::id())
            ->with('distributions')
            ->findOrFail($deliveryId);

        if ($delivery->parent_delivery_id) {
            return response()->json([
                'success' => false,
                'message' => 'Use a acao de remover distribuicao para excluir um destino especifico.',
            ], 422);
        }

        // Pending/rejected can always be deleted; approved requires explicit confirmation
        // (frontend sends 'force' flag for approved deliveries)
        $allowedStatuses = [DeliveryStatus::PENDING, DeliveryStatus::REJECTED, DeliveryStatus::APPROVED];
        if (! in_array($delivery->status, $allowedStatuses)) {
            return response()->json(['success' => false, 'message' => 'Esta entrega não pode ser excluída.'], 400);
        }

        $blockedDistribution = $delivery->distributions->first(function (ProductionDelivery $distribution) {
            return $distribution->paid
                || $distribution->billing_status !== BillingStatus::UNBILLED
                || $distribution->associate_receipt_id
                || $distribution->billing_receipt_id;
        });

        if ($blockedDistribution) {
            return response()->json([
                'success' => false,
                'message' => 'Esta entrega nao pode ser excluida porque possui distribuicoes vinculadas a comprovantes, faturamento ou pagamento.',
            ], 422);
        }

        // Also delete child distributions
        ProductionDelivery::where('tenant_id', $tenantId)
            ->where('parent_delivery_id', $deliveryId)
            ->delete();

        $delivery->delete();

        return response()->json(['success' => true, 'message' => 'Entrega excluída.']);
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
                        ->whereNull('parent_delivery_id')   // apenas recepções, não distribuições
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
                        // Preço depende do cliente; será resolvido por PricingService na distribuição
                        'unit_price' => 0.0,
                        'admin_fee_percentage' => (float) ($project->admin_fee_percentage ?? 10),
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
                    ->whereNull('parent_delivery_id')   // apenas recepções, não distribuições
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
                    'admin_fee_percentage' => (float) ($project->admin_fee_percentage ?? 10),
                    'is_free' => false,
                ];
            });

        return response()->json($demands);
    }

    /**
     * Get all reception deliveries (parent_delivery_id IS NULL) for a project — used by
     * the register page to show full persistent history instead of localStorage-only.
     */
    public function getProjectDeliveries()
    {
        $projectId = (int) request()->route('project');
        $tenantId  = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)
            ->with('customers:id')
            ->find($projectId);
        if (! $project) {
            return response()->json(['error' => 'Projeto não encontrado'], 404);
        }

        $projectCustomerIds = $project->customers->pluck('id')->values()->all();

        $deliveries = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->whereNull('parent_delivery_id')
            ->with(['associate.user', 'projectDemand.product', 'product', 'distributions.customer.organization', 'distributions.associateReceipt'])
            ->orderBy('delivery_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($d) use ($projectCustomerIds) {
                $productName = $d->projectDemand?->product?->name ?? $d->product?->name ?? '-';
                $productUnit = $d->projectDemand?->product?->unit ?? $d->product?->unit ?? 'un';
                $associateName = $d->associate?->user?->name ?? $d->associate?->name ?? '—';
                $associateId   = $d->associate_id;

                $distributions = $d->distributions->map(fn($dist) => [
                    'id'               => $dist->id,
                    'customer_id'      => $dist->customer_id,
                    'customer'         => optional($dist->customer)->trade_name ?? optional($dist->customer)->name ?? '?',
                    'organization'     => optional($dist->customer?->organization)->short_name
                                        ?? optional($dist->customer?->organization)->name,
                    'qty'              => (float) $dist->quantity,
                    'net'              => (float) $dist->net_value,
                    'price_source'     => $dist->price_source,
                    'billed'           => $dist->billing_status instanceof BillingStatus
                                         && $dist->billing_status !== BillingStatus::UNBILLED,
                    'paid'             => (bool) $dist->paid || $dist->billing_status === BillingStatus::PAID,
                    'billing_status'   => $dist->billing_status?->value,
                    'in_receipt'       => (bool) $dist->associate_receipt_id,
                    'receipt_id'       => $dist->associate_receipt_id,
                    'receipt_number'   => $dist->associateReceipt?->formatted_number,
                    'billing_receipt_id' => $dist->billing_receipt_id,
                    'locked'           => (bool) $dist->paid
                        || $dist->billing_status !== BillingStatus::UNBILLED
                        || (bool) $dist->billing_receipt_id
                        || ($dist->associateReceipt?->isLocked() ?? false),
                ]);

                $hasBilled = $distributions->contains('billed', true);
                $issueCount = 0;
                $issueSeverity = null;

                if ($d->status === DeliveryStatus::APPROVED) {
                    if ((float) $distributions->sum('qty') <= 0 || (float) $distributions->sum('qty') + 0.0005 < (float) $d->quantity) {
                        $issueCount++;
                        $issueSeverity = 'warning';
                    }

                    if ((float) $distributions->sum('qty') > (float) $d->quantity + 0.0005) {
                        $issueCount++;
                        $issueSeverity = 'critical';
                    }
                }

                foreach ($d->distributions as $dist) {
                    if (! $dist->customer_id || (float) ($dist->unit_price ?? 0) <= 0 || (float) ($dist->gross_value ?? 0) <= 0) {
                        $issueCount++;
                        $issueSeverity = 'critical';
                    }
                }

                return [
                    'id'             => $d->id,
                    'projectId'      => $d->sales_project_id,
                    'associateId'    => $associateId,
                    'productName'    => $productName,
                    'productUnit'    => $productUnit,
                    'associateName'  => $associateName,
                    'qty'            => (float) $d->quantity,
                    'date'           => $d->delivery_date?->format('Y-m-d') ?? '',
                    'quality'        => $d->quality_grade ?? '',
                    'status'         => $d->status?->value ?? 'pending',
                    'distributedQty' => (float) $distributions->sum('qty'),
                    'distributions'  => $distributions->values()->all(),
                    'has_billed'     => $hasBilled,
                    'issue_count'    => $issueCount,
                    'issue_severity' => $issueSeverity,
                    'customerIds'     => $projectCustomerIds,
                ];
            });

        return response()->json($deliveries);
    }

    /**
     * Get associate deliveries for a project
     */
    public function getAssociateDeliveries()
    {
        $projectId   = (int) request()->route('project');
        $associateId = (int) request()->route('associate');
        $tenantId    = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Tenant não encontrado'], 403);
        }

        $fromDate     = request()->query('from_date');
        $toDate       = request()->query('to_date');
        $approvedOnly = (bool) request()->query('approved_only', false);

        // SOMENTE distribuições (parent_delivery_id NOT NULL) — verdade financeira
        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->whereNotNull('parent_delivery_id')
            ->with(['product', 'customer', 'parentDelivery.projectDemand.product'])
            ->orderBy('delivery_date', 'asc');

        if ($approvedOnly) {
            $query->where('status', DeliveryStatus::APPROVED);

            // Excluir distribuições já pagas — via fluxo legado (paid=true)
            // ou via novo fluxo (associado a comprovante PAGO)
            $query->where('paid', false)
                  ->where('billing_status', '!=', BillingStatus::PAID->value)
                  ->whereNull('associate_receipt_id');
        }
        if ($fromDate) {
            $query->whereDate('delivery_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('delivery_date', '<=', $toDate);
        }

        $deliveries = $query->get()->map(function ($delivery) {
            // Nome do produto: via demanda da recepção pai, ou diretamente
            $productName = $delivery->parentDelivery?->projectDemand?->product?->name
                ?? $delivery->product?->name
                ?? '-';
            $unit = $delivery->parentDelivery?->projectDemand?->product?->unit
                ?? $delivery->product?->unit
                ?? 'un';
            $customerName = optional($delivery->customer)->trade_name
                ?? optional($delivery->customer)->name
                ?? '—';

            return [
                'id'              => $delivery->id,
                'product_name'    => $productName,
                'customer_name'   => $customerName,
                'delivery_date'   => $delivery->delivery_date?->format('d/m/Y') ?? '-',
                'delivery_date_raw' => $delivery->delivery_date?->format('Y-m-d') ?? '',
                'quantity'        => (float) $delivery->quantity,
                'unit'            => $unit,
                'gross_value'     => (float) $delivery->gross_value,
                'net_value'       => (float) $delivery->net_value,
                'status'          => $delivery->status->getLabel(),
                'status_value'    => $delivery->status->value,
            ];
        });

        return response()->json($deliveries);
    }

    /**
     * Editar entrega aprovada que ainda não foi entregue ao cliente final.
     */
    public function updateDelivery(Request $request)
    {
        $deliveryId = (int) $request->route('delivery');
        $tenantId   = session('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $delivery = ProductionDelivery::where('tenant_id', $tenantId)
            ->with('salesProject')
            ->findOrFail($deliveryId);

        if (! in_array($delivery->status, [DeliveryStatus::PENDING, DeliveryStatus::APPROVED])) {
            return response()->json(['success' => false, 'message' => 'Apenas entregas pendentes ou aprovadas podem ser editadas.'], 400);
        }

        if ($delivery->salesProject && ! $delivery->salesProject->status?->allowsFinancial()) {
            return response()->json(['success' => false, 'message' => 'Não é possível editar entregas de um projeto que não está em fase financeira ativa.'], 400);
        }

        $validated = $request->validate([
            'delivery_date' => 'required|date',
            'quantity'      => 'required|numeric|min:0.001',
            'unit_price'    => 'nullable|numeric|min:0',
            'quality_grade' => 'nullable|string|max:50',
            'notes'         => 'nullable|string|max:1000',
        ]);

        if ($delivery->parent_delivery_id && (
            $delivery->paid
            || $delivery->billing_status !== BillingStatus::UNBILLED
            || $delivery->associate_receipt_id
            || $delivery->billing_receipt_id
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Esta distribuicao nao pode ser alterada porque ja esta em comprovante, faturada ou paga.',
            ], 422);
        }

        if (! $delivery->parent_delivery_id) {
            $distributedQuantity = (float) ProductionDelivery::where('tenant_id', $tenantId)
                ->where('parent_delivery_id', $delivery->id)
                ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
                ->sum('quantity');

            if ((float) $validated['quantity'] + 0.0001 < $distributedQuantity) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf(
                        'Nao e possivel reduzir esta entrega para %.4f, pois ja existem %.4f distribuidos. Ajuste as distribuicoes antes de alterar a entrega.',
                        (float) $validated['quantity'],
                        $distributedQuantity
                    ),
                ], 422);
            }
        }

        $delivery->update([
            'delivery_date' => $validated['delivery_date'],
            'quantity'      => $validated['quantity'],
            'unit_price'    => $validated['unit_price'] ?? $delivery->unit_price,
            'quality_grade' => $validated['quality_grade'] ?? null,
            'notes'         => $validated['notes'] ?? null,
        ]);

        $delivery->refresh();

        return response()->json([
            'success'  => true,
            'message'  => 'Entrega atualizada com sucesso.',
            'delivery' => [
                'id'            => $delivery->id,
                'delivery_date' => $delivery->delivery_date->format('d/m/Y'),
                'quantity'      => (float) $delivery->quantity,
                'net_value'     => (float) $delivery->net_value,
                'quality_grade' => $delivery->quality_grade,
            ],
        ]);
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

            // Validação: participante restrito
            if ($project->restrict_participants) {
                $isAllowed = \App\Models\ProjectAssociate::where('sales_project_id', $project->id)
                    ->where('associate_id', $validated['associate_id'])
                    ->exists();
                if (! $isAllowed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este associado não está na lista de participantes deste projeto.',
                    ], 422);
                }
            }

            // Validação: limite de faturamento por associado
            if ($project->max_total_value_per_associate) {
                $accumulated = (float) \App\Models\ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('sales_project_id', $project->id)
                    ->where('associate_id', $validated['associate_id'])
                    ->whereNull('parent_delivery_id')
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->selectRaw('SUM(quantity * unit_price) as total')
                    ->value('total');

                // Calcular o valor estimado da nova entrega
                $newEntryPrice = 0.0;
                if (! $project->allow_any_product && ! empty($validated['project_demand_id'])) {
                    $tempDemand = \App\Models\ProjectDemand::find($validated['project_demand_id']);
                    $newEntryPrice = $tempDemand ? (float) $tempDemand->unit_price : 0.0;
                } elseif (! empty($validated['product_id'])) {
                    // Projetos livres: recepções têm unit_price=0 (preço ao cliente via PricingService)
                    $newEntryPrice = 0.0;
                }

                $newEntryValue = (float) $validated['quantity'] * $newEntryPrice;
                $limit = (float) $project->max_total_value_per_associate;

                if (($accumulated + $newEntryValue) > $limit) {
                    $remaining = max(0, $limit - $accumulated);
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'Limite máximo de faturamento atingido para este projeto. Limite: R$ %s | Já entregue: R$ %s | Disponível: R$ %s.',
                            number_format($limit, 2, ',', '.'),
                            number_format($accumulated, 2, ',', '.'),
                            number_format($remaining, 2, ',', '.')
                        ),
                    ], 422);
                }
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
                // Entrega avulsa: sem projeto (preço ao associado não rastreado; preço ao cliente via distribuição)
                $product = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
                $unitPrice = 0.0;
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
                // Projeto livre: preço ao associado não rastreado; preço ao cliente via PricingService na distribuição
                $product = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
                $unitPrice = 0.0;
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

            // Validação: limite de quantidade por produto/associado no projeto
            if (! $isStandalone && $project && $productId) {
                $productLimit = \App\Models\ProjectAssociateProductLimit::where('sales_project_id', $project->id)
                    ->where('associate_id', $validated['associate_id'])
                    ->where('product_id', $productId)
                    ->first();

                if ($productLimit) {
                    $accumulatedQty = (float) ProductionDelivery::where('tenant_id', $tenantId)
                        ->where('sales_project_id', $project->id)
                        ->where('associate_id', $validated['associate_id'])
                        ->where('product_id', $productId)
                        ->whereNull('parent_delivery_id')
                        ->whereNotIn('status', ['cancelled', 'rejected'])
                        ->sum('quantity');

                    $maxQty = (float) $productLimit->max_quantity;
                    if (($accumulatedQty + $quantity) > $maxQty) {
                        DB::rollBack();
                        $remaining = max(0, $maxQty - $accumulatedQty);
                        return response()->json([
                            'success' => false,
                            'message' => sprintf(
                                'Limite de quantidade por produto atingido para este associado. Limite: %s | Já entregue: %s | Disponível: %s.',
                                number_format($maxQty, 3, ',', '.'),
                                number_format($accumulatedQty, 3, ',', '.'),
                                number_format($remaining, 3, ',', '.')
                            ),
                        ], 422);
                    }
                }
            }

            $grossValue = bcmul((string) $quantity, (string) $unitPrice, 8);

            if ($isStandalone || ! $project) {
                $adminFeeAmount = '0';
                $netValue       = $grossValue;
            } else {
                $calculator     = app(ProjectFinancialCalculator::class);
                $financial      = $calculator->calculate($project, $grossValue);
                $adminFeeAmount = $financial['total_fee'];
                $netValue       = $financial['net'];
            }

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
                'success'  => true,
                'message'  => 'Entrega registrada com sucesso!',
                'delivery' => [
                    'id'        => $delivery->id,
                    'quantity'  => (float) $delivery->quantity,
                    'net_value' => (float) $delivery->net_value,
                    'status'    => $delivery->status->getLabel(),
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
            ->whereNull('parent_delivery_id')
            ->with(['associate.user', 'projectDemand.product', 'product', 'distributions.customer', 'distributions.associateReceipt'])
            ->orderBy('delivery_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($delivery) => $this->deliveryViewData($delivery));

        // Show only the project's additional customers in the distribution modal.
        // Fall back to all tenant customers when no participants are configured.
        $project->loadMissing(['customers']);
        $projectCustomers = $project->customers;

        if ($projectCustomers->isNotEmpty()) {
            $customers = $projectCustomers->sortBy('name')->values();
        } else {
            $customers = Customer::where('tenant_id', $tenantId)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'trade_name']);
        }

        $currentTenant = $this->currentTenant();
        $integrity = app(DeliveryProjectIntegrityService::class)->inspect((int) $tenantId, $project);

        return view('delivery.project-deliveries', compact('project', 'deliveries', 'currentTenant', 'customers', 'integrity'));
    }

    public function projectDeliveryFragment()
    {
        $projectId = (int) request()->route('project');
        $deliveryId = (int) request()->route('delivery');
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $project = SalesProject::where('tenant_id', $tenantId)
            ->with('customers')
            ->findOrFail($projectId);

        $deliveryModel = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->whereNull('parent_delivery_id')
            ->with(['associate.user', 'projectDemand.product', 'product', 'distributions.customer', 'distributions.associateReceipt'])
            ->findOrFail($deliveryId);

        $projectCustomers = $project->customers;
        if ($projectCustomers->isNotEmpty()) {
            $customers = $projectCustomers->sortBy('name')->values();
        } else {
            $customers = Customer::where('tenant_id', $tenantId)
                ->where('status', true)
                ->orderBy('name')
                ->get(['id', 'name', 'trade_name']);
        }

        $delivery = $this->deliveryViewData($deliveryModel);

        return response()->json([
            'success' => true,
            'delivery_id' => $deliveryModel->id,
            'desktop' => view('delivery.partials.project-delivery-row', compact('delivery', 'customers'))->render(),
            'mobile' => view('delivery.partials.project-delivery-mobile-card', compact('delivery', 'customers'))->render(),
        ]);
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

        $project->update(['status' => ProjectStatus::DELIVERIES_CLOSED]);

        return response()->json([
            'success' => true,
            'message' => 'Entregas encerradas! O projeto não aceita mais novas recepções de associados.',
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

        // deliverToClient foi removido do fluxo operacional.
        // Saídas de estoque devem ser registradas diretamente pelo módulo de estoque.
        return response()->json([
            'success' => false,
            'message' => 'Esta ação foi desativada. Utilize o módulo de Comprovantes de Clientes para faturar distribuições.',
        ], 410);
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

        // Agrupa apenas RECEPÇÕES aprovadas por produto (parent_delivery_id IS NULL)
        // para evitar duplicidade com as distribuições que derivam das mesmas recepções
        $approvedByProduct = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNull('parent_delivery_id')
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

    /**
     * Lista todos os projetos do tenant (qualquer status), incluindo finalizados.
     * Apenas visualização — não permite editar projetos finalizados.
     */
    public function projectsList()
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $tenant = $this->currentTenant();

        $query = SalesProject::where('tenant_id', $tenantId)
            ->with('customer')
            ->withCount([
                'deliveries as deliveries_approved_count' => fn ($q) => $q
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED),
            ])
            ->withSum(
                ['deliveries as net_total' => fn ($q) => $q
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED)],
                'net_value'
            )
            ->orderByDesc('created_at');

        if ($statusFilter = request('status')) {
            $query->where('status', $statusFilter);
        }

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('contract_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%"));
            });
        }

        $projects = $query->paginate(20)->appends(request()->query());

        return view('delivery.projects-list', compact('projects', 'tenant'));
    }

    public function allDeliveries()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $currentTenant = $this->currentTenant();

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNull('parent_delivery_id')  // exclude distribution children
            ->with(['salesProject', 'associate.user', 'product', 'receiver', 'approver', 'distributions.customer']);

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

        // Resumo de estoque por produto (recepções aprovadas — parent_delivery_id IS NULL)
        $stockSummary = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNull('parent_delivery_id')
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
            'total' => ProductionDelivery::where('tenant_id', $tenantId)->whereNull('parent_delivery_id')->count(),
            'pending' => ProductionDelivery::where('tenant_id', $tenantId)->whereNull('parent_delivery_id')->where('status', DeliveryStatus::PENDING)->count(),
            'approved' => ProductionDelivery::where('tenant_id', $tenantId)->whereNull('parent_delivery_id')->where('status', DeliveryStatus::APPROVED)->count(),
            'rejected' => ProductionDelivery::where('tenant_id', $tenantId)->whereNull('parent_delivery_id')->where('status', DeliveryStatus::REJECTED)->count(),
        ];

        $associates = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->whereHas('user', fn ($q) => $q->where('status', true))
            ->get()
            ->mapWithKeys(fn ($a) => [$a->id => $a->user->name ?? "#{$a->id}"]);

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->with('organization:id,name,short_name')
            ->get(['id', 'name', 'trade_name', 'organization_id']);

        $organizations = \App\Models\Organization::where('tenant_id', $tenantId)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('delivery.all-deliveries', compact('deliveries', 'projects', 'stockSummary', 'stats', 'currentTenant', 'statusFilter', 'projectFilter', 'dateFrom', 'dateTo', 'search', 'associates', 'customers', 'organizations'));
    }

    // ────────────────────────────────────────────────────────
    //  RELATÓRIOS PDF
    // ────────────────────────────────────────────────────────

    /**
     * Build a filtered query reusing the same filters from allDeliveries.
     */
    private function buildFilteredDeliveriesQuery(int $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        // Reports use DISTRIBUTIONS only: financial truth lives on child records (parent_delivery_id NOT NULL).
        // Receptions (parent_delivery_id IS NULL) have unit_price=0 and no customer — they are
        // the physical intake records, not the sales/financial records.
        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNotNull('parent_delivery_id')
            ->with(['salesProject', 'associate.user', 'product', 'customer.organization']);

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
        if ($organizationId = request('organization_id')) {
            $query->whereHas('customer', fn ($q) => $q->where('organization_id', (int) $organizationId));
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
                    ->orWhereHas('associate.user', fn ($aq) => $aq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', "%{$search}%")->orWhere('trade_name', 'like', "%{$search}%"));
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
        if ($oid = request('organization_id')) {
            $filters['organization'] = \App\Models\Organization::where('tenant_id', $tenantId)->find($oid)?->name ?? '';
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
            'delivery_date'    => $d->delivery_date?->format('d/m/Y') ?? '—',
            'project'          => $d->salesProject->title ?? 'Avulsa',
            'associate'        => $d->associate?->user?->name ?? '—',
            'product'          => $d->product?->name ?? '—',
            'unit'             => $d->product?->unit ?? 'un',
            'customer'         => $d->customer?->trade_name ?? $d->customer?->name ?? '—',
            'organization'     => $d->customer?->organization?->short_name ?? $d->customer?->organization?->name ?? '—',
            'quantity'         => (float) $d->quantity,
            'unit_price'       => (float) $d->unit_price,
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
     * PDF: Relatório completo de distribuições por cliente (uso interno).
     * Agrupa por cliente → produto, exibindo associado, quantidade e valor bruto.
     */
    public function reportDistributionsByCustomer()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(403);
        }

        $tenant  = $this->currentTenant();
        $filters = $this->resolveFilterLabels($tenantId);

        // Apenas distribuições (parent_delivery_id NOT NULL), sem REJECTED/CANCELLED
        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNotNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->with(['salesProject', 'associate.user', 'product', 'customer.organization']);

        if ($pid = request('project_id')) {
            $query->where('sales_project_id', $pid);
        }
        if ($dateFrom = request('date_from')) {
            $query->whereDate('delivery_date', '>=', $dateFrom);
        }
        if ($dateTo = request('date_to')) {
            $query->whereDate('delivery_date', '<=', $dateTo);
        }
        if ($cid = request('customer_id')) {
            $query->where('customer_id', (int) $cid);
        }

        $distributions = $query->orderBy('delivery_date')->orderByDesc('id')->get();

        // Agrupar por organização → cliente → produto
        $groups = [];
        foreach ($distributions as $d) {
            $customerId       = $d->customer_id ?? 0;
            $customerName     = $d->customer?->trade_name ?? $d->customer?->name ?? 'Sem cliente';
            $organizationId   = $d->customer?->organization_id ?? 0;
            $organizationName = $d->customer?->organization?->name ?? 'Sem organização';
            $productId        = $d->product_id ?? 0;
            $productName      = $d->product?->name ?? 'Desconhecido';
            $unit             = $d->product?->unit ?? 'un';

            if (! isset($groups[$organizationId])) {
                $groups[$organizationId] = [
                    'organization_name' => $organizationName,
                    'customers'         => [],
                    'total_qty'         => 0.0,
                    'total_gross'       => 0.0,
                ];
            }
            if (! isset($groups[$organizationId]['customers'][$customerId])) {
                $groups[$organizationId]['customers'][$customerId] = [
                    'customer_name' => $customerName,
                    'products'      => [],
                    'total_qty'     => 0.0,
                    'total_gross'   => 0.0,
                ];
            }
            if (! isset($groups[$organizationId]['customers'][$customerId]['products'][$productId])) {
                $groups[$organizationId]['customers'][$customerId]['products'][$productId] = [
                    'product_name' => $productName,
                    'unit'         => $unit,
                    'rows'         => [],
                    'total_qty'    => 0.0,
                    'total_gross'  => 0.0,
                ];
            }

            $qty   = (float) $d->quantity;
            $gross = (float) $d->gross_value;
            $groups[$organizationId]['customers'][$customerId]['products'][$productId]['rows'][] = [
                'delivery_date' => $d->delivery_date?->format('d/m/Y') ?? '—',
                'associate'     => $d->associate?->user?->name ?? '—',
                'quantity'      => $qty,
                'unit_price'    => (float) $d->unit_price,
                'gross'         => $gross,
                'unit'          => $unit,
            ];
            $groups[$organizationId]['customers'][$customerId]['products'][$productId]['total_qty']   += $qty;
            $groups[$organizationId]['customers'][$customerId]['products'][$productId]['total_gross']  += $gross;
            $groups[$organizationId]['customers'][$customerId]['total_qty']   += $qty;
            $groups[$organizationId]['customers'][$customerId]['total_gross']  += $gross;
            $groups[$organizationId]['total_qty']   += $qty;
            $groups[$organizationId]['total_gross']  += $gross;
        }

        // Reindexar e ordenar: organização → clientes → produtos
        $groups = collect($groups)
            ->sortBy('organization_name')
            ->map(fn ($org) => array_merge($org, [
                'customers' => collect($org['customers'])
                    ->sortBy('customer_name')
                    ->map(fn ($c) => array_merge($c, [
                        'products' => collect($c['products'])->sortBy('product_name')->values()->all(),
                    ]))
                    ->values()->all(),
            ]))
            ->values()
            ->all();

        $totals = [
            'distributions_count' => $distributions->count(),
            'organizations_count' => count($groups),
            'customers_count'     => $distributions->pluck('customer_id')->unique()->count(),
            'total_qty'           => $distributions->sum('quantity'),
            'total_gross'         => $distributions->sum('gross_value'),
        ];

        $singleCustomer = ($totals['customers_count'] === 1)
            ? ($groups[0]['customers'][0]['customer_name'] ?? null)
            : null;
        $title = $singleCustomer
            ? 'Distribuições — ' . $singleCustomer
            : 'Relatório de Distribuições por Cliente';

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.distributions-by-customer', [
            'tenant'       => $tenant,
            'title'        => $title,
            'subtitle'     => isset($filters['project']) ? $filters['project'] : null,
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters'      => $filters,
            'groups'       => $groups,
            'totals'       => $totals,
        ], array_merge(
            $svc->systemPdfOptions('pdf.distributions-by-customer', $title),
            ['paper' => 'a4', 'orientation' => 'portrait']
        ));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'distribuicoes-por-cliente-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * PDF: Relatório compacto de distribuições por cliente (uso para cobrança).
     * Exibe produto, quantidade total e valor total — sem detalhes de associado.
     */
    public function reportDistributionsByCustomerCompact()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(403);
        }

        $tenant  = $this->currentTenant();
        $filters = $this->resolveFilterLabels($tenantId);

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNotNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->with(['product', 'customer.organization']);

        if ($pid = request('project_id')) {
            $query->where('sales_project_id', $pid);
        }
        if ($dateFrom = request('date_from')) {
            $query->whereDate('delivery_date', '>=', $dateFrom);
        }
        if ($dateTo = request('date_to')) {
            $query->whereDate('delivery_date', '<=', $dateTo);
        }
        if ($cid = request('customer_id')) {
            $query->where('customer_id', (int) $cid);
        }
        if ($oid = request('organization_id')) {
            $query->whereHas('customer', fn ($q) => $q->where('organization_id', (int) $oid));
        }

        $distributions = $query->orderBy('delivery_date')->get();

        // Agrupar por organização → cliente → produto (compacto: só totais)
        $groups = [];
        foreach ($distributions as $d) {
            $customerId       = $d->customer_id ?? 0;
            $customerName     = $d->customer?->trade_name ?? $d->customer?->name ?? 'Sem cliente';
            $organizationId   = $d->customer?->organization_id ?? 0;
            $organizationName = $d->customer?->organization?->name ?? 'Sem organização';
            $productId        = $d->product_id ?? 0;
            $productName      = $d->product?->name ?? 'Desconhecido';
            $unit             = $d->product?->unit ?? 'un';

            if (! isset($groups[$organizationId])) {
                $groups[$organizationId] = [
                    'organization_name' => $organizationName,
                    'customers'         => [],
                    'total_qty'         => 0.0,
                    'total_gross'       => 0.0,
                ];
            }
            if (! isset($groups[$organizationId]['customers'][$customerId])) {
                $groups[$organizationId]['customers'][$customerId] = [
                    'customer_name' => $customerName,
                    'products'      => [],
                    'total_qty'     => 0.0,
                    'total_gross'   => 0.0,
                ];
            }
            if (! isset($groups[$organizationId]['customers'][$customerId]['products'][$productId])) {
                $groups[$organizationId]['customers'][$customerId]['products'][$productId] = [
                    'product_name' => $productName,
                    'unit'         => $unit,
                    'total_qty'    => 0.0,
                    'total_gross'  => 0.0,
                ];
            }

            $qty   = (float) $d->quantity;
            $gross = (float) $d->gross_value;
            $groups[$organizationId]['customers'][$customerId]['products'][$productId]['total_qty']   += $qty;
            $groups[$organizationId]['customers'][$customerId]['products'][$productId]['total_gross']  += $gross;
            $groups[$organizationId]['customers'][$customerId]['total_qty']   += $qty;
            $groups[$organizationId]['customers'][$customerId]['total_gross']  += $gross;
            $groups[$organizationId]['total_qty']   += $qty;
            $groups[$organizationId]['total_gross']  += $gross;
        }

        $groups = collect($groups)
            ->sortBy('organization_name')
            ->map(fn ($org) => array_merge($org, [
                'customers' => collect($org['customers'])
                    ->sortBy('customer_name')
                    ->map(fn ($c) => array_merge($c, [
                        'products' => collect($c['products'])->sortBy('product_name')->values()->all(),
                    ]))
                    ->values()->all(),
            ]))
            ->values()
            ->all();

        $totals = [
            'organizations_count' => count($groups),
            'customers_count'     => $distributions->pluck('customer_id')->unique()->count(),
            'total_qty'           => $distributions->sum('quantity'),
            'total_gross'         => $distributions->sum('gross_value'),
        ];

        $isSingleCustomer = $totals['customers_count'] === 1;
        $title = $isSingleCustomer
            ? 'Resumo de Distribuições — ' . ($groups[0]['customers'][0]['customer_name'] ?? 'Cliente')
            : 'Relatório de Distribuições — Resumo por Organização/Cliente';

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.distributions-by-customer-compact', [
            'tenant'       => $tenant,
            'title'        => $title,
            'subtitle'     => isset($filters['project']) ? $filters['project'] : null,
            'generated_at' => now()->format('d/m/Y H:i'),
            'filters'      => $filters,
            'groups'       => $groups,
            'totals'       => $totals,
        ], array_merge(
            $svc->systemPdfOptions('pdf.distributions-by-customer-compact', $title),
            ['paper' => 'a4', 'orientation' => 'portrait']
        ));

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'distribuicoes-por-cliente-resumo-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * JSON: Retorna clientes disponíveis e datas de entrega sugeridas para o modal de seleção.
     */
    public function customerDeliveryOptions()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Não autenticado'], 403);
        }

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->whereNotNull('parent_delivery_id')
            ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
            ->with(['customer', 'salesProject'])
            ->select(['id', 'customer_id', 'sales_project_id', 'delivery_date']);

        if ($pid = request('project_id')) {
            $query->where('sales_project_id', (int) $pid);
        }

        $distributions = $query->orderBy('delivery_date')->get();

        // Clientes únicos
        $customers = $distributions
            ->filter(fn ($d) => $d->customer_id)
            ->groupBy('customer_id')
            ->map(fn ($items) => [
                'id'   => $items->first()->customer_id,
                'name' => $items->first()->customer?->trade_name ?? $items->first()->customer?->name ?? 'Sem nome',
            ])
            ->values()
            ->sortBy('name')
            ->values()
            ->all();

        // Agrupar datas únicas por proximidade (gap ≤ 2 dias = mesmo grupo)
        $sortedDates = $distributions
            ->filter(fn ($d) => $d->delivery_date)
            ->pluck('delivery_date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->unique()->sort()->values()->all();

        $dateGroups = [];
        $curr = null;
        foreach ($sortedDates as $ds) {
            if ($curr === null) {
                $curr = ['from' => $ds, 'to' => $ds, 'count' => 1];
            } else {
                $gap = (int) \Carbon\Carbon::parse($curr['to'])->diffInDays(\Carbon\Carbon::parse($ds));
                if ($gap <= 2) {
                    $curr['to'] = $ds;
                    $curr['count']++;
                } else {
                    $dateGroups[] = $curr;
                    $curr = ['from' => $ds, 'to' => $ds, 'count' => 1];
                }
            }
        }
        if ($curr !== null) $dateGroups[] = $curr;

        $dateGroups = array_map(function ($g) {
            $from = \Carbon\Carbon::parse($g['from']);
            $to   = \Carbon\Carbon::parse($g['to']);
            if ($g['from'] === $g['to']) {
                $label = $from->format('d/m/Y');
            } elseif ($from->format('m/Y') === $to->format('m/Y')) {
                $label = $from->format('d') . ' a ' . $to->format('d/m/Y');
            } else {
                $label = $from->format('d/m') . ' a ' . $to->format('d/m/Y');
            }
            return [
                'label'     => $label,
                'date_from' => $g['from'],
                'date_to'   => $g['to'],
                'count'     => $g['count'],
            ];
        }, $dateGroups);

        // Também meses (agrupamento secundário)
        $datesByMonth = $distributions
            ->filter(fn ($d) => $d->delivery_date)
            ->groupBy(fn ($d) => $d->delivery_date->format('Y-m'))
            ->map(fn ($items, $ym) => [
                'label'     => \Carbon\Carbon::parse($ym . '-01')->translatedFormat('F \d\e Y'),
                'date_from' => $items->min('delivery_date')->format('Y-m-d'),
                'date_to'   => $items->max('delivery_date')->format('Y-m-d'),
                'count'     => $items->count(),
            ])
            ->sortKeys()->values()->all();

        return response()->json([
            'customers'      => $customers,
            'date_groups'    => $dateGroups,
            'dates_by_month' => $datesByMonth,
            'all_dates'      => $sortedDates,
        ]);
    }

    /**
     * PDF: Relatório de entregas individual por cliente (Declaração/Extrato).
     * Um cliente por relatório, período selecionável. Layout B&W limpo.
     */
    public function reportCustomerDeliveryStatement()
{
    $tenantId = session('tenant_id');
    if (! $tenantId) abort(403);

    $customerId = (int) request('customer_id');
    if (! $customerId) abort(422, 'customer_id é obrigatório.');

    $tenant   = $this->currentTenant();
    $dateFrom = request('date_from');
    $dateTo   = request('date_to');
    $showUnitPrice = request('col_unit_price', '1') !== '0';
    $showTotal     = request('col_total', '1') !== '0';
    $projectId     = request('project_id');
    $layout        = request('layout', 'grouped'); // 'grouped', 'ungrouped', 'matrix'

    $query = ProductionDelivery::where('tenant_id', $tenantId)
        ->whereNotNull('parent_delivery_id')
        ->where('customer_id', $customerId)
        ->whereNotIn('status', [DeliveryStatus::REJECTED->value, DeliveryStatus::CANCELLED->value])
        ->with(['product', 'associate.user', 'salesProject', 'customer.organization'])
        ->orderBy('delivery_date')
        ->orderBy('id');

    if ($dateFrom) $query->whereDate('delivery_date', '>=', $dateFrom);
    if ($dateTo)   $query->whereDate('delivery_date', '<=', $dateTo);
    if ($projectId) $query->where('sales_project_id', $projectId);

    $distributions = $query->get();

    if ($distributions->isEmpty()) {
        abort(404, 'Nenhuma distribuição encontrada.');
    }

    $customer = $distributions->first()->customer;
    $organization = $customer?->organization;

    // Período formatado (igual ao original)
    $periodLabel = null;
    if ($dateFrom && $dateTo) {
        $periodLabel = \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') . ' a ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
    } elseif ($dateFrom) {
        $periodLabel = 'A partir de ' . \Carbon\Carbon::parse($dateFrom)->format('d/m/Y');
    } elseif ($dateTo) {
        $periodLabel = 'Até ' . \Carbon\Carbon::parse($dateTo)->format('d/m/Y');
    } else {
        $minDate = $distributions->min('delivery_date');
        $maxDate = $distributions->max('delivery_date');
        $periodLabel = $minDate && $maxDate ? $minDate->format('d/m/Y') . ' a ' . $maxDate->format('d/m/Y') : 'Todas as datas';
    }

    $totals = [
        'total_qty'   => $distributions->sum('quantity'),
        'total_gross' => $distributions->sum('gross_value'),
        'items_count' => $distributions->count(),
    ];

    // Projeto(s)
    $projectNames = $distributions->pluck('salesProject.title')->filter()->unique()->values()->all();
    $projectLabel = count($projectNames) === 1 ? $projectNames[0] : (count($projectNames) > 1 ? implode(', ', $projectNames) : null);

    $data = [
        'tenant'          => $tenant,
        'customer'        => $customer,
        'organization'    => $organization,
        'period_label'    => $periodLabel,
        'project_label'   => $projectLabel,
        'totals'          => $totals,
        'generated_at'    => now()->format('d/m/Y H:i'),
        'show_unit_price' => $showUnitPrice,
        'show_total'      => $showTotal,
        'layout'          => $layout,
    ];

    // Prepara dados conforme layout
    if ($layout === 'ungrouped') {
        $rows = [];
        foreach ($distributions as $d) {
            $rows[] = [
                'date'        => $d->delivery_date?->format('d/m/Y') ?? '—',
                'product_name'=> $d->product?->name ?? 'Desconhecido',
                'quantity'    => (float) $d->quantity,
                'unit_price'  => (float) $d->unit_price,
                'total'       => (float) $d->gross_value,
                'unit'        => $d->product?->unit ?? 'un',
            ];
        }
        $data['rows'] = $rows;
    }
    elseif ($layout === 'matrix') {
        // Matrix: produto x data (colunas dinâmicas)
        // Estrutura: $matrix[$productId]['product_name'] = ... , 'unit' => ... , 'dates' => [ 'data1' => qty, ... ]
        $matrix = [];
        $allDates = [];
        foreach ($distributions as $d) {
            $dateKey = $d->delivery_date?->format('Y-m-d');
            if (!$dateKey) continue;
            $allDates[$dateKey] = $d->delivery_date->format('d/m/Y'); // label

            $prodId = $d->product_id ?? 0;
            if (!isset($matrix[$prodId])) {
                $matrix[$prodId] = [
                    'product_name' => $d->product?->name ?? 'Desconhecido',
                    'unit'         => $d->product?->unit ?? 'un',
                    'dates'        => [],
                    'total_qty'    => 0.0,
                    'unit_price'   => (float) $d->unit_price, // assume mesmo preço por produto
                ];
            }
            $qty = (float) $d->quantity;
            $matrix[$prodId]['dates'][$dateKey] = ($matrix[$prodId]['dates'][$dateKey] ?? 0) + $qty;
            $matrix[$prodId]['total_qty'] += $qty;
        }
        // Ordenar datas
        ksort($allDates);
        $data['matrix'] = [
            'dates'   => $allDates,
            'products'=> $matrix,
        ];
    }
    else { // grouped padrão
        $productGroups = [];
        foreach ($distributions as $d) {
            $productId   = $d->product_id ?? 0;
            $productName = $d->product?->name ?? 'Desconhecido';
            $unit        = $d->product?->unit ?? 'un';
            if (!isset($productGroups[$productId])) {
                $productGroups[$productId] = [
                    'product_name' => $productName,
                    'unit'         => $unit,
                    'total_qty'    => 0.0,
                    'total_gross'  => 0.0,
                    'unit_price'   => (float) $d->unit_price,
                ];
            }
            $productGroups[$productId]['total_qty']   += (float) $d->quantity;
            $productGroups[$productId]['total_gross'] += (float) $d->gross_value;
        }
        $data['product_groups'] = collect($productGroups)->values()->all();
    }

    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.customer-delivery-statement', $data)
        ->setPaper('a4', $layout === 'matrix' ? 'landscape' : 'portrait');

    $filename = 'extrato-'
        . \Illuminate\Support\Str::slug($customer->trade_name ?? $customer->name ?? 'cliente')
        . '-' . now()->format('Y-m-d') . '.pdf';

    return response()->streamDownload(fn() => print $pdf->output(), $filename, ['Content-Type' => 'application/pdf']);
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

        // SOMENTE distribuições (parent_delivery_id NOT NULL) — verdade financeira
        $producers = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $project->id)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
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
     * Verifica se já existem comprovantes de um associado num projeto e retorna JSON.
     * Usado pelo modal de geração de comprovante no portal externo.
     */
    public function checkAssociateReceipt(Request $request)
    {
        $projectId   = (int) $request->route('project');
        $associateId = (int) $request->route('associate');
        $tenantId    = session('tenant_id');

        if (!$tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $receipts = \App\Models\AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->orderBy('id')
            ->get();

        $tenantSlug = $this->currentTenant()?->slug ?? '';

        $receiptData = $receipts->map(fn($r) => [
            'id'          => $r->id,
            'number'      => $r->formatted_number,
            'issued_at'   => $r->issued_at?->format('d/m/Y') ?? '—',
            'status'      => $r->status?->value ?? 'draft',
            'status_label'=> $r->status?->getLabel() ?? 'Rascunho',
            'total_net'   => $r->total_net ? number_format((float) $r->total_net, 2, ',', '.') : null,
            'is_paid'     => $r->status === \App\Enums\ReceiptStatus::PAID,
            'reprint_url' => route('delivery.projects.receipt-reprint', [
                'tenant'  => $tenantSlug,
                'project' => $projectId,
                'receipt' => $r->id,
            ]),
        ]);

        // Total de distribuições aprovadas NÃO pagas deste associado neste projeto
        $totalDist = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->where('paid', false)
            ->where('billing_status', '!=', BillingStatus::PAID->value)
            ->whereNull('associate_receipt_id')
            ->count();

        // Verificar distribuições não cobertas (só relevante quando múltiplos comprovantes)
        $uncoveredCount = 0;
        if ($receipts->count() > 1) {
            $coveredIds = $receipts
                ->flatMap(fn($r) => $r->delivery_ids ?? [])
                ->unique()
                ->map('intval')
                ->all();

            $uncoveredCount = ProductionDelivery::where('tenant_id', $tenantId)
                ->where('sales_project_id', $projectId)
                ->where('associate_id', $associateId)
                ->where('status', DeliveryStatus::APPROVED)
                ->whereNotNull('parent_delivery_id')
                ->whereNotIn('id', empty($coveredIds) ? [0] : $coveredIds)
                ->count();
        }

        $criticalIssues = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->where(function ($query) {
                $query->whereNull('customer_id')
                    ->orWhereNull('parent_delivery_id')
                    ->orWhereNull('unit_price')
                    ->orWhere('unit_price', '<=', 0)
                    ->orWhere('quantity', '<=', 0);
            })
            ->count();

        $project = SalesProject::where('tenant_id', $tenantId)->find($projectId);
        $issues = [];
        if ($project) {
            $associateParentIds = ProductionDelivery::where('tenant_id', $tenantId)
                ->where('sales_project_id', $projectId)
                ->where('associate_id', $associateId)
                ->whereNull('parent_delivery_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $inspection = app(DeliveryProjectIntegrityService::class)->inspect((int) $tenantId, $project);
            foreach (['critical', 'warning', 'info'] as $severity) {
                foreach (($inspection[$severity] ?? []) as $issue) {
                    $deliveryId = isset($issue['deliveryId']) ? (int) $issue['deliveryId'] : null;
                    if ($deliveryId && ! in_array($deliveryId, $associateParentIds, true)) {
                        continue;
                    }

                    $issues[] = array_merge($issue, ['severity' => $severity]);
                }
            }
        }

        return response()->json([
            'success'         => true,
            'has_receipts'    => $receipts->count() > 0,
            'receipt_count'   => $receipts->count(),
            'receipts'        => $receiptData->all(),
            'total_dist'      => $totalDist,
            'uncovered_count' => $uncoveredCount,
            'critical_issues' => $criticalIssues,
            'issues'          => $issues,
        ]);
    }

    /**
     * Gera e faz download do comprovante PDF de um produtor em um projeto (portal externo).
     */
    public function generateAssociateReceiptPdf()
    {
        $projectId   = (int) request()->route('project');
        $associateId = (int) request()->route('associate');
        $tenantId    = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $project   = SalesProject::where('tenant_id', $tenantId)->findOrFail($projectId);
        $associate = Associate::where('tenant_id', $tenantId)->with('user')->findOrFail($associateId);
        $tenant    = $this->currentTenant();

        // Comprovante usa DISTRIBUIÇÕES: verdade financeira (customer, price, net_value)
        $distributions = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associateId)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->whereNull('associate_receipt_id')
            ->with(['product', 'customer', 'parentDelivery'])
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get();

        if ($distributions->isEmpty()) {
            return redirect()->back()->with('error', 'Nenhuma distribuição aprovada encontrada para este produtor. Distribua as recepções antes de gerar o comprovante.');
        }

        if ($message = $this->receiptDistributionIntegrityMessage($distributions, (int) $tenantId, $projectId, $associateId)) {
            return redirect()->back()->with('error', $message);
        }

        // Criar novo comprovante apenas com distribuicoes pendentes.
        $year   = now()->year;
        $receipt = \App\Models\AssociateReceipt::create([
            'tenant_id'        => $tenantId,
            'sales_project_id' => $projectId,
            'associate_id'     => $associateId,
            'receipt_year'     => $year,
            'receipt_number'   => \App\Models\AssociateReceipt::nextNumber($tenantId, $year),
            'issued_at'        => today(),
            'delivery_ids'     => $distributions->pluck('id')->all(),
        ]);

        // Congelar snapshot financeiro (apenas se o comprovante ainda não foi pago)
        if (! $receipt->isLocked()) {
            app(\App\Services\AssociateReceiptService::class)
                ->freezeReceipt($receipt, $distributions, $project);
        }

        $receiptData = \App\Services\ReceiptDataBuilder::fromDeliveries($distributions, null, $project);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', [
            'tenant'          => $tenant,
            'project'         => $project,
            'associate'       => $associate,
            'receipt'         => $receipt,
            'summary'         => $receiptData['summary'],
            'productsSummary' => $receiptData['productsSummary'],
            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
            'feeBreakdown'    => $receiptData['feeBreakdown'],
        ])->setPaper('a4', 'portrait');

        $safeName     = \Illuminate\Support\Str::slug($associate->user->name ?? 'associado');
        $receiptLabel = str_replace('/', '-', $receipt->formatted_number);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "comprovante-{$receiptLabel}-{$safeName}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Gera comprovante PDF a partir de entregas selecionadas manualmente.
     * Aceita POST com array de IDs de entregas aprovadas (de um mesmo associado).
     */
    public function generateSelectedDeliveriesReceipt(Request $request)
    {
        $projectId = (int) $request->route('project');
        $tenantId  = session('tenant_id');

        if (!$tenantId) {
            return response()->json(['success' => false, 'message' => 'Sessão expirada.'], 403);
        }

        $deliveryIds = $request->input('delivery_ids', []);
        if (empty($deliveryIds) || !is_array($deliveryIds)) {
            return response()->json(['success' => false, 'message' => 'Selecione ao menos uma entrega.'], 422);
        }

        // Sanitizar IDs
        $deliveryIds = array_values(array_unique(array_map('intval', $deliveryIds)));

        $project = SalesProject::where('tenant_id', $tenantId)->findOrFail($projectId);
        $tenant  = $this->currentTenant();

        // Aceitar IDs de DISTRIBUIÇÕES diretamente (parent_delivery_id NOT NULL)
        $distributions = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED)
            ->whereIn('id', $deliveryIds)
            ->with(['product', 'customer', 'associate.user', 'parentDelivery'])
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get();

        if ($distributions->count() !== count($deliveryIds)) {
            return response()->json(['success' => false, 'message' => 'Nenhuma distribuição aprovada encontrada para os IDs selecionados.'], 422);
        }

        // Verificar que todas as distribuições pertencem ao mesmo associado
        $associateIds = $distributions->pluck('associate_id')->unique();
        if ($associateIds->count() > 1) {
            return response()->json(['success' => false, 'message' => 'Selecione distribuições de um mesmo produtor para gerar o comprovante.'], 422);
        }

        if ($message = $this->receiptDistributionIntegrityMessage($distributions, (int) $tenantId, $projectId, (int) $associateIds->first())) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        $associate = Associate::where('tenant_id', $tenantId)
            ->with('user')
            ->findOrFail($associateIds->first());

        // Criar novo comprovante armazenando os IDs das recepções originais selecionadas
        $year    = now()->year;
        $receipt = \App\Models\AssociateReceipt::create([
            'tenant_id'        => $tenantId,
            'sales_project_id' => $projectId,
            'associate_id'     => $associate->id,
            'receipt_year'     => $year,
            'receipt_number'   => \App\Models\AssociateReceipt::nextNumber($tenantId, $year),
            'issued_at'        => today(),
            'delivery_ids'     => $distributions->pluck('id')->all(),
        ]);

        // Congelar snapshot financeiro no comprovante e vincular distribuições
        app(\App\Services\AssociateReceiptService::class)
            ->freezeReceipt($receipt, $distributions, $project);

        // Verificar distribuições não cobertas pelos comprovantes deste associado/projeto
        $allReceipts = \App\Models\AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associate->id)
            ->get();

        $coveredIds = $allReceipts
            ->flatMap(fn($r) => $r->delivery_ids ?? [])
            ->unique()
            ->map('intval')
            ->all();

        $uncoveredCount = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associate->id)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->whereNotIn('id', empty($coveredIds) ? [0] : $coveredIds)
            ->count();

        $receiptData = \App\Services\ReceiptDataBuilder::fromDeliveries($distributions, null, $project);

        $visibleColumns = $request->input('visible_columns', ['unit_price', 'gross']);
        if (!is_array($visibleColumns)) {
            $visibleColumns = ['unit_price', 'gross'];
        }
        $allowedCols = ['unit_price', 'gross', 'admin_fee', 'net'];
        $visibleColumns = array_values(array_filter($visibleColumns, fn($c) => in_array($c, $allowedCols)));

        $pdf = Pdf::loadView('pdf.project-associate-receipt', [
            'tenant'          => $tenant,
            'project'         => $project,
            'associate'       => $associate,
            'receipt'         => $receipt,
            'summary'         => $receiptData['summary'],
            'productsSummary' => $receiptData['productsSummary'],
            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
            'feeBreakdown'    => $receiptData['feeBreakdown'],
            'visible_columns' => $visibleColumns,
        ])->setPaper('a4', 'portrait');

        $safeName     = \Illuminate\Support\Str::slug($associate->user->name ?? 'associado');
        $receiptLabel = str_replace('/', '-', $receipt->formatted_number);
        $filename     = "comprovante-{$receiptLabel}-{$safeName}-parcial.pdf";

        // Retornar Base64 para download via JS (POST não permite download direto)
        $base64 = base64_encode($pdf->output());

        $tenantSlug = $this->currentTenant()?->slug ?? '';

        return response()->json([
            'success'         => true,
            'filename'        => $filename,
            'pdf'             => $base64,
            'receipt_id'      => $receipt->id,
            'receipt_number'  => $receipt->formatted_number,
            'uncovered_count' => $uncoveredCount,
            'reprint_url'     => route('delivery.projects.receipt-reprint', [
                'tenant'  => $tenantSlug,
                'project' => $projectId,
                'receipt' => $receipt->id,
            ]),
        ]);
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
            ->whereNotNull('parent_delivery_id')
            ->whereNotNull('customer_id')
            ->with(['product', 'customer', 'parentDelivery'])
            ->orderBy('delivery_date')
            ->get();

        if ($deliveries->isEmpty()) {
            return back()->with('error', 'Nenhuma entrega aprovada encontrada para este associado neste projeto.');
        }

        if ($message = $this->receiptDistributionIntegrityMessage($deliveries, (int) $tenantId, (int) $projectId, (int) $associate->id)) {
            return back()->with('error', $message);
        }

        $receiptData = \App\Services\ReceiptDataBuilder::fromDeliveries($deliveries, null, $project);

        $svc = app(\App\Services\TemplatedPdfService::class);
        $pdf = $svc->generateSystemPdf('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'title' => 'Comprovante de Entrega',
            'subtitle' => $project->title,
            'generated_at' => now()->format('d/m/Y H:i'),
            'project' => $project,
            'associate' => $associate,
            'deliveries' => $deliveries,
            'summary' => $receiptData['summary'],
            'productsSummary' => $receiptData['productsSummary'],
            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
            'feeBreakdown' => $receiptData['feeBreakdown'],
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

    /**
     * Store multiple delivery entries (batch) for the same product/associate
     */
    public function storeBatch(Request $request)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['success' => false, 'message' => 'Selecione uma organização primeiro.'], 403);
        }

        $isStandalone = (bool) $request->input('is_standalone', false);

        $validated = $request->validate([
            'sales_project_id'         => $isStandalone ? 'nullable|exists:sales_projects,id' : 'required|exists:sales_projects,id',
            'project_demand_id'        => 'nullable|exists:project_demands,id',
            'product_id'               => 'nullable|exists:products,id',
            'associate_id'             => 'required|exists:associates,id',
            'entries'                  => 'required|array|min:1|max:50',
            'entries.*.delivery_date'  => 'required|date',
            'entries.*.quantity'       => 'required|numeric|min:0.001',
            'entries.*.quality_grade'  => 'nullable|string|max:50',
            'entries.*.notes'          => 'nullable|string|max:500',
        ]);

        $project = null;
        if (! $isStandalone) {
            $project = SalesProject::where('tenant_id', $tenantId)->find($validated['sales_project_id']);
            if (! $project) {
                return response()->json(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
            }
            if ($project->status !== ProjectStatus::ACTIVE) {
                return response()->json([
                    'success' => false,
                    'message' => 'O projeto precisa estar "Em Execução". Status atual: '.$project->status->getLabel(),
                ], 422);
            }
            if (! $project->allow_any_product && empty($validated['project_demand_id'])) {
                return response()->json(['success' => false, 'message' => 'Selecione o produto da demanda do projeto.'], 422);
            }
            if ($project->allow_any_product && empty($validated['product_id'])) {
                return response()->json(['success' => false, 'message' => 'Selecione o produto a ser entregue.'], 422);
            }

            // Validação: participante restrito (batch)
            if ($project->restrict_participants) {
                $isAllowed = \App\Models\ProjectAssociate::where('sales_project_id', $project->id)
                    ->where('associate_id', $validated['associate_id'])
                    ->exists();
                if (! $isAllowed) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este associado não está na lista de participantes deste projeto.',
                    ], 422);
                }
            }

            // Validação: limite de faturamento por associado (batch) — pré-check antes de iniciar transação
            if ($project->max_total_value_per_associate) {
                $accumulated = (float) ProductionDelivery::where('tenant_id', $tenantId)
                    ->where('sales_project_id', $project->id)
                    ->where('associate_id', $validated['associate_id'])
                    ->whereNull('parent_delivery_id')
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->selectRaw('SUM(quantity * unit_price) as total')
                    ->value('total');

                $batchTotalValue = collect($validated['entries'])->sum(fn ($e) => (float) $e['quantity']) * 0.0; // placeholder; preciso do unit_price
                // Preço será calculado mais adiante, então fazemos o check com estimativa zero
                // O check real será feito dentro da transação (abaixo), mas sinalizamos cedo para UX
                $limit = (float) $project->max_total_value_per_associate;
                if ($accumulated >= $limit) {
                    return response()->json([
                        'success' => false,
                        'message' => sprintf(
                            'Limite máximo de faturamento atingido para este projeto. Limite: R$ %s | Já entregue: R$ %s.',
                            number_format($limit, 2, ',', '.'),
                            number_format($accumulated, 2, ',', '.')
                        ),
                    ], 422);
                }
            }
        } else {
            if (empty($validated['product_id'])) {
                return response()->json(['success' => false, 'message' => 'Selecione o produto a ser entregue.'], 422);
            }
        }

        try {
            DB::beginTransaction();

            $unitPrice      = 0.0;
            $productId      = null;
            $demandId       = null;
            $adminFeePercent = 0.0;

            if ($isStandalone) {
                $product        = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
                $unitPrice      = 0.0; // Preço ao associado não rastreado; preço ao cliente via PricingService
                $productId      = $product->id;
                $adminFeePercent = 0.0;
            } elseif (! $project->allow_any_product) {
                $demand         = ProjectDemand::where('tenant_id', $tenantId)->findOrFail($validated['project_demand_id']);
                $unitPrice      = (float) $demand->unit_price;
                $productId      = $demand->product_id;
                $demandId       = $demand->id;
                $adminFeePercent = (float) ($project->admin_fee_percentage ?? 10);
            } else {
                $product        = Product::where('tenant_id', $tenantId)->findOrFail($validated['product_id']);
                $unitPrice      = 0.0; // Preço ao associado não rastreado; preço ao cliente via PricingService
                $productId      = $product->id;
                $adminFeePercent = (float) ($project->admin_fee_percentage ?? 10);
            }

            $created = [];

            // Pré-carregar limite por produto/associado para validação no loop
            $productLimit = null;
            if (! $isStandalone && $project && $productId) {
                $productLimit = \App\Models\ProjectAssociateProductLimit::where('sales_project_id', $project->id)
                    ->where('associate_id', $validated['associate_id'])
                    ->where('product_id', $productId)
                    ->first();
            }

            $accumulatedQtyInSession = 0.0; // rastreia quantidade acumulada durante o batch
            $accumulatedValueInSession = 0.0;

            // Qty já acumulada antes deste batch (para limites)
            $preBatchQty = $productLimit ? (float) ProductionDelivery::where('tenant_id', $tenantId)
                ->where('sales_project_id', $project->id)
                ->where('associate_id', $validated['associate_id'])
                ->where('product_id', $productId)
                ->whereNull('parent_delivery_id')
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->sum('quantity') : 0.0;

            $preBatchValue = ($project && $project->max_total_value_per_associate) ? (float) ProductionDelivery::where('tenant_id', $tenantId)
                ->where('sales_project_id', $project->id)
                ->where('associate_id', $validated['associate_id'])
                ->whereNull('parent_delivery_id')
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->selectRaw('SUM(quantity * unit_price) as total')
                ->value('total') : 0.0;

            foreach ($validated['entries'] as $entry) {
                $quantity       = (float) $entry['quantity'];
                $grossValue     = $quantity * $unitPrice;

                // Validação intra-batch: limite de faturamento por associado
                if ($project && $project->max_total_value_per_associate) {
                    $limit = (float) $project->max_total_value_per_associate;
                    if (($preBatchValue + $accumulatedValueInSession + $grossValue) > $limit) {
                        DB::rollBack();
                        $remaining = max(0, $limit - $preBatchValue - $accumulatedValueInSession);
                        return response()->json([
                            'success' => false,
                            'message' => sprintf(
                                'Limite máximo de faturamento atingido para este projeto. Disponível: R$ %s.',
                                number_format($remaining, 2, ',', '.')
                            ),
                        ], 422);
                    }
                }

                // Validação intra-batch: limite de quantidade por produto
                if ($productLimit) {
                    $maxQty = (float) $productLimit->max_quantity;
                    if (($preBatchQty + $accumulatedQtyInSession + $quantity) > $maxQty) {
                        DB::rollBack();
                        $remaining = max(0, $maxQty - $preBatchQty - $accumulatedQtyInSession);
                        return response()->json([
                            'success' => false,
                            'message' => sprintf(
                                'Limite de quantidade por produto atingido. Disponível: %s.',
                                number_format($remaining, 3, ',', '.')
                            ),
                        ], 422);
                    }
                }

                $grossValueEntry = bcmul((string) $quantity, (string) $unitPrice, 8);

                if ($isStandalone || ! $project) {
                    $adminFeeAmountEntry = '0';
                    $netValueEntry       = $grossValueEntry;
                } else {
                    if (! isset($calculatorBatch)) {
                        $calculatorBatch = app(ProjectFinancialCalculator::class);
                    }
                    $finEntry            = $calculatorBatch->calculate($project, $grossValueEntry);
                    $adminFeeAmountEntry = $finEntry['total_fee'];
                    $netValueEntry       = $finEntry['net'];
                }

                $delivery = ProductionDelivery::create([
                    'tenant_id'         => $tenantId,
                    'sales_project_id'  => $isStandalone ? null : $validated['sales_project_id'],
                    'project_demand_id' => $demandId,
                    'associate_id'      => $validated['associate_id'],
                    'product_id'        => $productId,
                    'delivery_date'     => $entry['delivery_date'],
                    'quantity'          => $quantity,
                    'unit_price'        => $unitPrice,
                    'admin_fee_amount'  => $adminFeeAmountEntry,
                    'net_value'         => $netValueEntry,
                    'status'            => DeliveryStatus::PENDING,
                    'quality_grade'     => $entry['quality_grade'] ?? null,
                    'notes'             => $entry['notes'] ?? null,
                    'received_by'       => Auth::id(),
                    'paid'              => false,
                ]);

                $accumulatedQtyInSession   += $quantity;
                $accumulatedValueInSession += $grossValue;

                $created[] = [
                    'id'         => $delivery->id,
                    'date'       => $entry['delivery_date'],
                    'quantity'   => $quantity,
                    'net_value'  => $netValue,
                ];
            }

            DB::commit();

            $count = count($created);

            return response()->json([
                'success'    => true,
                'message'    => $count.' entrega'.($count > 1 ? 's registradas' : ' registrada').' com sucesso!',
                'count'      => $count,
                'deliveries' => $created,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Erro ao registrar: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reimprime o PDF de um comprovante já salvo, utilizando os delivery_ids armazenados.
     */
    public function reprintReceipt(Request $request)
    {
        $projectId = (int) $request->route('project');
        $receiptId = (int) $request->route('receipt');
        $tenantId  = session('tenant_id');

        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Sessão expirada.');
        }

        $receipt = \App\Models\AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->findOrFail($receiptId);

        $project   = SalesProject::where('tenant_id', $tenantId)->findOrFail($projectId);
        $associate = Associate::where('tenant_id', $tenantId)->with('user')->findOrFail($receipt->associate_id);
        $tenant    = $this->currentTenant();

        // Reimpressao deve reproduzir somente as distribuicoes vinculadas a este comprovante.
        $storedIds = collect($receipt->delivery_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $query = ProductionDelivery::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->where('associate_id', $associate->id)
            ->where('status', DeliveryStatus::APPROVED)
            ->whereNotNull('parent_delivery_id')
            ->with(['product', 'customer', 'parentDelivery'])
            ->orderBy('delivery_date');

        if (!empty($storedIds)) {
            $query->whereIn('id', $storedIds);
        } else {
            $query->where('associate_receipt_id', $receipt->id);
        }

        $deliveries = $query->get();

        if ($deliveries->isEmpty()) {
            return redirect()->back()->with('error', 'Não há entregas disponíveis para reimprimir este comprovante.');
        }

        if ($message = $this->receiptDistributionIntegrityMessage($deliveries, (int) $tenantId, $projectId, (int) $associate->id, $receipt->id)) {
            return redirect()->back()->with('error', $message);
        }

        $receiptData = \App\Services\ReceiptDataBuilder::fromDeliveries($deliveries, null, $project);

        $pdf = Pdf::loadView('pdf.project-associate-receipt', [
            'tenant'          => $tenant,
            'project'         => $project,
            'associate'       => $associate,
            'receipt'         => $receipt,
            'summary'         => $receiptData['summary'],
            'productsSummary' => $receiptData['productsSummary'],
            'hasRoundingDivergence' => $receiptData['hasRoundingDivergence'],
            'feeBreakdown'    => $receiptData['feeBreakdown'],
        ])->setPaper('a4', 'portrait');

        $safeName     = \Illuminate\Support\Str::slug($associate->user->name ?? 'associado');
        $receiptLabel = str_replace('/', '-', $receipt->formatted_number);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, "comprovante-{$receiptLabel}-{$safeName}.pdf", ['Content-Type' => 'application/pdf']);
    }

    /**
     * Retorna JSON com a lista de comprovantes salvos para um projeto.
     */
    public function projectReceiptsList(Request $request)
    {
        $projectId = (int) $request->route('project');
        $tenantId  = session('tenant_id');

        if (!$tenantId) {
            return response()->json(['success' => false], 403);
        }

        $receipts = \App\Models\AssociateReceipt::where('tenant_id', $tenantId)
            ->where('sales_project_id', $projectId)
            ->with('associate.user')
            ->orderByDesc('id')
            ->get()
            ->map(function ($r) use ($projectId) {
                $tenantSlug = request()->route('tenant') instanceof \App\Models\Tenant
                    ? request()->route('tenant')->slug
                    : (\App\Models\Tenant::find(session('tenant_id'))?->slug ?? '');
                return [
                    'id'             => $r->id,
                    'number'         => $r->formatted_number,
                    'associate_name' => $r->associate?->user?->name ?? '—',
                    'issued_at'      => $r->issued_at?->format('d/m/Y') ?? '—',
                    'delivery_count' => is_array($r->delivery_ids) ? count($r->delivery_ids) : '—',
                    'status'         => $r->status?->value ?? 'draft',
                    'status_label'   => $r->status?->getLabel() ?? 'Rascunho',
                    'reprint_url'    => route('delivery.projects.receipt-reprint', [
                        'tenant'  => $tenantSlug,
                        'project' => $projectId,
                        'receipt' => $r->id,
                    ]),
                ];
            });

        return response()->json(['success' => true, 'receipts' => $receipts]);
    }
}
