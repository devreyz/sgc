<?php

namespace App\Services\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ServiceExecution;
use App\Models\ServiceExecutionResource;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ServiceResourceService
{
    public function __construct(private ServiceObligationAdjustmentService $adjustments) {}

    public static function formRules(): array
    {
        return [
            'operation_key' => 'required|uuid', 'resource_id' => 'nullable|integer|min:1', 'description' => 'nullable|string|max:191',
            'resource_key' => 'nullable|string|max:80',
            'provided_by' => 'nullable|in:organization,provider,customer,third_party',
            'effect' => 'nullable|in:information_only,deduct_from_receivable,add_to_receivable,reimburse_provider,create_association_expense',
            'quantity' => 'nullable|numeric|decimal:0,4|min:0|max:999999999', 'unit' => 'nullable|string|max:30',
            'unit_price' => 'nullable|numeric|decimal:0,4|min:0|max:999999999', 'amount' => 'nullable|numeric|decimal:0,2|min:0|max:999999999',
            'photos' => 'nullable|array|max:5', 'photos.*' => 'nullable|file|mimetypes:image/jpeg,image/png,image/webp|max:12288',
        ];
    }

    public function saveReport(ServiceExecution $execution, array $data, User $actor, ?int $providerId = null): void
    {
        $files = [];
        try {
            DB::transaction(function () use ($execution, $data, $actor, $providerId, &$files): void {
                $execution = ServiceExecution::query()->where('tenant_id', $execution->tenant_id)->lockForUpdate()->findOrFail($execution->id);
                $order = $execution->order;
                abort_unless($order && (!$providerId || ((int) $execution->service_provider_id === $providerId
                    && (int) $order->service_provider_id === $providerId)), 404);
                if ($execution->status === 'cancelled' || $order->operational_status === 'cancelled') {
                    throw ValidationException::withMessages(['resource' => 'A ordem foi anulada.']);
                }
                $resource = isset($data['resource_id'])
                    ? $execution->resources()->where('tenant_id', $execution->tenant_id)->lockForUpdate()->findOrFail($data['resource_id'])
                    : null;
                if ($resource && $providerId) {
                    abort_unless($resource->effect === 'information_only' && (int) $resource->created_by === (int) $actor->id, 403);
                }
                if (!$order->isBilled() && (!$resource || $resource->effect === 'information_only')) {
                    $fields = Arr::only($data, ['description', 'quantity', 'unit', 'unit_price', 'amount', 'provided_by', 'effect']);
                    $fields['description'] = trim((string) ($fields['description'] ?? ''));
                    if ($fields['description'] === '') {
                        throw ValidationException::withMessages(['description' => 'Informe o que foi usado.']);
                    }
                    if ($providerId) {
                        $fields['effect'] = 'information_only';
                        $fields['provided_by'] = 'provider';
                    }
                    $amount = round((float) ($fields['amount'] ?? (($fields['quantity'] ?? 0) * ($fields['unit_price'] ?? 0))), 2);
                    if ($amount > 999999999) {
                        throw ValidationException::withMessages(['amount' => 'O valor total ultrapassa o limite permitido.']);
                    }
                    $fields['amount'] = $amount;
                    if ($resource) {
                        unset($fields['effect']);
                        activity('service_order')->performedOn($order)->causedBy($actor)->withProperties([
                            'resource_id' => $resource->id, 'before' => $resource->only(array_keys($fields)), 'after' => $fields,
                        ])->log('Recurso corrigido');
                        $execution->resources()->whereKey($resource->id)->update($fields);
                    } else {
                        $permission = match ($fields['effect'] ?? 'information_only') {
                            'deduct_from_receivable', 'add_to_receivable' => 'manage_service_receivables',
                            'reimburse_provider' => 'manage_service_payables',
                            default => null,
                        };
                        abort_if($permission && !$actor->checkPermissionTo($permission), 403);
                        $fields['resource_key'] = $providerId ? 'manual-'.substr($data['operation_key'], 0, 8)
                            : ($data['resource_key'] ?? 'manual-'.substr($data['operation_key'], 0, 8));
                        $resource = $this->add($execution, $fields, $data['operation_key'], $actor);
                    }
                }
                if (!$resource) {
                    throw ValidationException::withMessages(['resource' => 'A ordem faturada aceita apenas fotos em recursos existentes.']);
                }
                foreach (array_filter($data['photos'] ?? []) as $photo) {
                    app(ServiceEvidenceService::class)->upload($execution, $resource->evidenceKey(), $photo, $actor,
                        function (string $path) use (&$files): void { $files[] = $path; }, $resource);
                }
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($files);
            throw $error;
        }
    }

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
            if ($execution->status === 'cancelled' || $execution->order?->operational_status === 'cancelled' || $execution->order?->isBilled()) {
                throw ValidationException::withMessages(['execution' => 'Esta ordem está fechada para alterações de recursos.']);
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
