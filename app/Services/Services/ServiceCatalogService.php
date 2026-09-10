<?php

namespace App\Services\Services;

use App\Models\Service;
use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceCatalogService
{
    public function __construct(private CanonicalServiceSnapshot $snapshots) {}

    public function createDraft(Service $service, array $attributes, array $fields = []): ServiceVersion
    {
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
        $source->loadMissing('fields');

        return $this->createDraft($source->service, collect($source->getAttributes())->except(['id', 'tenant_id', 'service_id', 'version', 'status', 'snapshot_hash', 'published_at', 'published_by', 'retired_at', 'created_at', 'updated_at'])->all(), $source->fields->map(fn ($field) => collect($field->getAttributes())->except(['id', 'tenant_id', 'service_version_id', 'created_at', 'updated_at'])->all())->all());
    }

    public function publish(ServiceVersion $version, User $actor): ServiceVersion
    {
        return DB::transaction(function () use ($version, $actor): ServiceVersion {
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
            $snapshot = $version->snapshot();
            $version->forceFill(['status' => 'published', 'snapshot_hash' => $this->snapshots->hash($snapshot), 'published_at' => now(), 'published_by' => $actor->id])->save();
            $version->service()->update(['current_version_id' => $version->id]);

            return $version->fresh('fields');
        }, 3);
    }
}
