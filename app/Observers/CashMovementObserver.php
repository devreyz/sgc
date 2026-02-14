<?php

namespace App\Observers;

use App\Models\CashMovement;
use App\Models\BankAccount;
use App\Enums\CashMovementType;
use Illuminate\Support\Facades\DB;

class CashMovementObserver
{
    /**
     * Handle the CashMovement "created" event.
     */
    public function created(CashMovement $cashMovement): void
    {
        $this->updateBankAccountBalance($cashMovement);
    }

    /**
     * Handle the CashMovement "deleted" event.
     */
    public function deleted(CashMovement $cashMovement): void
    {
        // Reverter o saldo quando deletado
        if ($cashMovement->bank_account_id) {
            $account = BankAccount::find($cashMovement->bank_account_id);
            if ($account) {
                $amount = $cashMovement->amount;
                
                // Reverter: se era entrada, subtrai; se era saída, adiciona
                if ($cashMovement->type === CashMovementType::INCOME) {
                    $account->decrement('current_balance', $amount);
                } elseif ($cashMovement->type === CashMovementType::EXPENSE) {
                    $account->increment('current_balance', $amount);
                }
                
                $account->update(['balance_date' => now()]);
            }
        }
    }

    /**
     * Atualiza o saldo da conta bancária
     */
    protected function updateBankAccountBalance(CashMovement $cashMovement): void
    {
        if (!$cashMovement->bank_account_id) {
            return;
        }

        $account = BankAccount::find($cashMovement->bank_account_id);
        if (!$account) {
            return;
        }

        $amount = $cashMovement->amount;

        DB::transaction(function () use ($account, $cashMovement, $amount) {
            // Atualizar saldo baseado no tipo de movimentação
            if ($cashMovement->type === CashMovementType::INCOME) {
                $newBalance = $account->current_balance + $amount;
            } elseif ($cashMovement->type === CashMovementType::EXPENSE) {
                $newBalance = $account->current_balance - $amount;
            } elseif ($cashMovement->type === CashMovementType::TRANSFER) {
                // Na transferência, debita da conta origem
                $newBalance = $account->current_balance - $amount;
                
                // E credita na conta destino
                if ($cashMovement->transfer_to_account_id) {
                    $toAccount = BankAccount::find($cashMovement->transfer_to_account_id);
                    if ($toAccount) {
                        $toAccount->update([
                            'current_balance' => $toAccount->current_balance + $amount,
                            'balance_date' => now(),
                        ]);
                    }
                }
            } else {
                return;
            }

            // Atualizar saldo da conta origem
            $account->update([
                'current_balance' => $newBalance,
                'balance_date' => now(),
            ]);

            // Atualizar o balance_after no próprio movimento
            $cashMovement->update(['balance_after' => $newBalance]);
        });
    }
}
