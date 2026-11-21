<?php

namespace App\Services\Services;

use App\Enums\ServiceOrderStatus;
use App\Models\Asset;
use App\Models\Associate;
use App\Models\Service;
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

    public function handle(int $tenantId, ServiceVersion $version, array $data, User $actor, ?ServiceOrder $existing = null): ServiceOrder
    {
        return DB::transaction(function () use ($tenantId, $version, $data, $actor, $existing): ServiceOrder {
            $execution = $existing?->execution()->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            if ($existing && (! in_array($execution->status, ['draft', 'in_progress', 'rejected'], true) || (int) $execution->lock_version !== (int) ($data['lock_version'] ?? -1))) {
                throw ValidationException::withMessages(['order' => 'A ordem mudou ou saiu da edição. Reabra a ordem para conferir.']);
            }
            if ($existing) {
                $existing = ServiceOrder::query()->where('tenant_id', $tenantId)->whereKey($existing->id)->lockForUpdate()->firstOrFail();
                $existing->setRelation('execution', $execution);
                if ($existing->operational_status === 'cancelled') {
                    throw ValidationException::withMessages(['order' => 'Esta ordem foi anulada.']);
                }
                if ((int) $execution->service_version_id !== (int) $version->id && ! ServiceOrderDetails::canChangeVersion($existing)) {
                    throw ValidationException::withMessages(['service_version_id' => 'A versão só pode ser alterada enquanto a ordem estiver agendada e a execução não tiver começado.']);
                }
            }
            if ($execution && $execution->status !== 'draft'
                && ((int) $existing->service_id !== (int) $version->service_id
                    || (int) $execution->service_provider_id !== (int) ($data['service_provider_id'] ?? 0))) {
                throw ValidationException::withMessages(['service_version_id' => 'Com a execução iniciada, mantenha o serviço e o prestador.']);
            }
            if ($existing && (int) $execution->service_provider_id !== (int) ($data['service_provider_id'] ?? 0)
                && ($execution->evidences()->exists() || $execution->resources()->exists())) {
                throw ValidationException::withMessages(['service_provider_id' => 'A ordem já possui anexos ou recursos registrados para o prestador atual.']);
            }
            $sameVersion = $execution && (int) $execution->service_version_id === (int) $version->id;
            $sameService = $existing && (int) $existing->service_id === (int) $version->service_id;
            $previousVersionId = $execution?->service_version_id;
            $service = Service::query()->where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($version->service_id);
            $version = ServiceVersion::query()->whereKey($version->id)->where('tenant_id', $tenantId)
                ->whereIn('status', $sameService ? ['published', 'retired'] : ['published'])->first();
            if (! $version) {
                throw ValidationException::withMessages(['service_version_id' => 'A versão publicada não pertence à organização.']);
            }
            if (! $sameService && (! $service->status || ($service->current_version_id && (int) $service->current_version_id !== (int) $version->id))) {
                throw ValidationException::withMessages(['service_version_id' => 'A tarifa selecionada saiu de uso. Reabra o lançamento e escolha a tarifa atual.']);
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
            $beneficiaryName = trim((string) (filled($data['beneficiary_name'] ?? null)
                ? $data['beneficiary_name']
                : ($associate?->nickname ?: $associate?->display_name ?: data_get($data, 'beneficiary_snapshot.name', ''))));
            if ($beneficiaryName === '' || mb_strlen($beneficiaryName) > 191) {
                throw ValidationException::withMessages(['beneficiary_name' => 'Informe o nome ou apelido do beneficiário (até 191 caracteres), mesmo que não seja associado.']);
            }
            $whatsapp = data_get($existing?->beneficiary_snapshot, 'whatsapp');
            if (array_key_exists('beneficiary_whatsapp', $data)) {
                $rawPhone = trim((string) $data['beneficiary_whatsapp']);
                $whatsapp = $rawPhone === '' ? null : preg_replace('/\D+/', '', $rawPhone);
                if ($whatsapp !== null && ! preg_match('/\A\d{10,15}\z/', $whatsapp)) {
                    throw ValidationException::withMessages(['beneficiary_whatsapp' => 'Informe o WhatsApp com DDD.']);
                }
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
            if ($sameService) {
                $orderData = array_replace($existing->order_data ?? [], $orderData);
            }
            foreach ($orderData as $key => $value) {
                if ($value !== null && ! is_scalar($value)) {
                    throw ValidationException::withMessages(["order_data.$key" => 'Informe um valor simples para este campo.']);
                }
            }
            $scheduled = array_key_exists('scheduled_at', $data)
                ? (isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null)
                : ($existing?->scheduled_at ?? $existing?->scheduled_date);
            $executionDate = array_key_exists('execution_date', $data) ? $data['execution_date'] : $existing?->execution_date;
            $order = $existing ?? new ServiceOrder([
                'number' => $this->numbers->next($tenantId, (int) Carbon::parse($executionDate ?? $scheduled ?? now())->format('Y')),
                'created_by' => $actor->id,
            ]);
            $order->fill([
                'service_id' => $version->service_id, 'service_version_id' => $version->id,
                'associate_id' => $associate?->id, 'service_provider_id' => $provider?->id,
                'asset_id' => $asset?->id, 'scheduled_at' => $scheduled,
                'scheduled_date' => $scheduled?->toDateString(), 'execution_date' => $executionDate, 'location' => $data['location'] ?? null,
                'unit' => $version->unit,
                'order_data' => $orderData,
                'beneficiary_snapshot' => ['id' => $associate?->id, 'name' => $beneficiaryName, 'document' => $associate?->cpf_cnpj,
                    'whatsapp' => $whatsapp],
                'provider_snapshot' => $provider ? ['id' => $provider->id, 'name' => $provider->name, 'document' => $provider->cpf] : null,
            ]);
            if (! $existing) {
                $order->fill(['operational_status' => 'scheduled', 'status' => ServiceOrderStatus::SCHEDULED,
                    'unit_price' => 0, 'total_price' => 0, 'final_price' => 0]);
            }
            $order->tenant_id = $tenantId;
            $order->save();
            $snapshot = $version->snapshot();
            if ($sameService && ! $sameVersion) {
                $oldFields = collect(data_get($execution->catalog_snapshot, 'fields', []))->keyBy('key');
                foreach ($snapshot['fields'] as $field) {
                    $oldField = $oldFields->get($field['key']);
                    if ($oldField && filled(data_get($execution->values, $field['key']))
                        && (($oldField['type'] ?? null) !== ($field['type'] ?? null)
                            || ($oldField['unit'] ?? null) !== ($field['unit'] ?? null))) {
                        throw ValidationException::withMessages(['service_version_id' => "O campo {$field['label']} mudou de tipo ou unidade. Escolha uma versão compatível."]);
                    }
                }
            }
            if ($sameVersion) {
                $snapshot = $execution->catalog_snapshot;
                if (isset($data['reset_customer_rate'])) {
                    $snapshot['customer_rate'] = $version->customer_rate;
                }
            }
            if (isset($data['customer_rate']) && ! isset($data['reset_customer_rate'])) {
                if (! $version->receivable_enabled) {
                    throw ValidationException::withMessages(['customer_rate' => 'Este serviço não gera cobrança.']);
                }
                $snapshot['customer_rate'] = $data['customer_rate'];
            }
            $keepProviderRate = $sameVersion && (int) $execution->service_provider_id === (int) $provider?->id
                && ! isset($data['provider_rate_source']);
            if (! $keepProviderRate) {
                unset($snapshot['provider_compensation']);
            }
            if ($provider && $version->payable_enabled && ! $keepProviderRate) {
                $override = ServiceProviderVersionRate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('service_version_id', $version->id)
                    ->where('service_provider_id', $provider->id)
                    ->where('active', true)
                    ->first();
                if (($data['provider_rate_source'] ?? null) === 'catalog') {
                    $override = null;
                }
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
            if (isset($data['provider_rate'])) {
                if (! $provider || ! $version->payable_enabled || ! in_array($version->provider_pricing_method, ['fixed', 'quantity_x_rate'], true)) {
                    throw ValidationException::withMessages(['provider_rate' => 'Este serviço usa outra forma de calcular o pagamento.']);
                }
                $snapshot['provider_compensation'] = [
                    'source' => 'order_override', 'provider_rate_id' => null, 'method' => $version->provider_pricing_method,
                    'rate' => $data['provider_rate'], 'percentage' => null,
                ];
            }
            if ($execution && ! $sameVersion && ($execution->evidences()->exists() || $execution->resources()->exists())
                && (! $sameService || collect(['fields', 'execution_config', 'financial_config', 'evidence_config'])->contains(
                    fn (string $key) => ($snapshot[$key] ?? []) != data_get($execution->catalog_snapshot, $key, [])
                ))) {
                throw ValidationException::withMessages(['service_version_id' => 'A ordem já possui anexos ou recursos. Mantenha o serviço ou cancele a ordem.']);
            }
            $execution ??= $order->execution()->make(['status' => 'draft', 'derived_values' => []]);
            $execution->fill([
                'service_version_id' => $version->id, 'service_provider_id' => $provider?->id,
                'associate_id' => $associate?->id, 'unit' => $version->unit, 'catalog_snapshot' => $snapshot,
                'values' => $sameService
                    ? array_replace($execution->values ?? [], $orderData)
                    : $orderData,
                'derived_values' => $sameVersion ? ($execution->derived_values ?? []) : [],
                'quantity' => $sameVersion ? $execution->quantity : null,
                'lock_version' => (int) $execution->lock_version + (int) ($existing !== null),
            ]);
            $execution->tenant_id = $tenantId;
            $execution->save();
            $this->fields->validate($execution, 'order', $orderData);
            activity('service_order')->performedOn($order)->causedBy($actor)->withProperties([
                'tenant_id' => $tenantId, 'previous_service_version_id' => $previousVersionId, 'service_version_id' => $version->id,
                'provider_rate' => $data['provider_rate'] ?? null,
            ])->log($existing ? 'Ordem de serviço corrigida' : 'Ordem de serviço criada');

            return $order->fresh(['execution', 'serviceVersion', 'service', 'serviceProvider', 'associate']);
        }, 3);
    }
}
