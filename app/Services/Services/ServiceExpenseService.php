<?php

namespace App\Services\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ChartAccount;
use App\Models\ServiceOrder;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceExpenseService
{
    public function create(Tenant $tenant, array $data, User $actor): Expense
    {
        return DB::transaction(function () use ($tenant, $data, $actor): Expense {
            $amount = app(ServiceCalculationRules::class)->number($data['amount'] ?? null, 'amount');
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'O valor da despesa deve ser maior que zero.']);
            }
            $order = null;
            if (filled($data['service_order_id'] ?? null)) {
                $order = ServiceOrder::query()->where('tenant_id', $tenant->id)->whereNotNull('service_version_id')->whereKey($data['service_order_id'])->firstOrFail();
            }
            if (filled($data['chart_account_id'] ?? null)) {
                $data['chart_account_id'] = ChartAccount::query()->where('tenant_id', $tenant->id)->where('type', 'despesa')->whereKey($data['chart_account_id'])->value('id')
                    ?? throw ValidationException::withMessages(['chart_account_id' => 'Plano de contas inválido para esta organização.']);
            }
            $expense = new Expense(Arr::only($data, ['description', 'document_number', 'amount', 'discount', 'interest', 'fine', 'date', 'due_date', 'chart_account_id', 'supplier_id', 'notes']) + [
                'status' => ExpenseStatus::PENDING,
                'expenseable_type' => $order ? ServiceOrder::class : null,
                'expenseable_id' => $order?->id,
                'origin_module' => 'services',
                'created_by' => $actor->id,
            ]);
            $expense->tenant_id = $tenant->id;
            $expense->save();

            return $expense;
        }, 3);
    }
}
