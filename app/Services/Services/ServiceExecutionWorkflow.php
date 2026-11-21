<?php

namespace App\Services\Services;

use App\Models\ServiceExecution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceExecutionWorkflow
{
    public function __construct(private ServiceFieldValidator $validator, private ServiceCompositionCalculator $calculator, private GenerateServiceObligations $obligations, private CanonicalServiceSnapshot $snapshots) {}

    public function start(ServiceExecution $execution, array $values, string $operationKey, User $actor): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $values, $operationKey, $actor) {
            $execution = $this->lock($execution);
            if ($execution->start_operation_key === $operationKey) {
                return $execution;
            }if (! in_array($execution->status, ['draft', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'A execução não pode ser iniciada neste estado.']);
            }$this->validator->validate($execution, 'start', $values);
            $execution->forceFill(['values' => array_replace($execution->values ?? [], $values), 'status' => 'in_progress', 'started_at' => $execution->started_at ?? now(), 'started_by' => $actor->id, 'start_operation_key' => $operationKey, 'lock_version' => $execution->lock_version + 1])->save();
            $execution->order->update(['operational_status' => 'in_progress']);

            return $execution->fresh();
        }, 3);
    }

    public function saveDraft(ServiceExecution $execution, array $values, User $actor): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $values) {
            $execution = $this->lock($execution);
            if (! in_array($execution->status, ['draft', 'in_progress', 'rejected'], true)) {
                throw ValidationException::withMessages(['status' => 'Esta execução já foi enviada. Reabra a ordem para ver os dados atualizados.']);
            }
            $execution->forceFill(['values' => array_replace($execution->values ?? [], $values), 'lock_version' => $execution->lock_version + 1])->save();

            return $execution->fresh();
        }, 3);
    }

    public function updateFromTable(ServiceExecution $execution, array $values, ?string $date, int $version, ?string $operationKey, User $actor, array $times = [], ?string $providerRate = null, ?string $correctionReason = null): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $values, $date, $version, $operationKey, $actor, $times, $providerRate, $correctionReason): ServiceExecution {
            $locked = $this->lock($execution);
            if ($operationKey && $locked->submit_operation_key === $operationKey) {
                return $locked;
            }
            if ((int) $locked->lock_version !== $version || ! in_array($locked->status, ['draft', 'in_progress', 'submitted', 'rejected'], true)) {
                throw ValidationException::withMessages(['order' => 'A ordem mudou ou saiu da edição. Atualize a tabela antes de continuar.']);
            }
            $locked->order->update(['execution_date' => $date] + $times);
            if ($providerRate !== null) {
                $snapshot = $locked->catalog_snapshot;
                $method = $snapshot['provider_pricing_method'] ?? null;
                if (! ($snapshot['payable_enabled'] ?? false) || ! $locked->service_provider_id
                    || ! in_array($method, ['quantity_x_rate', 'fixed'], true)) {
                    throw ValidationException::withMessages(['provider_rate' => 'Esta ordem usa outra forma de calcular o pagamento.']);
                }
                $oldRate = data_get($snapshot, 'provider_compensation.rate') ?? ($snapshot['default_provider_rate'] ?? null);
                $snapshot['provider_compensation'] = [
                    'source' => 'order_override', 'provider_rate_id' => null,
                    'method' => $method, 'rate' => $providerRate, 'percentage' => null,
                ];
                $locked->update(['catalog_snapshot' => $snapshot]);
                activity('service_order')->performedOn($locked->order)->causedBy($actor)->withProperties([
                    'tenant_id' => $locked->tenant_id, 'previous_rate' => $oldRate, 'provider_rate' => $providerRate,
                ])->log('Tarifa do prestador corrigida na ordem');
            }
            if ($locked->status === 'submitted') {
                $previousValues = $locked->values;
                $merged = array_replace($locked->values ?? [], $values);
                foreach (['order', 'start', 'execution', 'finish'] as $phase) $this->validator->validate($locked, $phase, $merged);
                $this->calculator->validateRuleEvidences($locked, (array) data_get($locked->catalog_snapshot, 'financial_config.rules', []), $merged);
                $derived = $this->quantities($locked, $merged);
                $this->validateFinancialQuantities($locked, $derived);
                $locked->forceFill(['values' => $merged, 'derived_values' => $derived, 'quantity' => $derived['primary_quantity'], 'lock_version' => $locked->lock_version + 1])->save();
                activity('service_order')->performedOn($locked->order)->causedBy($actor)->withProperties(['tenant_id' => $locked->tenant_id, 'previous_values' => $previousValues, 'values' => $merged, 'reason' => $correctionReason])->log('Execução corrigida antes da conferência');
                return $locked->fresh();
            }
            if (! $operationKey) {
                return $this->saveDraft($locked, $values, $actor);
            }
            if ($locked->status !== 'in_progress') {
                $locked = $this->start($locked, $values, $operationKey, $actor);
            }

            return $this->submit($locked, $values, $operationKey, $actor);
        }, 3);
    }

    public function submit(ServiceExecution $execution, array $values, string $operationKey, User $actor): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $values, $operationKey, $actor) {
            $execution = $this->lock($execution);
            if ($execution->submit_operation_key === $operationKey) {
                return $execution;
            }if ($execution->status !== 'in_progress') {
                throw ValidationException::withMessages(['status' => 'Somente execução em andamento pode ser finalizada.']);
            }$values = array_replace($execution->values ?? [], $values);
            $this->validator->validate($execution, 'finish', $values);
            $this->validator->validate($execution, 'order', $values);
            $this->validator->validate($execution, 'start', $values);
            $this->validator->validate($execution, 'execution', $values);
            $this->calculator->validateRuleEvidences(
                $execution,
                (array) data_get($execution->catalog_snapshot, 'financial_config.rules', []),
                $values,
            );
            $derived = $this->quantities($execution, $values);
            $quantity = $derived['primary_quantity'];
            $this->validateFinancialQuantities($execution, $derived);
            $execution->forceFill(['values' => $values, 'derived_values' => $derived, 'quantity' => $quantity, 'submitted_at' => now(), 'submitted_by' => $actor->id, 'submit_operation_key' => $operationKey, 'status' => 'submitted', 'lock_version' => $execution->lock_version + 1])->save();
            $execution->order->update(['operational_status' => 'submitted']);
            if (data_get($execution->catalog_snapshot, 'review_mode') === 'automatic') {
                return $this->validateLocked($execution, $actor, $operationKey);
            }

            return $execution->fresh();
        }, 3);
    }

    public function preview(ServiceExecution $execution, array $values): array
    {
        $values = array_replace($execution->values ?? [], $values);
        // A legacy service that charges by quantity needs a recorded quantity.
        // Fixed or explicitly configured calculations supply their own quantity.
        $snapshot = (array) $execution->catalog_snapshot;
        $version = $execution->version;
        $rateBased = (data_get($snapshot, 'receivable_enabled', $version?->receivable_enabled)
                && data_get($snapshot, 'customer_pricing_method', $version?->customer_pricing_method) === 'quantity_x_rate')
            || (data_get($snapshot, 'payable_enabled', $version?->payable_enabled)
                && data_get($snapshot, 'provider_compensation.method', data_get($snapshot, 'provider_pricing_method', $version?->provider_pricing_method)) === 'quantity_x_rate');
        $fallback = $execution->quantity === null && $rateBased ? NAN : (float) ($execution->quantity ?? 1);
        $derived = $this->quantities($execution, $values, $fallback);
        $this->validateFinancialQuantities($execution, $derived);

        $preview = clone $execution;
        $preview->forceFill(['values' => $values, 'derived_values' => $derived, 'quantity' => $derived['primary_quantity']]);

        return $this->calculator->calculate($preview, false) + ['primary_quantity' => $derived['primary_quantity']];
    }

    private function quantities(ServiceExecution $execution, array $values, ?float $fallback = null): array
    {
        return app(ServiceCalculationRules::class)->quantities(
            (array) data_get($execution->catalog_snapshot, 'execution_config', []),
            (array) data_get($execution->catalog_snapshot, 'fields', []),
            $values,
            $fallback ?? (float) ($execution->quantity ?? 1)
        );
    }

    public function approve(ServiceExecution $execution, string $operationKey, User $actor, ?int $expectedVersion = null): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $operationKey, $actor, $expectedVersion) {
            $execution = $this->lock($execution);
            if ($execution->review_operation_key === $operationKey) {
                return $execution;
            }if ($execution->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Somente execução enviada pode ser aprovada.']);
            }
            if ($expectedVersion !== null && (int) $execution->lock_version !== $expectedVersion) {
                throw ValidationException::withMessages(['order' => 'A execução foi corrigida desde que esta tela foi aberta. Confira os dados atualizados antes de validar.']);
            }

            return $this->validateLocked($execution, $actor, $operationKey);
        }, 3);
    }

    public function requestCorrection(ServiceExecution $execution, string $reason, string $operationKey, User $actor): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $reason, $operationKey, $actor) {
            $execution = $this->lock($execution);
            if (trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'Informe o motivo da correção.']);
            }if ($execution->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Esta execução não aguarda revisão.']);
            }$execution->forceFill(['status' => 'rejected', 'rejected_at' => now(), 'reviewed_by' => $actor->id, 'review_reason' => $reason, 'review_operation_key' => $operationKey, 'revision' => $execution->revision + 1, 'lock_version' => $execution->lock_version + 1])->save();
            $execution->order->update(['operational_status' => 'rejected']);

            return $execution->fresh();
        }, 3);
    }

    private function validateLocked(ServiceExecution $execution, User $actor, string $operationKey): ServiceExecution
    {
        $this->validator->validate($execution, 'review', $execution->values ?? []);
        $composition = $this->calculator->calculate($execution);
        $snapshot = ['catalog' => $execution->catalog_snapshot, 'values' => $execution->values, 'derived_values' => $execution->derived_values, 'quantity' => $execution->quantity, 'composition' => $composition];
        $execution->forceFill(['status' => 'validated', 'validated_at' => now(), 'validated_by' => $actor->id, 'reviewed_by' => $actor->id, 'review_operation_key' => $operationKey, 'snapshot_hash' => $this->snapshots->hash($snapshot), 'lock_version' => $execution->lock_version + 1])->save();
        $this->obligations->handle($execution, $composition, $actor);
        $order = $execution->order;
        $order->update(['operational_status' => 'validated',
            'execution_date' => $order->execution_date ?? $order->scheduled_date ?? $order->scheduled_at,
            'actual_quantity' => $execution->quantity, 'final_price' => $composition['receivable_total'], 'provider_payment' => $composition['payable_total']]);

        return $execution->fresh(['compositionLines', 'obligations']);
    }

    private function lock(ServiceExecution $execution): ServiceExecution
    {
        $locked = ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
        if ($locked->status === 'cancelled') {
            throw ValidationException::withMessages(['status' => 'Esta ordem foi cancelada.']);
        }
        if ((int) $locked->service_provider_id !== (int) $execution->service_provider_id
            || (int) $locked->service_version_id !== (int) $execution->service_version_id) {
            throw ValidationException::withMessages(['status' => 'A ordem foi corrigida. Reabra para carregar os dados atuais.']);
        }

        return $locked;
    }

    private function validateFinancialQuantities(ServiceExecution $execution, array $derived): void
    {
        if (data_get($execution->catalog_snapshot, 'receivable_enabled')
            && data_get($execution->catalog_snapshot, 'customer_pricing_method') === 'quantity_x_rate'
            && (float) ($derived['customer_quantity'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'A quantidade usada na cobrança deve ser maior que zero.']);
        }
        if (data_get($execution->catalog_snapshot, 'payable_enabled')
            && data_get($execution->catalog_snapshot, 'provider_pricing_method') === 'quantity_x_rate'
            && (float) ($derived['provider_quantity'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'A quantidade usada na remuneração do prestador deve ser maior que zero.']);
        }
    }
}
