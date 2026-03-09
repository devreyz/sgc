<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\Customer;
use App\Models\Product;
use App\Models\QuickSale;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashierController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private function routeTenantSlug(): ?string
    {
        $routeTenant = request()->route('tenant');
        $routeSlug = null;
        if (is_string($routeTenant)) {
            $routeSlug = $routeTenant;
        } elseif (is_object($routeTenant)) {
            $routeSlug = $routeTenant->slug ?? null;
        }

        return session('tenant_slug') ?? $routeSlug;
    }

    // =========================================================================
    //  DASHBOARD / POS
    // =========================================================================

    public function index()
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();

        $todaySales = QuickSale::where('tenant_id', $tenantId)
            ->whereDate('sale_date', today())
            ->where('status', 'confirmed')
            ->get();

        $pendingSales = QuickSale::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with('product')
            ->latest()
            ->get();

        $stats = [
            'total_today' => $todaySales->sum(fn ($s) => $s->quantity * $s->unit_price),
            'sales_count' => $todaySales->count(),
            'pending_count' => $pendingSales->count(),
            'products_low_stock' => Product::where('tenant_id', $tenantId)
                ->where('status', true)
                ->whereColumn('current_stock', '<=', 'min_stock')
                ->count(),
        ];

        return view('cashier.dashboard', compact('products', 'todaySales', 'pendingSales', 'stats'));
    }

    // =========================================================================
    //  NOVA VENDA RÁPIDA
    // =========================================================================

    public function create()
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();

        $customers = Customer::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('cashier.create-sale', compact('products', 'customers'));
    }

    public function store(Request $request)
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $validated = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0.01',
            'payment_method'   => 'required|in:dinheiro,pix,cartao_debito,cartao_credito,boleto,outro',
            'customer_name'    => 'nullable|string|max:255',
            'notes'            => 'nullable|string|max:1000',
        ]);

        $batchId = now()->format('YmdHis') . '-' . Auth::id();
        $createdSales = [];

        DB::transaction(function () use ($validated, $tenantId, $batchId, &$createdSales) {
            foreach ($validated['items'] as $item) {
                $product = Product::where('tenant_id', $tenantId)->findOrFail($item['product_id']);

                $sale = QuickSale::create([
                    'tenant_id'      => $tenantId,
                    'product_id'     => $product->id,
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'total_value'     => round($item['quantity'] * $item['unit_price'], 2),
                    'payment_method'  => $validated['payment_method'],
                    'status'          => 'pending',
                    'sale_date'       => today(),
                    'notes'           => trim(
                        ($validated['customer_name'] ? "Cliente: {$validated['customer_name']}\n" : '') .
                        ($validated['notes'] ?? '') .
                        "\nLote: {$batchId}"
                    ),
                    'created_by'      => Auth::id(),
                ]);
                $createdSales[] = $sale;
            }
        });

        // Auto-confirmar todas as vendas do lote
        $errors = [];
        foreach ($createdSales as $sale) {
            try {
                DB::transaction(function () use ($sale) {
                    $stockService = app(StockService::class);
                    $movement = $stockService->exit(
                        $sale->product,
                        (float) $sale->quantity,
                        StockMovementReason::VENDA,
                        $sale,
                        ['notes' => "Venda rápida (caixa) #{$sale->id}"]
                    );
                    $sale->update([
                        'status'            => 'confirmed',
                        'stock_movement_id' => $movement->id,
                        'confirmed_by'      => \Illuminate\Support\Facades\Auth::id(),
                        'confirmed_at'      => now(),
                    ]);
                });
            } catch (\Exception $e) {
                $errors[] = "{$sale->product->name}: {$e->getMessage()}";
            }
        }

        if (!empty($errors)) {
            return redirect()->route('cashier.dashboard', ['tenant' => $this->routeTenantSlug()])
                ->with('warning', 'Vendas criadas, mas com erros: ' . implode('; ', $errors));
        }

        $total = collect($createdSales)->sum(fn ($s) => $s->total_value);
        $count = count($createdSales);

        return redirect()->route('cashier.dashboard', ['tenant' => $this->routeTenantSlug()])
            ->with('success', "{$count} produto(s) vendido(s) — Total: R$ " . number_format($total, 2, ',', '.'));
    }

    // =========================================================================
    //  CONFIRMAR VENDA
    // =========================================================================

    public function confirm()
    {
        $sale = (int) request()->route('sale');
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $sale = QuickSale::where('tenant_id', $tenantId)
            ->where('id', $sale)
            ->with('product')
            ->first();

        if (!$sale) {
            return redirect()->route('cashier.dashboard', ['tenant' => $this->routeTenantSlug()])
                ->with('error', 'Venda não encontrada.');
        }

        if ($sale->status !== 'pending') {
            return redirect()->route('cashier.dashboard', ['tenant' => $this->routeTenantSlug()])
                ->with('warning', "Venda #{$sale->id} já foi {$sale->status}.");
        }

        return view('cashier.confirm-sale', compact('sale'));
    }

    public function storeConfirm(Request $request)
    {
        $sale = (int) request()->route('sale');
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $sale = QuickSale::where('tenant_id', $tenantId)
            ->where('id', $sale)
            ->where('status', 'pending')
            ->with('product')
            ->firstOrFail();

        try {
            DB::transaction(function () use ($sale) {
                $stockService = app(StockService::class);
                $movement = $stockService->exit(
                    $sale->product,
                    (float) $sale->quantity,
                    StockMovementReason::VENDA,
                    $sale,
                    ['notes' => "Venda rápida (caixa) #{$sale->id}"]
                );

                $sale->update([
                    'status'            => 'confirmed',
                    'stock_movement_id' => $movement->id,
                    'confirmed_by'      => Auth::id(),
                    'confirmed_at'      => now(),
                ]);
            });

            return redirect()->route('cashier.dashboard', ['tenant' => $this->routeTenantSlug()])
                ->with('success', "Venda #{$sale->id} confirmada! Estoque atualizado.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao confirmar: ' . $e->getMessage());
        }
    }

    // =========================================================================
    //  CANCELAR VENDA
    // =========================================================================

    public function cancel(Request $request)
    {
        $sale = (int) request()->route('sale');
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $sale = QuickSale::where('tenant_id', $tenantId)
            ->where('id', $sale)
            ->whereIn('status', ['pending', 'confirmed'])
            ->with('product')
            ->firstOrFail();

        try {
            DB::transaction(function () use ($sale) {
                if ($sale->status === 'confirmed' && $sale->stockMovement) {
                    $stockService = app(StockService::class);
                    $stockService->reverse(
                        $sale->stockMovement,
                        "Cancelamento Venda Caixa #{$sale->id}"
                    );
                }

                $sale->update([
                    'status'              => 'cancelled',
                    'cancellation_reason' => 'Cancelado pelo operador de caixa',
                    'cancelled_by'        => Auth::id(),
                    'cancelled_at'        => now(),
                ]);
            });

            return redirect()->route('cashier.dashboard', ['tenant' => $this->routeTenantSlug()])
                ->with('success', "Venda #{$sale->id} cancelada.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao cancelar: ' . $e->getMessage());
        }
    }

    // =========================================================================
    //  HISTÓRICO
    // =========================================================================

    public function history(Request $request)
    {
        $tenantId = session('tenant_id');
        if (!$tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $query = QuickSale::where('tenant_id', $tenantId)
            ->with('product')
            ->latest('sale_date');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('sale_date', $request->date);
        }

        $sales = $query->paginate(20);

        return view('cashier.history', compact('sales'));
    }

    // =========================================================================
    //  API: Buscar preço do produto (AJAX)
    // =========================================================================

    public function getProductPrice()
    {
        $product = (int) request()->route('product');
        $tenantId = session('tenant_id');
        $product = Product::where('tenant_id', $tenantId)
            ->where('id', $product)
            ->first();

        if (!$product) {
            return response()->json(['error' => 'Produto não encontrado'], 404);
        }

        return response()->json([
            'sale_price'    => $product->sale_price,
            'cost_price'    => $product->cost_price,
            'current_stock' => $product->current_stock,
            'unit'          => $product->unit,
            'name'          => $product->name,
        ]);
    }
}
