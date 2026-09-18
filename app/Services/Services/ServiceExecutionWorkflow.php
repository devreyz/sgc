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
            if ($execution->isFrozen()) {
                throw ValidationException::withMessages(['status' => 'Execução validada é imutável.']);
            }$execution->forceFill(['values' => array_replace($execution->values ?? [], $values), 'lock_version' => $execution->lock_version + 1])->save();

            return $execution->fresh();
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
            $derived = app(ServiceCalculationRules::class)->quantities(
                (array) data_get($execution->catalog_snapshot, 'execution_config', []),
                (array) data_get($execution->catalog_snapshot, 'fields', []),
                $values,
                (float) ($execution->quantity ?? 1)
            );
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

    public function approve(ServiceExecution $execution, string $operationKey, User $actor): ServiceExecution
    {
        return DB::transaction(function () use ($execution, $operationKey, $actor) {
            $execution = $this->lock($execution);
            if ($execution->review_operation_key === $operationKey) {
                return $execution;
            }if ($execution->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Somente execução enviada pode ser aprovada.']);
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
        $execution->order->update(['operational_status' => 'validated', 'execution_date' => now()->toDateString(), 'actual_quantity' => $execution->quantity, 'final_price' => $composition['receivable_total'], 'provider_payment' => $composition['payable_total']]);

        return $execution->fresh(['compositionLines', 'obligations']);
    }

    private function lock(ServiceExecution $execution): ServiceExecution
    {
        return ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
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
