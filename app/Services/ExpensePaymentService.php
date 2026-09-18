<?php

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\ExpenseStatus;
use App\Models\BankAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpensePaymentService
{
    public function pay(Expense $expense, array $data, User $actor): Expense
    {
        return DB::transaction(function () use ($expense, $data, $actor): Expense {
            $expense = Expense::query()->where('tenant_id', $expense->tenant_id)->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if (! in_array($expense->status, [ExpenseStatus::PENDING, ExpenseStatus::OVERDUE], true)) {
                throw ValidationException::withMessages(['expense' => 'Esta despesa não está pendente de pagamento.']);
            }
            $amount = round((float) ($data['paid_amount'] ?? 0), 2);
            if (abs($amount - $expense->total_amount) > .001) {
                throw ValidationException::withMessages(['paid_amount' => 'Como a despesa não possui parcelamento financeiro, informe exatamente o total calculado.']);
            }
            $account = BankAccount::query()->where('tenant_id', $expense->tenant_id)->where('status', true)->whereKey($data['bank_account_id'] ?? null)->firstOrFail();
            $expense->update([
                'paid_date' => $data['payment_date'], 'bank_account_id' => $account->id,
                'payment_method' => $data['payment_method'], 'paid_amount' => $amount,
                'status' => ExpenseStatus::PAID, 'paid_by' => $actor->id,
            ]);
            $movement = new CashMovement([
                'type' => CashMovementType::EXPENSE, 'amount' => $amount,
                'description' => 'Despesa: '.$expense->description, 'movement_date' => $data['payment_date'],
                'bank_account_id' => $account->id, 'reference_type' => Expense::class,
                'reference_id' => $expense->id, 'chart_account_id' => $expense->chart_account_id,
                'payment_method' => $data['payment_method'], 'document_number' => $expense->document_number,
                'created_by' => $actor->id,
            ]);
            $movement->tenant_id = $expense->tenant_id;
            $movement->save();

            return $expense->fresh();
        }, 3);
    }
}
