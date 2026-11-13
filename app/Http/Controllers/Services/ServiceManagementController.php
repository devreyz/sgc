<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Enums\ServiceOrderStatus;
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
use App\Services\FinancialDocumentIdentityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceManagementController extends Controller
{
    public function index(Request $request, Tenant $tenant, ServiceHumanStatusResolver $statuses): View
    {
        $this->allow($request, 'view_service_management');
        $search = trim($request->string('q')->limit(100)->toString());
        $dates = $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''),
        ]);
        $serviceDate = 'COALESCE(execution_date, scheduled_date, DATE(scheduled_at))';
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')
            ->with(['service', 'execution.obligations'])
            ->when($dates['from'] ?? null, fn ($query, $date) => $query->whereRaw("$serviceDate >= ?", [$date]))
            ->when($dates['to'] ?? null, fn ($query, $date) => $query->whereRaw("$serviceDate <= ?", [$date]))
            ->when($request->filled('status'), fn ($q) => $q->where('operational_status', $request->string('status')->toString()))
            ->when($search !== '', fn ($q) => $q->where(fn ($query) => $query
                ->where('number', 'like', '%'.$search.'%')
                ->orWhere('beneficiary_snapshot->name', 'like', '%'.$search.'%')
                ->orWhere('provider_snapshot->name', 'like', '%'.$search.'%')
                ->orWhereHas('service', fn ($service) => $service->where('name', 'like', '%'.$search.'%'))))
            ->orderByRaw("$serviceDate DESC")->orderByDesc('start_time')->orderByDesc('scheduled_at')->orderByDesc('id')->paginate(30)->withQueryString();
        $accounts = ($request->user()->checkPermissionTo('manage_service_payables') || $request->user()->checkPermissionTo('manage_service_receivables'))
            ? BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get()
            : collect();
        $negotiated = $accounts->isEmpty() ? collect() : DB::table('service_payment_plan_obligations as link')
            ->join('service_payment_plans as plan', 'plan.id', '=', 'link.service_payment_plan_id')
            ->where('plan.tenant_id', $tenant->id)->where('plan.status', 'active')
            ->whereIn('link.service_obligation_id', $orders->getCollection()->flatMap(fn ($order) => $order->execution?->obligations ?? [])->pluck('id'))
            ->pluck('link.service_obligation_id');

        return view('services.management-index', compact('orders', 'statuses', 'accounts', 'negotiated'));
    }

    public function updateExecutionRow(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'edit_service_order');
        $data = $request->validate([
            'execution_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'lock_version' => 'required|integer|min:0', 'intent' => 'required|in:save,submit',
            'operation_key' => 'required|uuid', 'values' => 'nullable|array|max:100',
            'values.*' => 'nullable|string|max:4000',
        ]);
        if ($data['intent'] === 'submit') {
            $this->allow($request, 'operate_all_service_orders_portal');
        }
        $execution = $this->order($tenant, $order)->execution;
        abort_unless($execution, 404);
        $fields = collect(data_get($execution->catalog_snapshot, 'fields', []))
            ->filter(fn (array $field) => ($field['visible_to_management'] ?? true)
                && in_array($field['phase'] ?? null, ['start', 'execution', 'finish'], true)
                && ! in_array($field['type'] ?? null, ['image', 'file', 'signature'], true))
            ->pluck('key')->all();
        $values = array_intersect_key($data['values'] ?? [], array_flip($fields));
        $result = $workflow->updateFromTable($execution, $values, $data['execution_date'], (int) $data['lock_version'],
            $data['intent'] === 'submit' ? $data['operation_key'] : null, $request->user(), \Illuminate\Support\Arr::only($data, ['start_time', 'end_time']));

        return back()->with('success', match ($result->status) {
            'validated' => 'Execução concluída e validada.',
            'submitted' => 'Execução enviada para conferência.',
            default => 'Dados da execução salvos.',
        });
    }

    public function create(Request $request, Tenant $tenant): View
    {
        $this->allow($request, 'create_service_order');

        return $this->entryView($tenant);
    }

    public function store(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        $this->allow($request, 'create_service_order');
        $data = $request->validate($this->entryRules());
        $this->allowPriceChanges($request, [$data]);
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($data['service_version_id'])->firstOrFail();
        $order = $creator->handle($tenant->id, $version, array_replace($data, ['order_data' => $request->input('order_data', [])]), $request->user());

        return redirect()->route('services.management.show', [$tenant->slug, $order]);
    }

    public function batch(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        $this->allow($request, 'create_service_order');
        $rules = collect($this->entryRules())->mapWithKeys(fn ($rule, $key) => [
            'rows.*.'.$key => str_replace('required_without:associate_id', 'required_without:rows.*.associate_id', $rule),
        ])->all();
        $data = $request->validate(['rows' => 'required|array|list|min:1|max:30', 'rows.*' => 'required|array'] + $rules);
        $this->allowPriceChanges($request, $data['rows']);
        DB::transaction(function () use ($data, $tenant, $request, $creator): void {
            $versions = ServiceVersion::query()->where('tenant_id', $tenant->id)
                ->whereIn('id', collect($data['rows'])->pluck('service_version_id'))->get()->keyBy('id');
            foreach ($data['rows'] as $index => $row) {
                $version = $versions->get($row['service_version_id']);
                if (! $version) {
                    throw ValidationException::withMessages(["rows.$index.service_version_id" => 'Escolha um serviço desta organização.']);
                }
                try {
                    $row['order_data'] = $request->input("rows.$index.order_data", []);
                    $creator->handle($tenant->id, $version, $row, $request->user());
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn ($messages, $field) => ["rows.$index.$field" => $messages])->all());
                }
            }
        }, 3);

        return redirect()->route('services.management.index', $tenant->slug)->with('success', count($data['rows']).' ordem(ns) criada(s).');
    }

    public function edit(Request $request, Tenant $tenant, int $order): View
    {
        $this->allow($request, 'edit_service_order');
        $order = $this->order($tenant, $order);
        abort_unless($order->execution?->status === 'draft', 409, 'Somente ordens que ainda não foram iniciadas podem ser editadas aqui.');

        return $this->entryView($tenant, $order);
    }

    public function update(Request $request, Tenant $tenant, int $order, CreateServiceOrder $creator): RedirectResponse
    {
        $this->allow($request, 'edit_service_order');
        $order = $this->order($tenant, $order);
        $data = $request->validate($this->entryRules() + ['lock_version' => 'required|integer|min:0', 'reset_customer_rate' => 'sometimes|accepted']);
        $this->allowPriceChanges($request, [$data]);
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->findOrFail($data['service_version_id']);
        $creator->handle($tenant->id, $version, array_replace($data, ['order_data' => $request->input('order_data', [])]), $request->user(), $order);

        return redirect()->route('services.management.show', [$tenant->slug, $order])->with('success', 'Ordem corrigida.');
    }

    public function remove(Request $request, Tenant $tenant, int $order): RedirectResponse
    {
        $this->allow($request, 'edit_service_order');
        $data = $request->validate(['reason' => 'required|string|min:5|max:500', 'action' => 'required|in:delete,cancel']);
        $order = $this->order($tenant, $order);
        DB::transaction(function () use ($order, $data, $request): void {
            $execution = $order->execution()->lockForUpdate()->firstOrFail();
            $lockedOrder = ServiceOrder::query()->where('tenant_id', $order->tenant_id)->lockForUpdate()->findOrFail($order->id);
            if (! in_array($execution->status, ['draft', 'in_progress', 'submitted', 'rejected'], true)
                || $execution->obligations()->exists() || $lockedOrder->payments()->exists() || $lockedOrder->ledgerEntries()->exists() || $lockedOrder->paid) {
                throw ValidationException::withMessages(['reason' => 'Esta ordem já possui validação ou movimentação financeira. Use os ajustes e estornos do dossiê.']);
            }
            if ($data['action'] === 'delete' && ($execution->status !== 'draft' || $execution->evidences()->exists()
                || $execution->resources()->exists() || $lockedOrder->expenses()->exists()
                || GeneratedDocument::query()->where('tenant_id', $lockedOrder->tenant_id)
                    ->where(fn ($query) => $query
                        ->where(fn ($subject) => $subject->where('documentable_type', ServiceOrder::class)->where('documentable_id', $lockedOrder->id))
                        ->orWhere(fn ($subject) => $subject->where('documentable_type', $execution::class)->where('documentable_id', $execution->id)))
                    ->exists())) {
                throw ValidationException::withMessages(['reason' => 'Esta ordem tem histórico. Escolha Cancelar para manter os registros vinculados.']);
            }
            $execution->update(['status' => 'cancelled', 'lock_version' => $execution->lock_version + 1]);
            $lockedOrder->update(['status' => ServiceOrderStatus::CANCELLED, 'operational_status' => 'cancelled',
                'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => $data['reason']]);
            activity('service_order')->performedOn($lockedOrder)->causedBy($request->user())
                ->withProperties(['reason' => $data['reason']])->log($data['action'] === 'delete' ? 'Ordem excluída da lista' : 'Ordem cancelada');
            if ($data['action'] === 'delete') {
                $lockedOrder->delete();
            }
        }, 3);

        return redirect()->route('services.management.index', $tenant->slug)->with('success', $data['action'] === 'delete' ? 'Ordem excluída.' : 'Ordem cancelada.');
    }

    private function entryRules(): array
    {
        return ['service_version_id' => 'required|integer', 'associate_id' => 'nullable|integer',
            'beneficiary_name' => 'nullable|string|max:191|required_without:associate_id',
            'service_provider_id' => 'nullable|integer', 'asset_id' => 'nullable|integer',
            'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:191', 'order_data' => 'nullable|array|max:100',
            'order_data.description' => 'nullable|string|max:2000', 'order_data.notes' => 'nullable|string|max:2000',
            'customer_rate' => 'nullable|numeric|decimal:0,4|min:0|max:999999999', 'provider_rate_source' => 'nullable|in:catalog,provider'];
    }

    private function allowPriceChanges(Request $request, array $rows): void
    {
        if (collect($rows)->contains(fn (array $row) => isset($row['customer_rate']) || isset($row['provider_rate_source']) || isset($row['reset_customer_rate']))) {
            $this->allow($request, 'view_service_financials');
        }
    }

    private function entryView(Tenant $tenant, ?ServiceOrder $order = null): View
    {
        return view('services.management-create', [
            'order' => $order,
            'versions' => ServiceVersion::query()->where('tenant_id', $tenant->id)->where(fn ($query) => $query
                ->where(fn ($active) => $active->where('status', 'published')->whereHas('service', fn ($service) => $service->where('status', true)))
                ->when($order, fn ($versions) => $versions->orWhere('id', $order->service_version_id)))
                ->with(['service', 'fields', 'providerRates' => fn ($query) => $query->where('tenant_id', $tenant->id)->where('active', true)])->get(),
            'providers' => ServiceProvider::query()->where('tenant_id', $tenant->id)->active()->with('services:id')->orderBy('name')->get(),
            'associates' => Associate::query()->where('tenant_id', $tenant->id)->active()->with('user')->orderBy('id')->get(),
            'assets' => Asset::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Tenant $tenant, int $order, ServiceHumanStatusResolver $statuses): View
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        $notes = $request->user()->checkPermissionTo('view_service_financials') && $order->execution
            ? GeneratedDocument::query()->where('tenant_id', $tenant->id)
                ->where('documentable_type', $order->execution::class)->where('documentable_id', $order->execution->id)
                ->where('document_settings->template_key', 'service_internal_note')->latest('id')->limit(5)->get()
            : collect();

        return view('services.management-show', compact('order', 'accounts', 'statuses', 'notes'));
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
        $data = $request->validate([
            'operation_key' => 'required|uuid',
            'effect' => 'required|in:increase,decrease',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|min:5|max:500',
        ]);
        $amount = abs((float) $data['amount']) * ($data['effect'] === 'decrease' ? -1 : 1);
        $adjustments->add($record, $data['effect'], $amount, $data['reason'], $data['operation_key'], $request->user());

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

        return app(TemplatedPdfService::class)->generateSystemPdf('pdf.service-accountability', compact('summary', 'tenant') + ['suppress_internal_footer' => true], [
            'title' => 'Prestação de contas de serviços',
        ])->download('prestacao-servicos.pdf');
    }

    public function agreements(Request $request, Tenant $tenant, FinancialDocumentIdentityService $identities): View
    {
        $this->allow($request, 'manage_service_agreements');
        $creating = $request->routeIs('services.management.agreements.create');
        $obligations = $creating ? ServiceObligation::query()->where('tenant_id', $tenant->id)->where('direction', 'receivable')
            ->whereIn('status', ['open', 'partially_paid'])->whereNotIn('id', DB::table('service_payment_plan_obligations as link')
                ->join('service_payment_plans as plans', 'plans.id', '=', 'link.service_payment_plan_id')
                ->where('plans.tenant_id', $tenant->id)->where('plans.status', 'active')->select('link.service_obligation_id'))
            ->with(['execution.order.service', 'allocations.paymentEvent'])->orderBy('due_date')->get() : collect();
        $agreements = $creating ? collect() : ServiceNegotiation::query()->where('tenant_id', $tenant->id)
            ->with(['plan.installments.paymentEvent', 'plan.installments.verificationIdentity', 'plan.obligations', 'generatedDocument', 'creator'])
            ->latest()->get();
        $agreements->flatMap(fn (ServiceNegotiation $agreement) => $agreement->plan?->installments ?? collect())
            ->each(function (ServicePaymentPlanInstallment $installment) use ($identities, $request): void {
                if ($installment->status !== 'cancelled' && ! $installment->verificationIdentity) {
                    $installment->setRelation('verificationIdentity', $identities->ensure($installment, $request->user()));
                }
            });
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        return view('services.agreements', compact('obligations', 'agreements', 'accounts', 'creating'));
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

        return redirect()->route('services.management.agreements', [$tenant->slug])
            ->with('success', 'Termo criado. Ele já aparece na lista abaixo e está pronto para impressão.')
            ->with('agreement_document_id', $agreement->generated_document_id);
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

    public function cancelAgreement(Request $request, Tenant $tenant, int $agreement, ServiceNegotiationService $negotiations): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $record = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->findOrFail($agreement);
        $negotiations->cancel($record, $data['reason'], $request->user());

        return back()->with('success', 'Acordo cancelado. Os saldos estão disponíveis para nova negociação.');
    }

    public function regenerateAgreementDocument(Request $request, Tenant $tenant, int $agreement, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $agreement = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->whereKey($agreement)->with('plan.installments')->firstOrFail();
        $document = $documents->generate($agreement, 'service_negotiation', 'Termo '.$agreement->number, $this->negotiationDocumentVariables($tenant, $agreement), $request->user());
        $agreement->update(['generated_document_id' => $document->id]);

        return redirect()->route('services.management.documents.download', [$tenant->slug, $document, 'inline' => 1]);
    }

    public function generateOrderDocument(Request $request, Tenant $tenant, int $order, ServiceDocumentService $documents): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $type = $request->validate(['type' => 'required|in:order,execution,note'])['type'];
        if ($type !== 'order') {
            $this->allow($request, 'view_service_financials');
        }
        abort_unless($order->execution && $order->operational_status !== 'cancelled', 409, 'Esta ordem não permite emissão.');
        if ($type === 'note') {
            abort_unless($order->execution->isFrozen(), 409, 'Valide a execução antes de emitir a nota interna.');
            $variables = $this->executionDocumentVariables($order) + [
                'order_number' => $order->number, 'service_name' => $order->service?->name,
                'provider_name' => data_get($order->provider_snapshot, 'name') ?: '—',
                'beneficiary_name' => data_get($order->beneficiary_snapshot, 'name') ?: '—',
                'scheduled_at' => $order->scheduled_at?->format('d/m/Y H:i') ?: '—',
                'validated_at' => $order->execution->validated_at?->format('d/m/Y H:i') ?: '—',
                'quantity' => \App\Support\PdfQuantityFormatter::format($order->execution->quantity),
                'unit' => $order->execution->unit, 'location' => $order->location ?: '—',
                'notes' => data_get($order->order_data, 'notes') ?: '—',
            ];
            $document = $documents->generate($order->execution, 'service_internal_note', 'Nota interna '.$order->number, $variables, $request->user());

            return redirect()->route('services.management.documents.download', [$tenant->slug, $document, 'inline' => 1]);
        }
        $subject = $type === 'order' ? $order : $order->execution;
        $variables = ['order_number' => $order->number, 'service_name' => $order->service->name, 'provider_name' => $order->provider_snapshot['name'] ?? '', 'beneficiary_name' => $order->beneficiary_snapshot['name'] ?? '', 'scheduled_at' => $order->scheduled_at?->format('d/m/Y H:i'), 'location' => $order->location, 'status' => app(ServiceHumanStatusResolver::class)->resolve($order), 'quantity' => $order->execution->quantity === null ? null : \App\Support\PdfQuantityFormatter::format($order->execution->quantity), 'unit' => $order->execution->unit, 'validated_at' => $order->execution->validated_at?->format('d/m/Y H:i')]
            + $this->executionDocumentVariables($order);
        if (! $request->user()->checkPermissionTo('view_service_financials')) {
            $variables = array_replace($variables, array_fill_keys(['composicao_receber', 'composicao_pagar', 'total_receber', 'total_pagar'], '—'));
        }
        $document = $documents->generate($subject, $type === 'order' ? 'service_order' : 'service_execution', $type === 'order' ? 'Ordem '.$order->number : 'Execução '.$order->number, $variables, $request->user());

        return redirect()->route('services.management.documents.download', [$tenant->slug, $document, 'inline' => 1]);
    }

    public function document(Request $request, Tenant $tenant, int $document, TemplatedPdfService $pdf)
    {
        $this->allow($request, 'view_service_management');
        $record = GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereKey($document)->firstOrFail();
        if (in_array(data_get($record->document_settings, 'template_key'), ['service_internal_note', 'service_execution'], true)) {
            $this->allow($request, 'view_service_financials');
        }

        $filename = str($record->title)->slug().'.pdf';
        $systemView = data_get($record->document_settings, 'system_view');
        $documentPdf = $systemView ? $pdf->generateSystemPdf('pdf.service-issued-document', [
            'tenant' => $tenant, 'title' => $record->title, 'body' => $record->content,
            'suppress_internal_footer' => true,
        ], ['configuration_view' => $systemView, 'title' => $record->title]) : $pdf->generateFrozenDocument($record);

        return $request->boolean('inline')
            ? $documentPdf->stream($filename)
            : $documentPdf->download($filename);
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
            : '<table class="data-table"><thead><tr><th>Descrição</th><th>Fórmula</th><th class="text-right">Total</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        return $variables + [
            'execution_date' => $order->execution_date?->format('d/m/Y'),
            'start_time' => $order->start_time ? substr($order->start_time, 0, 5) : null,
            'end_time' => $order->end_time ? substr($order->end_time, 0, 5) : null,
            'notes' => data_get($order->order_data, 'notes'),
            'campos_execucao' => $fieldRows === '' ? '<p>Nenhum campo adicional informado.</p>' : '<table class="data-table"><thead><tr><th>Campo</th><th>Valor</th><th>Unidade</th></tr></thead><tbody>'.$fieldRows.'</tbody></table>',
            'composicao_receber' => $table($composition['receivable']),
            'composicao_pagar' => $table($composition['payable']),
            'total_receber' => 'R$ '.number_format((float) $execution->compositionLines->where('direction', 'receivable')->sum('amount'), 2, ',', '.'),
            'total_pagar' => 'R$ '.number_format((float) $execution->compositionLines->where('direction', 'payable')->sum('amount'), 2, ',', '.'),
        ];
    }

    private function negotiationDocumentVariables(Tenant $tenant, ServiceNegotiation $agreement): array
    {
        $agreement->loadMissing(['plan.obligations.execution.order.service', 'plan.installments.verificationIdentity']);
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
        $identityService = app(FinancialDocumentIdentityService::class);
        $installmentRows = $agreement->plan?->installments?->map(function (ServicePaymentPlanInstallment $item) use ($identityService): string {
            $kind = $item->kind === 'entry' ? 'Entrada' : 'Parcela '.$item->number;
            $status = match ($item->status) {
                'paid' => 'Recebida em '.$item->paid_at?->format('d/m/Y'),
                'cancelled' => 'Cancelada',
                default => 'Pendente',
            };
            $identity = $item->status === 'cancelled' ? null : ($item->verificationIdentity ?: $identityService->ensure($item, auth()->user()));
            $qr = $identity ? '<img src="'.$identityService->qrDataUri($identity, 70).'" width="46" height="46" alt="QR"><br><small>'.e($identity->reference_code).'</small>' : '—';

            return '<tr><td>'.e($kind).'</td><td>'.e($item->due_date?->format('d/m/Y') ?? '—').'</td><td style="text-align:right">R$ '.number_format((float) $item->amount, 2, ',', '.').'</td><td>'.e($status).'</td><td style="text-align:center">'.$qr.'</td></tr>';
        })->implode('') ?: collect(data_get($snapshot, 'installments', []))->map(fn (array $item): string => '<tr><td>'.e(($item['kind'] ?? null) === 'entry' ? 'Entrada' : 'Parcela '.($item['number'] ?? '—')).'</td><td>'.e(filled($item['due_date'] ?? null) ? Carbon::parse($item['due_date'])->format('d/m/Y') : '—').'</td><td style="text-align:right">R$ '.number_format((float) ($item['amount'] ?? 0), 2, ',', '.').'</td><td>Pendente</td><td>—</td></tr>')->implode('');

        return [
            'number' => $agreement->number,
            'agreement_status' => $agreement->status,
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
            'obligations_table' => '<table class="data-table"><thead><tr><th>Obrigação</th><th>OS</th><th>Serviço</th><th class="text-right">Saldo incluído</th></tr></thead><tbody>'.$obligationRows.'</tbody></table>',
            'installments_table' => '<table class="data-table"><thead><tr><th>Tipo</th><th>Vencimento</th><th class="text-right">Valor</th><th>Situação</th><th>Cobrança verificável</th></tr></thead><tbody>'.$installmentRows.'</tbody></table>',
        ];
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->checkPermissionTo($permission), 403);
    }
}
