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
        $equipment = \App\Models\Equipment::active()->get();
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
            'asset_id' => 'nullable|exists:equipment,id',
            'scheduled_date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'unit_price' => 'required|numeric|min:0',
            'location' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Calculate prices
        $totalPrice = $validated['quantity'] * $validated['unit_price'];
        $discount = 0;
        $finalPrice = $totalPrice - $discount;

        // Generate order number
        $lastOrder = ServiceOrder::latest('id')->first();
        $nextNumber = $lastOrder ? (intval(substr($lastOrder->number, 2)) + 1) : 1;
        $orderNumber = 'OS' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);

        // Create the service order
        $order = ServiceOrder::create([
            'number' => $orderNumber,
            'associate_id' => $validated['associate_id'],
            'service_id' => $validated['service_id'],
            'asset_id' => $validated['asset_id'],
            'scheduled_date' => $validated['scheduled_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'quantity' => $validated['quantity'],
            'unit' => $validated['unit'],
            'unit_price' => $validated['unit_price'],
            'total_price' => $totalPrice,
            'discount' => $discount,
            'final_price' => $finalPrice,
            'location' => $validated['location'],
            'distance_km' => $validated['distance_km'] ?? 0,
            'status' => ServiceOrderStatus::SCHEDULED,
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
            ->firstOrFail();

        $validated = $request->validate([
            'execution_date' => 'required|date',
            'hours_worked' => 'required|numeric|min:0',
            'work_description' => 'required|string',
            'receipt' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'horimeter_start' => 'nullable|numeric',
            'horimeter_end' => 'nullable|numeric',
            'odometer_start' => 'nullable|numeric',
            'odometer_end' => 'nullable|numeric',
            'fuel_used' => 'nullable|numeric',
        ]);

        // Upload receipt
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
        }

        // Update order status and details
        $order->update([
            'status' => ServiceOrderStatus::COMPLETED,
            'execution_date' => $validated['execution_date'],
            'work_description' => $validated['work_description'],
            'horimeter_start' => $validated['horimeter_start'] ?? null,
            'horimeter_end' => $validated['horimeter_end'] ?? null,
            'odometer_start' => $validated['odometer_start'] ?? null,
            'odometer_end' => $validated['odometer_end'] ?? null,
            'fuel_used' => $validated['fuel_used'] ?? null,
        ]);

        // Create work record with receipt
        ServiceProviderWork::create([
            'service_order_id' => $order->id,
            'service_provider_id' => $provider->id,
            'associate_id' => $order->associate_id,
            'work_date' => $validated['execution_date'],
            'description' => $validated['work_description'],
            'hours_worked' => $validated['hours_worked'],
            'unit_price' => $order->unit_price,
            'total_value' => $order->final_price,
            'location' => $order->location,
            'payment_status' => 'pendente',
            'notes' => 'Comprovante anexado: ' . $receiptPath,
        ]);

        return redirect()->route('provider.orders.show', $order->id)
            ->with('success', 'Serviço concluído com sucesso! Aguardando aprovação para pagamento.');
    }
}
