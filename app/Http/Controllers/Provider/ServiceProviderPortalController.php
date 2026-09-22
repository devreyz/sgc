<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\Associate;
use App\Models\ChartAccount;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceObligation;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Models\Tenant;
use App\Services\Services\CreateServiceOrder;
use App\Services\Services\ServiceCompositionCalculator;
use App\Services\Services\ServiceEvidenceService;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServiceExpenseAttachmentService;
use App\Services\Services\ServiceExpenseService;
use App\Services\Services\ServicePayoutRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceProviderPortalController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $base = ServiceOrder::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->with(['service', 'execution.obligations']);
        $today = (clone $base)->whereDate('scheduled_at', today())->orderBy('scheduled_at')->get();
        $upcoming = (clone $base)->whereDate('scheduled_at', '>', today())->orderBy('scheduled_at')->limit(8)->get();
        $pending = (clone $base)->whereIn('operational_status', ['in_progress', 'submitted', 'rejected'])->orderBy('scheduled_at')->get();
        $payables = ServiceObligation::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->where('direction', 'payable')->get();

        return view('provider.services-dashboard', compact('provider', 'operator', 'today', 'upcoming', 'pending') + ['due' => $payables->sum('total_amount'), 'paid' => $payables->sum('paid_amount'), 'balance' => $payables->sum('balance')]);
    }

    public function orders(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->with(['service', 'serviceProvider', 'execution.obligations'])->latest('scheduled_at')->paginate(20);

        return view('provider.services-orders', compact('provider', 'operator', 'orders'));
    }

    public function create(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $versions = $this->availableVersions($tenant, $provider, $operator)->get();
        $providers = $operator ? ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->orderBy('name')->get() : collect([$provider]);

        $associates = Associate::query()->where('tenant_id', $tenant->id)->active()->orderBy('id')->get();

        return view('provider.services-create', compact('provider', 'operator', 'providers', 'versions', 'associates'));
    }

    public function store(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $data = $request->validate(['service_version_id' => 'required|integer', 'service_provider_id' => $operator ? 'required|integer' : 'nullable', 'associate_id' => 'nullable|integer', 'beneficiary_name' => 'nullable|string|max:191', 'asset_id' => 'nullable|integer', 'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:191', 'order_data' => 'array']);
        if ($operator) {
            $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)->whereKey($data['service_provider_id'])->firstOrFail();
        }
        $version = $this->availableVersions($tenant, $provider, $operator)->whereKey($data['service_version_id'])->firstOrFail();
        $associate = filled($data['associate_id'] ?? null)
            ? Associate::query()->where('tenant_id', $tenant->id)->active()->find($data['associate_id'])
            : null;
        $beneficiaryName = trim((string) ($data['beneficiary_name'] ?? ''));
        if ($beneficiaryName === '' && $associate) {
            $beneficiaryName = trim((string) ($associate->nickname ?: $associate->display_name));
        }
        if ($beneficiaryName === '') {
            throw ValidationException::withMessages(['beneficiary_name' => 'Informe o nome/apelido ou selecione um membro.']);
        }
        $order = $creator->handle($tenant->id, $version, $data + ['service_provider_id' => $provider->id, 'beneficiary_name' => $beneficiaryName], $request->user());

        return redirect()->route('provider.orders.show', [$tenant, $order]);
    }

    public function show(Request $request, Tenant $tenant, int $order): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $financialPreview = null;
        try {
            $financialPreview = app(ServiceCompositionCalculator::class)->calculate($order->execution, false);
        } catch (ValidationException) {
            // Campos ainda incompletos: a tela mostra as tarifas e aguarda os dados.
        }

        return view('provider.services-show', compact('provider', 'operator', 'order', 'financialPreview'));
    }

    public function start(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['operation_key' => 'required|uuid', 'values' => 'array', 'evidences' => 'array', 'evidences.*' => 'nullable|file|max:12288']);
        DB::transaction(function () use ($request, $order, $operator, $data, $workflow, $evidence): void {
            $this->uploadSubmittedEvidences($request, $order, $operator, ['start'], $evidence);
            $workflow->start($order->execution, $this->portalValues($order, $data['values'] ?? [], ['start'], $operator), $data['operation_key'], $request->user());
        }, 3);

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), 'Serviço iniciado.', true);
    }

    public function draft(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['values' => 'array', 'evidences' => 'array', 'evidences.*' => 'nullable|file|max:12288']);
        DB::transaction(function () use ($request, $order, $operator, $data, $workflow, $evidence): void {
            $this->uploadSubmittedEvidences($request, $order, $operator, ['start', 'execution', 'finish'], $evidence);
            $workflow->saveDraft($order->execution, $this->portalValues($order, $data['values'] ?? [], ['start', 'execution', 'finish'], $operator), $request->user());
        }, 3);

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), 'Rascunho salvo.', false);
    }

    public function submit(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow, ServiceEvidenceService $evidence): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['operation_key' => 'required|uuid', 'values' => 'array', 'evidences' => 'array', 'evidences.*' => 'nullable|file|max:12288']);
        $execution = DB::transaction(function () use ($request, $order, $operator, $data, $workflow, $evidence) {
            $this->uploadSubmittedEvidences($request, $order, $operator, ['execution', 'finish'], $evidence);

            return $workflow->submit($order->execution, $this->portalValues($order, $data['values'] ?? [], ['execution', 'finish'], $operator), $data['operation_key'], $request->user());
        }, 3);

        $message = $execution->status === 'validated' ? 'Serviço validado e obrigações geradas.' : 'Serviço enviado para conferência.';

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), $message, true);
    }

    public function upload(Request $request, Tenant $tenant, int $order, ServiceEvidenceService $evidence): RedirectResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $order = $this->order($tenant, $provider, $operator, $order);
        $data = $request->validate(['field_key' => 'required|string|max:80', 'file' => 'required|file|max:12288']);
        $allowed = collect(data_get($order->execution->catalog_snapshot, 'fields', []))->contains(fn (array $field) => $field['key'] === $data['field_key']
            && in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)
            && ($operator ? ($field['visible_to_management'] ?? true) : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false))));
        abort_unless($allowed, 422, 'Esta evidência não pode ser enviada pelo prestador.');
        $evidence->upload($order->execution, $data['field_key'], $data['file'], $request->user());

        return back()->with('success', 'Evidência anexada.');
    }

    public function approve(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse|JsonResponse
    {
        [$provider, $operator] = $this->access($request, $tenant);
        abort_unless($operator && $request->user()->checkPermissionTo('approve_service_execution'), 403);
        $order = $this->order($tenant, $provider, true, $order);
        $data = $request->validate(['operation_key' => 'required|uuid']);
        $workflow->approve($order->execution, $data['operation_key'], $request->user());

        return $this->mutationResponse($request, $tenant, $order->fresh(['execution.evidences.document']), 'Execução conferida e concluída.', true);
    }

    public function financial(Request $request, Tenant $tenant): View
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $query = ServiceObligation::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->where('direction', 'payable');
        $due = (float) (clone $query)->selectRaw('COALESCE(SUM(principal_amount + adjustment_amount), 0) AS aggregate')->value('aggregate');
        $paid = (float) DB::table('service_payment_allocations as allocation')
            ->join('service_obligations as obligation', 'obligation.id', '=', 'allocation.service_obligation_id')
            ->join('service_payment_events as payment', 'payment.id', '=', 'allocation.service_payment_event_id')
            ->where('obligation.tenant_id', $tenant->id)
            ->where('obligation.direction', 'payable')
            ->when(! $operator, fn ($builder) => $builder->where('obligation.service_provider_id', $provider->id))
            ->whereIn('payment.status', ['confirmed', 'reversed'])
            ->sum('allocation.amount');
        $summary = ['due' => round($due, 2), 'paid' => round($paid, 2), 'balance' => max(0, round($due - $paid, 2))];
        $obligations = $query->with(['execution.order.service', 'provider'])->latest()->paginate(20);

        return view('provider.services-financial', compact('provider', 'operator', 'obligations', 'summary'));
    }

    public function payout(Request $request, Tenant $tenant, ServicePayoutRequestService $service): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $request->merge(['amounts' => array_filter((array) $request->input('amounts', []), fn ($value) => (float) $value > 0)]);
        $data = $request->validate(['operation_key' => 'required|uuid', 'amounts' => 'required|array|min:1', 'amounts.*' => 'numeric|min:0.01']);
        $service->create($provider, $data['amounts'], $data['operation_key'], $request->user());

        return back()->with('success', 'Solicitação enviada para análise.');
    }

    public function evidence(Request $request, Tenant $tenant, int $evidence, ServiceEvidenceService $files)
    {
        [$provider, $operator] = $this->access($request, $tenant);
        $record = ServiceExecutionEvidence::query()->where('tenant_id', $tenant->id)->whereKey($evidence)->when(! $operator, fn ($query) => $query->whereHas('execution', fn ($q) => $q->where('service_provider_id', $provider->id)))->with('document')->firstOrFail();

        return response($files->contents($record->document), 200, ['Content-Type' => $record->document->mime_type, 'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $record->document->original_name).'"']);
    }

    public function expenses(Request $request, Tenant $tenant): View
    {
        [, $operator] = $this->access($request, $tenant);
        abort_unless($operator && $request->user()->checkPermissionTo('manage_service_expenses'), 403);
        $expenses = Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')->with(['expenseable', 'documents'])->latest('date')->paginate(25);
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->latest()->limit(200)->get(['id', 'number', 'beneficiary_snapshot', 'scheduled_at']);
        $accounts = ChartAccount::query()->where('tenant_id', $tenant->id)->where('type', 'despesa')->orderBy('name')->get(['id', 'name']);

        return view('provider.services-expenses', compact('operator', 'expenses', 'orders', 'accounts'));
    }

    public function storeExpense(Request $request, Tenant $tenant, ServiceExpenseService $service, ServiceExpenseAttachmentService $attachments): RedirectResponse|JsonResponse
    {
        [, $operator] = $this->access($request, $tenant);
        abort_unless($operator && $request->user()->checkPermissionTo('manage_service_expenses'), 403);
        $data = $request->validate([
            'description' => 'required|string|max:191', 'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date', 'due_date' => 'required|date|after_or_equal:date', 'service_order_id' => 'nullable|integer',
            'chart_account_id' => 'nullable|integer', 'document_number' => 'nullable|string|max:80', 'notes' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array|max:5', 'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:12288',
        ]);
        DB::transaction(function () use ($tenant, $data, $request, $service, $attachments): void {
            $expense = $service->create($tenant, $data, $request->user());
            foreach ($request->file('attachments', []) as $file) {
                $attachments->upload($tenant, $expense, $file, $request->user());
            }
        }, 3);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Despesa de serviço registrada. O pagamento continua sendo controlado pelo motor financeiro.',
                'url' => route('provider.expenses', $tenant->slug),
            ]);
        }

        return back()->with('success', 'Despesa de serviço registrada. O pagamento continua sendo controlado pelo motor financeiro.');
    }

    public function expenseDocument(Request $request, Tenant $tenant, int $document, ServiceExpenseAttachmentService $attachments)
    {
        [, $operator] = $this->access($request, $tenant);
        abort_unless($operator && $request->user()->checkPermissionTo('manage_service_expenses'), 403);
        $record = Document::query()->where('tenant_id', $tenant->id)
            ->where('documentable_type', Expense::class)->whereKey($document)->with('documentable')->firstOrFail();
        abort_unless($record->documentable?->origin_module === 'services', 404);

        return response($attachments->contents($record), 200, [
            'Content-Type' => $record->mime_type,
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $record->original_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array{0:?ServiceProvider,1:bool} */
    private function access(Request $request, Tenant $tenant): array
    {
        $operator = $request->user()->checkPermissionTo('operate_all_service_orders_portal');
        $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', true)->first();
        abort_unless($operator || $provider, 403, 'Seu acesso não permite operar serviços nesta organização.');

        return [$provider, $operator];
    }

    private function provider(Request $request, Tenant $tenant): ServiceProvider
    {
        $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', true)->first();
        abort_unless($provider, 403, 'Seu acesso não está vinculado a um prestador ativo.');

        return $provider;
    }

    private function order(Tenant $tenant, ?ServiceProvider $provider, bool $operator, int $id): ServiceOrder
    {
        return ServiceOrder::query()->where('tenant_id', $tenant->id)->when(! $operator, fn ($query) => $query->where('service_provider_id', $provider->id))->whereKey($id)->with(['service', 'serviceVersion.providerRates', 'serviceProvider', 'execution.evidences.document', 'execution.resources', 'execution.compositionLines', 'execution.obligations'])->firstOrFail();
    }

    private function availableVersions(Tenant $tenant, ?ServiceProvider $provider, bool $operator)
    {
        return ServiceVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->when(! $operator, fn ($query) => $query->where('allow_provider_create_order', true))
            ->whereHas('service', fn ($query) => $query->where('status', true))
            ->when(! $operator, fn ($query) => $query->whereHas('service.serviceProviders', fn ($eligible) => $eligible->whereKey($provider->id)))
            ->with(['service', 'fields'])
            ->orderBy('service_id');
    }

    private function portalValues(ServiceOrder $order, array $values, array $phases, bool $operator): array
    {
        $keys = collect(data_get($order->execution->catalog_snapshot, 'fields', []))
            ->filter(fn (array $field) => in_array($field['phase'] ?? null, $phases, true)
                && ($operator ? ($field['visible_to_management'] ?? true) : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false))))
            ->pluck('key')->all();

        return array_intersect_key($values, array_flip($keys));
    }

    private function uploadSubmittedEvidences(Request $request, ServiceOrder $order, bool $operator, array $phases, ServiceEvidenceService $service): void
    {
        $fields = collect(data_get($order->execution->catalog_snapshot, 'fields', []))->keyBy('key');
        foreach ((array) $request->file('evidences', []) as $key => $file) {
            if (! $file) {
                continue;
            }
            $field = $fields->get($key);
            $allowed = $field
                && in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)
                && in_array($field['phase'] ?? null, $phases, true)
                && ($operator ? ($field['visible_to_management'] ?? true) : (($field['visible_to_provider'] ?? false) && ($field['editable_by_provider'] ?? false)));
            abort_unless($allowed, 422, 'Esta evidência não pode ser enviada neste momento.');
            $service->upload($order->execution, (string) $key, $file, $request->user());
        }
    }

    private function mutationResponse(Request $request, Tenant $tenant, ServiceOrder $order, string $message, bool $reload): RedirectResponse|JsonResponse
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        $order->loadMissing('execution.evidences.document');

        return response()->json([
            'ok' => true,
            'message' => $message,
            'reload' => $reload,
            'url' => route(str_starts_with((string) $request->route()?->getName(), 'services.management.execute.') ? 'services.management.execute' : 'provider.orders.show', [$tenant, $order]),
            'status' => $order->execution?->status,
            'values' => $order->execution?->values ?? [],
            'evidences' => $order->execution?->evidences->map(fn (ServiceExecutionEvidence $item): array => [
                'field_key' => $item->field_key,
                'name' => $item->document?->original_name,
                'mime_type' => $item->document?->mime_type,
                'url' => route('provider.evidences.download', [$tenant, $item]),
            ])->values()->all() ?? [],
        ]);
    }
}
