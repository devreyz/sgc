<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Associate;
use App\Models\BankAccount;
use App\Models\GeneratedDocument;
use App\Models\Service;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceNegotiation;
use App\Models\ServiceObligation;
use App\Models\ServiceOrder;
use App\Models\ServicePaymentEvent;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Models\Tenant;
use App\Services\Services\CreateServiceOrder;
use App\Services\Services\ServiceDocumentService;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServiceHumanStatusResolver;
use App\Services\Services\ServiceNegotiationService;
use App\Services\Services\ServiceObligationAdjustmentService;
use App\Services\Services\ServicePaymentService;
use App\Services\Services\ServiceReportService;
use App\Services\Services\ServiceResourceService;
use App\Services\TemplatedPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ServiceManagementController extends Controller
{
    public function index(Request $request, Tenant $tenant, ServiceHumanStatusResolver $statuses): View
    {
        $this->allow($request, 'view_service_management');
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->with(['service', 'serviceProvider', 'associate', 'execution.obligations'])->when($request->filled('status'), fn ($q) => $q->where('operational_status', $request->string('status')))->orderByRaw("CASE operational_status WHEN 'submitted' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'scheduled' THEN 3 ELSE 4 END")->orderBy('scheduled_at')->paginate(30);

        return view('services.management-index', compact('orders', 'statuses'));
    }

    public function create(Request $request, Tenant $tenant): View
    {
        $this->allow($request, 'create_service_order');

        return view('services.management-create', ['versions' => ServiceVersion::query()->where('tenant_id', $tenant->id)->where('status', 'published')->whereHas('service', fn ($query) => $query->where('status', true))->with(['service', 'fields'])->get(), 'providers' => ServiceProvider::query()->where('tenant_id', $tenant->id)->active()->with('services:id')->orderBy('name')->get(), 'associates' => Associate::query()->where('tenant_id', $tenant->id)->orderBy('id')->get()]);
    }

    public function store(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        $request->validate(['beneficiary_name' => 'required|string|max:191', 'order_data' => 'nullable|array']);
        $this->allow($request, 'create_service_order');
        $data = $request->validate(['service_version_id' => 'required|integer', 'associate_id' => 'nullable|integer', 'service_provider_id' => 'nullable|integer', 'asset_id' => 'nullable|integer', 'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:191']);
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($data['service_version_id'])->firstOrFail();
        $order = $creator->handle($tenant->id, $version, $data + ['beneficiary_name' => $request->input('beneficiary_name'), 'order_data' => $request->input('order_data', [])], $request->user());

        return redirect()->route('services.management.show', [$tenant, $order]);
    }

    public function show(Request $request, Tenant $tenant, int $order, ServiceHumanStatusResolver $statuses): View
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        return view('services.management-show', compact('order', 'accounts', 'statuses'));
    }

    public function approve(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $this->allow($request, 'approve_service_execution');
        $data = $request->validate(['operation_key' => 'required|uuid']);
        $workflow->approve($this->order($tenant, $order)->execution, $data['operation_key'], $request->user());

        return back()->with('success', 'Execução aprovada e obrigações geradas.');
    }

    public function correction(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $this->allow($request, 'review_service_execution');
        $data = $request->validate(['operation_key' => 'required|uuid', 'reason' => 'required|string|min:5']);
        $workflow->requestCorrection($this->order($tenant, $order)->execution, $data['reason'], $data['operation_key'], $request->user());

        return back()->with('success', 'Correção solicitada.');
    }

    public function payment(Request $request, Tenant $tenant, int $obligation, ServicePaymentService $payments): RedirectResponse
    {
        $record = ServiceObligation::query()->where('tenant_id', $tenant->id)->whereKey($obligation)->firstOrFail();
        $this->allow($request, $record->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables');
        $data = $request->validate(['operation_key' => 'required|uuid', 'amount' => 'required|numeric|min:0.01', 'payment_method' => 'required|in:dinheiro,pix,transferencia,boleto,cartao,cheque,outro', 'payment_date' => 'required|date', 'bank_account_id' => 'nullable|integer']);
        $payments->record($record, (float) $data['amount'], $data['payment_method'], $data['payment_date'], $data['bank_account_id'] ?? null, $data['operation_key'], $request->user());

        return back()->with('success', $record->direction === 'payable' ? 'Pagamento ao prestador registrado.' : 'Recebimento registrado.');
    }

    public function reverse(Request $request, Tenant $tenant, int $payment, ServicePaymentService $payments): RedirectResponse
    {
        $this->allow($request, 'reverse_service_payment');
        $event = ServicePaymentEvent::query()->where('tenant_id', $tenant->id)->whereKey($payment)->firstOrFail();
        $data = $request->validate(['operation_key' => 'required|uuid', 'reason' => 'required|string|min:5']);
        $payments->reverse($event, $data['reason'], $data['operation_key'], $request->user());

        return back()->with('success', 'Pagamento estornado com movimento inverso.');
    }

    public function resource(Request $request, Tenant $tenant, int $order, ServiceResourceService $resources): RedirectResponse
    {
        $this->allow($request, 'manage_service_resources');
        $execution = $this->order($tenant, $order)->execution;
        $data = $request->validate(['operation_key' => 'required|uuid', 'resource_key' => 'required|string|max:80', 'description' => 'required|string|max:191', 'provided_by' => 'required|in:organization,provider,customer,third_party', 'effect' => 'required|in:information_only,deduct_from_receivable,add_to_receivable,reimburse_provider,create_association_expense', 'quantity' => 'nullable|numeric|min:0', 'unit' => 'nullable|string|max:30', 'unit_price' => 'nullable|numeric|min:0', 'amount' => 'nullable|numeric|min:0']);
        $operationKey = $data['operation_key'];
        unset($data['operation_key']);
        $resources->add($execution, $data, $operationKey, $request->user());

        return back()->with('success', 'Recurso registrado e incorporado à futura composição financeira.');
    }

    public function adjustment(Request $request, Tenant $tenant, int $obligation, ServiceObligationAdjustmentService $adjustments): RedirectResponse
    {
        $record = ServiceObligation::query()->where('tenant_id', $tenant->id)->whereKey($obligation)->firstOrFail();
        $this->allow($request, $record->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables');
        $data = $request->validate(['operation_key' => 'required|uuid', 'type' => 'required|string|max:40', 'amount' => 'required|numeric|not_in:0', 'reason' => 'required|string|min:5|max:500']);
        $adjustments->add($record, $data['type'], (float) $data['amount'], $data['reason'], $data['operation_key'], $request->user());

        return back()->with('success', 'Ajuste auditável registrado.');
    }

    public function reports(Request $request, Tenant $tenant, ServiceReportService $reports): View
    {
        $this->allow($request, 'view_service_reports');
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'provider_id' => 'nullable|integer', 'service_id' => 'nullable|integer', 'asset_id' => 'nullable|integer']);
        $summary = $reports->summary($tenant->id, Carbon::parse($data['from'] ?? now()->startOfMonth()), Carbon::parse($data['to'] ?? now()->endOfMonth()), $data['provider_id'] ?? null, $data['service_id'] ?? null, $data['asset_id'] ?? null);

        $providers = ServiceProvider::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);
        $services = Service::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);
        $assets = Asset::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);

        return view('services.reports', compact('summary', 'providers', 'services', 'assets'));
    }

    public function generateReport(Request $request, Tenant $tenant, ServiceReportService $reports)
    {
        $this->allow($request, 'view_service_reports');
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'provider_id' => 'nullable|integer', 'service_id' => 'nullable|integer', 'asset_id' => 'nullable|integer']);
        $summary = $reports->summary($tenant->id, Carbon::parse($data['from']), Carbon::parse($data['to']), $data['provider_id'] ?? null, $data['service_id'] ?? null, $data['asset_id'] ?? null);

        return Pdf::loadView('pdf.service-accountability', compact('summary'))->setPaper('a4', 'landscape')->download('prestacao-servicos.pdf');
    }

    public function agreements(Request $request, Tenant $tenant): View
    {
        $this->allow($request, 'manage_service_agreements');
        $obligations = ServiceObligation::query()->where('tenant_id', $tenant->id)->where('direction', 'receivable')->whereIn('status', ['open', 'partially_paid'])->with('execution.order.service')->get();
        $agreements = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->with('plan.installments')->latest()->get();

        return view('services.agreements', compact('obligations', 'agreements'));
    }

    public function negotiate(Request $request, Tenant $tenant, ServiceNegotiationService $negotiations, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate(['obligation_ids' => 'required|array|min:1', 'obligation_ids.*' => 'integer', 'installments' => 'required|integer|min:1|max:120', 'first_due_date' => 'required|date']);
        $agreement = $negotiations->create($tenant->id, $data['obligation_ids'], $data['installments'], $data['first_due_date'], $request->user());
        $documents->generate($agreement, 'service_negotiation', 'Termo '.$agreement->number, ['number' => $agreement->number, 'original_amount' => 'R$ '.number_format((float) $agreement->original_amount, 2, ',', '.'), 'negotiated_amount' => 'R$ '.number_format((float) $agreement->negotiated_amount, 2, ',', '.'), 'installments' => $agreement->plan->installments->count()], $request->user());

        return back()->with('success', 'Termo criado sem alterar as obrigações originais.');
    }

    public function generateOrderDocument(Request $request, Tenant $tenant, int $order, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $type = $request->validate(['type' => 'required|in:order,execution'])['type'];
        $subject = $type === 'order' ? $order : $order->execution;
        $variables = ['order_number' => $order->number, 'service_name' => $order->service->name, 'provider_name' => $order->provider_snapshot['name'] ?? '', 'beneficiary_name' => $order->beneficiary_snapshot['name'] ?? '', 'scheduled_at' => $order->scheduled_at?->format('d/m/Y H:i'), 'location' => $order->location, 'status' => $order->operational_status, 'quantity' => $order->execution->quantity, 'unit' => $order->execution->unit, 'validated_at' => $order->execution->validated_at?->format('d/m/Y H:i')];
        $document = $documents->generate($subject, $type === 'order' ? 'service_order' : 'service_execution', $type === 'order' ? 'Ordem '.$order->number : 'Execução '.$order->number, $variables, $request->user());

        return redirect()->route('services.management.documents.download', [$tenant, $document]);
    }

    public function document(Request $request, Tenant $tenant, int $document, TemplatedPdfService $pdf)
    {
        $this->allow($request, 'view_service_management');
        $record = GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereKey($document)->firstOrFail();

        return $pdf->generateFrozenDocument($record)->download(str($record->title)->slug().'.pdf');
    }

    public function evidence(Request $request, Tenant $tenant, int $evidence)
    {
        $this->allow($request, 'view_service_management');
        $record = ServiceExecutionEvidence::query()->where('tenant_id', $tenant->id)->whereKey($evidence)->with('document')->firstOrFail();
        abort_unless($record->document->tenant_id === $tenant->id && $record->document->disk === 'local', 403);

        return Storage::disk('local')->download($record->document->path, $record->document->original_name);
    }

    private function order(Tenant $tenant, int $id): ServiceOrder
    {
        return ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->whereKey($id)->with(['service', 'serviceVersion', 'serviceProvider', 'associate', 'execution.evidences.document', 'execution.resources', 'execution.compositionLines', 'execution.obligations.allocations.paymentEvent'])->firstOrFail();
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->checkPermissionTo($permission), 403);
    }
}
