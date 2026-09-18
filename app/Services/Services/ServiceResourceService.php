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
    public function add(ServiceExecution $execution, array $data, string $operationKey, User $actor): ServiceExecutionResource
    {
        return DB::transaction(function () use ($execution, $data, $operationKey, $actor): ServiceExecutionResource {
            if ($existing = ServiceExecutionResource::query()->where('tenant_id', $execution->tenant_id)->where('operation_key', $operationKey)->first()) {
                if ((int) $existing->service_execution_id !== (int) $execution->id) {
                    throw ValidationException::withMessages(['operation_key' => 'A operação pertence a outra execução.']);
                }

                return $existing;
            }
            $execution = ServiceExecution::query()->whereKey($execution->id)->where('tenant_id', $execution->tenant_id)->lockForUpdate()->firstOrFail();
            if ($execution->isFrozen()) {
                throw ValidationException::withMessages(['execution' => 'Execução validada não aceita novos recursos.']);
            }
            $effect = $data['effect'] ?? 'information_only';
            if (! in_array($effect, ['information_only', 'deduct_from_receivable', 'add_to_receivable', 'reimburse_provider', 'create_association_expense'], true)) {
                throw ValidationException::withMessages(['effect' => 'Efeito de recurso inválido.']);
            }
            $amount = round((float) ($data['amount'] ?? ((float) ($data['quantity'] ?? 0) * (float) ($data['unit_price'] ?? 0))), 2);
            app(ServiceCalculationRules::class)->number($amount, 'amount');
            $resource = new ServiceExecutionResource(array_replace(Arr::only($data, ['resource_key', 'description', 'provided_by', 'quantity', 'unit', 'unit_price']), ['effect' => $effect, 'operation_key' => $operationKey, 'amount' => $amount, 'created_by' => $actor->id]));
            $resource->tenant_id = $execution->tenant_id;
            $resource->service_execution_id = $execution->id;
            $resource->save();
            if ($effect === 'create_association_expense') {
                $expense = new Expense(['description' => 'Recurso de serviço: '.$resource->description, 'amount' => $amount, 'discount' => 0, 'interest' => 0, 'fine' => 0, 'date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'status' => ExpenseStatus::PENDING, 'expenseable_type' => ServiceExecution::class, 'expenseable_id' => $execution->id, 'origin_module' => 'services', 'notes' => 'Origem idempotente: service_resource/'.$resource->id, 'created_by' => $actor->id]);
                $expense->tenant_id = $execution->tenant_id;
                $expense->save();
                $resource->update(['expense_id' => $expense->id]);
            }

            return $resource->fresh();
        }, 3);
    }
}
