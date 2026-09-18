<?php

namespace App\Services\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\Asset;
use App\Models\Associate;
use App\Models\ServiceOrder;
use App\Models\ServiceProvider;
use App\Models\ServiceProviderService;
use App\Models\ServiceProviderVersionRate;
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
            if (! $version->service?->status) {
                throw ValidationException::withMessages(['service_version_id' => 'O serviço está inativo.']);
            }
            $associate = ! empty($data['associate_id']) ? Associate::query()->whereKey($data['associate_id'])->where('tenant_id', $tenantId)->active()->first() : null;
            $provider = ! empty($data['service_provider_id']) ? ServiceProvider::query()->whereKey($data['service_provider_id'])->where('tenant_id', $tenantId)->first() : null;
            $asset = ! empty($data['asset_id']) ? Asset::query()->whereKey($data['asset_id'])->where('tenant_id', $tenantId)->first() : null;
            if (! empty($data['associate_id']) && ! $associate) {
                throw ValidationException::withMessages(['associate_id' => 'Beneficiário inválido para esta organização.']);
            }
            if ($version->members_only && ! $associate) {
                throw ValidationException::withMessages(['associate_id' => 'Este serviço é exclusivo para membros. Selecione um membro ativo como beneficiário.']);
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
            if ($provider && ! ServiceProviderService::query()
                ->where('tenant_id', $tenantId)
                ->where('service_provider_id', $provider->id)
                ->where('service_id', $version->service_id)
                ->where('status', true)
                ->exists()) {
                throw ValidationException::withMessages([
                    'service_provider_id' => 'Este prestador não está habilitado para executar o serviço selecionado.',
                ]);
            }
            $beneficiaryName = trim((string) ($data['beneficiary_name'] ?? $associate?->display_name ?? data_get($data, 'beneficiary_snapshot.name', '')));
            if ($beneficiaryName === '' || mb_strlen($beneficiaryName) > 191) {
                throw ValidationException::withMessages(['beneficiary_name' => 'Informe o nome ou apelido do beneficiário (até 191 caracteres), mesmo que não seja associado.']);
            }
            if ($provider && ! $provider->status) {
                throw ValidationException::withMessages(['service_provider_id' => 'O prestador está inativo.']);
            }
            $allowedOrderKeys = $version->fields()->where('phase', 'order')->pluck('key')->all();
            $submittedOrderData = (array) ($data['order_data'] ?? []);
            $orderData = array_intersect_key($submittedOrderData, array_flip(array_merge($allowedOrderKeys, ['description', 'notes'])));
            $orderData = array_replace($orderData, array_filter([
                'location' => $data['location'] ?? null,
                'description' => $data['description'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($value) => $value !== null));
            $scheduled = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : now();
            $order = new ServiceOrder([
                'number' => $this->numbers->next($tenantId, (int) $scheduled->format('Y')),
                'service_id' => $version->service_id, 'service_version_id' => $version->id,
                'associate_id' => $associate?->id, 'service_provider_id' => $provider?->id,
                'asset_id' => $asset?->id, 'scheduled_at' => $scheduled,
                'scheduled_date' => $scheduled->toDateString(), 'location' => $data['location'] ?? null,
                'unit' => $version->unit, 'unit_price' => 0,
                'order_data' => $orderData, 'operational_status' => 'scheduled',
                'status' => ServiceOrderStatus::SCHEDULED, 'total_price' => 0, 'final_price' => 0,
                'beneficiary_snapshot' => ['id' => $associate?->id, 'name' => $beneficiaryName, 'document' => $associate?->cpf_cnpj],
                'provider_snapshot' => $provider ? ['id' => $provider->id, 'name' => $provider->name, 'document' => $provider->cpf] : null,
                'created_by' => $actor->id,
            ]);
            $order->tenant_id = $tenantId;
            $order->save();
            $snapshot = $version->snapshot();
            if ($provider && $version->payable_enabled) {
                $override = ServiceProviderVersionRate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('service_version_id', $version->id)
                    ->where('service_provider_id', $provider->id)
                    ->where('active', true)
                    ->first();
                // A fórmula pertence à versão. A configuração individual só
                // substitui o valor da tarifa/percentual, nunca a fórmula.
                $method = $version->provider_pricing_method;
                $snapshot['provider_compensation'] = [
                    'source' => $override ? 'provider_service_version' : 'service_version_default',
                    'provider_rate_id' => $override?->id,
                    'method' => $method,
                    'rate' => $method === 'fixed'
                        ? ($override?->fixed_amount ?? $override?->rate ?? $version->default_provider_rate)
                        : ($override?->rate ?? $override?->fixed_amount ?? $version->default_provider_rate),
                    'percentage' => $override?->percentage ?? $version->provider_percentage,
                ];
            }
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
