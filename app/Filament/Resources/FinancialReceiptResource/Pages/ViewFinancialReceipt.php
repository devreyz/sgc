<?php

namespace App\Filament\Resources\FinancialReceiptResource\Pages;

use App\Filament\Resources\FinancialReceiptResource;
use App\Services\FinancialReceiptService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFinancialReceipt extends ViewRecord
{
    protected static string $resource = FinancialReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->visible(fn () => $this->record->isDraft()),
            Actions\Action::make('print')->label('Imprimir recibo')->icon('heroicon-o-printer')
                ->visible(fn () => ! $this->record->isDraft())
                ->url(fn () => route('financial-receipts.print', $this->record))->openUrlInNewTab(),
            Actions\Action::make('cancel')->label('Cancelar e estornar')->icon('heroicon-o-x-circle')->color('danger')
                ->visible(fn () => $this->record->isIssued() && auth()->user()->can('financial_receipt.cancel'))
                ->form([Forms\Components\Textarea::make('reason')->label('Motivo')->required()->minLength(10)])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    app(FinancialReceiptService::class)->cancel($this->record, auth()->user(), $data['reason']);
                    Notification::make()->title('Recibo cancelado e valor estornado')->success()->send();
                    $this->refreshFormData(['status', 'cancelled_at', 'cancellation_reason']);
                }),
        ];
    }
}
