<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\ServiceExecutionEvidence;
use App\Models\ServiceObligation;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Models\Tenant;
use App\Services\Services\CreateServiceOrder;
use App\Services\Services\ServiceEvidenceService;
use App\Services\Services\ServiceExecutionWorkflow;
use App\Services\Services\ServicePayoutRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ServiceProviderPortalController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        $provider = $this->provider($request, $tenant);
        $base = ServiceOrder::query()->where('tenant_id', $tenant->id)->where('service_provider_id', $provider->id)->with(['service', 'execution.obligations']);
        $today = (clone $base)->whereDate('scheduled_at', today())->orderBy('scheduled_at')->get();
        $upcoming = (clone $base)->whereDate('scheduled_at', '>', today())->orderBy('scheduled_at')->limit(8)->get();
        $pending = (clone $base)->whereIn('operational_status', ['in_progress', 'submitted', 'rejected'])->orderBy('scheduled_at')->get();
        $payables = ServiceObligation::query()->where('tenant_id', $tenant->id)->where('service_provider_id', $provider->id)->where('direction', 'payable')->get();

        return view('provider.services-dashboard', compact('provider', 'today', 'upcoming', 'pending') + ['due' => $payables->sum('total_amount'), 'paid' => $payables->sum('paid_amount'), 'balance' => $payables->sum('balance')]);
    }

    public function orders(Request $request, Tenant $tenant): View
    {
        $provider = $this->provider($request, $tenant);
        $orders = ServiceOrder::query()->where('tenant_id', $tenant->id)->where('service_provider_id', $provider->id)->with(['service', 'execution.obligations'])->latest('scheduled_at')->paginate(20);

        return view('provider.services-orders', compact('provider', 'orders'));
    }

    public function create(Request $request, Tenant $tenant): View
    {
        $provider = $this->provider($request, $tenant);
        $versions = $this->availableVersions($tenant, $provider)->get();

        return view('provider.services-create', compact('provider', 'versions'));
    }

    public function store(Request $request, Tenant $tenant, CreateServiceOrder $creator): RedirectResponse
    {
        $request->validate(['beneficiary_name' => 'required|string|max:191']);
        $provider = $this->provider($request, $tenant);
        $data = $request->validate(['service_version_id' => 'required|integer', 'associate_id' => 'nullable|integer', 'asset_id' => 'nullable|integer', 'scheduled_at' => 'required|date', 'location' => 'nullable|string|max:191', 'order_data' => 'array']);
        $version = $this->availableVersions($tenant, $provider)->whereKey($data['service_version_id'])->firstOrFail();
        $order = $creator->handle($tenant->id, $version, $data + ['service_provider_id' => $provider->id, 'beneficiary_name' => $request->input('beneficiary_name')], $request->user());

        return redirect()->route('provider.orders.show', [$tenant, $order]);
    }

    public function show(Request $request, Tenant $tenant, int $order): View
    {
        $provider = $this->provider($request, $tenant);
        $order = $this->order($tenant, $provider, $order);

        return view('provider.services-show', compact('provider', 'order'));
    }

    public function start(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $order = $this->order($tenant, $provider, $order);
        $data = $request->validate(['operation_key' => 'required|uuid', 'values' => 'array']);
        $workflow->start($order->execution, $this->providerValues($order, $data['values'] ?? [], ['start']), $data['operation_key'], $request->user());

        return back()->with('success', 'Serviço iniciado.');
    }

    public function draft(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $order = $this->order($tenant, $provider, $order);
        $data = $request->validate(['values' => 'array']);
        $workflow->saveDraft($order->execution, $this->providerValues($order, $data['values'] ?? [], ['start', 'execution', 'finish']), $request->user());

        return back()->with('success', 'Rascunho salvo.');
    }

    public function submit(Request $request, Tenant $tenant, int $order, ServiceExecutionWorkflow $workflow): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $order = $this->order($tenant, $provider, $order);
        $data = $request->validate(['operation_key' => 'required|uuid', 'values' => 'array']);
        $execution = $workflow->submit($order->execution, $this->providerValues($order, $data['values'] ?? [], ['execution', 'finish']), $data['operation_key'], $request->user());

        return back()->with('success', $execution->status === 'validated' ? 'Serviço validado e obrigações geradas.' : 'Serviço enviado para conferência.');
    }

    public function upload(Request $request, Tenant $tenant, int $order, ServiceEvidenceService $evidence): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $order = $this->order($tenant, $provider, $order);
        $data = $request->validate(['field_key' => 'required|string|max:80', 'file' => 'required|file|max:12288']);
        $allowed = collect(data_get($order->execution->catalog_snapshot, 'fields', []))->contains(fn (array $field) => $field['key'] === $data['field_key']
            && in_array($field['type'] ?? null, ['image', 'file', 'signature'], true)
            && ($field['visible_to_provider'] ?? false)
            && ($field['editable_by_provider'] ?? false));
        abort_unless($allowed, 422, 'Esta evidência não pode ser enviada pelo prestador.');
        $evidence->upload($order->execution, $data['field_key'], $data['file'], $request->user());

        return back()->with('success', 'Evidência anexada.');
    }

    public function financial(Request $request, Tenant $tenant): View
    {
        $provider = $this->provider($request, $tenant);
        $obligations = ServiceObligation::query()->where('tenant_id', $tenant->id)->where('service_provider_id', $provider->id)->where('direction', 'payable')->with('execution.order.service')->latest()->paginate(20);

        return view('provider.services-financial', compact('provider', 'obligations'));
    }

    public function payout(Request $request, Tenant $tenant, ServicePayoutRequestService $service): RedirectResponse
    {
        $provider = $this->provider($request, $tenant);
        $request->merge(['amounts' => array_filter((array) $request->input('amounts', []), fn ($value) => (float) $value > 0)]);
        $data = $request->validate(['operation_key' => 'required|uuid', 'amounts' => 'required|array|min:1', 'amounts.*' => 'numeric|min:0.01']);
        $service->create($provider, $data['amounts'], $data['operation_key'], $request->user());

        return back()->with('success', 'Solicitação enviada para análise.');
    }

    public function evidence(Request $request, Tenant $tenant, int $evidence)
    {
        $provider = $this->provider($request, $tenant);
        $record = ServiceExecutionEvidence::query()->where('tenant_id', $tenant->id)->whereKey($evidence)->whereHas('execution', fn ($q) => $q->where('service_provider_id', $provider->id))->with('document')->firstOrFail();
        abort_unless($record->document->disk === 'local', 403);

        return Storage::disk('local')->download($record->document->path, $record->document->original_name);
    }

    private function provider(Request $request, Tenant $tenant): ServiceProvider
    {
        $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('user_id', $request->user()->id)->where('status', true)->first();
        abort_unless($provider, 403, 'Seu acesso não está vinculado a um prestador ativo.');

        return $provider;
    }

    private function order(Tenant $tenant, ServiceProvider $provider, int $id): ServiceOrder
    {
        return ServiceOrder::query()->where('tenant_id', $tenant->id)->where('service_provider_id', $provider->id)->whereKey($id)->with(['service', 'serviceVersion', 'execution.evidences.document', 'execution.resources', 'execution.compositionLines', 'execution.obligations'])->firstOrFail();
    }

    private function availableVersions(Tenant $tenant, ServiceProvider $provider)
    {
        return ServiceVersion::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('allow_provider_create_order', true)
            ->whereHas('service', fn ($query) => $query->where('status', true))
            ->whereHas('service.serviceProviders', fn ($query) => $query->whereKey($provider->id))
            ->with(['service', 'fields'])
            ->orderBy('service_id');
    }

    private function providerValues(ServiceOrder $order, array $values, array $phases): array
    {
        $keys = collect(data_get($order->execution->catalog_snapshot, 'fields', []))
            ->filter(fn (array $field) => in_array($field['phase'] ?? null, $phases, true)
                && ($field['visible_to_provider'] ?? false)
                && ($field['editable_by_provider'] ?? false))
            ->pluck('key')->all();

        return array_intersect_key($values, array_flip($keys));
    }
}
