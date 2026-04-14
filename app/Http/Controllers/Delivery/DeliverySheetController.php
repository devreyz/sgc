<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerProductPrice;
use App\Models\Product;
use App\Models\Tenant;
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
            ->get(['id', 'name', 'unit', 'sale_price']);

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

        $products = Product::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'sale_price']);

        // Preços específicos deste cliente
        $customPrices = CustomerProductPrice::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->whereNull('project_id')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->get()
            ->keyBy('product_id');

        $result = $products->map(function ($p) use ($customPrices) {
            $override = $customPrices->get($p->id);
            return [
                'id'         => $p->id,
                'name'       => $p->name,
                'unit'       => $p->unit,
                'sale_price' => $override ? (float) $override->sale_price : (float) $p->sale_price,
                'has_custom' => (bool) $override,
            ];
        });

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
            'customer_id'  => 'required|integer',
            'product_ids'  => 'required|array|min:1',
            'product_ids.*' => 'integer',
            'sheet_date'   => 'nullable|date',
        ]);

        $tenantModel = $this->currentTenant();

        $customer = Customer::where('tenant_id', $tenantId)
            ->findOrFail($request->customer_id);

        $productIds = $request->product_ids;

        $products = Product::where('tenant_id', $tenantId)
            ->whereIn('id', $productIds)
            ->orderBy('name')
            ->get(['id', 'name', 'unit', 'sale_price']);

        // Preços específicos por cliente
        $customPrices = CustomerProductPrice::where('tenant_id', $tenantId)
            ->where('customer_id', $customer->id)
            ->whereNull('project_id')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            })
            ->get()
            ->keyBy('product_id');

        // Monta itens com preço resolvido
        $items = $products->map(function ($p) use ($customPrices) {
            $override = $customPrices->get($p->id);
            return [
                'id'         => $p->id,
                'name'       => $p->name,
                'unit'       => $p->unit,
                'sale_price' => $override ? (float) $override->sale_price : (float) $p->sale_price,
            ];
        })->values()->all();

        $sheetDate = $request->sheet_date
            ? \Carbon\Carbon::parse($request->sheet_date)->format('d/m/Y')
            : now()->format('d/m/Y');

        $pdf = Pdf::loadView('pdf.delivery-sheet', [
            'tenant'    => $tenantModel,
            'customer'  => $customer,
            'items'     => $items,
            'sheetDate' => $sheetDate,
        ])
        ->setPaper('a4', 'landscape')
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
