<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use App\Models\Service;
use App\Services\Services\ServiceCatalogService;
use App\Services\Services\ServicePresetRegistry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Service {
            $versionData = collect($data)->filter(fn ($value, $key) => str_starts_with($key, 'version_'));
            $serviceData = collect($data)->reject(fn ($value, $key) => str_starts_with($key, 'version_'))->all();
            $preset = app(ServicePresetRegistry::class)->get($versionData->get('version_preset'));
            $serviceData['base_price'] = $versionData->get('version_customer_rate', 0) ?: 0;
            unset($serviceData['version_preset']);
            $service = Service::query()->create($serviceData);

            $version = app(ServiceCatalogService::class)->createDraft($service, [
                'unit' => $service->unit,
                'review_mode' => $versionData->get('version_review_mode', 'manual'),
                'allow_provider_create_order' => (bool) $versionData->get('version_allow_provider_create_order', false),
                'members_only' => (bool) $versionData->get('version_members_only', false),
                'receivable_enabled' => (bool) $versionData->get('version_receivable_enabled', $preset['receivable_enabled']),
                'customer_pricing_method' => $versionData->get('version_customer_pricing_method', $preset['customer_pricing_method']),
                'customer_rate' => $versionData->get('version_customer_rate'),
                'customer_percentage' => $versionData->get('version_customer_percentage'),
                'payable_enabled' => (bool) $versionData->get('version_payable_enabled', $preset['payable_enabled']),
                'provider_pricing_method' => $versionData->get('version_provider_pricing_method', $preset['provider_pricing_method']),
                'default_provider_rate' => $versionData->get('version_default_provider_rate'),
                'provider_percentage' => $versionData->get('version_provider_percentage'),
                'execution_config' => $preset['execution_config'],
            ], $preset['fields']);

            if ($versionData->get('version_publish', true)) {
                app(ServiceCatalogService::class)->publish($version, auth()->user());
            }

            return $service->fresh();
        });
    }
}
