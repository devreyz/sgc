<?php

namespace App\Http\Controllers\Pdv;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\PdvCustomerResource;
use App\Http\Resources\PdvSaleResource;
use App\Models\PdvCustomer;
use App\Models\PdvSale;
use App\Models\Product;
use App\Services\PdvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PdvController extends Controller
{
    public function __construct(private PdvService $pdvService)
    {
        $this->middleware('auth');
    }

    private function tenantId(): ?int
    {
        // Resolve pela rota primeiro (mais confiável que session para AJAX)
        $routeTenant = request()->route('tenant');
        if ($routeTenant instanceof \App\Models\Tenant) {
            return $routeTenant->id;
        }

        return session('tenant_id');
    }

    private function tenantSlug(): ?string
    {
        $routeTenant = request()->route('tenant');
        if (is_string($routeTenant)) {
            return $routeTenant;
        }
        if (is_object($routeTenant)) {
            return $routeTenant->slug ?? null;
        }

        return session('tenant_slug');
    }

    // ─── PÁGINA PRINCIPAL PDV ─────────────────────────────
    public function index()
    {
        if (! $this->tenantId()) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $stats = $this->pdvService->getStats($this->tenantId());
        $paymentMethods = PaymentMethod::cases();

        return view('pdv.index', compact('stats', 'paymentMethods'));
    }

    // ─── API: BUSCAR PRODUTOS ─────────────────────────────
    public function searchProducts(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $products = $this->pdvService->searchProducts($query, $this->tenantId());

        return response()->json($products);
    }

    // ─── API: LISTAR PRODUTOS (carregamento inicial) ──────
    public function products(): JsonResponse
    {
        $products = Product::where('tenant_id', $this->tenantId())
            ->where('status', true)
            ->select('id', 'name', 'sku', 'sale_price', 'current_stock', 'unit')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return response()->json($products);
    }

    // ─── API: FINALIZAR VENDA ─────────────────────────────
    public function completeSale(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'payments' => 'nullable|array',
            'payments.*.payment_method' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'pdv_customer_id' => 'nullable|integer|exists:pdv_customers,id',
            'customer_name' => 'nullable|string|max:255',
            'is_fiado' => 'nullable|boolean',
            'fiado_due_date' => 'nullable|date',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $sale = $this->pdvService->completeSale($validator->validated(), $this->tenantId());
            $sale->load('items.product', 'payments');

            return response()->json([
                'success' => true,
                'sale' => new PdvSaleResource($sale),
                'message' => "Venda {$sale->code} finalizada!",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar venda: '.$e->getMessage(),
            ], 500);
        }
    }

    // ─── API: CANCELAR VENDA ──────────────────────────────
    public function cancelSale(Request $request, $sale): JsonResponse
    {
        $saleId = $this->resolveSaleId($sale);
        $model = PdvSale::where('id', $saleId)
            ->where('tenant_id', $this->tenantId())
            ->firstOrFail();

        if ($model->status === 'cancelled') {
            return response()->json(['message' => 'Venda já cancelada'], 422);
        }

        try {
            $this->pdvService->cancelSale($model, $request->input('reason', ''));

            return response()->json(['success' => true, 'message' => "Venda {$model->code} cancelada."]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro: '.$e->getMessage()], 500);
        }
    }

    // ─── HISTÓRICO DE VENDAS ──────────────────────────────
    public function history(Request $request)
    {
        if (! $this->tenantId()) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $query = PdvSale::where('tenant_id', $this->tenantId())
            ->with('customer', 'items.product', 'payments', 'creator')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->filled('is_fiado')) {
            $query->where('is_fiado', true);
        }

        $sales = $query->paginate(30);

        return view('pdv.history', compact('sales'));
    }

    // ─── API: HISTÓRICO JSON ──────────────────────────────
    public function historyApi(Request $request)
    {
        $query = PdvSale::where('tenant_id', $this->tenantId())
            ->with(['customer', 'items.product', 'payments', 'fiadoPayments', 'creator'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }
        if ($request->boolean('is_fiado')) {
            $query->where('is_fiado', true);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);
        $paginated = $query->paginate($perPage);

        return PdvSaleResource::collection($paginated);
    }

    // ─── FIADO: LISTAR PENDENTES ──────────────────────────
    public function fiadoPending()
    {
        $sales = PdvSale::where('tenant_id', $this->tenantId())
            ->where('status', 'completed')
            ->where('is_fiado', true)
            ->with('customer')
            ->latest()
            ->get()
            ->filter(fn ($s) => $s->fiado_remaining > 0);

        return PdvSaleResource::collection($sales->values());
    }

    // ─── FIADO: REGISTRAR PAGAMENTO ───────────────────────
    public function payFiado(Request $request, $sale): JsonResponse
    {
        $saleId = $this->resolveSaleId($sale);
        $model = PdvSale::where('id', $saleId)
            ->where('tenant_id', $this->tenantId())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $this->pdvService->payFiadoMultiple(
                $model,
                $request->payments,
                $request->notes
            );

            return response()->json(['success' => true, 'message' => 'Pagamento registrado!']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Erro: '.$e->getMessage()], 500);
        }
    }

    // ─── CLIENTES PDV ─────────────────────────────────────
    public function customers(): JsonResponse
    {
        $customers = PdvCustomer::where('tenant_id', $this->tenantId())
            ->select('id', 'name', 'cpf_cnpj', 'phone', 'email', 'credit_balance', 'status')
            ->orderBy('name')
            ->get();

        return response()->json($customers);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cpf_cnpj' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $customer = PdvCustomer::create([
            'tenant_id' => $this->tenantId(),
            ...$validator->validated(),
        ]);

        return response()->json(['success' => true, 'customer' => new PdvCustomerResource($customer)]);
    }

    // ─── STATS API ────────────────────────────────────────
    public function stats(): JsonResponse
    {
        return response()->json($this->pdvService->getStats($this->tenantId()));
    }

    // ─── COMPROVANTE / RECEIPT ─────────────────────────────
    public function receipt($sale)
    {
        $saleId = $this->resolveSaleId($sale);
        $model = PdvSale::where('id', $saleId)
            ->where('tenant_id', $this->tenantId())
            ->firstOrFail();

        $model->load(['items.product', 'payments', 'fiadoPayments', 'customer', 'creator']);
        $tenant = \App\Models\Tenant::find($this->tenantId());

        return view('pdv.receipt', ['sale' => $model, 'tenant' => $tenant]);
    }

    // ─── DETALHE DE VENDA (JSON) ───────────────────────────
    public function saleDetail(Request $request, $sale): JsonResponse
    {
                $saleId = $this->resolveSaleId($sale);
                Log::info("Fetching details for sale ID: {$saleId}, Tenant ID: {$this->tenantId()}");
                $model = PdvSale::where('id', $saleId)
                        ->where('tenant_id', $this->tenantId())
                        ->firstOrFail();

        $model->load(['items.product', 'payments', 'fiadoPayments', 'customer', 'creator']);

        return response()->json(new PdvSaleResource($model));
    }

    // ─── DETALHE DE CLIENTE (JSON) ────────────────────────
    public function getCustomer(Request $request, $customer): JsonResponse
    {
        $customerId = $this->resolveCustomerId($customer);
        Log::info("Fetching customer param: {$customer}, resolved ID: {$customerId}, route params: " . json_encode(request()->route()->parameters()));
        $model = PdvCustomer::where('id', $customerId)
            ->where('tenant_id', $this->tenantId())
            ->firstOrFail();

        $model->load(['sales' => fn ($q) => $q->latest()->limit(20)->with('payments')]);

        return response()->json(new PdvCustomerResource($model));
    }

    // ─── ATUALIZAR CLIENTE ────────────────────────────────
    public function updateCustomer(Request $request, $customer): JsonResponse
    {
        $model = PdvCustomer::where('id', (int) $customer)
            ->where('tenant_id', $this->tenantId())
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'cpf_cnpj' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
            'status' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $model->update($validator->validated());

        return response()->json(['success' => true, 'customer' => new PdvCustomerResource($model)]);
    }

    /**
     * Resolve com segurança o ID da venda a partir do parâmetro recebido
     * (lida com casos onde o parâmetro da rota pode ter sido deslocado)
     */
    private function resolveSaleId($sale): int
    {
        $routeSale = request()->route('sale');
        if ($routeSale instanceof \App\Models\PdvSale) {
            return $routeSale->id;
        }

        if (is_numeric($sale)) {
            return (int) $sale;
        }

        $params = request()->route() ? request()->route()->parameters() : [];
        if (is_array($params)) {
            if (array_key_exists('sale', $params) && is_numeric($params['sale'])) {
                return (int) $params['sale'];
            }
            foreach ($params as $v) {
                if (is_numeric($v)) return (int) $v;
            }
        }

        return 0;
    }

    /**
     * Resolve com segurança o ID do cliente a partir do parâmetro recebido
     */
    private function resolveCustomerId($customer): int
    {
        $routeCustomer = request()->route('customer');
        if ($routeCustomer instanceof \App\Models\PdvCustomer) {
            return $routeCustomer->id;
        }

        if (is_numeric($customer)) {
            return (int) $customer;
        }

        $params = request()->route() ? request()->route()->parameters() : [];
        if (is_array($params)) {
            if (array_key_exists('customer', $params) && is_numeric($params['customer'])) {
                return (int) $params['customer'];
            }
            foreach ($params as $v) {
                if (is_numeric($v)) return (int) $v;
            }
        }

        return 0;
    }
}
