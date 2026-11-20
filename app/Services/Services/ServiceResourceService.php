<?php

namespace App\Services\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionResource;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceResourceService
{
    public function __construct(private ServiceObligationAdjustmentService $adjustments) {}

    public function add(ServiceExecution $execution, array $data, string $operationKey, User $actor): ServiceExecutionResource
    {
        return DB::transaction(function () use ($execution, $data, $operationKey, $actor): ServiceExecutionResource {
            $execution = ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
            if ($existing = ServiceExecutionResource::query()->where('tenant_id', $execution->tenant_id)->where('operation_key', $operationKey)->first()) {
                if ((int) $existing->service_execution_id !== (int) $execution->id) {
                    throw ValidationException::withMessages(['operation_key' => 'A operação pertence a outra execução.']);
                }

                return $existing;
            }
            if ($execution->status === 'cancelled' || $execution->order?->operational_status === 'cancelled') {
                throw ValidationException::withMessages(['execution' => 'Esta ordem foi anulada.']);
            }
            $effect = $data['effect'] ?? 'information_only';
            if (! in_array($effect, ['information_only', 'deduct_from_receivable', 'add_to_receivable', 'reimburse_provider', 'create_association_expense'], true)) {
                throw ValidationException::withMessages(['effect' => 'Efeito de recurso inválido.']);
            }
            $amount = round((float) ($data['amount'] ?? ((float) ($data['quantity'] ?? 0) * (float) ($data['unit_price'] ?? 0))), 2);
            app(ServiceCalculationRules::class)->number($amount, 'amount');
            if ($effect !== 'information_only' && $amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Informe o valor total ou a quantidade e o preço unitário.']);
            }
            $resource = new ServiceExecutionResource(array_replace(Arr::only($data, ['resource_key', 'description', 'provided_by', 'quantity', 'unit', 'unit_price']), ['effect' => $effect, 'operation_key' => $operationKey, 'amount' => $amount, 'created_by' => $actor->id]));
            $resource->tenant_id = $execution->tenant_id;
            $resource->service_execution_id = $execution->id;
            if ($effect === 'create_association_expense') {
                $expense = new Expense(['description' => 'Recurso de serviço: '.$resource->description, 'amount' => $amount, 'discount' => 0, 'interest' => 0, 'fine' => 0, 'date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'status' => ExpenseStatus::PENDING, 'expenseable_type' => ServiceExecution::class, 'expenseable_id' => $execution->id, 'origin_module' => 'services', 'notes' => 'Origem: recurso de serviço ('.$operationKey.')', 'created_by' => $actor->id]);
                $expense->tenant_id = $execution->tenant_id;
                $expense->save();
                $resource->expense_id = $expense->id;
            }
            $resource->metadata = ['added_after_validation' => $execution->isFrozen()];
            $resource->save();

            if ($execution->isFrozen()) {
                $this->applyToValidatedExecution($execution, $resource, $operationKey, $actor);
            }

            return $resource->fresh();
        }, 3);
    }

    private function applyToValidatedExecution(ServiceExecution $execution, ServiceExecutionResource $resource, string $operationKey, User $actor): void
    {
        $direction = match ($resource->effect) {
            'deduct_from_receivable', 'add_to_receivable' => 'receivable',
            'reimburse_provider' => 'payable',
            default => null,
        };
        if ($direction === null) {
            return;
        }

        abort_unless($actor->checkPermissionTo($direction === 'payable' ? 'manage_service_payables' : 'manage_service_receivables'), 403);
        $obligations = $execution->obligations()->where('tenant_id', $execution->tenant_id)
            ->where('direction', $direction)->where('status', '!=', 'cancelled')->lockForUpdate()->get();
        if ($obligations->count() !== 1) {
            throw ValidationException::withMessages(['effect' => 'A ordem precisa ter exatamente um valor ativo no destino escolhido.']);
        }

        $type = $resource->effect === 'deduct_from_receivable' ? 'decrease' : 'increase';
        $this->adjustments->add($obligations->first(), $type, (float) $resource->amount, 'Recurso: '.$resource->description, $operationKey, $actor, [
            'kind' => 'resource',
            'resource_id' => $resource->id,
        ]);
    }
}
