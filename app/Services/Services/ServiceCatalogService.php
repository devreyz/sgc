<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Models\ServiceProviderService;
use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCatalogService
{
    public function __construct(private CanonicalServiceSnapshot $snapshots) {}

    public function createDraft(Service $service, array $attributes, array $fields = []): ServiceVersion
    {
        foreach (['execution_config', 'financial_config', 'evidence_config', 'document_config'] as $configKey) {
            $attributes[$configKey] = $this->arrayConfig($attributes[$configKey] ?? []);
        }

        if ($fields === []
            && blank(data_get($attributes, 'execution_config.quantity_mode'))
            && in_array('quantity_x_rate', [$attributes['customer_pricing_method'] ?? null, $attributes['provider_pricing_method'] ?? null], true)) {
            $attributes['execution_config'] = array_replace($attributes['execution_config'] ?? [], ['quantity_mode' => 'field', 'quantity_field' => 'quantity']);
            $fields = [['key' => 'quantity', 'label' => 'Quantidade executada', 'type' => 'quantity', 'phase' => 'finish', 'required' => true, 'minimum' => 0.0001, 'unit' => $attributes['unit'] ?? 'un', 'visible_to_provider' => true, 'editable_by_provider' => true, 'reportable' => true]];
        }
        $attributes['execution_config'] = array_replace(['quantity_mode' => 'fixed_one'], $attributes['execution_config'] ?? []);

        return DB::transaction(function () use ($service, $attributes, $fields): ServiceVersion {
            $service = Service::query()->whereKey($service->id)->where('tenant_id', $service->tenant_id)->lockForUpdate()->firstOrFail();
            $next = ((int) $service->versions()->max('version')) + 1;
            $version = new ServiceVersion($attributes);
            $version->tenant_id = $service->tenant_id;
            $version->service_id = $service->id;
            $version->version = $next;
            $version->status = 'draft';
            $version->save();
            foreach (array_values($fields) as $position => $field) {
                $record = $version->fields()->make($field + ['sort_order' => $position]);
                $record->tenant_id = $service->tenant_id;
                $record->save();
            }

            return $version->fresh('fields');
        }, 3);
    }

    public function clone(ServiceVersion $source): ServiceVersion
    {
        return DB::transaction(function () use ($source): ServiceVersion {
            $source->loadMissing(['fields', 'providerRates']);

            $versionAttributes = collect($source->getFillable())
                ->reject(fn (string $key) => in_array($key, ['service_id', 'version', 'status', 'snapshot_hash', 'published_at', 'published_by', 'retired_at'], true))
                ->mapWithKeys(fn (string $key) => [$key => $source->getAttribute($key)])
                ->all();
            $fieldAttributes = $source->fields->map(fn ($field) => collect($field->getFillable())
                ->reject(fn (string $key) => $key === 'service_version_id')
                ->mapWithKeys(fn (string $key) => [$key => $field->getAttribute($key)])
                ->all())->all();

            $version = $this->createDraft($source->service, $versionAttributes, $fieldAttributes);

            foreach ($source->providerRates as $sourceRate) {
                $rate = $version->providerRates()->make(collect($sourceRate->getAttributes())->except(['id', 'tenant_id', 'service_version_id', 'created_at', 'updated_at'])->all());
                $rate->tenant_id = $version->tenant_id;
                $rate->save();
            }

            return $version->fresh(['fields', 'providerRates']);
        }, 3);
    }

    public function publish(ServiceVersion $version, User $actor): ServiceVersion
    {
        return DB::transaction(function () use ($version, $actor): ServiceVersion {
            Service::query()->whereKey($version->service_id)->where('tenant_id', $version->tenant_id)->lockForUpdate()->firstOrFail();
            $version = ServiceVersion::query()->whereKey($version->id)->where('tenant_id', $version->tenant_id)->lockForUpdate()->firstOrFail();
            if ($version->status !== 'draft') {
                throw ValidationException::withMessages(['status' => 'Somente uma versão em rascunho pode ser publicada.']);
            }
            if ($version->payable_enabled && ! $version->provider_pricing_method) {
                throw ValidationException::withMessages(['provider_pricing_method' => 'Defina como o prestador será remunerado.']);
            }
            if ($version->receivable_enabled && ! $version->customer_pricing_method) {
                throw ValidationException::withMessages(['customer_pricing_method' => 'Defina como o serviço será cobrado.']);
            }
            if ($version->receivable_enabled && ! $this->pricingIsValid($version->customer_pricing_method, $version->customer_rate, $version->customer_percentage)) {
                throw ValidationException::withMessages(['customer_rate' => 'Informe um valor ou percentual de cobrança maior que zero.']);
            }
            if ($version->receivable_enabled && $version->customer_pricing_method === 'percent_of_base' && (float) $version->customer_rate <= 0) {
                throw ValidationException::withMessages(['customer_rate' => 'Informe o valor base sobre o qual o percentual de cobrança será aplicado.']);
            }
            $activeRates = $version->providerRates()->where('active', true)->get();
            $validOverrideProviderIds = [];
            foreach ($activeRates as $rate) {
                $eligible = ServiceProviderService::query()
                    ->where('tenant_id', $version->tenant_id)
                    ->where('service_id', $version->service_id)
                    ->where('service_provider_id', $rate->service_provider_id)
                    ->where('status', true)
                    ->exists();
                if (! $eligible) {
                    throw ValidationException::withMessages(['provider_rates' => 'Existe tarifa individual para um prestador que não está habilitado neste serviço.']);
                }
                $valid = $this->pricingIsValid($rate->calculation_method, $rate->calculation_method === 'fixed' ? ($rate->fixed_amount ?? $rate->rate) : $rate->rate, $rate->percentage);
                if (! $valid) {
                    throw ValidationException::withMessages(['provider_rates' => 'Revise as tarifas individuais: o valor exigido pelo método de cálculo deve ser maior que zero.']);
                }
                $validOverrideProviderIds[] = (int) $rate->service_provider_id;
            }
            if ($version->payable_enabled && ! $this->pricingIsValid($version->provider_pricing_method, $version->default_provider_rate, $version->provider_percentage)) {
                $eligibleProviderIds = ServiceProviderService::query()
                    ->where('tenant_id', $version->tenant_id)
                    ->where('service_id', $version->service_id)
                    ->where('status', true)
                    ->pluck('service_provider_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                if ($eligibleProviderIds === [] || array_diff($eligibleProviderIds, $validOverrideProviderIds)) {
                    throw ValidationException::withMessages(['default_provider_rate' => 'Defina uma remuneração padrão válida ou uma tarifa individual para cada prestador habilitado.']);
                }
            }
            app(ServiceCalculationRules::class)->validateConfiguration($version);
            $version->service->versions()->where('status', 'published')->update(['status' => 'retired', 'retired_at' => now()]);
            $snapshot = $version->snapshot();
            $version->forceFill(['status' => 'published', 'snapshot_hash' => $this->snapshots->hash($snapshot), 'published_at' => now(), 'published_by' => $actor->id])->save();
            $version->service()->update(['current_version_id' => $version->id]);

            return $version->fresh('fields');
        }, 3);
    }

    private function pricingIsValid(?string $method, mixed $rate, mixed $percentage): bool
    {
        return match ($method) {
            'fixed', 'quantity_x_rate' => (float) $rate > 0,
            'percent_of_base' => (float) $percentage > 0,
            default => false,
        };
    }

    private function arrayConfig(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw ValidationException::withMessages([
            'configuration' => 'A configuração da versão anterior está inválida. Revise os campos JSON antes de criar uma nova versão.',
        ]);
    }

    public function setActive(ServiceVersion $version, bool $active): void
    {
        DB::transaction(function () use ($version, $active): void {
            $service = Service::query()->whereKey($version->service_id)->where('tenant_id', $version->tenant_id)->lockForUpdate()->firstOrFail();
            $version = $service->versions()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if (! in_array($version->status, ['published', 'retired'], true)) {
                throw ValidationException::withMessages(['status' => 'Publique o rascunho antes de ativar.']);
            }
            if ($active) {
                $service->versions()->where('status', 'published')->whereKeyNot($version->id)->update(['status' => 'retired', 'retired_at' => now()]);
            }
            $version->update(['status' => $active ? 'published' : 'retired', 'retired_at' => $active ? null : now()]);
            if ($active || (int) $service->current_version_id === (int) $version->id) {
                $service->update(['current_version_id' => $active ? $version->id : null]);
            }
        }, 3);
    }
}
