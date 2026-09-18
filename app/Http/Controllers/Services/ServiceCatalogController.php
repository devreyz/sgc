<?php

namespace App\Http\Controllers\Services;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderVersionRate;
use App\Models\ServiceVersion;
use App\Models\ServiceVersionField;
use App\Models\Tenant;
use App\Services\Services\ServiceCatalogService;
use App\Services\Services\ServicePresetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceCatalogController extends Controller
{
    public function index(Request $request, Tenant $tenant): View
    {
        $this->allow($request, 'manage_service_catalog');
        $services = Service::query()->where('tenant_id', $tenant->id)->with(['currentVersion', 'versions'])->orderBy('name')->get();

        return view('services.catalog-index', compact('services'));
    }

    public function create(Request $request, Tenant $tenant, ServicePresetRegistry $presets): View
    {
        $this->allow($request, 'manage_service_catalog');

        return view('services.catalog-form', ['presets' => $presets->all()]);
    }

    public function store(Request $request, Tenant $tenant, ServiceCatalogService $catalog, ServicePresetRegistry $presets): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $data = $request->validate(['name' => 'required|string|max:191', 'code' => 'nullable|string|max:191|unique:services,code', 'description' => 'nullable|string', 'preset' => 'required|string', 'unit' => 'required|string|max:30', 'review_mode' => 'required|in:automatic,manual', 'allow_provider_create_order' => 'boolean', 'receivable_enabled' => 'boolean', 'customer_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base', 'customer_rate' => 'nullable|numeric|min:0', 'payable_enabled' => 'boolean', 'provider_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base', 'default_provider_rate' => 'nullable|numeric|min:0', 'provider_percentage' => 'nullable|numeric|min:0|max:100', 'publish' => 'boolean']);
        $preset = $presets->get($data['preset']);
        $service = new Service(['name' => $data['name'], 'code' => $data['code'] ?? null, 'description' => $data['description'] ?? null, 'type' => ServiceType::OUTRO, 'unit' => $data['unit'], 'base_price' => $data['customer_rate'] ?? 0, 'status' => true]);
        $service->tenant_id = $tenant->id;
        $service->save();
        $version = $catalog->createDraft($service, ['unit' => $data['unit'], 'review_mode' => $data['review_mode'], 'allow_provider_create_order' => $data['allow_provider_create_order'] ?? false, 'receivable_enabled' => $data['receivable_enabled'] ?? false, 'customer_pricing_method' => $data['customer_pricing_method'] ?? $preset['customer_pricing_method'], 'customer_rate' => $data['customer_rate'] ?? null, 'payable_enabled' => $data['payable_enabled'] ?? false, 'provider_pricing_method' => $data['provider_pricing_method'] ?? null, 'default_provider_rate' => $data['default_provider_rate'] ?? null, 'provider_percentage' => $data['provider_percentage'] ?? null, 'execution_config' => $preset['execution_config'] ?? []], $preset['fields'] ?? []);
        if ($data['publish'] ?? false) {
            $catalog->publish($version, $request->user());
        }

        return redirect()->route('services.catalog.show', [$tenant, $version]);
    }

    public function show(Request $request, Tenant $tenant, int $version): View
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->with(['service', 'fields', 'providerRates.provider'])->firstOrFail();
        $providers = ServiceProvider::query()->where('tenant_id', $tenant->id)->active()->orderBy('name')->get();

        return view('services.catalog-show', compact('version', 'providers'));
    }

    public function update(Request $request, Tenant $tenant, int $version): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        abort_unless($version->status === 'draft', 422, 'Duplique a versão publicada para alterá-la.');
        $data = $request->validate([
            'unit' => 'required|string|max:30', 'review_mode' => 'required|in:automatic,manual',
            'allow_provider_create_order' => 'boolean', 'receivable_enabled' => 'boolean', 'payable_enabled' => 'boolean',
            'customer_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base',
            'customer_rate' => 'nullable|numeric|min:0', 'customer_percentage' => 'nullable|numeric|min:0|max:100',
            'provider_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base',
            'default_provider_rate' => 'nullable|numeric|min:0', 'provider_percentage' => 'nullable|numeric|min:0|max:100',
        ]);
        $version->update($data + [
            'allow_provider_create_order' => $request->boolean('allow_provider_create_order'),
            'receivable_enabled' => $request->boolean('receivable_enabled'),
            'payable_enabled' => $request->boolean('payable_enabled'),
        ]);

        return back()->with('success', 'Configuração do rascunho atualizada.');
    }

    public function field(Request $request, Tenant $tenant, int $version): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        abort_unless($version->status === 'draft', 422, 'Campos de versão publicada são imutáveis.');
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'], 'label' => 'required|string|max:191',
            'type' => 'required|in:'.implode(',', ServiceVersionField::TYPES), 'phase' => 'required|in:'.implode(',', ServiceVersionField::PHASES),
            'section' => 'nullable|string|max:80', 'required' => 'boolean', 'visible_to_provider' => 'boolean', 'editable_by_provider' => 'boolean',
            'visible_to_management' => 'boolean', 'include_in_documents' => 'boolean', 'reportable' => 'boolean', 'unit' => 'nullable|string|max:30',
            'decimal_places' => 'nullable|integer|min:0|max:6', 'minimum' => 'nullable|numeric', 'maximum' => 'nullable|numeric',
            'options_text' => 'nullable|string', 'placeholder' => 'nullable|string|max:191', 'help' => 'nullable|string|max:500',
        ]);
        abort_if($version->fields()->where('key', $data['key'])->exists(), 422, 'Já existe um campo com essa chave.');
        $options = collect(preg_split('/\r\n|\r|\n/', (string) ($data['options_text'] ?? '')))->map(fn ($value) => trim($value))->filter()->values()->all();
        unset($data['options_text']);
        $field = new ServiceVersionField($data + [
            'required' => $request->boolean('required'), 'visible_to_provider' => $request->boolean('visible_to_provider'),
            'editable_by_provider' => $request->boolean('editable_by_provider'), 'visible_to_management' => $request->boolean('visible_to_management'),
            'include_in_documents' => $request->boolean('include_in_documents'), 'reportable' => $request->boolean('reportable'),
            'options' => $options, 'sort_order' => (int) $version->fields()->max('sort_order') + 1,
        ]);
        $field->tenant_id = $tenant->id;
        $field->service_version_id = $version->id;
        $field->save();

        return back()->with('success', 'Campo incluído no rascunho.');
    }

    public function deleteField(Request $request, Tenant $tenant, int $version, int $field): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        abort_unless($version->status === 'draft', 422, 'Campos de versão publicada são imutáveis.');
        ServiceVersionField::query()->where('tenant_id', $tenant->id)->where('service_version_id', $version->id)->whereKey($field)->firstOrFail()->delete();

        return back()->with('success', 'Campo removido do rascunho.');
    }

    public function publish(Request $request, Tenant $tenant, int $version, ServiceCatalogService $catalog): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        $catalog->publish($version, $request->user());

        return back()->with('success', 'Versão publicada.');
    }

    public function clone(Request $request, Tenant $tenant, int $version, ServiceCatalogService $catalog): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        $copy = $catalog->clone($version);

        return redirect()->route('services.catalog.show', [$tenant, $copy])->with('success', 'Nova versão em rascunho criada.');
    }

    public function rate(Request $request, Tenant $tenant, int $version): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        abort_unless($version->status === 'draft', 422, 'Duplique a versão publicada para alterar tarifas.');
        $data = $request->validate([
            'service_provider_id' => 'required|integer',
            'rate' => $version->provider_pricing_method === 'quantity_x_rate' ? 'required|numeric|min:0.0001' : 'nullable|numeric|min:0',
            'fixed_amount' => $version->provider_pricing_method === 'fixed' ? 'required|numeric|min:0.01' : 'nullable|numeric|min:0',
            'percentage' => $version->provider_pricing_method === 'percent_of_base' ? 'required|numeric|min:0.0001|max:100' : 'nullable|numeric|min:0|max:100',
        ]);
        $provider = ServiceProvider::query()->where('tenant_id', $tenant->id)->whereKey($data['service_provider_id'])->firstOrFail();
        $data['calculation_method'] = $version->provider_pricing_method;
        ServiceProviderVersionRate::query()->updateOrCreate(['tenant_id' => $tenant->id, 'service_version_id' => $version->id, 'service_provider_id' => $provider->id], $data + ['active' => true]);

        return back()->with('success', 'Remuneração específica salva.');
    }

    public function preview(Request $request, Tenant $tenant, int $version): View
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->with(['service', 'fields'])->firstOrFail();

        return view('services.catalog-preview', compact('version'));
    }

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()->checkPermissionTo($permission), 403);
    }
}
