<?php

namespace App\Http\Controllers\Services;

use App\Http\Controllers\Controller;
use App\Enums\ServiceOrderStatus;
use App\Enums\DocumentCategory;
use App\Enums\PaymentMethod;
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
use App\Services\Services\ServiceMoney;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            ->when($request->input('status') === 'cancelled' && $request->user()->checkPermissionTo('edit_service_order'), fn ($query) => $query->withTrashed())
            ->with(['service', 'execution.compositionLines', 'execution.obligations' => fn ($query) => $query->with('latestRateAdjustment')->withSum([
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
        $data = $request->validate([
            'reason' => 'required|string|min:5|max:500',
            'action' => 'required|in:delete,cancel,cancel_replace,reopen,purge',
            'confirmation' => 'required_if:action,purge|nullable|string|max:64',
        ]);
        if (in_array($data['action'], ['reopen', 'purge'], true)) {
            $order = ServiceOrder::withTrashed()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->findOrFail($order);
            if ($data['action'] === 'purge' && $data['confirmation'] !== $order->number) {
                throw ValidationException::withMessages(['confirmation' => 'Digite o número exato da ordem.']);
            }
            app(\App\Services\Services\ServiceOrderCorrection::class)->handle($order, $data['action'], $data['reason'], $request->user());
            if ($data['action'] === 'reopen') {
                return redirect()->route('services.management.edit', [$tenant->slug, $order])
                    ->with('success', 'Ordem reaberta com o mesmo número. Corrija os dados e finalize novamente.');
            }

            return redirect()->route('services.management.index', [$tenant->slug, 'status' => $order->operational_status])
                ->with('success', 'Ordem excluída definitivamente. O número foi liberado para reutilização no ano da ordem.');
        }
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
                || $lockedOrder->payments()->exists() || $lockedOrder->ledgerEntries()->withTrashed()->exists()
                || $lockedOrder->paid || $lockedOrder->paid_date || $lockedOrder->associate_paid_at || $lockedOrder->provider_paid_at) {
                throw ValidationException::withMessages(['reason' => 'Esta ordem já foi cancelada ou tem movimentação financeira anterior. Consulte o dossiê.']);
            }
            if ($obligations->contains(fn ($obligation) => $obligation->paid_amount !== 0.0 || $obligation->activePaymentPlan())
                || ServicePayoutRequestItem::query()->whereIn('service_obligation_id', $obligations->modelKeys())
                    ->whereHas('request', fn ($query) => $query->whereIn('status', ['pending', 'approved']))->exists()) {
                throw ValidationException::withMessages(['reason' => 'Antes de anular, resolva os pagamentos, acordos ou solicitações de repasse ativos no dossiê.']);
            }
            if ($data['action'] === 'delete') {
                if (! in_array($execution->status, ['draft', 'in_progress', 'submitted'], true)) {
                    throw ValidationException::withMessages(['reason' => 'A situação da ordem mudou. Atualize a lista antes de continuar.']);
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
                if ($execution->status !== 'submitted' && ($execution->evidences()->exists() || $execution->resources()->exists())) {
                    throw ValidationException::withMessages(['reason' => 'Esta ordem tem anexos ou recursos vinculados. Use a anulação para manter os vínculos disponíveis no dossiê.']);
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
            'beneficiary_whatsapp' => 'nullable|regex:/\A\+?[0-9 ()-]{10,25}\z/',
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
        $order->loadMissing(['execution.obligations.allocations.paymentEvent.bankAccount', 'execution.obligations.latestRateAdjustment']);
        $accounts = BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        return view('services.management-show', compact('tenant', 'order', 'accounts', 'statuses'));
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

    public function correctReceiptAccount(Request $request, Tenant $tenant, int $payment, ServicePaymentService $payments): RedirectResponse
    {
        $this->allow($request, 'reverse_service_payment');
        $this->allow($request, 'manage_service_receivables');
        $event = ServicePaymentEvent::query()->where('tenant_id', $tenant->id)->whereKey($payment)->firstOrFail();
        $data = $request->validate([
            'operation_key' => 'required|uuid',
            'bank_account_id' => 'required|integer|min:1',
            'reason' => 'required|string|min:5|max:500',
        ]);
        $payments->correctReceiptAccount($event, (int) $data['bank_account_id'], $data['reason'], $data['operation_key'], $request->user());

        return back()->with('success', 'Conta corrigida. O recebimento mantém o mesmo valor e a mesma data.')
            ->with('service_receipt_account_corrected', $event->id);
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
        return $this->updateObligationRate($request, $tenant, $obligation, $adjustments, 'payable');
    }

    public function updateCustomerRate(Request $request, Tenant $tenant, int $obligation, ServiceObligationAdjustmentService $adjustments): RedirectResponse
    {
        return $this->updateObligationRate($request, $tenant, $obligation, $adjustments, 'receivable');
    }

    private function updateObligationRate(Request $request, Tenant $tenant, int $obligation, ServiceObligationAdjustmentService $adjustments, string $direction): RedirectResponse
    {
        $provider = $direction === 'payable';
        $field = $provider ? 'provider_rate' : 'customer_rate';
        $this->allow($request, 'view_service_management');
        $this->allow($request, $provider ? 'manage_service_payables' : 'manage_service_receivables');
        $record = ServiceObligation::query()->where('tenant_id', $tenant->id)->where('direction', $direction)->findOrFail($obligation);
        $data = $request->validate(['operation_key' => 'required|uuid', $field => 'required|numeric|decimal:0,4|gt:0|max:999999999', 'expected_total' => 'required|numeric|min:0', 'reason' => 'required|string|min:5|max:500']);
        $arguments = [$record, (float) $data[$field], (float) $data['expected_total'], $data['reason'], $data['operation_key'], $request->user()];
        if ($provider) {
            $adjustments->changeProviderRate(...$arguments);
        } else {
            $adjustments->changeCustomerRate(...$arguments);
        }

        return back()->with('success', 'Tarifa atualizada nesta OS. O saldo foi recalculado e os pagamentos foram preservados.');
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

    public function agreements(Request $request, Tenant $tenant, ServiceNegotiationService $negotiations): View
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate([
            'origin' => 'nullable|integer|min:1', 'beneficiary' => 'nullable|integer|min:1',
            'supersedes' => 'nullable|integer|min:1', 'q' => 'nullable|string|max:100',
        ]);
        $creating = $request->routeIs('services.management.agreements.create');
        $agreementQuery = ServiceNegotiation::query()->where('tenant_id', $tenant->id);
        $agreement = $request->route('agreement')
            ? (clone $agreementQuery)->with(['plan.installments.paymentEvent', 'plan.installments.verificationIdentity', 'plan.obligations.execution.order.service', 'generatedDocument', 'supersedes'])->findOrFail($request->route('agreement'))
            : null;
        $agreements = $creating || $agreement ? null : $agreementQuery->with('plan')->latest('id')->paginate(20);
        $previous = $creating && isset($data['supersedes'])
            ? (clone $agreementQuery)->where('status', 'active')->with('plan.obligations')->findOrFail($data['supersedes'])
            : null;
        abort_if($previous && $previous->plan?->status !== 'active', 404);

        $origin = isset($data['origin']) ? $this->order($tenant, $data['origin']) : null;
        $originDebt = $origin?->execution?->obligations->firstWhere('direction', 'receivable');
        $previousDebts = $previous?->plan->obligations ?? collect();
        $beneficiaryId = $origin ? $originDebt?->associate_id : ($previous ? $previousDebts->first()?->associate_id : ($data['beneficiary'] ?? null));
        $beneficiary = $beneficiaryId
            ? Associate::withTrashed()->where('tenant_id', $tenant->id)->with('user')->findOrFail($beneficiaryId)
            : null;
        $eligible = $negotiations->eligible($tenant->id, $previous?->plan?->id);
        $obligations = collect();
        $matches = collect();
        if ($creating && ($beneficiary || $originDebt || $previous)) {
            $obligations = (clone $eligible)
                ->when($beneficiary, fn ($query) => $query->where('associate_id', $beneficiary->id),
                    fn ($query) => $query->whereIn('id', $previous ? $previousDebts->modelKeys() : [$originDebt?->id]))
                ->with('execution.order.service')->withSum([
                    'allocations as settled_amount' => fn ($query) => $query->whereHas('paymentEvent', fn ($events) => $events->whereIn('status', ['confirmed', 'reversed'])),
                ], 'amount')->orderBy('due_date')->orderBy('id')->get()->filter(fn (ServiceObligation $debt) => $debt->balanceCents() > 0);
        } elseif ($creating && ! $origin && filled($data['q'] ?? null)) {
            $search = '%'.addcslashes(trim($data['q']), '%_\\').'%';
            $matches = (clone $eligible)->where(function ($query) use ($search) {
                $query->where('party_snapshot->name', 'like', $search)
                    ->orWhereHas('associate', fn ($person) => $person->where('nickname', 'like', $search)
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', $search)))
                    ->orWhereHas('execution.order', fn ($order) => $order->where('number', 'like', $search));
            })->with(['associate.user', 'execution.order'])->latest('id')->limit(100)->get()
                ->unique(fn (ServiceObligation $debt) => $debt->associate_id ? 'associate-'.$debt->associate_id : 'debt-'.$debt->id)
                ->take(25);
        }
        $activeAgreement = $originDebt && ! $obligations->contains('id', $originDebt->id)
            ? (clone $agreementQuery)->whereHas('plan', fn ($plan) => $plan->where('status', 'active')
                ->whereHas('obligations', fn ($debt) => $debt->whereKey($originDebt->id)))->first()
            : null;
        $requiredIds = $previous ? $obligations->whereIn('id', $previousDebts->modelKeys())->pluck('id')->all() : [];
        if ($originDebt && $obligations->contains('id', $originDebt->id)) {
            $requiredIds[] = $originDebt->id;
        }
        if ($origin && ! $obligations->contains('id', $originDebt?->id)) {
            $obligations = collect();
        }

        $events = $agreement?->plan ? $agreement->plan->receiptEvents()->with(['allocations.obligation.execution.order', 'registrar', 'receiptProof'])
            ->orderByDesc('id')->get() : collect();
        foreach ($agreement?->plan?->installments ?? [] as $item) {
            if ($item->paymentEvent && ! $events->contains('id', $item->paymentEvent->id)) {
                $events->push($item->paymentEvent->loadMissing(['allocations.obligation.execution.order', 'registrar', 'receiptProof']));
            }
        }
        $accounts = $agreement?->plan?->status === 'active' && $request->user()->checkPermissionTo('manage_service_receivables')
            ? BankAccount::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get() : collect();

        return view('services.agreements', compact('tenant', 'creating', 'agreement', 'agreements', 'previous', 'origin',
            'originDebt', 'activeAgreement', 'beneficiary', 'obligations', 'matches', 'requiredIds', 'events', 'accounts'));
    }

    public function negotiate(Request $request, Tenant $tenant, ServiceNegotiationService $negotiations): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate([
            'operation_key' => 'required|uuid',
            'origin' => 'nullable|integer|min:1',
            'supersedes' => 'nullable|integer|min:1',
            'received_entry' => 'nullable|boolean',
            'obligation_ids' => 'required|array|min:1|max:120',
            'obligation_ids.*' => 'required|integer|distinct|min:1',
            'installments' => 'required|array|min:1|max:120',
            'installments.*.kind' => 'required|in:entry,installment',
            'installments.*.due_date' => 'required|date_format:Y-m-d',
            'installments.*.amount' => 'required|decimal:0,2|min:0.01|max:999999999999.99',
        ]);
        if (isset($data['origin'])) {
            $origin = $this->order($tenant, $data['origin']);
            $originDebt = $origin->execution?->obligations->firstWhere('direction', 'receivable');
            if (! $originDebt || ! in_array($originDebt->id, $data['obligation_ids'])) {
                throw ValidationException::withMessages(['obligation_ids' => 'Inclua a ordem de origem na negociação.']);
            }
        }
        $previous = isset($data['supersedes'])
            ? ServiceNegotiation::query()->where('tenant_id', $tenant->id)->findOrFail($data['supersedes']) : null;
        $agreement = $negotiations->create($tenant->id, $data['obligation_ids'], $data['installments'], $request->user(), $previous, $data['operation_key']);

        return redirect()->route('services.management.agreements.show', [$tenant->slug, $agreement, 'origin' => $data['origin'] ?? null])
            ->with('receive_agreement_entry', ($data['received_entry'] ?? false) ? $agreement->id : null)
            ->with('success', $previous ? 'Saldo renegociado.' : 'Negociação criada. Registre os recebimentos quando forem confirmados.');
    }

    public function payAgreementInstallment(Request $request, Tenant $tenant, int $agreement, int $installment, ServicePaymentService $payments): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
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

    public function payAgreement(Request $request, Tenant $tenant, int $agreement, ServicePaymentService $payments): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $this->allow($request, 'manage_service_receivables');
        $agreement = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->with('plan')->findOrFail($agreement);
        abort_unless($agreement->plan, 404);
        $data = $request->validate([
            'operation_key' => 'required|uuid',
            'amounts' => 'required|array|max:120',
            'amounts.*' => 'nullable|decimal:0,2|min:0.01|max:999999999999.99',
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->except(PaymentMethod::CHEQUE)],
            'payment_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'bank_account_id' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
        ]);
        $amounts = array_filter($data['amounts'], fn ($amount) => $amount !== null);
        if ($amounts === [] || array_filter(array_keys($amounts), fn ($id) => ! is_int($id) || $id < 1)) {
            throw ValidationException::withMessages(['amounts' => 'Informe o valor recebido em pelo menos uma parcela.']);
        }
        $file = $request->file('proof');
        $details = ['document_number' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null];
        if ($file) {
            $details['proof_hash'] = hash_file('sha256', $file->getPathname());
        }

        $path = null;
        try {
            $event = DB::transaction(function () use ($tenant, $agreement, $data, $amounts, $details, $file, $payments, $request, &$path) {
                if ($path) {
                    Storage::disk('local')->delete($path);
                    $path = null;
                }
                $event = $payments->recordInstallments($agreement->plan, $amounts, $data['payment_method'], $data['payment_date'],
                    (int) $data['bank_account_id'], $data['operation_key'], $request->user(), $details);
                if ($file && ! $event->receiptProof()->exists()) {
                    $path = $file->store('service-payment-proofs/'.$tenant->id, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['proof' => 'O comprovante não pôde ser salvo. Tente novamente.']);
                    }
                    $document = $event->receiptProof()->make([
                        'name' => 'Comprovante de recebimento '.$event->id, 'original_name' => $file->getClientOriginalName(),
                        'path' => $path, 'disk' => 'local', 'mime_type' => $file->getMimeType(), 'size' => $file->getSize(),
                        'extension' => $file->extension(), 'category' => DocumentCategory::COMPROVANTE, 'uploaded_by' => $request->user()->id,
                    ]);
                    $document->tenant_id = $tenant->id;
                    $document->save();
                }

                return $event;
            }, 3);
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', 'Recebimento registrado.')->with('received_payment_id', $event->id);
    }

    public function rescheduleAgreementInstallment(Request $request, Tenant $tenant, int $agreement, int $installment, FinancialDocumentIdentityService $identities): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate(['due_date' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:500']);
        DB::transaction(function () use ($tenant, $agreement, $installment, $data, $request, $identities): void {
            $record = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($agreement);
            $plan = $record->plan()->lockForUpdate()->firstOrFail();
            $item = $plan->installments()->where('tenant_id', $tenant->id)->lockForUpdate()->findOrFail($installment);
            if ($record->status !== 'active' || $plan->status !== 'active' || $item->status !== 'scheduled' || $item->receivedCents() !== 0) {
                throw ValidationException::withMessages(['due_date' => 'Altere o vencimento apenas de uma parcela em aberto sem recebimento.']);
            }
            $before = $item->due_date->toDateString();
            if ($before === $data['due_date']) {
                return;
            }
            $item->update(['due_date' => $data['due_date']]);
            $identities->ensure($item, $request->user());
            activity('service_negotiation')->performedOn($item)->causedBy($request->user())->withProperties([
                'agreement_id' => $record->id, 'previous_due_date' => $before, 'due_date' => $data['due_date'], 'reason' => $data['reason'],
            ])->log('Vencimento alterado');
        }, 3);

        return back()->with('success', 'Vencimento atualizado.');
    }

    public function paymentReceipt(Request $request, Tenant $tenant, int $payment, TemplatedPdfService $pdf)
    {
        $event = $this->receivablePayment($request, $tenant, $payment);
        $agreement = ServiceNegotiation::query()->where('tenant_id', $tenant->id)
            ->where('payment_plan_id', data_get($event->metadata, 'payment_plan_id', 0))->with('plan.installments')->first();
        $installments = $agreement?->plan?->installments?->filter(fn ($installment) => $installment->eventCents($event) > 0) ?? collect();
        $title = 'Recibo de pagamento';
        $subtitle = 'REC-SVC-'.str_pad((string) $event->id, 6, '0', STR_PAD_LEFT);
        $document = $pdf->generateSystemPdf('pdf.service-payment-receipt', compact('tenant', 'event', 'agreement', 'installments', 'title', 'subtitle'),
            ['title' => $title, 'header_subtitle' => $subtitle, 'paper' => 'a4', 'prefer_runtime_layout' => true, 'standard_header' => true]);
        $filename = str($title.' '.$subtitle)->slug().'.pdf';
        $response = $request->boolean('inline') ? $document->stream($filename) : $document->download($filename);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    public function paymentProof(Request $request, Tenant $tenant, int $payment)
    {
        $event = $this->receivablePayment($request, $tenant, $payment);
        $document = $event->receiptProof()->where('tenant_id', $tenant->id)->firstOrFail();
        abort_unless($document->disk === 'local' && Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, 'comprovante-'.$event->id.'.'.$document->extension,
            ['Cache-Control' => 'no-store, private']);
    }

    private function receivablePayment(Request $request, Tenant $tenant, int $payment): ServicePaymentEvent
    {
        $this->allow($request, 'manage_service_receivables');

        return ServicePaymentEvent::query()->where('tenant_id', $tenant->id)->where('event_type', 'payment')
            ->whereIn('status', ['confirmed', 'reversed'])
            ->whereHas('allocations.obligation', fn ($query) => $query->where('tenant_id', $tenant->id)->where('direction', 'receivable'))
            ->with(['allocations.obligation.execution.order' => fn ($order) => $order->withTrashed(), 'registrar'])
            ->findOrFail($payment);
    }

    public function cancelAgreement(Request $request, Tenant $tenant, int $agreement, ServiceNegotiationService $negotiations): RedirectResponse
    {
        $this->allow($request, 'manage_service_agreements');
        $data = $request->validate(['reason' => 'required|string|min:5|max:500']);
        $record = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->findOrFail($agreement);
        $negotiations->cancel($record, $data['reason'], $request->user());

        return back()->with('success', 'Acordo cancelado. Os saldos estão disponíveis para nova negociação.');
    }

    public function regenerateAgreementDocument(Request $request, Tenant $tenant, int $agreement, ServiceDocumentService $documents)
    {
        $this->allow($request, 'manage_service_agreements');
        $agreement = ServiceNegotiation::query()->where('tenant_id', $tenant->id)->whereKey($agreement)->with('plan.installments')->firstOrFail();

        return $documents->pdf($tenant, 'service_negotiation', 'Nota interna de cobrança '.$agreement->number,
            $this->negotiationDocumentVariables($tenant, $agreement), $request->boolean('inline', true),
            'Nota interna de cobrança', $agreement->number);
    }

    public function generateOrderDocument(Request $request, Tenant $tenant, int $order, ServiceDocumentService $documents)
    {
        $this->allow($request, 'view_service_management');
        $order = $this->order($tenant, $order);
        $data = $request->validate([
            'type' => 'required|in:order,execution,note',
            'copy' => 'nullable|in:association,beneficiary|prohibited_unless:type,order',
        ]);
        $type = $data['type'];
        $inline = $request->boolean('inline', true);
        abort_unless($order->execution && $order->operational_status !== 'cancelled', 409, 'Esta ordem não permite emissão.');
        if ($type !== 'order') {
            $this->allow($request, 'view_service_financials');
            abort_unless($order->execution->isFrozen(), 409, 'Valide a execução antes de abrir a nota interna.');

            return $documents->pdf($tenant, 'service_internal_note', 'Nota interna de cobrança '.$order->number,
                $documents->orderVariables($order, true), $inline, 'Nota interna de cobrança', $order->number);
        }
        if ($request->user()->checkPermissionTo('view_service_financials')) {
            $this->attachFinancialPreview($order);
        }

        return $documents->orderPdf($tenant, $order, $data['copy'] ?? 'association',
            $request->user()->checkPermissionTo('view_service_financials'), $inline);
    }

    public function document(Request $request, Tenant $tenant, int $document, TemplatedPdfService $pdf)
    {
        $record = GeneratedDocument::query()->where('tenant_id', $tenant->id)->whereKey($document)->firstOrFail();
        $this->allow($request, $record->documentable_type === ServiceNegotiation::class ? 'manage_service_agreements' : 'view_service_management');
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
        ], ['configuration_view' => $systemView, 'title' => $record->title, 'standard_header' => true]) : $pdf->generateFrozenDocument($record);

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
        return $files->view($request, $record);
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
        $events = $agreement->plan?->receiptEvents()->get() ?? collect();
        $hasEntry = $agreement->plan?->installments->first()?->kind === 'entry';
        $installmentRows = $agreement->plan?->installments?->map(function (ServicePaymentPlanInstallment $item) use ($identityService, $events, $hasEntry): string {
            $kind = $item->kind === 'entry' ? 'Entrada' : 'Parcela '.($item->number - (int) $hasEntry);
            $status = $item->financialLabel($events);
            $identity = $item->status === 'scheduled' ? ($item->verificationIdentity ?: $identityService->ensure($item, auth()->user())) : null;
            $qr = $identity ? '<img src="'.$identityService->qrDataUri($identity, 70).'" width="44" height="44" alt="QR"><br><small>'.e($identity->reference_code).'</small>' : '';

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
