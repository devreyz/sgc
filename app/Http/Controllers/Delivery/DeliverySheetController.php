<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\PricingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DeliverySheetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'any.role:registrador_entregas,financeiro,admin']);
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
     * UI de seleção de cliente e produtos para gerar ficha.
     */
    public function index()
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return redirect()->route('home')->with('error', 'Selecione uma organização primeiro.');
        }

        $tenant = $this->currentTenant();

        $customers = Customer::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'trade_name']);

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        return view('delivery.sheet-selector', compact('tenant', 'customers', 'products'));
    }

    /**
     * API: retorna produtos com preços para um cliente específico.
     */
    public function productsForCustomer(Request $request, $tenant, $customer)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            return response()->json(['error' => 'Sem tenant'], 403);
        }

        $customerId = is_object($customer) ? $customer->id : $customer;

        $customerModel = Customer::where('tenant_id', $tenantId)
            ->with('priceTable.items')
            ->find($customerId);

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        $pricingService = app(PricingService::class);

        $result = $products->map(function ($p) use ($pricingService, $customerModel) {
            $pricing = $pricingService->resolvePrice($p, $customerModel);
            $price   = (float) $pricing['sale_price'];
            return [
                'id'         => $p->id,
                'name'       => $p->name,
                'unit'       => $p->unit,
                'sale_price' => $price,
                'has_custom' => $pricing['source'] === 'price_table',
            ];
        })->filter(fn ($item) => $item['sale_price'] > 0);

        return response()->json($result->values());
    }

    /**
     * Gera o PDF da ficha de entrega e faz download.
     */
    public function generate(Request $request, $tenant)
    {
        $tenantId = session('tenant_id');
        if (! $tenantId) {
            abort(403);
        }

        $request->validate([
            'customer_id'   => 'required|integer',
            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'integer',
            'sheet_date'    => 'nullable|date',
            'layout'        => 'nullable|in:landscape,portrait',
        ]);

        $tenantModel = $this->currentTenant();

        $customer = Customer::where('tenant_id', $tenantId)
            ->with('priceTable.items')
            ->findOrFail($request->customer_id);

        $productIds = $request->product_ids;

        $products = Product::where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'unit']);

        $pricingService = app(PricingService::class);

        // Monta itens com preço resolvido via PriceTable — exclui sem preço
        $items = $products->map(function ($p) use ($pricingService, $customer) {
            $pricing = $pricingService->resolvePrice($p, $customer);
            return [
                'id'         => $p->id,
                'name'       => $p->name,
                'unit'       => $p->unit,
                'sale_price' => (float) $pricing['sale_price'],
            ];
        })->filter(fn ($item) => $item['sale_price'] > 0)->values()->all();

        $sheetDate = $request->sheet_date
            ? \Carbon\Carbon::parse($request->sheet_date)->format('d/m/Y')
            : now()->format('d/m/Y');

        $layout = $request->input('layout', 'landscape'); // 'landscape' | 'portrait'

        $pdf = Pdf::loadView('pdf.delivery-sheet', [
            'tenant'    => $tenantModel,
            'customer'  => $customer,
            'items'     => $items,
            'sheetDate' => $sheetDate,
            'layout'    => $layout,
        ])
        ->setPaper('a4', $layout)
        ->setOption('defaultFont', 'DejaVu Sans')
        ->setOption('isHtml5ParserEnabled', true)
        ->setOption('isRemoteEnabled', true);

        $safeName    = \Illuminate\Support\Str::slug($customer->name);
        $dateSlug    = now()->format('Ymd');
        $filename    = "ficha-entrega-{$safeName}-{$dateSlug}.pdf";

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
