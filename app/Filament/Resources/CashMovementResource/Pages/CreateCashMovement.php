<?php

namespace App\Filament\Resources\CashMovementResource\Pages;

use App\Filament\Resources\CashMovementResource;
use App\Models\BankAccount;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCashMovement extends CreateRecord
{
    protected static string $resource = CashMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
        // Calcular saldo após a movimentação
        $bankAccount = BankAccount::find($data['bank_account_id']);
        if ($bankAccount) {
            $amount = $data['amount'];
            
            if ($data['type'] === 'income') {
                $data['balance_after'] = $bankAccount->current_balance + $amount;
            } elseif ($data['type'] === 'expense') {
                $data['balance_after'] = $bankAccount->current_balance - $amount;
            } elseif ($data['type'] === 'transfer' && isset($data['transfer_to_account_id'])) {
                $data['balance_after'] = $bankAccount->current_balance - $amount;
            }
        }
        
        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);
        
        // Atualizar saldo da conta de origem
        $bankAccount = BankAccount::find($data['bank_account_id']);
        if ($bankAccount && isset($data['balance_after'])) {
            $bankAccount->update(['current_balance' => $data['balance_after']]);
        }
        
        // Se for transferência, atualizar conta de destino
        if ($data['type'] === 'transfer' && isset($data['transfer_to_account_id'])) {
            $transferAccount = BankAccount::find($data['transfer_to_account_id']);
            if ($transferAccount) {
                $transferAccount->increment('current_balance', $data['amount']);
            }
        }
        
        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
