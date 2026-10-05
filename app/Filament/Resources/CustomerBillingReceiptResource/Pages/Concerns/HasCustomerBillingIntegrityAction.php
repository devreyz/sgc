<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages\Concerns;

use App\Services\DeliveryParentRecoveryService;
use Filament\Actions;
use Filament\Notifications\Notification;

trait HasCustomerBillingIntegrityAction
{
    protected function integrityAction(): Actions\Action
    {
        return Actions\Action::make('repairIntegrity')
            ->label('Corrigir integridade')
            ->icon('heroicon-o-wrench-screwdriver')
            ->color('warning')
            ->visible(function (): bool {
                $user = auth()->user();
                if (! $user || ! $user->can('update_customer::billing::receipt')) {
                    return false;
                }
                $diagnosis = app(DeliveryParentRecoveryService::class)
                    ->diagnosisForCustomerReceipt($this->record);

                return $diagnosis['recoverable'] > 0 || $diagnosis['unrecoverable'] > 0;
            })
            ->modalHeading(fn (): string => 'Verificar '.$this->record->formatted_number)
            ->modalDescription(function (): string {
                $diagnosis = app(DeliveryParentRecoveryService::class)
                    ->diagnosisForCustomerReceipt($this->record);
                $parts = [];
                if ($diagnosis['recoverable'] > 0) {
                    $parts[] = "{$diagnosis['recoverable']} entrega(s)-pai removida(s) podem ser restauradas sem reativar distribuições excluídas ou alterar valores financeiros.";
                }
                if ($diagnosis['unrecoverable'] > 0) {
                    $parts[] = "{$diagnosis['unrecoverable']} distribuição(ões) exigem revisão manual porque o registro não existe ou pertence a outro contexto.";
                }

                return $parts === []
                    ? 'Nenhum vínculo quebrado foi encontrado neste comprovante.'
                    : implode(' ', $parts);
            })
            ->requiresConfirmation()
            ->modalSubmitActionLabel('Restaurar entregas-pai')
            ->action(function (): void {
                $actor = auth()->user();
                if (! $actor || ! $actor->can('update_customer::billing::receipt')) {
                    Notification::make()->danger()->title('Sessão expirada')->send();

                    return;
                }

                $result = app(DeliveryParentRecoveryService::class)
                    ->restoreForCustomerReceipt($this->record, $actor);
                $this->record->refresh();

                if ($result['restored'] !== []) {
                    Notification::make()->success()
                        ->title('Integridade restaurada')
                        ->body(count($result['restored']).' entrega(s)-pai restaurada(s). Distribuições excluídas permaneceram no histórico e o snapshot financeiro foi preservado.')
                        ->send();
                } elseif ($result['unresolved'] === []) {
                    Notification::make()->info()
                        ->title('Nenhuma correção necessária')
                        ->body('Os vínculos deste comprovante já estão íntegros.')
                        ->send();
                }
                if ($result['unresolved'] !== []) {
                    Notification::make()->warning()
                        ->title('Revisão adicional necessária')
                        ->body(count($result['unresolved']).' distribuição(ões) não puderam ser corrigidas automaticamente.')
                        ->persistent()
                        ->send();
                }
            });
    }
}
