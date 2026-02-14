<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderWork;
use App\Models\ServiceOrder;
use App\Enums\ServiceOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProviderDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show provider dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get provider record linked to user
        $provider = ServiceProvider::where('user_id', $user->id)->first();
        
        if (!$provider) {
            // Show message that no provider profile exists
            return view('provider.no-profile', ['user' => $user]);
        }

        $stats = [
            'pending_orders' => ServiceOrder::where('service_provider_id', $provider->id)
                ->where('status', ServiceOrderStatus::SCHEDULED)
                ->count(),
            'in_progress_orders' => ServiceOrder::where('service_provider_id', $provider->id)
                ->where('status', ServiceOrderStatus::IN_PROGRESS)
                ->count(),
            'completed_this_month' => ServiceProviderWork::where('service_provider_id', $provider->id)
                ->whereMonth('work_date', now()->month)
                ->count(),
            'current_balance' => $provider->current_balance ?? 0,
        ];

        $recentOrders = ServiceOrder::where('service_provider_id', $provider->id)
            ->with(['service', 'asset', 'associate'])
            ->orderBy('scheduled_date', 'desc')
            ->limit(5)
            ->get();

        $recentWorks = ServiceProviderWork::where('service_provider_id', $provider->id)
            ->with(['serviceOrder', 'serviceProvider'])
            ->orderBy('work_date', 'desc')
            ->limit(10)
            ->get();

        return view('provider.dashboard', compact('provider', 'stats', 'recentOrders', 'recentWorks'));
    }

    /**
     * Show all service orders
     */
    public function orders(Request $request)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        if (!$provider) {
            return redirect()->route('provider.dashboard')
                ->with('error', 'Perfil de prestador não encontrado.');
        }

        $query = ServiceOrder::where('service_provider_id', $provider->id)
            ->with(['service', 'asset', 'associate']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('scheduled_date', 'desc')->paginate(20);

        return view('provider.orders', compact('provider', 'orders'));
    }

    /**
     * Show work form
     */
    public function createWork($orderId)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->with(['service', 'asset', 'associate'])
            ->firstOrFail();

        return view('provider.work-form', compact('provider', 'order'));
    }

    /**
     * Store work record
     */
    public function storeWork(Request $request, $orderId)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->firstOrFail();

        $validated = $request->validate([
            'work_date' => 'required|date',
            'hours_worked' => 'required|numeric|min:0',
            'value' => 'required|numeric|min:0',
            'description' => 'required|string',
            'receipt_path' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $data = [
            'service_order_id' => $order->id,
            'service_provider_id' => $provider->id,
            'work_date' => $validated['work_date'],
            'hours_worked' => $validated['hours_worked'],
            'value' => $validated['value'],
            'description' => $validated['description'],
        ];

        // Handle file upload
        if ($request->hasFile('receipt_path')) {
            $path = $request->file('receipt_path')->store('receipts', 'public');
            $data['receipt_path'] = $path;
        }

        ServiceProviderWork::create($data);

        return redirect()->route('provider.orders')
            ->with('success', 'Serviço registrado com sucesso!');
    }

    /**
     * Show work history
     */
    public function works(Request $request)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $query = ServiceProviderWork::where('service_provider_id', $provider->id)
            ->with(['serviceOrder', 'serviceProvider']);

        // Filter by date range
        if ($request->start_date) {
            $query->where('work_date', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('work_date', '<=', $request->end_date);
        }

        $works = $query->orderBy('work_date', 'desc')->paginate(20);
        $totalValue = $query->sum('total_value');

        return view('provider.works', compact('provider', 'works', 'totalValue'));
    }

    /**
     * Show form to create new service order
     */
    public function createOrder()
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        if (!$provider) {
            return redirect()->route('provider.dashboard')
                ->with('error', 'Perfil de prestador não encontrado.');
        }

        // Get available equipment
        $equipment = \App\Models\Asset::available()->get();
        
        // Get only services that this provider offers (via pivot entries)
        $providerServices = \App\Models\ServiceProviderService::where('service_provider_id', $provider->id)
            ->where('status', true)
            ->with('service')
            ->get();

        $services = $providerServices->map(function ($ps) {
            $service = $ps->service;
            if ($service) {
                $service->provider_hourly_rate = $ps->provider_hourly_rate;
                $service->provider_daily_rate = $ps->provider_daily_rate;
                $service->provider_unit_rate = $ps->provider_unit_rate;
            }
            return $service;
        })->filter();
        
        // Get all associates
        $associates = \App\Models\Associate::with('user')->whereHas('user', function($q) {
            $q->where('status', true);
        })->get();

        return view('provider.create-order', compact('provider', 'equipment', 'services', 'associates'));
    }

    /**
     * Store new service order
     */
    public function storeOrder(Request $request)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        if (!$provider) {
            return redirect()->route('provider.dashboard')
                ->with('error', 'Perfil de prestador não encontrado.');
        }

        $validated = $request->validate([
            'associate_id' => 'nullable|exists:associates,id',
            'service_id' => 'required|exists:services,id',
            'asset_id' => 'nullable|exists:assets,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'quantity' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'location' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'non_associate_name' => 'nullable|string|max:255',
            'non_associate_doc' => 'nullable|string|max:50',
            'non_associate_phone' => 'nullable|string|max:20',
        ]);

        // Buscar dados do serviço para preencher automaticamente
        $service = \App\Models\Service::find($validated['service_id']);
        
        // Determinar se é associado ou não-associado
        $isAssociate = !empty($validated['associate_id']);
        
        // Se não forneceu unit e unit_price, usar do serviço
        if (empty($validated['unit'])) {
            $validated['unit'] = $service->unit ?? 'hora';
        }
        
        if (empty($validated['unit_price'])) {
            // Usar preço correto baseado no tipo de pessoa
            if ($isAssociate && $service->associate_price) {
                $validated['unit_price'] = $service->associate_price;
            } elseif (!$isAssociate && $service->non_associate_price) {
                $validated['unit_price'] = $service->non_associate_price;
            } else {
                $validated['unit_price'] = $service->base_price ?? 0;
            }
        }

        // Calculate prices apenas se tiver quantidade
        $totalPrice = null;
        $finalPrice = null;
        if (!empty($validated['quantity']) && !empty($validated['unit_price'])) {
            $totalPrice = $validated['quantity'] * $validated['unit_price'];
            $discount = 0;
            $finalPrice = $totalPrice - $discount;
        }

        // Generate order number
        $lastOrder = ServiceOrder::latest('id')->first();
        $nextNumber = $lastOrder ? (intval(substr($lastOrder->number, 2)) + 1) : 1;
        $orderNumber = 'OS' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        
        // Handle non-associate data
        $notesExtra = $validated['notes'] ?? '';
        if (!empty($validated['non_associate_name'])) {
            $nonAssociateInfo = "\n\n[PESSOA AVULSA]\n";
            $nonAssociateInfo .= "Nome: " . $validated['non_associate_name'];
            if (!empty($validated['non_associate_doc'])) {
                $nonAssociateInfo .= "\nCPF/CNPJ: " . $validated['non_associate_doc'];
            }
            if (!empty($validated['non_associate_phone'])) {
                $nonAssociateInfo .= "\nTelefone: " . $validated['non_associate_phone'];
            }
            $notesExtra .= $nonAssociateInfo;
        }

        // Create the service order
        $order = ServiceOrder::create([
            'number' => $orderNumber,
            'service_provider_id' => $provider->id,
            'associate_id' => $validated['associate_id'] ?? null,
            'service_id' => $validated['service_id'],
            'asset_id' => $validated['asset_id'] ?? null,
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'quantity' => $validated['quantity'] ?? null,
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'] ?? null,
            'total_price' => $totalPrice ?? 0,
            'discount' => 0,
            'final_price' => $finalPrice ?? 0,
            'location' => $validated['location'],
            'distance_km' => $validated['distance_km'] ?? 0,
            'status' => ServiceOrderStatus::SCHEDULED,
            'notes' => $notesExtra,
            'created_by' => $user->id,
        ]);

        return redirect()->route('provider.orders')
            ->with('success', 'Ordem de serviço criada com sucesso! Número: ' . $orderNumber);
    }

    /**
     * Show order details
     */
    public function showOrder($orderId)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->with(['service', 'asset', 'associate', 'serviceProvider', 'works'])
            ->firstOrFail();

        return view('provider.show-order', compact('provider', 'order'));
    }

    /**
     * Complete service order with receipt
     */
    public function completeOrder(Request $request, $orderId)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->with(['service', 'associate'])
            ->firstOrFail();

        // Não permitir completar se já foi processado
        if ($order->status === ServiceOrderStatus::COMPLETED) {
            return back()->with('error', 'Esta ordem já foi concluída.');
        }

        $validated = $request->validate([
            'execution_date' => 'required|date',
            'actual_quantity' => 'required|numeric|min:0',
            'work_description' => 'required|string',
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'horimeter_start' => 'nullable|numeric',
            'horimeter_end' => 'nullable|numeric',
            'odometer_start' => 'nullable|numeric',
            'odometer_end' => 'nullable|numeric',
            'fuel_used' => 'nullable|numeric',
        ]);

        \DB::transaction(function () use ($order, $validated, $request, $provider) {
            // Upload receipt
            $receiptPath = null;
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
            }

            // Buscar dados do serviço e valores do prestador
            $service = $order->service;
            
            // Buscar valor do prestador da tabela pivot
            $providerService = \App\Models\ServiceProviderService::where('service_provider_id', $provider->id)
                ->where('service_id', $service->id)
                ->first();
            
            if (!$providerService) {
                throw new \Exception('Serviço não encontrado para este prestador. Configure os valores antes de concluir.');
            }
            
            // Determinar valor do prestador com base na unidade
            $providerRate = 0;
            if ($order->unit === 'hora') {
                $providerRate = $providerService->provider_hourly_rate ?? 0;
            } elseif ($order->unit === 'diaria' || $order->unit === 'dia') {
                $providerRate = $providerService->provider_daily_rate ?? 0;
            } else {
                // Para outras unidades, usar provider_unit_rate
                $providerRate = $providerService->provider_unit_rate ?? 0;
            }
            
            // Determinar valor cobrado do cliente baseado em associado ou não
            $unitPrice = 0;
            if ($order->associate_id) {
                $unitPrice = $service->associate_price ?? $service->base_price;
            } else {
                $unitPrice = $service->non_associate_price ?? $service->base_price;
            }

            // Calcular valores
            $totalChargeToClient = $validated['actual_quantity'] * $unitPrice; // Valor que será cobrado
            $totalPaymentToProvider = $validated['actual_quantity'] * $providerRate; // Valor a pagar ao prestador
            $cooperativeProfit = $totalChargeToClient - $totalPaymentToProvider; // Lucro da cooperativa

            // Atualizar ordem de serviço para AGUARDANDO PAGAMENTO
            $order->update([
                'status' => ServiceOrderStatus::AWAITING_PAYMENT,
                'execution_date' => $validated['execution_date'],
                'actual_quantity' => $validated['actual_quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $totalChargeToClient,
                'final_price' => $totalChargeToClient,
                'provider_payment' => $totalPaymentToProvider,
                'work_description' => $validated['work_description'],
                'receipt_path' => $receiptPath,
                'horimeter_start' => $validated['horimeter_start'] ?? null,
                'horimeter_end' => $validated['horimeter_end'] ?? null,
                'odometer_start' => $validated['odometer_start'] ?? null,
                'odometer_end' => $validated['odometer_end'] ?? null,
                'fuel_used' => $validated['fuel_used'] ?? null,
                'associate_payment_status' => 'pending', // Associado deve pagar
                'provider_payment_status' => 'pending', // Prestador receberá após associado pagar
            ]);

            // NÃO criar lançamentos financeiros aqui - isso será feito ao registrar o pagamento
        });

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Ordem concluída! Agora aguardando registro de pagamento. Valor total: R$ ' . number_format($order->final_price, 2, ',', '.'));
    }

    /**
     * Show form to edit order (only before submission)
     */
    public function editOrder($orderId)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->with(['service', 'asset', 'associate'])
            ->firstOrFail();

        // Não permitir editar se já foi enviado para avaliação
        if ($order->payment_status !== 'pending') {
            return redirect()->route('provider.orders.show', $order->id)
                ->with('error', 'Esta ordem já foi enviada para avaliação e não pode ser editada.');
        }

        // Não permitir editar se já foi completada
        if ($order->status === ServiceOrderStatus::COMPLETED) {
            return redirect()->route('provider.orders.show', $order->id)
                ->with('error', 'Esta ordem já foi concluída. Crie uma nova ordem se necessário.');
        }

        // Buscar dados para os selects
        $services = \App\Models\Service::active()->get();
        $associates = \App\Models\Associate::active()->get();
        $equipment = \App\Models\Asset::where('status', \App\Enums\AssetStatus::DISPONIVEL)
            ->orWhere('id', $order->asset_id)
            ->get();

        return view('provider.edit-order', compact('provider', 'order', 'services', 'associates', 'equipment'));
    }

    /**
     * Update order (only before submission)
     */
    public function updateOrder(Request $request, $orderId)
    {
        $user = Auth::user();
        $provider = ServiceProvider::where('user_id', $user->id)->first();

        $order = ServiceOrder::where('id', $orderId)
            ->where('service_provider_id', $provider->id)
            ->firstOrFail();

        // Não permitir editar se já foi enviado para avaliação
        if ($order->payment_status !== 'pending') {
            return back()->with('error', 'Esta ordem já foi enviada para avaliação e não pode ser editada.');
        }

        // Não permitir editar se já foi completada
        if ($order->status === ServiceOrderStatus::COMPLETED) {
            return back()->with('error', 'Esta ordem já foi concluída.');
        }

        $validated = $request->validate([
            'associate_id' => 'nullable|exists:associates,id',
            'service_id' => 'required|exists:services,id',
            'asset_id' => 'nullable|exists:assets,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'quantity' => 'nullable|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'unit_price' => 'nullable|numeric|min:0',
            'location' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Buscar dados do serviço para preencher automaticamente se mudou
        if ($validated['service_id'] != $order->service_id) {
            $service = \App\Models\Service::find($validated['service_id']);
            if (empty($validated['unit'])) {
                $validated['unit'] = $service->unit ?? 'hora';
            }
            if (empty($validated['unit_price'])) {
                $validated['unit_price'] = $service->base_price ?? 0;
            }
        }

        // Calculate prices apenas se tiver quantidade
        if (!empty($validated['quantity']) && !empty($validated['unit_price'])) {
            $totalPrice = $validated['quantity'] * $validated['unit_price'];
            $validated['total_price'] = $totalPrice;
            $validated['final_price'] = $totalPrice;
        }

        $order->update($validated);

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Ordem de serviço atualizada com sucesso!');
    }
}
