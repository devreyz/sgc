<?php

namespace App\Filament\Resources\FinancialReceiptResource\Pages;

use App\Filament\Resources\FinancialReceiptResource;
use App\Models\FinancialReceipt;
use App\Services\FinancialReceiptService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditFinancialReceipt extends EditRecord
{
    protected static string $resource = FinancialReceiptResource::class;
    protected function afterSave(): void { $this->record->recalculateTotal(); }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('issue')->label('Emitir recibo')->icon('heroicon-o-check-circle')->color('success')
                ->visible(fn () => $this->record->isDraft() && auth()->user()->can('financial_receipt.issue'))
                ->requiresConfirmation()->modalDescription('A emissão creditará a conta e impedirá alterações posteriores.')
                ->action(function (): void {
                    app(FinancialReceiptService::class)->issue($this->record, auth()->user());
                    Notification::make()->title('Recibo emitido com sucesso')->success()->send();
                    $this->redirect(static::getResource()::getUrl('view', ['record' => $this->record]));
                }),
            Actions\ViewAction::make(),
        ];
    }
}
