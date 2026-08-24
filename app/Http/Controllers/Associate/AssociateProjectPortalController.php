<?php

namespace App\Http\Controllers\Associate;

use App\Enums\DeliveryStatus;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\ProductionDelivery;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\AssociateFinancialSummaryService;
use App\Services\AssociateProjectLimitService;
use App\Services\ProjectDemandService;
use App\Services\ReceiptDataBuilder;
use App\Services\TemplatedPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssociateProjectPortalController extends Controller
{
    public function __construct(
        private readonly AssociateProjectLimitService $limits,
        private readonly AssociateFinancialSummaryService $financial,
        private readonly ProjectDemandService $demands,
    ) {
        $this->middleware(['auth', 'role:associado']);
    }

    public function show(Request $request)
    {
        [$project, $associate] = $this->context($request);

        return view('associate.project-workspace', compact('project', 'associate'));
    }

    public function simulatorPage(Request $request)
    {
        [$project, $associate] = $this->context($request);
        $historyScope = substr(hash_hmac(
            'sha256',
            implode(':', [$project->tenant_id, $project->id, $request->user()->id]),
            (string) config('app.key'),
        ), 0, 32);

        return view('associate.project-simulator', compact('project', 'associate', 'historyScope'));
    }

    public function data(Request $request): JsonResponse
    {
        [$project, $associate] = $this->context($request);

        return match ((string) $request->route('section')) {
            'summary' => response()->json($this->summary($project, $associate)),
            'limits' => response()->json([
                'summary' => $this->limits->summary($project, $associate),
                'products' => $this->limits->eligibleProducts($project, $associate),
                'catalog_open' => (bool) $project->allow_any_product,
            ]),
            'prices' => response()->json($this->prices($request, $project)),
            'simulator' => response()->json($this->simulator($project, $associate)),
            'deliveries' => response()->json($this->deliveries($request, $project, $associate)),
            'distributions' => response()->json($this->distributions($request, $project, $associate)),
            'receipts' => response()->json($this->receipts($request, $project, $associate)),
            'payments' => response()->json($this->payments($request, $project, $associate)),
            default => response()->json(['message' => 'Secao nao encontrada.'], 404),
        };
    }

    public function downloadReceipt(Request $request)
    {
        [$project, $associate] = $this->context($request);
        $receipt = AssociateReceipt::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->findOrFail((int) $request->route('receipt'));

        abort_if($receipt->status === ReceiptStatus::OBSOLETE, 409, 'Este comprovante esta obsoleto e nao pode ser usado como documento vigente.');

        $distributions = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->where('associate_receipt_id', $receipt->id)
            ->whereNotNull('parent_delivery_id')
            ->where('status', DeliveryStatus::APPROVED->value)
            ->with(['product', 'customer', 'parentDelivery'])
            ->orderBy('delivery_date')
            ->get();

        abort_if($distributions->isEmpty(), 404, 'O comprovante nao possui distribuicoes validas.');

        $data = ReceiptDataBuilder::fromDeliveries(
            $distributions,
            null,
            $project,
            $receipt->fee_snapshot,
        );
        $tenant = $request->route('tenant') instanceof Tenant
            ? $request->route('tenant')
            : Tenant::findOrFail($project->tenant_id);
        $pdfService = app(TemplatedPdfService::class);
        $pdf = $pdfService->generateSystemPdf('pdf.associate-portal-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $data['summary'],
            'productsSummary' => $data['productsSummary'],
            'hasRoundingDivergence' => $data['hasRoundingDivergence'],
            'feeBreakdown' => $data['feeBreakdown'],
            'feeColumns' => $data['feeColumns'],
        ], $pdfService->systemPdfOptions(
            'pdf.associate-portal-receipt',
            'Comprovante do '.($tenant->associateTerm() ?: 'Membro'),
            $project->type,
            (int) $project->tenant_id,
        ));

        $filename = 'comprovante-'.str_replace('/', '-', $receipt->formatted_number).'-'.Str::slug($associate->display_name).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function context(Request $request): array
    {
        $tenantId = (int) session('tenant_id');
        abort_unless($tenantId > 0, 403);

        $associate = Associate::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $project = SalesProject::query()
            ->where('tenant_id', $tenantId)
            ->with(['customer.priceTable', 'customers.priceTable'])
            ->findOrFail((int) $request->route('project'));
        $this->limits->assertContext($project, $associate);

        return [$project, $associate];
    }

    private function summary(SalesProject $project, Associate $associate): array
    {
        $financial = $this->financial->summary($project->tenant_id, $associate->id, $project->id);
        $base = $this->limits->summary($project, $associate);

        return $base + [
            'project' => [
                'title' => $project->title,
                'status' => $project->status?->value,
                'status_label' => $project->status?->getLabel(),
                'period' => trim(($project->start_date?->format('d/m/Y') ?? '').' - '.($project->end_date?->format('d/m/Y') ?? ''), ' -'),
                'payment_forecast' => $project->member_payment_forecast_date?->format('d/m/Y'),
                'payment_forecast_note' => $project->member_payment_forecast_note,
            ],
            'total_gross' => $financial['total_gross'],
            'total_fees' => $financial['total_fees'],
            'effective_fee_percentage' => $financial['total_gross'] > 0
                ? ($financial['total_fees'] / $financial['total_gross']) * 100
                : 0,
            'total_net' => $financial['total_net'],
            'paid' => $financial['paid'],
            'receivable' => $financial['receivable'],
            'unbilled' => $financial['unbilled'],
        ];
    }

    private function deliveries(Request $request, SalesProject $project, Associate $associate): array
    {
        $query = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNull('parent_delivery_id')
            ->with([
                'product:id,name,unit',
                'distributions' => fn ($query) => $query
                    ->where('status', DeliveryStatus::APPROVED->value)
                    ->select([
                        'id', 'tenant_id', 'sales_project_id', 'associate_id', 'parent_delivery_id', 'customer_id', 'quantity', 'unit_price',
                        'gross_value', 'admin_fee_amount', 'net_value', 'associate_receipt_id',
                    ]),
                'distributions.associateReceipt',
                'distributions.customer:id,name,trade_name',
            ]);
        $this->filters($query, $request);
        $page = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(function (ProductionDelivery $item) use ($project) {
            $distributed = (float) $item->distributions->sum('quantity');
            $financial = $this->financial->resolveDistributions($item->distributions, $project);

            return [
                'id' => $item->id,
                'date' => $item->delivery_date?->format('d/m/Y'),
                'product' => $item->product?->name,
                'unit' => $item->product?->unit,
                'quantity' => (float) $item->quantity,
                'distributed' => $distributed,
                'remaining' => max(0, (float) $item->quantity - $distributed),
                'distribution_count' => $item->distributions->count(),
                'gross' => $financial['gross'],
                'fees' => $financial['fees'],
                'net' => $financial['net'],
                'status' => $item->status?->value,
                'status_label' => $item->status?->getLabel(),
                'quality' => $item->quality_grade,
                'notes' => $item->notes,
                'rejection_reason' => $item->quality_notes,
                'distributions' => $item->distributions->map(fn ($distribution) => [
                    'customer' => $distribution->customer?->trade_name ?: $distribution->customer?->name,
                    'quantity' => (float) $distribution->quantity,
                    'unit_price' => (float) $distribution->unit_price,
                    'gross' => (float) $distribution->gross_value,
                ])->values(),
            ];
        }));

        return $page->toArray();
    }

    private function prices(Request $request, SalesProject $project): array
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:30',
        ]);
        $search = Str::lower(trim((string) ($validated['search'] ?? '')));
        $catalog = $this->demands->catalog($project)
            ->when($search !== '', fn ($items) => $items->filter(function (array $item) use ($search): bool {
                $haystack = Str::lower($item['product_name'].' '.collect($item['destinations'])->pluck('customer')->implode(' '));

                return Str::contains(Str::ascii($haystack), Str::ascii($search));
            }))
            ->values();
        $perPage = min(30, max(5, (int) ($validated['per_page'] ?? 15)));
        $page = max(1, (int) ($validated['page'] ?? 1));
        $lastPage = max(1, (int) ceil($catalog->count() / $perPage));
        $page = min($page, $lastPage);

        return [
            'data' => $catalog->forPage($page, $perPage)->values(),
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $catalog->count(),
            'from' => $catalog->isEmpty() ? null : (($page - 1) * $perPage) + 1,
            'to' => $catalog->isEmpty() ? null : min($page * $perPage, $catalog->count()),
        ];
    }

    private function simulator(SalesProject $project, Associate $associate): array
    {
        $summary = $this->limits->summary($project, $associate);
        $eligible = $this->limits->eligibleProducts($project, $associate)->keyBy('product_id');
        $configured = $this->limits->productLimits($project, $associate)->keyBy('product_id');
        $configuredProductIds = ProjectDemand::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->pluck('product_id')
            ->merge($configured->keys())
            ->map(fn ($id) => (int) $id)
            ->unique();
        $fullCatalog = $this->demands->catalog($project);
        $catalog = $fullCatalog
            ->map(function (array $item) use ($eligible, $configured, $configuredProductIds): array {
                $productId = (int) $item['product_id'];
                $limit = $configured->get($productId);
                $availability = $eligible->get($productId);
                $deliveryEnabled = $configuredProductIds->contains($productId);

                return $item + [
                    // A product may be priced for a destination without being enabled for delivery.
                    // It remains available for a planning simulation, but is visually secondary.
                    'configured' => $deliveryEnabled,
                    'delivery_enabled' => $deliveryEnabled,
                    'configured_limit' => $limit['maximum_quantity'] ?? $availability['associate_limit'] ?? null,
                    'remaining_quantity' => $limit['remaining_quantity'] ?? $availability['remaining_quantity'] ?? null,
                    'delivered_quantity' => $limit['delivered_quantity'] ?? $availability['associate_delivered'] ?? 0,
                ];
            })
            ->sortBy(fn (array $item) => ($item['configured'] ? '0:' : '1:').Str::lower($item['product_name']))
            ->take(250)
            ->values();

        return [
            'summary' => [
                'financial_limit' => $summary['financial_limit'],
                'financial_consumed' => $summary['financial_consumed'],
                'financial_remaining' => $summary['financial_remaining'],
            ],
            'products' => $catalog,
            'delivery_enabled_total' => $catalog->where('delivery_enabled', true)->count(),
            'total_products' => $fullCatalog->count(),
            'catalog_truncated' => $fullCatalog->count() > $catalog->count(),
        ];
    }

    private function distributions(Request $request, SalesProject $project, Associate $associate): array
    {
        $query = ProductionDelivery::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->whereNotNull('parent_delivery_id')
            ->with(['product:id,name,unit', 'customer:id,name,trade_name', 'associateReceipt:id,receipt_year,receipt_number,status']);
        $this->filters($query, $request);
        $page = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(fn (ProductionDelivery $item) => [
            'id' => $item->id,
            'date' => $item->delivery_date?->format('d/m/Y'),
            'product' => $item->product?->name,
            'unit' => $item->product?->unit,
            'customer' => $item->customer?->trade_name ?: $item->customer?->name,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'gross' => (float) $item->gross_value,
            'receipt' => $item->associateReceipt?->formatted_number,
            'status' => $item->billing_status?->value,
        ]));

        return $page->toArray();
    }

    private function receipts(Request $request, SalesProject $project, Associate $associate): array
    {
        $baseQuery = AssociateReceipt::query()
            ->where('tenant_id', $project->tenant_id)
            ->where('sales_project_id', $project->id)
            ->where('associate_id', $associate->id)
            ->with([
                'project',
                'payments',
                'distributions' => fn ($query) => $query
                    ->whereNotNull('parent_delivery_id')
                    ->where('status', DeliveryStatus::APPROVED->value),
            ]);
        $currentReceipt = (clone $baseQuery)
            ->where('status', '!=', ReceiptStatus::OBSOLETE->value)
            ->orderByDesc('receipt_year')->orderByDesc('receipt_number')->first();
        $page = $baseQuery
            ->withSum('payments', 'amount')
            ->orderByDesc('receipt_year')->orderByDesc('receipt_number')->orderByDesc('issued_at')
            ->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(function (AssociateReceipt $receipt) use ($currentReceipt, $project) {
            $totals = $this->financial->receiptTotals($receipt);

            return [
                'id' => $receipt->id,
                'number' => $receipt->formatted_number,
                'date' => $receipt->issued_at?->format('d/m/Y'),
                'gross' => $totals['gross'],
                'fees' => $totals['fees'],
                'net' => $totals['net'],
                'paid' => $totals['paid'],
                'remaining' => $totals['remaining'],
                'distribution_count' => $totals['distribution_count'],
                'status' => $receipt->status?->value,
                'status_label' => $receipt->status?->getLabel(),
                'obsolete_reason' => $receipt->obsolete_reason,
                'current_receipt' => $receipt->status === ReceiptStatus::OBSOLETE ? $currentReceipt?->formatted_number : null,
                'preview_url' => $receipt->status === ReceiptStatus::OBSOLETE ? null : route('associate.projects.receipts.download', [
                    'tenant' => request()->route('tenant'),
                    'project' => $project->id,
                    'receipt' => $receipt->id,
                ]),
            ];
        }));

        return $page->toArray();
    }

    private function payments(Request $request, SalesProject $project, Associate $associate): array
    {
        $page = AssociateReceiptPayment::query()
            ->where('tenant_id', $project->tenant_id)
            ->whereHas('receipt', fn (Builder $query) => $query
                ->where('sales_project_id', $project->id)
                ->where('associate_id', $associate->id))
            ->with('receipt:id,receipt_year,receipt_number')
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(fn ($payment) => [
            'id' => $payment->id,
            'receipt' => $payment->receipt?->formatted_number,
            'date' => $payment->payment_date?->format('d/m/Y'),
            'amount' => (float) $payment->amount,
            'method' => $payment->payment_method,
        ]));

        return $page->toArray();
    }

    private function filters(Builder $query, Request $request): void
    {
        $request->validate([
            'status' => 'nullable|string|max:30',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:50',
        ]);
        $query->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn (Builder $q) => $q->whereHas('product', fn (Builder $p) => $p->where('name', 'like', '%'.$request->string('search').'%')));
    }

    private function perPage(Request $request): int
    {
        return min(50, max(5, $request->integer('per_page', 15)));
    }
}
