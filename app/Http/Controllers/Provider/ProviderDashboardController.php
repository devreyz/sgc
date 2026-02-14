<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderService;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderPayment;
use App\Models\Service;
use App\Models\Asset;
use App\Models\Associate;
use App\Models\ProviderPaymentRequest;
use App\Enums\ServiceOrderStatus;
use App\Enums\ServiceOrderPaymentStatus;
use App\Enums\AssetStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProviderDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function getProvider()
    {
        return ServiceProvider::where('user_id', Auth::id())->first();
    }

    // =========================================================================
    //  DASHBOARD
    // =========================================================================

    public function index()
    {
        $user = Auth::user();
        $provider = $this->getProvider();

        if (!$provider) {
            return view('provider.no-profile', ['user' => $user]);
        }

        // Ordens agendadas para os próximos 30 dias
        $upcomingOrders = ServiceOrder::where('service_provider_id', $provider->id)
            ->where('status', ServiceOrderStatus::SCHEDULED)
            ->whereBetween('scheduled_date', [now(), now()->addDays(30)])
            ->with(['service', 'associate'])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->get();

        // Ordens recentes (últimas 5)
        $recentOrders = ServiceOrder::where('service_provider_id', $provider->id)
            ->with(['service', 'associate'])
            ->latest('created_at')
            ->limit(5)
            ->get();

        // Resumo financeiro usando provider_remaining
        $ordersForFinancial = ServiceOrder::where('service_provider_id', $provider->id)
            ->whereIn('status', [ServiceOrderStatus::AWAITING_PAYMENT, ServiceOrderStatus::COMPLETED])
            ->get();

        $pending_receivable = $ordersForFinancial->sum('provider_remaining');
        $total_received = (float) ServiceOrderPayment::where('type', 'provider')
            ->where('status', ServiceOrderPaymentStatus::BILLED)
            ->whereHas('serviceOrder', fn($q) => $q->where('service_provider_id', $provider->id))
            ->sum('amount');

        // Solicitações de saque pendentes
        $pending_requests = ProviderPaymentRequest::where('service_provider_id', $provider->id)
            ->where('status', 'pending')
            ->sum('amount');

        $stats = [
            'scheduled' => ServiceOrder::where('service_provider_id', $provider->id)
                ->where('status', ServiceOrderStatus::SCHEDULED)->count(),
            'awaiting_payment' => ServiceOrder::where('service_provider_id', $provider->id)
                ->where('status', ServiceOrderStatus::AWAITING_PAYMENT)->count(),
            'completed_month' => ServiceOrder::where('service_provider_id', $provider->id)
                ->whereIn('status', [
                    ServiceOrderStatus::AWAITING_PAYMENT,
                    ServiceOrderStatus::PAID,
                    ServiceOrderStatus::COMPLETED,
                ])
                ->whereMonth('execution_date', now()->month)
                ->whereYear('execution_date', now()->year)
                ->count(),
            'pending_receivable' => $pending_receivable,
            'total_received' => $total_received,
            'pending_requests' => $pending_requests,
            'available_withdrawal' => max(0, $pending_receivable - $pending_requests),
        ];

        return view('provider.dashboard', compact('provider', 'stats', 'recentOrders', 'upcomingOrders'));
    }

    // =========================================================================
    //  LISTAGEM DE ORDENS
    // =========================================================================

    public function orders(Request $request)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $query = ServiceOrder::where('service_provider_id', $provider->id)
            ->with(['service', 'associate']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest('scheduled_date')->paginate(20);

        return view('provider.orders', compact('provider', 'orders'));
    }

    // =========================================================================
    //  CRIAR ORDEM
    // =========================================================================

    public function createOrder()
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard')->with('error', 'Perfil de prestador não encontrado.');
        }

        $providerServices = ServiceProviderService::where('service_provider_id', $provider->id)
            ->where('status', true)
            ->with('service')
            ->get();

        $services = $providerServices->map(function ($ps) {
            $service = $ps->service;
            if (!$service || !$service->status) return null;
            $service->pivot_hourly = $ps->provider_hourly_rate;
            $service->pivot_daily = $ps->provider_daily_rate;
            $service->pivot_unit = $ps->provider_unit_rate;
            return $service;
        })->filter()->values();

        $associates = Associate::with('user')
            ->whereHas('user', fn($q) => $q->where('status', true))
            ->get();

        $equipment = Asset::where('status', AssetStatus::DISPONIVEL)->get();

        return view('provider.create-order', compact('provider', 'services', 'associates', 'equipment'));
    }

    public function storeOrder(Request $request)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard')->with('error', 'Perfil não encontrado.');
        }

        $validated = $request->validate([
            'service_id'          => 'required|exists:services,id',
            'client_type'         => 'required|in:associate,non_associate',
            'associate_id'        => 'required_if:client_type,associate|nullable|exists:associates,id',
            'non_associate_name'  => 'required_if:client_type,non_associate|nullable|string|max:255',
            'non_associate_doc'   => 'nullable|string|max:50',
            'non_associate_phone' => 'nullable|string|max:20',
            'scheduled_date'      => 'required|date',
            'location'            => 'required|string|max:255',
            'asset_id'            => 'nullable|exists:assets,id',
            'notes'               => 'nullable|string',
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $isAssociate = $validated['client_type'] === 'associate';

        $unitPrice = $isAssociate
            ? ($service->associate_price ?? $service->base_price ?? 0)
            : ($service->non_associate_price ?? $service->base_price ?? 0);

        $notes = trim($validated['notes'] ?? '');
        if (!$isAssociate && !empty($validated['non_associate_name'])) {
            $extra = "\n\n[PESSOA AVULSA]\nNome: " . $validated['non_associate_name'];
            if (!empty($validated['non_associate_doc'])) $extra .= "\nCPF/CNPJ: " . $validated['non_associate_doc'];
            if (!empty($validated['non_associate_phone'])) $extra .= "\nTelefone: " . $validated['non_associate_phone'];
            $notes .= $extra;
        }

        // Prevenir submissões duplicadas: checar ordens muito recentes com mesmos dados
        $recentDuplicate = ServiceOrder::where('service_provider_id', $provider->id)
            ->where('service_id', $validated['service_id'])
            ->where('scheduled_date', $validated['scheduled_date'])
            ->where('location', $validated['location'])
            ->where(function ($q) use ($isAssociate, $validated) {
                if ($isAssociate) {
                    $q->where('associate_id', $validated['associate_id']);
                } else {
                    $q->whereNull('associate_id');
                }
            })
            ->where('created_by', Auth::id())
            ->where('created_at', '>=', now()->subSeconds(30))
            ->first();

        if ($recentDuplicate) {
            return redirect()->route('provider.orders.show', $recentDuplicate->id)
                ->with('warning', 'Ordem já enviada recentemente — evitando duplicata.');
        }

        $order = ServiceOrder::create([
            'service_provider_id' => $provider->id,
            'service_id'          => $validated['service_id'],
            'associate_id'        => $isAssociate ? $validated['associate_id'] : null,
            'asset_id'            => $validated['asset_id'] ?? null,
            'scheduled_date'      => $validated['scheduled_date'],
            'unit'                => $service->unit ?? 'hora',
            'unit_price'          => $unitPrice,
            'total_price'         => 0,
            'final_price'         => 0,
            'provider_payment'    => 0,
            'location'            => $validated['location'],
            'status'              => ServiceOrderStatus::SCHEDULED,
            'notes'               => trim($notes),
            'created_by'          => Auth::id(),
        ]);

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Ordem criada! Número: ' . $order->number);
    }

    // =========================================================================
    //  DETALHES DA ORDEM
    // =========================================================================

    public function showOrder($orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->with(['service', 'associate', 'asset', 'payments'])
            ->firstOrFail();

        $providerService = ServiceProviderService::where('service_provider_id', $provider->id)
            ->where('service_id', $order->service_id)
            ->first();

        return view('provider.show-order', compact('provider', 'order', 'providerService'));
    }

    // =========================================================================
    //  INICIAR EXECUÇÃO
    // =========================================================================

    public function startExecution($orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->firstOrFail();

        if ($order->status !== ServiceOrderStatus::SCHEDULED) {
            return back()->with('error', 'Esta ordem não pode ser iniciada no status atual.');
        }

        $order->update(['status' => ServiceOrderStatus::IN_PROGRESS]);

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Execução iniciada! Registre as informações ao finalizar.');
    }

    // =========================================================================
    //  CONCLUIR ORDEM (REGISTRAR EXECUÇÃO)
    // =========================================================================

    public function completeOrder(Request $request, $orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->with('service')
            ->firstOrFail();

        if (!in_array($order->status, [ServiceOrderStatus::SCHEDULED, ServiceOrderStatus::IN_PROGRESS])) {
            return back()->with('error', 'Esta ordem não pode ser concluída no status atual.');
        }

        $validated = $request->validate([
            'execution_date'   => 'required|date',
            'actual_quantity'  => 'required|numeric|min:0.1',
            'work_description' => 'required|string|min:5',
            'horimeter_start'  => 'nullable|numeric',
            'horimeter_end'    => 'nullable|numeric',
            'fuel_used'        => 'nullable|numeric',
        ]);

        $providerService = ServiceProviderService::where('service_provider_id', $provider->id)
            ->where('service_id', $order->service_id)
            ->first();

        if (!$providerService) {
            return back()->with('error', 'Valores do prestador não configurados. Contate o administrador.');
        }

        $providerRate = match ($order->unit) {
            'hora'          => (float)($providerService->provider_hourly_rate ?? 0),
            'diaria', 'dia' => (float)($providerService->provider_daily_rate ?? 0),
            default         => (float)($providerService->provider_unit_rate ?? 0),
        };

        $service    = $order->service;
        $clientRate = $order->associate_id
            ? ($service->associate_price ?? $service->base_price ?? 0)
            : ($service->non_associate_price ?? $service->base_price ?? 0);

        $qty           = (float) $validated['actual_quantity'];
        $totalClient   = round($qty * $clientRate, 2);
        $totalProvider = round($qty * $providerRate, 2);

        DB::transaction(function () use ($order, $validated, $clientRate, $totalClient, $totalProvider) {
            $order->update([
                'status'                   => ServiceOrderStatus::AWAITING_PAYMENT,
                'execution_date'           => $validated['execution_date'],
                'actual_quantity'           => $validated['actual_quantity'],
                'unit_price'               => $clientRate,
                'total_price'              => $totalClient,
                'final_price'              => $totalClient,
                'provider_payment'         => $totalProvider,
                'work_description'         => $validated['work_description'],
                'horimeter_start'          => $validated['horimeter_start'] ?? null,
                'horimeter_end'            => $validated['horimeter_end'] ?? null,
                'fuel_used'                => $validated['fuel_used'] ?? null,
                'associate_payment_status' => ServiceOrderPaymentStatus::PENDING,
                'provider_payment_status'  => ServiceOrderPaymentStatus::PENDING,
            ]);
        });

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', sprintf(
                'Serviço concluído! Cliente paga: R$ %s | Você recebe: R$ %s',
                number_format($totalClient, 2, ',', '.'),
                number_format($totalProvider, 2, ',', '.')
            ));
    }

    // =========================================================================
    //  FINANCEIRO
    // =========================================================================

    public function financial()
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        // Ordens com valores pendentes (cliente pagou, prestador ainda não recebeu)
        $pendingOrders = ServiceOrder::where('service_provider_id', $provider->id)
            ->whereIn('status', [ServiceOrderStatus::AWAITING_PAYMENT, ServiceOrderStatus::COMPLETED])
            ->get()
            ->filter(fn($order) => $order->provider_remaining > 0);

        // Pagamentos já recebidos
        $receivedPayments = ServiceOrderPayment::where('type', 'provider')
            ->where('status', ServiceOrderPaymentStatus::BILLED)
            ->whereHas('serviceOrder', fn($q) => $q->where('service_provider_id', $provider->id))
            ->with('serviceOrder.service')
            ->latest('payment_date')
            ->paginate(15);

        // Solicitações de saque pendentes
        $pendingRequests = ProviderPaymentRequest::where('service_provider_id', $provider->id)
            ->where('status', 'pending')
            ->with('serviceOrder.service')
            ->latest('request_date')
            ->get();

        // Histórico de saques aprovados
        $approvedRequests = ProviderPaymentRequest::where('service_provider_id', $provider->id)
            ->where('status', 'approved')
            ->with('serviceOrder.service')
            ->latest('approved_at')
            ->limit(10)
            ->get();

        // Cálculos
        $pending_receivable = $pendingOrders->sum('provider_remaining');
        $total_received = $receivedPayments->total() > 0 
            ? ServiceOrderPayment::where('type', 'provider')
                ->where('status', ServiceOrderPaymentStatus::BILLED)
                ->whereHas('serviceOrder', fn($q) => $q->where('service_provider_id', $provider->id))
                ->sum('amount')
            : 0;
        $pending_requests_total = $pendingRequests->sum('amount');
        $available_withdrawal = max(0, $pending_receivable - $pending_requests_total);

        return view('provider.financial', compact(
            'provider', 
            'pendingOrders',
            'receivedPayments', 
            'pendingRequests',
            'approvedRequests',
            'pending_receivable', 
            'total_received',
            'pending_requests_total',
            'available_withdrawal'
        ));
    }

    // =========================================================================
    //  SOLICITAR SAQUE
    // =========================================================================

    public function requestPayment($orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->whereIn('status', [ServiceOrderStatus::AWAITING_PAYMENT, ServiceOrderStatus::COMPLETED])
            ->with('service')
            ->firstOrFail();

        // Verificar se tem saldo disponível
        if ($order->provider_remaining <= 0) {
            return redirect()->route('provider.financial')
                ->with('error', 'Esta ordem não possui saldo disponível para saque.');
        }

        // Verificar se já existe solicitação pendente para esta ordem
        $existingRequest = ProviderPaymentRequest::where('service_order_id', $order->id)
            ->where('service_provider_id', $provider->id)
            ->where('status', 'pending')
            ->first();

        return view('provider.request-payment', compact('provider', 'order', 'existingRequest'));
    }

    public function storePaymentRequest(Request $request, $orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->firstOrFail();

        // Validar valor disponível
        $available = $order->provider_remaining;

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $available,
            'description' => 'nullable|string|max:1000',
            'bank_info' => 'required|string|max:2000',
        ]);

        ProviderPaymentRequest::create([
            'service_provider_id' => $provider->id,
            'service_order_id' => $order->id,
            'amount' => $validated['amount'],
            'request_date' => now(),
            'bank_info' => $validated['bank_info'],
            'description' => $validated['description'] ?? 'Solicitação de saque',
            'status' => 'pending',
        ]);

        return redirect()->route('provider.financial')
            ->with('success', 'Solicitação de saque enviada! Aguarde aprovação da administração.');
    }

    public function registerClientPayment($orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->where('status', ServiceOrderStatus::AWAITING_PAYMENT)
            ->with(['service', 'associate'])
            ->firstOrFail();

        // Calcular valores
        $totalPaid = ServiceOrderPayment::where('service_order_id', $order->id)
            ->where('type', 'client')
            ->sum('amount');

        $clientRemaining = $order->final_price - $totalPaid;

        return view('provider.register-payment', compact('provider', 'order', 'totalPaid', 'clientRemaining'));
    }

    public function storeClientPayment(Request $request, $orderId)
    {
        $provider = $this->getProvider();
        if (!$provider) {
            return redirect()->route('provider.dashboard');
        }

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->firstOrFail();

        // Calcular valor disponível
        $totalPaid = ServiceOrderPayment::where('service_order_id', $order->id)
            ->where('type', 'client')
            ->sum('amount');

        $clientRemaining = $order->final_price - $totalPaid;

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $clientRemaining,
            'payment_method' => 'required|in:dinheiro,pix,transferencia,cheque,cartao,boleto',
            'payment_date' => 'required|date',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:5120',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Upload do comprovante se houver
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts/client-payments', 'public');
        }

        // Criar registro de pagamento pendente de aprovação
        ServiceOrderPayment::create([
            'service_order_id' => $order->id,
            'type' => 'client',
            'status' => 'pending',
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'receipt_path' => $receiptPath,
            'notes' => $validated['notes'],
            'registered_by' => auth()->id(),
        ]);

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Pagamento do cliente registrado! Aguarde confirmação da administração.');
    }
}
