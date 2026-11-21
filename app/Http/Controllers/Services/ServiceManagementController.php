<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Enums\ServiceOrderStatus;
use App\Models\Asset;
use App\Models\Associate;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\GeneratedDocument;
use App\Models\Service;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceNegotiation;
use App\Models\ServiceObligation;
use App\Models\ServiceExecution;
use App\Models\ServicePayoutRequestItem;
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
use App\Services\Services\ServiceOrderNumberService;
use App\Services\Services\ServiceReportService;
use App\Services\Services\ServiceReportLayout;
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
            ->with(['service', 'execution.compositionLines', 'execution.obligations' => fn ($query) => $query->withSum([
                'allocations as settled_amount' => fn ($allocations) => $allocations->whereHas('paymentEvent', fn ($events) => $events->whereIn('status', ['confirmed', 'reversed'])),
            ], 'amount')])
            ->when($request->boolean('undated'), fn ($query) => $query->whereRaw("$serviceDate IS NULL"))
            ->when(! $request->boolean('undated') ? ($dates['from'] ?? null) : null, fn ($query, $date) => $query->whereRaw("$serviceDate >= ?", [$date]))
            ->when(! $request->boolean('undated') ? ($dates['to'] ?? null) : null, fn ($query, $date) => $query->whereRaw("$serviceDate <= ?", [$date]))
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
        if ($request->user()->checkPermissionTo('view_service_financials')) {
            $pending = $orders->getCollection()->filter(fn ($item) => in_array($item->execution?->status, ['draft', 'in_progress', 'rejected', 'submitted'], true));
            $pending->loadMissing(['execution.version.fields', 'execution.provider', 'execution.resources']);
            foreach ($pending as $pendingOrder) $this->attachFinancialPreview($pendingOrder);
        }
        $negotiated = $accounts->isEmpty() ? collect() : DB::table('service_payment_plan_obligations as link')
            ->join('service_payment_plans as plan', 'plan.id', '=', 'link.service_payment_plan_id')
            ->where('plan.tenant_id', $tenant->id)->where('plan.status', 'active')
            ->whereIn('link.service_obligation_id', $orders->getCollection()->flatMap(fn ($order) => $order->execution?->obligations ?? [])->pluck('id'))
            ->pluck('link.service_obligation_id');

        return view('services.management-index', compact('tenant', 'orders', 'statuses', 'accounts', 'negotiated'));
    }

    public function updateExecutionRow(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'edit_service_order');
        $data = $request->validate([
            'execution_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'provider_rate' => 'nullable|numeric|decimal:0,4|gt:0|max:999999999',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'lock_version' => 'required|integer|min:0', 'intent' => 'required|in:save,submit',
            'operation_key' => 'required|uuid', 'values' => 'nullable|array|max:100',
            'values.*' => 'nullable|string|max:4000',
        ]);
        if (isset($data['provider_rate'])) {
            $this->allow($request, 'view_service_financials');
        }
        if ($data['intent'] === 'submit') {
            $this->allow($request, 'operate_all_service_orders_portal');
        }
        $execution = $this->order($tenant, $order)->execution;
        abort_unless($execution, 404);
        $correctionReason = $execution->status === 'submitted'
            ? $request->validate(['correction_reason' => 'required|string|min:5|max:500'])['correction_reason'] : null;
        $fields = collect(data_get($execution->catalog_snapshot, 'fields', []))
            ->filter(fn (array $field) => ($field['visible_to_management'] ?? true)
                && in_array($field['phase'] ?? null, ['start', 'execution', 'finish'], true)
                && ! in_array($field['type'] ?? null, ['image', 'file', 'signature'], true))
            ->pluck('key')->all();
        [$meterStart, $meterEnd] = \App\Services\Services\ServiceCalculationRules::meterPair((array) $execution->catalog_snapshot);
        if ($meterStart && in_array($meterEnd, $fields, true)) $fields[] = 'meter_pauses_'.$meterStart;
        $values = array_intersect_key($data['values'] ?? [], array_flip($fields));
        $result = $workflow->updateFromTable($execution, $values, $data['execution_date'] ?? null, (int) $data['lock_version'],
            $data['intent'] === 'submit' ? $data['operation_key'] : null, $request->user(),
            \Illuminate\Support\Arr::only($data, ['start_time', 'end_time']), $data['provider_rate'] ?? null, $correctionReason);
        if ($correctionReason !== null) return back()->with('success', 'Medições corrigidas. A OS continua aguardando conferência.');

        return back()->with('success', match ($result->status) {
            'validated' => 'Execução concluída e validada.',
            'submitted' => 'Execução enviada para conferência.',
            default => 'Dados da execução salvos.',
        });
    }

    public function create(Request $request, Tenant $tenant): View
    {
        $this->allow($request, 'create_service_order');

        return $this->entryView($tenant, batch: $request->boolean('batch'));
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

    public function edit(Request $request, Tenant $tenant, int $order): View|RedirectResponse
    {
        $this->allow($request, 'edit_service_order');
        $order = $this->order($tenant, $order);
        if (in_array($order->execution?->status, ['submitted', 'validated'], true)) {
            if (! \App\Services\Services\ServiceOrderDetails::editable($order)) {
                return redirect()->route('services.management.show', [$tenant->slug, $order])->with('error', 'Esta ordem já foi quitada ou anulada. Consulte o histórico para conferir os dados.');
            }
            return view('services.management-details', compact('order', 'tenant'));
        }
        if (! in_array($order->execution?->status, ['draft', 'in_progress', 'rejected'], true)) {
            return redirect()->route('services.management.show', [$tenant->slug, $order])->with('error', 'A situação da ordem mudou. Confira os dados atuais antes de editar.');
        }

        return $this->entryView($tenant, $order);
    }

    public function update(Request $request, Tenant $tenant, int $order, CreateServiceOrder $creator): RedirectResponse
    {
        $this->allow($request, 'edit_service_order');
        $order = $this->order($tenant, $order);
        if ($request->boolean('details_only')) {
            $request->merge(['beneficiary_name' => trim((string) $request->input('beneficiary_name'))]);
            $data = $request->validate([
                'details_token' => 'required|string|size:64', 'beneficiary_name' => 'required|string|max:191',
                'execution_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
                'scheduled_at' => 'nullable|date_format:Y-m-d\TH:i',
                'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i',
                'location' => 'nullable|string|max:191', 'notes' => 'nullable|string|max:4000',
                'reason' => 'required|string|min:5|max:500',
            ]);
            app(\App\Services\Services\ServiceOrderDetails::class)->update($order, $data, $request->user());
            return back()->with('success', 'Detalhes corrigidos. A situação da ordem, as quantidades e os pagamentos foram preservados.');
        }
        $data = $request->validate($this->entryRules() + ['lock_version' => 'required|integer|min:0', 'reset_customer_rate' => 'sometimes|accepted']);
        $this->allowPriceChanges($request, [$data]);
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->findOrFail($data['service_version_id']);
        $creator->handle($tenant->id, $version, array_replace($data, ['order_data' => $request->input('order_data', [])]), $request->user(), $order);

        return redirect()->route('services.management.show', [$tenant->slug, $order])->with('success', 'Ordem corrigida.');
    }

    public function remove(Request $request, Tenant $tenant, int $order, ServiceOrderNumberService $numbers, FinancialDocumentIdentityService $identities): RedirectResponse
    {
        $this->allow($request, 'edit_service_order');
        $data = $request->validate(['reason' => 'required|string|min:5|max:500', 'action' => 'required|in:delete,cancel,cancel_replace']);
        if ($data['action'] === 'cancel_replace') {
            $this->allow($request, 'create_service_order');
        }
        $order = $this->order($tenant, $order);
        $replacement = DB::transaction(function () use ($order, $data, $request, $numbers, $identities): ?ServiceOrder {
            $execution = $order->execution()->lockForUpdate()->firstOrFail();
            $lockedOrder = ServiceOrder::query()->where('tenant_id', $order->tenant_id)->lockForUpdate()->findOrFail($order->id);
            $wasValidated = $execution->isFrozen();
            $obligations = $execution->obligations()->orderBy('id')->lockForUpdate()->get();
            if (! in_array($execution->status, ['draft', 'in_progress', 'submitted', 'rejected', 'validated'], true)
                || $lockedOrder->payments()->exists() || $lockedOrder->ledgerEntries()->exists() || $lockedOrder->paid) {
                throw ValidationException::withMessages(['reason' => 'Esta ordem já foi cancelada ou tem movimentação financeira anterior. Consulte o dossiê.']);
            }
            if ($obligations->contains(fn ($obligation) => $obligation->paid_amount !== 0.0 || $obligation->activePaymentPlan())
                || ServicePayoutRequestItem::query()->whereIn('service_obligation_id', $obligations->modelKeys())
                    ->whereHas('request', fn ($query) => $query->whereIn('status', ['pending', 'approved']))->exists()) {
                throw ValidationException::withMessages(['reason' => 'Antes de anular, resolva os pagamentos, acordos ou solicitações de repasse ativos no dossiê.']);
            }
            if ($data['action'] === 'delete') {
                if (! in_array($execution->status, ['draft', 'submitted'], true)) {
                    throw ValidationException::withMessages(['reason' => 'Só é possível excluir rascunhos e ordens que aguardam conferência. Para os outros casos, use a anulação.']);
                }
                $hasExpense = $lockedOrder->expenses()->exists()
                    || Expense::query()->where('tenant_id', $lockedOrder->tenant_id)
                        ->where('expenseable_type', $execution->getMorphClass())->where('expenseable_id', $execution->id)->exists();
                $hasDocument = GeneratedDocument::query()->where('tenant_id', $lockedOrder->tenant_id)
                    ->where(fn ($query) => $query
                        ->where(fn ($subject) => $subject->where('documentable_type', ServiceOrder::class)->where('documentable_id', $lockedOrder->id))
                        ->orWhere(fn ($subject) => $subject->where('documentable_type', $execution::class)->where('documentable_id', $execution->id)))
                    ->exists();
                if ($hasExpense || $hasDocument) {
                    throw ValidationException::withMessages(['reason' => 'Esta ordem tem despesa ou documento vinculado. Use a anulação para manter os vínculos disponíveis no dossiê.']);
                }
                if ($execution->status === 'draft' && ($execution->evidences()->exists() || $execution->resources()->exists())) {
                    throw ValidationException::withMessages(['reason' => 'Este rascunho tem anexos ou recursos vinculados. Use a anulação para manter os vínculos disponíveis no dossiê.']);
                }
            }
            $execution->update(['status' => 'cancelled', 'lock_version' => $execution->lock_version + 1]);
            $lockedOrder->update(['status' => ServiceOrderStatus::CANCELLED, 'operational_status' => 'cancelled',
                'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => $data['reason']]);
            foreach ($obligations as $obligation) {
                $obligation->update(['status' => 'cancelled']);
                $identities->invalidateDocument($obligation, $data['reason'], $request->user());
            }
            $replacement = null;
            if ($data['action'] === 'cancel_replace') {
                $replacement = new ServiceOrder($lockedOrder->only([
                    'service_id', 'service_version_id', 'associate_id', 'service_provider_id', 'asset_id',
                    'scheduled_date', 'scheduled_at', 'execution_date', 'start_time', 'end_time',
                    'unit', 'location', 'order_data', 'beneficiary_snapshot', 'provider_snapshot',
                ]) + [
                    'number' => $numbers->next($lockedOrder->tenant_id, now()->year),
                    'status' => ServiceOrderStatus::SCHEDULED, 'operational_status' => 'scheduled',
                    'unit_price' => 0, 'total_price' => 0, 'final_price' => 0, 'created_by' => $request->user()->id,
                ]);
                $replacement->tenant_id = $lockedOrder->tenant_id;
                $replacement->save();
                $draft = $replacement->execution()->make($execution->only([
                    'service_version_id', 'service_provider_id', 'associate_id', 'unit', 'values', 'catalog_snapshot',
                ]) + ['status' => 'draft', 'derived_values' => []]);
                $draft->tenant_id = $lockedOrder->tenant_id;
                $draft->save();
                activity('service_order')->performedOn($replacement)->causedBy($request->user())
                    ->withProperties(['original_order_id' => $lockedOrder->id, 'reason' => $data['reason']])->log('Rascunho criado para corrigir ordem anulada');
            }
            activity('service_order')->performedOn($lockedOrder)->causedBy($request->user())
                ->withProperties(['reason' => $data['reason'], 'replacement_order_id' => $replacement?->id])
                ->log($data['action'] === 'delete' ? 'Ordem excluída da lista' : ($wasValidated ? 'Ordem finalizada anulada' : 'Ordem cancelada'));
            if ($data['action'] === 'delete') {
                $lockedOrder->delete();
            }

            return $replacement;
        }, 3);

        if ($replacement) {
            return redirect()->route('services.management.edit', [$tenant->slug, $replacement])
                ->with('success', 'Ordem original anulada. Revise o rascunho antes de finalizar. Anexos e recursos permanecem na original.');
        }
        return redirect()->route('services.management.index', $tenant->slug)->with('success', $data['action'] === 'delete' ? 'Ordem excluída da lista. O motivo está registrado na prestação de contas.' : 'Ordem anulada. O motivo está registrado no dossiê.');
    }

    private function entryRules(): array
    {
        return ['service_version_id' => 'required|integer', 'associate_id' => 'nullable|integer',
            'beneficiary_name' => 'nullable|string|max:191|required_without:associate_id',
            'service_provider_id' => 'nullable|integer', 'asset_id' => 'nullable|integer',
            'scheduled_at' => 'nullable|date', 'execution_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'location' => 'nullable|string|max:191', 'order_data' => 'nullable|array|max:100',
            'order_data.description' => 'nullable|string|max:2000', 'order_data.notes' => 'nullable|string|max:2000',
            'customer_rate' => 'nullable|numeric|decimal:0,4|min:0|max:999999999', 'provider_rate_source' => 'nullable|in:catalog,provider',
            'provider_rate' => 'nullable|numeric|decimal:0,4|gt:0|max:999999999'];
    }

    private function allowPriceChanges(Request $request, array $rows): void
    {
        if (collect($rows)->contains(fn (array $row) => isset($row['customer_rate']) || isset($row['provider_rate']) || isset($row['provider_rate_source']) || isset($row['reset_customer_rate']))) {
            $this->allow($request, 'view_service_financials');
        }
    }

    private function entryView(Tenant $tenant, ?ServiceOrder $order = null, bool $batch = false): View
    {
        return view($order || $batch ? 'services.management-create' : 'provider.services-create', [
            'order' => $order,
            'managementEntry' => true,
            'operator' => true,
            'versions' => ServiceVersion::query()->where('tenant_id', $tenant->id)->where(fn ($query) => $query
                ->where(fn ($active) => $active->where('status', 'published')->whereHas('service', fn ($service) => $service->where('status', true)))
                ->when($order, fn ($versions) => $versions->orWhere(fn ($history) => $history
                    ->where('service_id', $order->service_id)->whereIn('status', ['published', 'retired']))))
                ->with(['service', 'fields', 'providerRates' => fn ($query) => $query->where('tenant_id', $tenant->id)->where('active', true)])
                ->orderBy('service_id')->orderByDesc('version')->get(),
            'providers' => ServiceProvider::query()->where('tenant_id', $tenant->id)->active()->with('services:id')->orderBy('name')->get(),
            'associates' => Associate::query()->where('tenant_id', $tenant->id)->active()->with('user')->orderBy('id')->get(),
            'assets' => Asset::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Tenant $tenant, int $order, ServiceHumanStatusResolver $statuses): View
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        if ($request->user()->checkPermissionTo('view_service_financials')) $this->attachFinancialPreview($order);
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        $notes = $request->user()->checkPermissionTo('view_service_financials') && $order->execution
            ? GeneratedDocument::query()->where('tenant_id', $tenant->id)
                ->where('documentable_type', $order->execution::class)->where('documentable_id', $order->execution->id)
                ->where('document_settings->template_key', 'service_internal_note')->latest('id')->limit(5)->get()
            : collect();

        return view('services.management-show', compact('tenant', 'order', 'accounts', 'statuses', 'notes'));
    }

    public function execute(Request $request, Tenant $tenant, int $order): View
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        $view = app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->show($request, $tenant, $order);
        $view->with('managementExecution', true);

        return $view;
    }

    public function previewExecution(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow)
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');

        return app(\App\Http\Controllers\Provider\ServiceProviderPortalController::class)->previewExecution($request, $tenant, $order, $workflow);
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
        $data = $request->validate(['operation_key' => 'required|uuid', 'lock_version' => 'required|integer|min:0']);
        $workflow->approve($this->order($tenant, $order)->execution, $data['operation_key'], $request->user(), (int) $data['lock_version']);

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
        abort_unless($execution, 404);
        $data = $request->validate(['operation_key' => 'required|uuid', 'resource_key' => 'nullable|string|max:80', 'description' => 'required|string|max:191', 'provided_by' => 'required|in:organization,provider,customer,third_party', 'effect' => 'required|in:information_only,deduct_from_receivable,add_to_receivable,reimburse_provider,create_association_expense', 'quantity' => 'nullable|numeric|min:0', 'unit' => 'nullable|string|max:30', 'unit_price' => 'nullable|numeric|min:0', 'amount' => 'nullable|numeric|min:0']);
        $operationKey = $data['operation_key'];
        unset($data['operation_key']);
        $data['resource_key'] ??= 'manual-'.substr($operationKey, 0, 8);
        $resources->add($execution, $data, $operationKey, $request->user());

        return back()->with('success', 'Recurso registrado na ordem.');
    }

    public function updateBeneficiary(Request $request, Tenant $tenant, int $order): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'operate_all_service_orders_portal');
        $request->merge(['beneficiary_name' => trim((string) $request->input('beneficiary_name'))]);
        $data = $request->validate(['beneficiary_name' => 'required|string|max:191', 'previous_name' => 'required|string|max:191', 'reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($request, $tenant, $order, $data): void {
            $record = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->whereKey($order)->lockForUpdate()->firstOrFail();
            abort_if($record->operational_status === 'cancelled', 409, 'Esta ordem foi anulada.');
            $snapshot = (array) $record->beneficiary_snapshot;
            if (($snapshot['name'] ?? '') !== $data['previous_name']) {
                throw ValidationException::withMessages(['beneficiary_name' => 'O nome mudou. Reabra a ordem para conferir.']);
            }
            if ($snapshot['name'] === $data['beneficiary_name']) return;
            $record->update(['beneficiary_snapshot' => array_replace($snapshot, ['name' => $data['beneficiary_name']])]);
            $record->execution()->where('tenant_id', $tenant->id)->whereIn('status', ['draft', 'in_progress', 'rejected'])->increment('lock_version');
            activity('service_order')->performedOn($record)->causedBy($request->user())->withProperties([
                'tenant_id' => $tenant->id, 'previous_name' => $snapshot['name'], 'beneficiary_name' => $data['beneficiary_name'], 'reason' => $data['reason'],
            ])->log('Nome do beneficiário corrigido');
        });
        return back()->with('success', 'Nome corrigido nesta ordem. Documentos já emitidos preservam o registro anterior; emita uma nova via para usar o nome atualizado.');
    }

    public function updateProviderRate(Request $request, Tenant $tenant, int $obligation, ServiceObligationAdjustmentService $adjustments): RedirectResponse
    {
        $this->allow($request, 'view_service_management');
        $this->allow($request, 'manage_service_payables');
        $record = ServiceObligation::query()->where('tenant_id', $tenant->id)->whereKey($obligation)->firstOrFail();
        $data = $request->validate(['operation_key' => 'required|uuid', 'provider_rate' => 'required|numeric|decimal:0,4|gt:0|max:999999999', 'expected_total' => 'required|numeric|min:0', 'reason' => 'required|string|min:5|max:500']);
        $adjustments->changeProviderRate($record, (float) $data['provider_rate'], (float) $data['expected_total'], $data['reason'], $data['operation_key'], $request->user());
        return back()->with('success', 'Tarifa do prestador atualizada nesta OS. O saldo foi recalculado e os pagamentos foram preservados.');
    }

    public function adjustment(Request $request, Tenant $tenant, int $obligation, ServiceObligationAdjustmentService $adjustments): RedirectResponse
    {
        $record = ServiceObligation::query()->where('tenant_id', $tenant->id)->whereKey($obligation)->firstOrFail();
        $this->allow($request, $record->direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables');
        $data = $request->validate([
            'operation_key' => 'required|uuid',
            'effect' => 'required|in:increase,decrease',
            'amount' => 'required|numeric|decimal:0,2|min:0.01|max:999999999999.99',
            'reason' => 'required|string|min:5|max:500',
        ]);
        $amount = abs((float) $data['amount']) * ($data['effect'] === 'decrease' ? -1 : 1);
        $adjustments->add($record, $data['effect'], $amount, $data['reason'], $data['operation_key'], $request->user());

        return back()->with('success', $data['effect'] === 'decrease' ? 'Desconto registrado.' : 'Acréscimo registrado.');
    }

    public function reports(Request $request, Tenant $tenant, ServiceReportService $reports): View
    {
        $this->allow($request, 'view_service_reports');
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'provider_id' => 'nullable|integer', 'service_id' => 'nullable|integer', 'asset_id' => 'nullable|integer', 'statuses' => 'nullable|array', 'statuses.*' => 'string|max:80', 'exclude' => 'nullable|array', 'exclude.*' => 'integer', 'sort' => 'nullable|in:date,status'] + ServiceReportLayout::rules());
        $reportLayout = new ServiceReportLayout($data);
        $summary = $reports->summary($tenant->id, Carbon::parse($data['from'] ?? now()->startOfMonth()), Carbon::parse($data['to'] ?? now()->endOfMonth()), $data['provider_id'] ?? null, $data['service_id'] ?? null, $data['asset_id'] ?? null, $data);

        $providers = ServiceProvider::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);
        $services = Service::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);
        $assets = Asset::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']);

        $deletedOrders = ServiceOrder::onlyTrashed()->where('tenant_id', $tenant->id)
            ->whereNotNull('service_version_id')->whereNotNull('cancellation_reason')
            ->where('deleted_at', '>=', Carbon::parse($summary['from'])->startOfDay())
            ->where('deleted_at', '<', Carbon::parse($summary['to'])->addDay()->startOfDay())
            ->when($data['provider_id'] ?? null, fn ($query, $id) => $query->where('service_provider_id', $id))
            ->when($data['service_id'] ?? null, fn ($query, $id) => $query->where('service_id', $id))
            ->when($data['asset_id'] ?? null, fn ($query, $id) => $query->where('asset_id', $id))
            ->orderByDesc('deleted_at')->orderByDesc('id')
            ->paginate(15, ['id', 'number', 'beneficiary_snapshot', 'deleted_at', 'cancellation_reason'], 'deleted_page')
            ->withQueryString();

        return view('services.reports', compact('summary', 'providers', 'services', 'assets', 'deletedOrders', 'reportLayout'));
    }

    public function generateReport(Request $request, Tenant $tenant, ServiceReportService $reports)
    {
        $this->allow($request, 'view_service_reports');
        $data = $request->validate(['from' => 'required|date', 'to' => 'required|date|after_or_equal:from', 'provider_id' => 'nullable|integer', 'service_id' => 'nullable|integer', 'asset_id' => 'nullable|integer', 'statuses' => 'nullable|array', 'statuses.*' => 'string|max:80', 'exclude' => 'nullable|array', 'exclude.*' => 'integer', 'sort' => 'nullable|in:date,status'] + ServiceReportLayout::rules());
        $reportLayout = new ServiceReportLayout($data);
        $summary = $reports->summary($tenant->id, Carbon::parse($data['from']), Carbon::parse($data['to']), $data['provider_id'] ?? null, $data['service_id'] ?? null, $data['asset_id'] ?? null, $data);

        $request->validate(['report_type' => 'nullable|in:accountability,conference']);
        if ($request->input('report_type') === 'conference') {
            return app(TemplatedPdfService::class)->generateSystemPdf('pdf.service-conference', compact('summary', 'tenant', 'reportLayout'), [
                'title' => 'Relatório de conferência de serviços', 'paper' => 'a4', 'orientation' => 'landscape', 'prefer_runtime_layout' => true,
            ])->download('conferencia-servicos.pdf');
        }
        return app(TemplatedPdfService::class)->generateSystemPdf('pdf.service-accountability', compact('summary', 'tenant', 'reportLayout') + ['suppress_internal_footer' => true], [
            'title' => 'Prestação de contas de serviços', 'paper' => 'a4', 'orientation' => 'landscape', 'prefer_runtime_layout' => true,
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
        $data = $request->validate([
            'type' => 'required|in:order,execution,note',
            'copy' => 'nullable|in:beneficiary|prohibited_unless:type,order',
        ]);
        $type = $data['type'];
        $beneficiaryOnly = ($data['copy'] ?? null) === 'beneficiary';
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
        $variables['beneficiary_only'] = $beneficiaryOnly;
        $title = $type === 'order' ? 'Ordem '.$order->number : 'Execução '.$order->number;
        if ($beneficiaryOnly) {
            $title .= ' · Via do beneficiário';
        }
        $document = $documents->generate($subject, $type === 'order' ? 'service_order' : 'service_execution', $title, $variables, $request->user());

        return redirect()->route('services.management.documents.download', [$tenant->slug, $document, 'inline' => 1]);
    }

    public function document(Request $request, Tenant $tenant, int $document, TemplatedPdfService $pdf)
    {
        $this->allow($request, 'view_service_management');
        $record = GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereKey($document)->firstOrFail();
        if (in_array($record->documentable_type, [ServiceOrder::class, ServiceExecution::class], true)) {
            $subject = $record->documentable;
            $documentOrder = $subject instanceof ServiceExecution ? $subject->order : $subject;
            abort_if($documentOrder?->operational_status === 'cancelled', 410, 'Documento de ordem anulada.');
        }
        if (in_array(data_get($record->document_settings, 'template_key'), ['service_internal_note', 'service_execution'], true)) {
            $this->allow($request, 'view_service_financials');
        }

        $filename = str($record->title)->slug().'.pdf';
        $systemView = data_get($record->document_settings, 'system_view');
        $documentPdf = $systemView ? $pdf->generateSystemPdf('pdf.service-issued-document', [
            'tenant' => $tenant, 'title' => $record->title, 'body' => $record->content,
            'suppress_internal_footer' => true,
        ], ['configuration_view' => $systemView, 'title' => $record->title]) : $pdf->generateFrozenDocument($record);

        $response = $request->boolean('inline')
            ? $documentPdf->stream($filename)
            : $documentPdf->download($filename);

        $response->headers->add([
            'Cache-Control' => 'no-store, private',
            'X-SGC-Document-Origin' => 'generated',
            'X-SGC-Document-Path' => 'Serviços/Documentos',
            'X-SGC-Document-Title' => $record->title,
        ]);

        return $response;
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

    private function attachFinancialPreview(ServiceOrder $order): void
    {
        if (!in_array($order->execution?->status, ['draft', 'in_progress', 'rejected', 'submitted'], true)) return;
        try {
            $preview = $order->execution->status === 'submitted'
                ? app(\App\Services\Services\ServiceCompositionCalculator::class)->calculate($order->execution, false)
                : app(ServiceExecutionWorkflow::class)->preview($order->execution, []);
            $order->execution->setAttribute('management_preview', $preview);
        } catch (ValidationException $exception) {
            if ($order->execution->status === 'submitted') {
                $order->execution->setAttribute('management_preview', ['error' => collect($exception->errors())->flatten()->first()]);
            }
        }
    }

    private function executionDocumentVariables(ServiceOrder $order): array
    {
        $execution = $order->execution;
        if ($execution->status === 'submitted') {
            $this->attachFinancialPreview($order);
        }
        $preview = (array) ($execution->management_preview ?? []);
        $documentLines = $execution->isFrozen() ? $execution->compositionLines : collect(array_merge($preview['receivable'] ?? [], $preview['payable'] ?? []))->map(fn ($line) => (object) $line);
        $documentTotal = static function (string $direction) use ($execution, $preview, $documentLines): string {
            $obligations = $execution->obligations->where('direction', $direction);
            $total = $execution->isFrozen() ? ($obligations->isNotEmpty() ? $obligations->sum('total_amount') : $documentLines->where('direction', $direction)->sum('amount')) : ($preview[$direction.'_total'] ?? null);
            return $total === null ? '' : 'R$ '.number_format((float) $total, 2, ',', '.');
        };
        $variables = [];
        $fieldRows = '';

        foreach ((array) data_get($execution->catalog_snapshot, 'fields', []) as $field) {
            if (! ($field['include_in_documents'] ?? false)) {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $value = data_get($execution->values, $key);
            if (in_array($field['type'] ?? null, ['file', 'image', 'signature'], true)) {
                continue;
            } elseif (is_bool($value)) {
                $value = $value ? 'Sim' : 'Não';
            } elseif (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = filled($value) ? (string) $value : '';
            if (in_array(trim($value), ['-', '–', '—'], true)) {
                $value = '';
            }
            $variables['campo.'.$key] = $value;
            $fieldRows .= '<tr><td>'.e($field['label'] ?? $key).'</td><td>'.e($value).'</td><td>'.e($value === '' ? '' : ($field['unit'] ?? '')).'</td></tr>';
        }

        [$meterStart] = \App\Services\Services\ServiceCalculationRules::meterPair((array) $execution->catalog_snapshot);
        $pauseRows = [];
        if ($meterStart) {
            $saved = json_decode((string) data_get($execution->values, 'meter_pauses_'.$meterStart, '[]'), true);
            $pauseRows = is_array($saved) ? array_filter($saved, 'is_array') : [];
        }
        $composition = ['receivable' => '', 'payable' => ''];
        foreach ($documentLines as $line) {
            $formula = data_get($line->rule_snapshot, 'formula') ?: trim(($line->quantity ?? '').' × '.($line->unit_price ?? ''));
            $composition[$line->direction] .= '<tr><td>'.e($line->description).'</td><td>'.e($formula).'</td><td style="text-align:right">'.($line->amount < 0 ? '− ' : '').'R$ '.number_format(abs((float) $line->amount), 2, ',', '.').'</td></tr>';
        }
        foreach ($execution->obligations as $obligation) {
            foreach ($obligation->adjustments as $adjustment) {
                $composition[$obligation->direction] .= '<tr><td>'.e($adjustment->reason).'</td><td>Ajuste registrado</td><td style="text-align:right">'.($adjustment->amount < 0 ? '- ' : '').'R$ '.number_format(abs((float) $adjustment->amount), 2, ',', '.').'</td></tr>';
            }
        }

        $table = static fn (string $rows): string => $rows === ''
            ? '<p>Nenhum item.</p>'
            : '<table class="data-table"><thead><tr><th>Descrição</th><th>Fórmula</th><th class="text-right">Total</th></tr></thead><tbody>'.$rows.'</tbody></table>';

        return $variables + [
            'meter_enabled' => filled($meterStart), 'meter_pauses' => $pauseRows,
            'payment_received' => 'R$ '.number_format((float) $execution->obligations->where('direction', 'receivable')->sum('paid_amount'), 2, ',', '.'),
            'payment_balance' => $execution->isFrozen() ? 'R$ '.number_format((float) $execution->obligations->where('direction', 'receivable')->sum('balance'), 2, ',', '.') : 'A confirmar após conferência',
            'financial_visible' => auth()->user()?->checkPermissionTo('view_service_financials') ?? false,
            'execution_date' => $order->execution_date?->format('d/m/Y'),
            'start_time' => $order->start_time ? substr($order->start_time, 0, 5) : null,
            'end_time' => $order->end_time ? substr($order->end_time, 0, 5) : null,
            'notes' => data_get($order->order_data, 'notes'),
            'campos_execucao' => $fieldRows === '' ? '<div style="height:22px;border-bottom:1px solid #bbb"></div>' : '<table class="data-table"><thead><tr><th>Campo</th><th>Valor</th><th>Unidade</th></tr></thead><tbody>'.$fieldRows.'</tbody></table>',
            'composicao_receber' => $table($composition['receivable']),
            'composicao_pagar' => $table($composition['payable']),
            'total_receber' => $documentTotal('receivable'),
            'total_pagar' => $documentTotal('payable'),
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
