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
use Illuminate\Validation\ValidationException;
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
        $data = $request->validate(['name' => 'required|string|max:191', 'code' => 'nullable|string|max:191', 'description' => 'nullable|string', 'preset' => 'required|string', 'unit' => 'required|string|max:30', 'review_mode' => 'required|in:automatic,manual', 'allow_provider_create_order' => 'boolean', 'members_only' => 'boolean', 'receivable_enabled' => 'boolean', 'customer_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base', 'customer_rate' => 'nullable|numeric|min:0', 'customer_percentage' => 'nullable|numeric|min:0|max:100', 'payable_enabled' => 'boolean', 'provider_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base', 'default_provider_rate' => 'nullable|numeric|min:0', 'provider_percentage' => 'nullable|numeric|min:0|max:100', 'publish' => 'boolean']);
        $preset = $presets->get($data['preset']);
        abort_if(Service::query()->where('tenant_id', $tenant->id)->where('code', $data['code'] ?? null)->whereNotNull('code')->exists(), 422, 'Já existe um serviço com este código na organização.');
        $service = new Service(['name' => $data['name'], 'code' => $data['code'] ?? null, 'description' => $data['description'] ?? null, 'type' => ServiceType::tryFrom($preset['service_type'] ?? '') ?? ServiceType::OUTRO, 'unit' => $data['unit'], 'base_price' => $data['customer_rate'] ?? 0, 'status' => true]);
        $service->tenant_id = $tenant->id;
        $service->save();
        $version = $catalog->createDraft($service, ['unit' => $data['unit'], 'review_mode' => $data['review_mode'], 'allow_provider_create_order' => $data['allow_provider_create_order'] ?? false, 'members_only' => $data['members_only'] ?? false, 'receivable_enabled' => $data['receivable_enabled'] ?? $preset['receivable_enabled'], 'customer_pricing_method' => $data['customer_pricing_method'] ?? $preset['customer_pricing_method'], 'customer_rate' => $data['customer_rate'] ?? null, 'customer_percentage' => $data['customer_percentage'] ?? null, 'payable_enabled' => $data['payable_enabled'] ?? $preset['payable_enabled'], 'provider_pricing_method' => $data['provider_pricing_method'] ?? $preset['provider_pricing_method'], 'default_provider_rate' => $data['default_provider_rate'] ?? null, 'provider_percentage' => $data['provider_percentage'] ?? null, 'execution_config' => $preset['execution_config'] ?? []], $preset['fields'] ?? []);
        if ($data['publish'] ?? false) {
            $catalog->publish($version, $request->user());
        }

        return redirect()->route('services.catalog.show', [$tenant, $version]);
    }

    public function show(Request $request, Tenant $tenant, int $version): View
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->with(['service.versions', 'fields', 'providerRates.provider'])->firstOrFail();
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
            'allow_provider_create_order' => 'boolean', 'members_only' => 'boolean', 'receivable_enabled' => 'boolean', 'payable_enabled' => 'boolean',
            'customer_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base',
            'customer_rate' => 'nullable|numeric|min:0', 'customer_percentage' => 'nullable|numeric|min:0|max:100',
            'provider_pricing_method' => 'nullable|in:fixed,quantity_x_rate,percent_of_base',
            'default_provider_rate' => 'nullable|numeric|min:0', 'provider_percentage' => 'nullable|numeric|min:0|max:100',
            'execution_config' => 'nullable|array',
            'execution_config.quantity_mode' => 'nullable|in:fixed_one,field,meter_difference',
            'execution_config.quantity_field' => 'nullable|string|max:80',
            'execution_config.meter_start_field' => 'nullable|string|max:80',
            'execution_config.meter_end_field' => 'nullable|string|max:80',
            'execution_config.customer_quantity_mode' => 'nullable|in:primary,fixed_one,field,meter_difference',
            'execution_config.customer_quantity_field' => 'nullable|string|max:80',
            'execution_config.customer_meter_start_field' => 'nullable|string|max:80',
            'execution_config.customer_meter_end_field' => 'nullable|string|max:80',
            'execution_config.customer_unit' => 'nullable|string|max:30',
            'execution_config.provider_quantity_mode' => 'nullable|in:primary,fixed_one,field,meter_difference',
            'execution_config.provider_quantity_field' => 'nullable|string|max:80',
            'execution_config.provider_meter_start_field' => 'nullable|string|max:80',
            'execution_config.provider_meter_end_field' => 'nullable|string|max:80',
            'execution_config.provider_unit' => 'nullable|string|max:30',
            'financial_config' => 'nullable|array',
            'financial_config.rules' => 'nullable|array|max:30',
            'financial_config.rules.*.description' => 'required|string|max:191',
            'financial_config.rules.*.direction' => 'required|in:receivable,payable',
            'financial_config.rules.*.method' => 'required|in:fixed_addition,fixed_deduction,percent_addition,percent_deduction,quantity_x_rate',
            'financial_config.rules.*.effect' => 'required|in:add,subtract',
            'financial_config.rules.*.value' => 'nullable|numeric|min:0',
            'financial_config.rules.*.percentage' => 'nullable|numeric|min:0|max:100',
            'financial_config.rules.*.value_field' => 'nullable|string|max:80',
            'financial_config.rules.*.quantity_field' => 'nullable|string|max:80',
            'financial_config.rules.*.input_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'financial_config.rules.*.input_label' => 'nullable|required_with:financial_config.rules.*.input_key|string|max:160',
            'financial_config.rules.*.input_role' => 'nullable|in:quantity,value',
            'financial_config.rules.*.input_unit' => 'nullable|string|max:30',
            'financial_config.rules.*.input_phase' => 'nullable|in:start,execution,finish',
            'financial_config.rules.*.input_required' => 'nullable|boolean',
            'financial_config.rules.*.evidence_required' => 'nullable|boolean',
            'financial_config.rules.*.evidence_key' => ['nullable', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'],
            'financial_config.rules.*.evidence_label' => 'nullable|string|max:160',
            'financial_config.rules.*.evidence_field' => 'nullable|string|max:80',
        ]);
        $data['execution_config'] = array_replace($version->execution_config ?? [], $data['execution_config'] ?? []);
        if (array_key_exists('financial_config', $data)) {
            $data['financial_config']['rules'] = collect(data_get($data, 'financial_config.rules', []))->map(function (array $rule): array {
                $rule['input_required'] = (bool) ($rule['input_required'] ?? false);
                $rule['evidence_required'] = (bool) ($rule['evidence_required'] ?? false);

                return $rule;
            })->values()->all();
        }
        $version->update($data + [
            'allow_provider_create_order' => $request->boolean('allow_provider_create_order'),
            'members_only' => $request->boolean('members_only'),
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
        $data = $this->fieldData($request, $version);
        abort_if($version->fields()->where('key', $data['key'])->exists(), 422, 'Já existe um campo com essa chave.');
        $field = new ServiceVersionField($data + ['sort_order' => (int) $version->fields()->max('sort_order') + 1]);
        $field->tenant_id = $tenant->id;
        $field->service_version_id = $version->id;
        $field->save();

        return back()->with('success', 'Campo incluído no rascunho.');
    }

    public function updateField(Request $request, Tenant $tenant, int $version, int $field): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        abort_unless($version->status === 'draft', 422, 'Campos de versão publicada são imutáveis.');
        $record = $version->fields()->where('tenant_id', $tenant->id)->whereKey($field)->firstOrFail();
        $data = $this->fieldData($request, $version, $record);
        $record->update($data);

        return back()->with('success', 'Campo atualizado no rascunho.');
    }

    private function fieldData(Request $request, ServiceVersion $version, ?ServiceVersionField $record = null): array
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/'], 'label' => 'required|string|max:160',
            'type' => 'required|in:'.implode(',', ServiceVersionField::TYPES), 'phase' => 'required|in:'.implode(',', ServiceVersionField::PHASES),
            'section' => 'nullable|string|max:80', 'required' => 'boolean', 'visible_to_provider' => 'boolean', 'editable_by_provider' => 'boolean',
            'visible_to_management' => 'boolean', 'include_in_documents' => 'boolean', 'reportable' => 'boolean', 'unit' => 'nullable|string|max:30',
            'decimal_places' => 'nullable|integer|min:0|max:6', 'minimum' => 'nullable|numeric', 'maximum' => 'nullable|numeric', 'sort_order' => 'nullable|integer|min:0|max:10000',
            'options_text' => 'nullable|string', 'evidence_for_field' => 'nullable|string|max:80', 'placeholder' => 'nullable|string|max:191', 'help' => 'nullable|string|max:500',
            'accepted_mime_types' => 'nullable|array', 'accepted_mime_types.*' => 'in:'.implode(',', ServiceVersionField::FILE_MIMES),
        ]);
        if ($record && $data['key'] !== $record->key) {
            throw ValidationException::withMessages(['key' => 'O identificador técnico não pode ser alterado. Crie outro campo para usar uma nova chave.']);
        }
        $type = $data['type'];
        $targetKey = $data['evidence_for_field'] ?? null;
        if ($targetKey) {
            $target = $version->fields()->where('key', $targetKey)->first();
            if (! in_array($type, ['image', 'file', 'signature'], true) || ! $target || in_array($target->type, ['image', 'file', 'signature'], true) || $target->phase !== $data['phase']) {
                throw ValidationException::withMessages(['evidence_for_field' => 'Vincule o arquivo a um dado da mesma etapa.']);
            }
        }
        if ($record && ! in_array($type, ['image', 'file', 'signature'], true) && $version->fields()->where('evidence_for_field', $record->key)->exists()) {
            throw ValidationException::withMessages(['type' => 'Este dado já possui comprovante vinculado e deve continuar como campo de preenchimento.']);
        }
        $options = collect(preg_split('/\r\n|\r|\n/', (string) ($data['options_text'] ?? '')))
            ->map(fn ($value) => trim($value))->filter()->mapWithKeys(function (string $line): array {
                [$value, $label] = array_pad(explode('|', $line, 2), 2, null);
                $value = trim($value);

                return [$value => trim($label ?? $value)];
            })->all();
        if ($type === 'select' && $options === []) {
            throw ValidationException::withMessages(['options_text' => 'Informe pelo menos uma opção para a lista.']);
        }
        unset($data['options_text']);
        $data = array_replace($data, [
            'required' => $request->boolean('required'), 'visible_to_provider' => $request->boolean('visible_to_provider'),
            'editable_by_provider' => $request->boolean('editable_by_provider'), 'visible_to_management' => $request->boolean('visible_to_management'),
            'include_in_documents' => $request->boolean('include_in_documents'), 'reportable' => $request->boolean('reportable'),
            'options' => $type === 'select' ? $options : [],
            'accepted_mime_types' => in_array($type, ['image', 'file', 'signature'], true) ? array_values(array_unique($data['accepted_mime_types'] ?? [])) : null,
            'evidence_for_field' => $targetKey ?: null,
        ]);
        if ($type !== 'file' && in_array('application/pdf', $data['accepted_mime_types'] ?? [], true)) {
            throw ValidationException::withMessages(['accepted_mime_types' => 'PDF só pode ser aceito em um campo de arquivo.']);
        }
        if (($data['sort_order'] ?? null) === null) {
            if ($record) {
                unset($data['sort_order']);
            } else {
                $data['sort_order'] = (int) $version->fields()->max('sort_order') + 1;
            }
        }

        return $data;
    }

    public function deleteField(Request $request, Tenant $tenant, int $version, int $field): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $version = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        abort_unless($version->status === 'draft', 422, 'Campos de versão publicada são imutáveis.');
        $linked = $version->fields()->where('evidence_for_field', $version->fields()->whereKey($field)->value('key'))->exists();
        abort_if($linked, 422, 'Desvincule primeiro o comprovante deste campo.');
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

    public function active(Request $request, Tenant $tenant, int $version, ServiceCatalogService $catalog): RedirectResponse
    {
        $this->allow($request, 'manage_service_catalog');
        $record = ServiceVersion::query()->where('tenant_id', $tenant->id)->whereKey($version)->firstOrFail();
        $data = $request->validate(['active' => 'required|boolean']);
        $catalog->setActive($record, (bool) $data['active']);

        return back()->with('success', $data['active'] ? 'Versão ativada para novas ordens.' : 'Versão desativada para novas ordens.');
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
