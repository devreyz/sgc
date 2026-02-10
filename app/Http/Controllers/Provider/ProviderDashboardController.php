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

        // Get available equipment and services for selection
        $equipment = \App\Models\Asset::available()->get();
        $services = \App\Models\Service::active()->get();
        $associates = \App\Models\Associate::active()->get();

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
        ]);

        // Buscar dados do serviço para preencher automaticamente
        $service = \App\Models\Service::find($validated['service_id']);
        
        // Se não forneceu unit e unit_price, usar do serviço
        if (empty($validated['unit'])) {
            $validated['unit'] = $service->unit ?? 'hora';
        }
        if (empty($validated['unit_price'])) {
            $validated['unit_price'] = $service->base_price ?? 0;
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

        // Create the service order
        $order = ServiceOrder::create([
            'number' => $orderNumber,
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
            'payment_status' => 'pending',
            'service_provider_id' => $provider->id,
            'notes' => $validated['notes'] ?? null,
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

            // Buscar valores do serviço
            $service = $order->service;
            $unitPrice = $service->base_price; // Valor cobrado do associado
            
            // Determinar valor do prestador com base na unidade
            $providerRate = 0;
            if ($order->unit === 'hora') {
                $providerRate = $service->provider_hourly_rate ?? 0;
            } elseif ($order->unit === 'diaria' || $order->unit === 'dia') {
                $providerRate = $service->provider_daily_rate ?? 0;
            } else {
                // Para outras unidades, usar provider_hourly_rate como padrão
                $providerRate = $service->provider_hourly_rate ?? 0;
            }

            // Calcular valores
            $totalChargeToAssociate = $validated['actual_quantity'] * $unitPrice; // Valor que o associado deve pagar
            $totalPaymentToProvider = $validated['actual_quantity'] * $providerRate; // Valor a pagar ao prestador
            $cooperativeProfit = $totalChargeToAssociate - $totalPaymentToProvider; // Lucro da cooperativa

            // Atualizar ordem de serviço
            $order->update([
                'status' => ServiceOrderStatus::COMPLETED,
                'execution_date' => $validated['execution_date'],
                'actual_quantity' => $validated['actual_quantity'],
                'unit_price' => $unitPrice,
                'total_price' => $totalChargeToAssociate,
                'final_price' => $totalChargeToAssociate,
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

            // CRIAR DÉBITO para o associado (valor que ele DEVE pagar pelo serviço)
            if ($order->associate_id) {
                $currentBalance = $order->associate->current_balance ?? 0;
                \App\Models\AssociateLedger::create([
                    'associate_id' => $order->associate_id,
                    'type' => \App\Enums\LedgerType::DEBIT,
                    'category' => \App\Enums\LedgerCategory::SERVICO,
                    'amount' => $totalChargeToAssociate,
                    'balance_after' => $currentBalance - $totalChargeToAssociate,
                    'description' => "Serviço executado - OS {$order->number} - {$service->name} - {$validated['actual_quantity']} {$order->unit}",
                    'reference_type' => get_class($order),
                    'reference_id' => $order->id,
                    'transaction_date' => $validated['execution_date'],
                    'created_by' => auth()->id() ?? $provider->user_id,
                ]);

                // Atualizar saldo do associado
                $order->associate()->update([
                    'current_balance' => $currentBalance - $totalChargeToAssociate
                ]);
            }

            // Criar registro de trabalho
            ServiceProviderWork::create([
                'service_order_id' => $order->id,
                'service_provider_id' => $provider->id,
                'associate_id' => $order->associate_id,
                'work_date' => $validated['execution_date'],
                'description' => $validated['work_description'],
                'hours_worked' => $validated['actual_quantity'],
                'unit_price' => $providerRate,
                'total_value' => $totalPaymentToProvider,
                'location' => $order->location,
                'payment_status' => 'pendente',
                'notes' => "Comprovante: {$receiptPath} | Associado deve: R$ " . number_format($totalChargeToAssociate, 2, ',', '.'),
            ]);
        });

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Serviço concluído! Associado deve pagar R$ ' . number_format($order->total_price, 2, ',', '.') . '. Você receberá R$ ' . number_format($order->provider_payment, 2, ',', '.') . ' após o pagamento do associado.');
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
