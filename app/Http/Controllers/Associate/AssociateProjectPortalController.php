<?php

namespace App\Http\Controllers\Associate;

use App\Enums\DeliveryStatus;
use App\Enums\ReceiptStatus;
use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\AssociateReceipt;
use App\Models\AssociateReceiptPayment;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\AssociateFinancialSummaryService;
use App\Services\AssociateProjectLimitService;
use App\Services\ReceiptDataBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssociateProjectPortalController extends Controller
{
    public function __construct(
        private readonly AssociateProjectLimitService $limits,
        private readonly AssociateFinancialSummaryService $financial,
    ) {
        $this->middleware(['auth', 'role:associado']);
    }

    public function show(Request $request)
    {
        [$project, $associate] = $this->context($request);

        return view('associate.project-workspace', compact('project', 'associate'));
    }

    public function data(Request $request): JsonResponse
    {
        [$project, $associate] = $this->context($request);

        return match ((string) $request->route('section')) {
            'summary' => response()->json($this->summary($project, $associate)),
            'limits' => response()->json([
                'summary' => $this->limits->summary($project, $associate),
                'products' => $project->allow_any_product
                    ? $this->limits->productLimits($project, $associate)
                    : $this->limits->eligibleProducts($project, $associate),
                'catalog_open' => (bool) $project->allow_any_product,
            ]),
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

        $data = ReceiptDataBuilder::fromDeliveries($distributions, null, $project);
        $tenant = $request->route('tenant') instanceof Tenant
            ? $request->route('tenant')
            : Tenant::findOrFail($project->tenant_id);
        $pdf = Pdf::loadView('pdf.project-associate-receipt', [
            'tenant' => $tenant,
            'project' => $project,
            'associate' => $associate,
            'receipt' => $receipt,
            'summary' => $data['summary'],
            'productsSummary' => $data['productsSummary'],
            'hasRoundingDivergence' => $data['hasRoundingDivergence'],
            'feeBreakdown' => $data['feeBreakdown'],
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(
            fn () => print $pdf->output(),
            'comprovante-'.str_replace('/', '-', $receipt->formatted_number).'-'.Str::slug($associate->display_name).'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
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
            ],
            'total_gross' => $financial['total_gross'],
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
            ->with(['product:id,name,unit', 'distributions:id,parent_delivery_id,customer_id,quantity,unit_price,gross_value,associate_receipt_id', 'distributions.customer:id,name,trade_name']);
        $this->filters($query, $request);
        $page = $query->orderByDesc('delivery_date')->orderByDesc('id')->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(function (ProductionDelivery $item) {
            $distributed = (float) $item->distributions->sum('quantity');
            return [
                'id' => $item->id,
                'date' => $item->delivery_date?->format('d/m/Y'),
                'product' => $item->product?->name,
                'unit' => $item->product?->unit,
                'quantity' => (float) $item->quantity,
                'distributed' => $distributed,
                'remaining' => max(0, (float) $item->quantity - $distributed),
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
            ->where('associate_id', $associate->id);
        $currentReceipt = (clone $baseQuery)
            ->where('status', '!=', ReceiptStatus::OBSOLETE->value)
            ->orderByDesc('receipt_year')->orderByDesc('receipt_number')->first(['id', 'receipt_year', 'receipt_number']);
        $page = $baseQuery
            ->withSum('payments', 'amount')
            ->orderByDesc('receipt_year')->orderByDesc('receipt_number')->orderByDesc('issued_at')
            ->paginate($this->perPage($request));
        $page->setCollection(collect($page->items())->map(fn (AssociateReceipt $receipt) => [
            'id' => $receipt->id,
            'number' => $receipt->formatted_number,
            'date' => $receipt->issued_at?->format('d/m/Y'),
            'gross' => (float) $receipt->total_gross,
            'fees' => (float) $receipt->total_fees,
            'net' => (float) $receipt->total_net,
            'paid' => (float) ($receipt->payments_sum_amount ?? $receipt->amount_paid ?? 0),
            'remaining' => $receipt->remaining_amount,
            'status' => $receipt->status?->value,
            'status_label' => $receipt->status?->getLabel(),
            'obsolete_reason' => $receipt->obsolete_reason,
            'current_receipt' => $receipt->status === ReceiptStatus::OBSOLETE ? $currentReceipt?->formatted_number : null,
            'download_url' => $receipt->status === ReceiptStatus::OBSOLETE ? null : route('associate.projects.receipts.download', [
                'tenant' => request()->route('tenant'),
                'project' => $project->id,
                'receipt' => $receipt->id,
            ]),
        ]));

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
