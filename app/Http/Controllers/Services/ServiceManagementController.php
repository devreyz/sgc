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
use App\Models\ServicePaymentPlanInstallment;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Models\Tenant;
use App\Services\Services\CreateServiceOrder;
use App\Services\Services\ServiceDocumentService;
use App\Services\Services\ServiceEvidenceService;
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
use Illuminate\Support\Facades\DB;
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

        return view('services.management-create', ['versions' => ServiceVersion::query()->where('tenant_id', $tenant->id)->where('status', 'published')->whereHas('service', fn ($query) => $query->where('status', true))->with(['service', 'fields'])->get(), 'providers' => ServiceProvider::query()->where('tenant_id', $tenant->id)->active()->with('services:id')->orderBy('name')->get(), 'associates' => Associate::query()->where('tenant_id', $tenant->id)->active()->orderBy('id')->get()]);
    }

    public function store(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        $this->allow($request, 'create_service_order');
        $data = $request->validate(['service_version_id' => 'required|integer', 'associate_id' => 'nullable|integer', 'beneficiary_name' => 'nullable|string|max:191|required_without:associate_id', 'service_provider_id' => 'nullable|integer', 'asset_id' => 'nullable|integer', 'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:191', 'order_data' => 'nullable|array']);
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($data['service_version_id'])->firstOrFail();
        $order = $creator->handle($tenant->id, $version, $data + ['order_data' => $request->input('order_data', [])], $request->user());

        return redirect()->route('services.management.show', [$tenant, $order]);
    }

    public function show(Request $request, Tenant $tenant, int $order, ServiceHumanStatusResolver $statuses): View
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        return view('services.management-show', compact('order', 'accounts', 'statuses'));
    }

    public function execute(Request $request, Tenant $tenant, int $order): View
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        $view = app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->show($request, $tenant, $order);
        $view->with('managementExecution', true);

        return $view;
    }

    public function startExecution(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence)
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        return app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->start($request, $tenant, $order, $workflow, $evidence);
    }

    public function draftExecution(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence)
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        return app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->draft($request, $tenant, $order, $workflow, $evidence);
    }

    public function submitExecution(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence)
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        return app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->submit($request, $tenant, $order, $workflow, $evidence);
    }

    public function approveExecution(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow)
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        return app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->approve($request, $tenant, $order, $workflow);
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
        $data = $request->validate(['operation_key' => 'required|uuid', 'amount' => 'required|numeric|min:0.01', 'payment_method' => 'required|in:dinheiro,pix,transferencia,boleto,cartao,outro', 'payment_date' => 'required|date', 'bank_account_id' => 'required|integer']);
        $payments->record($record, (float) $data['amount'], $data['payment_method'], $data['payment_date'], (int) $data['bank_account_id'], $data['operation_key'], $request->user());

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
        $agreements = ServiceNegotiation::query()->where('tenant_id', $tenant->id)
            ->with(['plan.installments.paymentEvent', 'generatedDocument', 'creator'])
            ->latest()->get();
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        return view('services.agreements', compact('obligations', 'agreements', 'accounts'));
    }

    public function negotiate(Request $request, Tenant $tenant, ServiceNegotiationService $negotiations, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate([
            'obligation_ids' => 'required|array|min:1',
            'obligation_ids.*' => 'integer',
            'installments' => 'required|array|min:1|max:120',
            'installments.*.kind' => 'required|in:entry,installment',
            'installments.*.due_date' => 'required|date',
            'installments.*.amount' => 'required|numeric|min:0.01',
        ]);
        $agreement = DB::transaction(function () use ($tenant, $data, $request, $negotiations, $documents): ServiceNegotiation {
            $agreement = $negotiations->create($tenant->id, $data['obligation_ids'], $data['installments'], $request->user());
            $document = $documents->generate($agreement, 'service_negotiation', 'Termo '.$agreement->number, $this->negotiationDocumentVariables($tenant, $agreement), $request->user());
            $agreement->update(['generated_document_id' => $document->id]);

            return $agreement;
        });

        return redirect()->route('services.management.documents.download', [$tenant, $agreement->generated_document_id]);
    }

    public function payAgreementInstallment(Request $request, Tenant $tenant, int $agreement, int $installment, ServicePaymentService $payments): RedirectResponse
    {
        $this->allow($request, 'manage_service_receivables');
        $agreement = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->whereKey($agreement)->firstOrFail();
        $installment = ServicePaymentPlanInstallment::query()->where('tenant_id', $tenant->id)
            ->where('service_payment_plan_id', $agreement->payment_plan_id)->whereKey($installment)->firstOrFail();
        $data = $request->validate([
            'operation_key' => 'required|uuid',
            'payment_method' => 'required|in:dinheiro,pix,transferencia,boleto,cartao,outro',
            'payment_date' => 'required|date',
            'bank_account_id' => 'required|integer',
        ]);
        $payments->recordInstallment($installment, $data['payment_method'], $data['payment_date'], (int) $data['bank_account_id'], $data['operation_key'], $request->user());

        return back()->with('success', ($installment->kind === 'entry' ? 'Entrada' : 'Parcela').' recebida e conciliada com as obrigações.');
    }

    public function regenerateAgreementDocument(Request $request, Tenant $tenant, int $agreement, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $agreement = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->whereKey($agreement)->with('plan.installments')->firstOrFail();
        $document = $documents->generate($agreement, 'service_negotiation', 'Termo '.$agreement->number, $this->negotiationDocumentVariables($tenant, $agreement), $request->user());
        $agreement->update(['generated_document_id' => $document->id]);

        return redirect()->route('services.management.documents.download', [$tenant, $document]);
    }

    public function generateOrderDocument(Request $request, Tenant $tenant, int $order, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $type = $request->validate(['type' => 'required|in:order,execution'])['type'];
        $subject = $type === 'order' ? $order : $order->execution;
        $variables = ['order_number' => $order->number, 'service_name' => $order->service->name, 'provider_name' => $order->provider_snapshot['name'] ?? '', 'beneficiary_name' => $order->beneficiary_snapshot['name'] ?? '', 'scheduled_at' => $order->scheduled_at?->format('d/m/Y H:i'), 'location' => $order->location, 'status' => $order->operational_status, 'quantity' => $order->execution->quantity, 'unit' => $order->execution->unit, 'validated_at' => $order->execution->validated_at?->format('d/m/Y H:i')]
            + $this->executionDocumentVariables($order);
        $document = $documents->generate($subject, $type === 'order' ? 'service_order' : 'service_execution', $type === 'order' ? 'Ordem '.$order->number : 'Execução '.$order->number, $variables, $request->user());

        return redirect()->route('services.management.documents.download', [$tenant, $document]);
    }

    public function document(Request $request, Tenant $tenant, int $document, TemplatedPdfService $pdf)
    {
        $this->allow($request, 'view_service_management');
        $record = GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereKey($document)->firstOrFail();

        return $pdf->generateFrozenDocument($record)->download(str($record->title)->slug().'.pdf');
    }

    public function evidence(Request $request, Tenant $tenant, int $evidence, ServiceEvidenceService $files)
    {
        $this->allow($request, 'view_service_management');
        $record = ServiceExecutionEvidence::query()->where('tenant_id', $tenant->id)->whereKey($evidence)->with('document')->firstOrFail();
        abort_unless($record->document->tenant_id === $tenant->id, 403);

        return response($files->contents($record->document), 200, ['Content-Type' => $record->document->mime_type, 'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $record->document->original_name).'"']);
    }

    private function order(Tenant $tenant, int $id): ServiceOrder
    {
        return ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->whereKey($id)->with(['service', 'serviceVersion', 'serviceProvider', 'associate', 'execution.evidences.document', 'execution.resources', 'execution.compositionLines', 'execution.obligations.allocations.paymentEvent'])->firstOrFail();
    }

    private function executionDocumentVariables(ServiceOrder $order): array
    {
        $execution = $order->execution;
        $variables = [];
        $fieldRows = '';

        foreach ((array) data_get($execution->catalog_snapshot, 'fields', []) as $field) {
            if (! ($field['include_in_documents'] ?? false)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $value = data_get($execution->values, $key);
            if (($field['type'] ?? null) === 'file') {
                $value = $execution->evidences->where('field_key', $key)->pluck('document.original_name')->filter()->join(', ');
            } elseif (is_bool($value)) {
                $value = $value ? 'Sim' : 'Não';
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = filled($value) ? (string) $value : '—';
            $variables['campo.'.$key] = $value;
            $fieldRows .= '<tr><td>'.e($field['label'] ?? $key).'</td><td>'.e($value).'</td><td>'.e($field['unit'] ?? '').'</td></tr>';
        }

        $composition = ['receivable' => '', 'payable' => ''];
        foreach ($execution->compositionLines as $line) {
            $formula = data_get($line->rule_snapshot, 'formula') ?: trim(($line->quantity ?? '').' × '.($line->unit_price ?? ''));
            $composition[$line->direction] .= '<tr><td>'.e($line->description).'</td><td>'.e($formula).'</td><td style="text-align:right">'.($line->amount < 0 ? '− ' : '').'R$ '.number_format(abs((float) $line->amount), 2, ',', '.').'</td></tr>';
        }

        $table = static fn (string $rows): string => $rows === ''
            ? '<p>Nenhum item.</p>'
            : '<table style="width:100%;border-collapse:collapse"><thead><tr><th>Descrição</th><th>Fórmula</th><th>Total</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        return $variables + [
            'campos_execucao' => $fieldRows === '' ? '<p>Nenhum campo adicional informado.</p>' : '<table style="width:100%;border-collapse:collapse"><thead><tr><th>Campo</th><th>Valor</th><th>Unidade</th></tr></thead><tbody>'.$fieldRows.'</tbody></table>',
            'composicao_receber' => $table($composition['receivable']),
            'composicao_pagar' => $table($composition['payable']),
            'total_receber' => 'R$ '.number_format((float) $execution->compositionLines->where('direction', 'receivable')->sum('amount'), 2, ',', '.'),
            'total_pagar' => 'R$ '.number_format((float) $execution->compositionLines->where('direction', 'payable')->sum('amount'), 2, ',', '.'),
        ];
    }

    private function negotiationDocumentVariables(Tenant $tenant, ServiceNegotiation $agreement): array
    {
        $agreement->loadMissing(['plan.obligations.execution.order.service', 'plan.installments']);
        $snapshot = (array) $agreement->terms_snapshot;
        $liveObligations = $agreement->plan?->obligations ?? collect();
        $party = (array) (data_get($snapshot, 'party')
            ?: $liveObligations->pluck('party_snapshot')->filter()->first()
            ?: $liveObligations->pluck('execution.order.beneficiary_snapshot')->filter()->first()
            ?: []);
        $obligationItems = collect(data_get($snapshot, 'obligations', []));
        if ($obligationItems->isEmpty() || $obligationItems->contains(fn (array $item): bool => blank($item['order_number'] ?? null))) {
            $obligationItems = $liveObligations->map(fn (ServiceObligation $obligation): array => [
                'number' => $obligation->number,
                'order_number' => $obligation->execution?->order?->number,
                'service' => $obligation->execution?->order?->service?->name,
                'included_amount' => $obligation->pivot?->included_amount ?? $obligation->balance,
            ]);
        }
        $obligationRows = $obligationItems->map(fn (array $item): string => '<tr><td>'.e($item['number'] ?? '—').'</td><td>'.e($item['order_number'] ?? '—').'</td><td>'.e($item['service'] ?? '—').'</td><td class="money">R$ '.number_format((float) ($item['included_amount'] ?? $item['balance'] ?? 0), 2, ',', '.').'</td></tr>')->implode('');
        $installmentRows = $agreement->plan?->installments?->map(function (ServicePaymentPlanInstallment $item): string {
            $kind = $item->kind === 'entry' ? 'Entrada' : 'Parcela '.$item->number;
            $status = $item->status === 'paid' ? 'Recebida em '.$item->paid_at?->format('d/m/Y') : 'Pendente';

            return '<tr><td>'.e($kind).'</td><td>'.e($item->due_date?->format('d/m/Y') ?? '—').'</td><td style="text-align:right">R$ '.number_format((float) $item->amount, 2, ',', '.').'</td><td>'.e($status).'</td></tr>';
        })->implode('') ?: collect(data_get($snapshot, 'installments', []))->map(fn (array $item): string => '<tr><td>'.e(($item['kind'] ?? null) === 'entry' ? 'Entrada' : 'Parcela '.($item['number'] ?? '—')).'</td><td>'.e(filled($item['due_date'] ?? null) ? Carbon::parse($item['due_date'])->format('d/m/Y') : '—').'</td><td style="text-align:right">R$ '.number_format((float) ($item['amount'] ?? 0), 2, ',', '.').'</td><td>Pendente</td></tr>')->implode('');

        return [
            'number' => $agreement->number,
            'organization_name' => $tenant->legal_name ?: $tenant->name,
            'organization_document' => $tenant->cnpj ?: 'não informado',
            'organization_address' => $tenant->full_address ?: 'não informado',
            'organization_city' => collect([$tenant->city, $tenant->state])->filter()->implode('/'),
            'organization_representative' => $tenant->legal_representative_name ?: 'Representante legal',
            'organization_representative_role' => $tenant->legal_representative_role ?: 'Representante',
            'debtor_name' => data_get($party, 'name') ?: data_get($party, 'nickname') ?: 'não identificado',
            'debtor_document' => data_get($party, 'cpf_cnpj') ?: data_get($party, 'document') ?: 'não informado',
            'created_at' => $agreement->created_at?->format('d/m/Y'),
            'original_amount' => 'R$ '.number_format((float) $agreement->original_amount, 2, ',', '.'),
            'negotiated_amount' => 'R$ '.number_format((float) $agreement->negotiated_amount, 2, ',', '.'),
            'installments' => $agreement->plan?->installments->count() ?? count(data_get($snapshot, 'installments', [])),
            'obligations_table' => '<table class="neg-table"><thead><tr><th>Obrigação</th><th>OS</th><th>Serviço</th><th class="money">Saldo incluído</th></tr></thead><tbody>'.$obligationRows.'</tbody></table>',
            'installments_table' => '<table class="neg-table"><thead><tr><th>Tipo</th><th>Vencimento</th><th class="money">Valor</th><th>Situação</th></tr></thead><tbody>'.$installmentRows.'</tbody></table>',
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->checkPermissionTo($permission), 403);
    }
}
