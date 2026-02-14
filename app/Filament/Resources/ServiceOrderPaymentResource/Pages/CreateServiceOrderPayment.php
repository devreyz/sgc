<?php

namespace App\Filament\Resources\ServiceOrderPaymentResource\Pages;

use App\Filament\Resources\ServiceOrderPaymentResource;
use App\Models\ServiceOrder;
use App\Models\AssociateLedger;
use App\Models\ServiceProviderLedger;
use App\Enums\ServiceOrderStatus;
use App\Enums\LedgerType;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CreateServiceOrderPayment extends CreateRecord
{
    protected static string $resource = ServiceOrderPaymentResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registered_by'] = Auth::id();
        return $data;
    }
    
    protected function afterCreate(): void
    {
        $payment = $this->record;
        $order = $payment->serviceOrder()->with(['associate', 'serviceProvider'])->first();
        
        if (!$order) {
            Notification::make()
                ->danger()
                ->title('Erro')
                ->body('Ordem de serviço não encontrada.')
                ->send();
            return;
        }
        
        DB::transaction(function () use ($payment, $order) {
            // 1. Criar lançamento no ledger do associado (DÉBITO - ele está pagando)
            if ($order->associate) {
                $lastLedger = AssociateLedger::where('associate_id', $order->associate_id)
                    ->latest('transaction_date')
                    ->latest('id')
                    ->first();
                    
                $currentBalance = $lastLedger ? $lastLedger->balance_after : 0;
                
               AssociateLedger::create([
                    'associate_id' => $order->associate_id,
                    'type' => LedgerType::DEBIT,
                    'amount' => $payment->amount,
                    'description' => "Pagamento de serviço - OS {$order->number}",
                    'transaction_date' => $payment->payment_date,
                    'balance_before' => $currentBalance,
                    'balance_after' => $currentBalance - $payment->amount,
                    'reference_type' => get_class($order),
                    'reference_id' => $order->id,
                    'payment_method' => $payment->payment_method->value,
                    'bank_account_id' => $payment->bank_account_id,
                ]);
            }
            
            // 2. Verificar se ordem foi totalmente paga
            $totalPaid = $order->payments()->sum('amount');
            
            if ($totalPaid >= $order->final_price) {
                // Ordem totalmente paga - marcar como PAID e creditar prestador
                $order->update([
                    'status' => ServiceOrderStatus::PAID,
                ]);
                
                // 3. Criar lançamento no ledger do prestador (CRÉDITO - ele está recebendo)
                if ($order->serviceProvider && $order->provider_payment > 0) {
                    $lastProviderLedger = ServiceProviderLedger::where('service_provider_id', $order->service_provider_id)
                        ->latest('transaction_date')
                        ->latest('id')
                        ->first();
                        
                    $providerBalance = $lastProviderLedger ? $lastProviderLedger->balance_after : 0;
                    
                    ServiceProviderLedger::create([
                        'service_provider_id' => $order->service_provider_id,
                        'type' => LedgerType::CREDIT,
                        'amount' => $order->provider_payment,
                        'description' => "Pagamento recebido - OS {$order->number}",
                        'transaction_date' => now(),
                        'balance_before' => $providerBalance,
                        'balance_after' => $providerBalance + $order->provider_payment,
                        'reference_type' => get_class($order),
                        'reference_id' => $order->id,
                    ]);
                    
                    // Atualizar saldo do prestador
                    $order->serviceProvider->update([
                        'current_balance' => $providerBalance + $order->provider_payment,
                    ]);
                }
                
                Notification::make()
                    ->success()
                    ->title('Ordem Totalmente Paga!')
                    ->body("Ordem {$order->number} foi marcada como PAGA e o prestador foi creditado com R$ " . number_format($order->provider_payment, 2, ',', '.'))
                    ->send();
            } else {
                $remaining = $order->final_price - $totalPaid;
                
                Notification::make()
                    ->info()
                    ->title('Pagamento Parcial Registrado')
                    ->body("Restam R$ " . number_format($remaining, 2, ',', '.') . " a pagar nesta ordem.")
                    ->send();
            }
        });
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
