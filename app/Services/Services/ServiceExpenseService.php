<?php

namespace App\Services\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ChartAccount;
use App\Models\ServiceOrder;
use App\Models\ServiceExecutionResource;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServiceExpenseService
{
    public function manage(Tenant $tenant, int $id, array $data, User $actor, ?ServiceProvider $provider, ServiceResourceService $resources): void
    {
        DB::transaction(function () use ($tenant, $id, $data, $actor, $provider, $resources): void {
            $action = $data['action'];
            $expense = Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')
                ->when($provider, fn ($query) => $query->where('service_provider_id', $provider->id))
                ->lockForUpdate()->findOrFail($id);
            $linkedResource = ServiceExecutionResource::query()->where('tenant_id', $tenant->id)->where('expense_id', $expense->id)->first();
            $linkedOrder = $expense->linkedServiceOrder();
            $hasActiveLink = $linkedOrder && $linkedOrder->operational_status !== 'cancelled';
            if ($action === 'convert') {
                abort_unless(! $provider && $actor->checkPermissionTo('manage_service_resources')
                    && $actor->checkPermissionTo('manage_service_receivables'), 403);
                $order = $this->activeOrder($tenant, (int) $data['service_order_id']);
                $execution = $order->execution;
                if ($linkedResource && $execution && (int) $linkedResource->service_execution_id === (int) $execution->id
                    && $linkedResource->effect === 'deduct_from_receivable') {
                    return;
                }
                if (! $execution || $linkedResource || $expense->status === ExpenseStatus::CANCELLED
                    || ($hasActiveLink && (int) $linkedOrder->id !== (int) $order->id)) {
                    throw ValidationException::withMessages(['action' => 'Use uma ordem com execução. A despesa precisa estar disponível e vinculada à ordem escolhida ou sem vínculo.']);
                }
                $resource = $resources->add($execution, [
                    'resource_key' => 'expense-'.$expense->id,
                    'description' => $expense->description,
                    'provided_by' => $expense->service_provider_id ? 'provider' : 'organization',
                    'effect' => 'deduct_from_receivable',
                    'amount' => $expense->total_amount,
                ], (string) Str::uuid(), $actor);
                $resource->update(['expense_id' => $expense->id]);
                $expense->expenseable()->associate($order);
                $expense->save();

                return;
            }

            if ($linkedResource || $expense->status === ExpenseStatus::CANCELLED
                || (in_array($action, ['edit', 'delete'], true) && (! in_array($expense->status, [ExpenseStatus::PENDING, ExpenseStatus::OVERDUE], true)
                    || (float) $expense->paid_amount > 0))) {
                throw ValidationException::withMessages(['action' => 'A despesa só pode ser alterada enquanto estiver em aberto e sem recurso financeiro aplicado.']);
            }

            if ($action === 'link') {
                if ($hasActiveLink) {
                    throw ValidationException::withMessages(['service_order_id' => 'Cancele o vínculo atual antes de escolher outra ordem.']);
                }
                $order = $this->activeOrder($tenant, (int) $data['service_order_id'], $provider?->id);
                $expense->expenseable()->associate($order);
                $expense->save();
            } elseif ($action === 'unlink') {
                abort_if($provider && $linkedOrder && (int) $linkedOrder->service_provider_id !== (int) $provider->id, 403);
                $expense->expenseable()->dissociate();
                $expense->save();
            } elseif ($action === 'delete') {
                $expense->delete();
            } else {
                $expense->update(Arr::only($data, ['description', 'amount', 'date', 'due_date', 'document_number', 'notes']));
            }
        }, 3);
    }

    public function link(Tenant $tenant, int $orderId, int $expenseId, User $actor): void
    {
        DB::transaction(function () use ($tenant, $orderId, $expenseId, $actor): void {
            $order = $this->activeOrder($tenant, $orderId);
            $expense = Expense::query()->where('tenant_id', $tenant->id)->where('origin_module', 'services')
                ->lockForUpdate()->findOrFail($expenseId);
            if ($expense->status !== ExpenseStatus::PENDING || $expense->expenseable_type || $expense->expenseable_id) {
                throw ValidationException::withMessages(['expense_id' => 'Selecione uma despesa pendente e ainda sem vínculo.']);
            }
            $expense->expenseable()->associate($order);
            $expense->save();
            activity('service_order')->performedOn($order)->causedBy($actor)
                ->withProperties(['expense_id' => $expense->id])->log('Despesa vinculada à ordem');
        }, 3);
    }

    public function create(Tenant $tenant, array $data, User $actor, ?ServiceProvider $reportingProvider = null): Expense
    {
        return DB::transaction(function () use ($tenant, $data, $actor, $reportingProvider): Expense {
            $amount = app(ServiceCalculationRules::class)->number($data['amount'] ?? null, 'amount');
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'O valor da despesa deve ser maior que zero.']);
            }
            $order = null;
            if (filled($data['service_order_id'] ?? null)) {
                $order = $this->activeOrder($tenant, (int) $data['service_order_id'], $reportingProvider?->id);
            }
            $providerId = $reportingProvider?->id ?? ($data['service_provider_id'] ?? null);
            if (filled($providerId)) {
                $providerId = ServiceProvider::query()->where('tenant_id', $tenant->id)->where('status', true)
                    ->when($reportingProvider, fn ($query) => $query->where('user_id', $actor->id))
                    ->whereKey($providerId)->lockForUpdate()->value('id')
                    ?? throw ValidationException::withMessages(['service_provider_id' => 'Escolha um prestador ativo desta organização.']);
            } else {
                $providerId = null;
            }
            if (filled($data['chart_account_id'] ?? null)) {
                $data['chart_account_id'] = ChartAccount::query()->where('tenant_id', $tenant->id)->where('type', 'despesa')->whereKey($data['chart_account_id'])->value('id')
                    ?? throw ValidationException::withMessages(['chart_account_id' => 'Plano de contas inválido para esta organização.']);
            }
            $expense = new Expense(Arr::only($data, ['description', 'document_number', 'amount', 'discount', 'interest', 'fine', 'date', 'due_date', 'chart_account_id', 'supplier_id', 'notes']) + [
                'status' => ExpenseStatus::PENDING,
                'expenseable_type' => $order ? ServiceOrder::class : null,
                'expenseable_id' => $order?->id,
                'service_provider_id' => $providerId,
                'origin_module' => 'services',
                'created_by' => $actor->id,
            ]);
            $expense->tenant_id = $tenant->id;
            $expense->save();

            return $expense;
        }, 3);
    }

    private function activeOrder(Tenant $tenant, int $orderId, ?int $providerId = null): ServiceOrder
    {
        $order = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')
            ->when($providerId, fn ($query) => $query->where('service_provider_id', $providerId))
            ->whereKey($orderId)->lockForUpdate()->first();
        if (! $order || $order->operational_status === 'cancelled') {
            throw ValidationException::withMessages(['service_order_id' => 'Escolha uma ordem ativa desta organização.']);
        }

        return $order;
    }
}
