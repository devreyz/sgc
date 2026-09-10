<?php

namespace App\Services\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\Asset;
use App\Models\Associate;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateServiceOrder
{
    public function __construct(private ServiceOrderNumberService $numbers, private ServiceFieldValidator $fields) {}

    public function handle(int $tenantId, ServiceVersion $version, array $data, User $actor): ServiceOrder
    {
        return DB::transaction(function () use ($tenantId, $version, $data, $actor): ServiceOrder {
            $version = ServiceVersion::query()->whereKey($version->id)->where('tenant_id', $tenantId)->where('status', 'published')->first();
            if (! $version) {
                throw ValidationException::withMessages(['service_version_id' => 'A versão publicada não pertence à organização.']);
            }
            $associate = ! empty($data['associate_id']) ? Associate::query()->whereKey($data['associate_id'])->where('tenant_id', $tenantId)->first() : null;
            $provider = ! empty($data['service_provider_id']) ? ServiceProvider::query()->whereKey($data['service_provider_id'])->where('tenant_id', $tenantId)->first() : null;
            $asset = ! empty($data['asset_id']) ? Asset::query()->whereKey($data['asset_id'])->where('tenant_id', $tenantId)->first() : null;
            if (! empty($data['associate_id']) && ! $associate) {
                throw ValidationException::withMessages(['associate_id' => 'Beneficiário inválido para esta organização.']);
            }
            if (! empty($data['service_provider_id']) && ! $provider) {
                throw ValidationException::withMessages(['service_provider_id' => 'Prestador inválido para esta organização.']);
            }
            if (! empty($data['asset_id']) && ! $asset) {
                throw ValidationException::withMessages(['asset_id' => 'Equipamento inválido para esta organização.']);
            }
            if ($version->payable_enabled && ! $provider) {
                throw ValidationException::withMessages(['service_provider_id' => 'Este serviço exige um prestador para calcular sua remuneração.']);
            }
            $scheduled = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : now();
            $order = new ServiceOrder([
                'number' => $this->numbers->next($tenantId, (int) $scheduled->format('Y')),
                'service_id' => $version->service_id, 'service_version_id' => $version->id,
                'associate_id' => $associate?->id, 'service_provider_id' => $provider?->id,
                'asset_id' => $asset?->id, 'scheduled_at' => $scheduled,
                'scheduled_date' => $scheduled->toDateString(), 'location' => $data['location'] ?? null,
                'order_data' => array_replace($data['order_data'] ?? [], array_filter(['location' => $data['location'] ?? null], fn ($value) => $value !== null)), 'operational_status' => 'scheduled',
                'status' => ServiceOrderStatus::SCHEDULED, 'total_price' => 0, 'final_price' => 0,
                'beneficiary_snapshot' => $associate ? ['id' => $associate->id, 'name' => $associate->display_name, 'document' => $associate->cpf_cnpj] : ($data['beneficiary_snapshot'] ?? null),
                'provider_snapshot' => $provider ? ['id' => $provider->id, 'name' => $provider->name, 'document' => $provider->cpf] : null,
                'created_by' => $actor->id,
            ]);
            $order->tenant_id = $tenantId;
            $order->save();
            $snapshot = $version->snapshot();
            $execution = $order->execution()->make(['service_version_id' => $version->id, 'service_provider_id' => $provider?->id, 'associate_id' => $associate?->id, 'status' => 'draft', 'unit' => $version->unit, 'values' => [], 'derived_values' => [], 'catalog_snapshot' => $snapshot]);
            $execution->tenant_id = $tenantId;
            $execution->save();
            $orderValues = $order->order_data ?? [];
            $this->fields->validate($execution, 'order', $orderValues);
            $execution->update(['values' => $orderValues]);
            activity('service_order')->performedOn($order)->causedBy($actor)->withProperties(['tenant_id' => $tenantId, 'service_version_id' => $version->id])->log('Ordem de serviço criada');

            return $order->fresh(['execution', 'serviceVersion', 'service', 'serviceProvider', 'associate']);
        }, 3);
    }
}
