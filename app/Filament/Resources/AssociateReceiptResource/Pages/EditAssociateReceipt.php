<?php

namespace App\Filament\Resources\AssociateReceiptResource\Pages;

use App\Filament\Resources\AssociateReceiptResource;
use Filament\Notifications\Notification;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssociateReceipt extends EditRecord
{
    protected static string $resource = AssociateReceiptResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! AssociateReceiptResource::canEdit($this->record)) {
            Notification::make()
                ->danger()
                ->title('Comprovante bloqueado')
                ->body('Comprovantes faturados, pagos ou parcialmente pagos nao podem ser editados.')
                ->send();

            $this->redirect(AssociateReceiptResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => AssociateReceiptResource::canDelete($this->record))
                ->using(function (): void {
                    if (! AssociateReceiptResource::canDelete($this->record)) {
                        Notification::make()
                            ->danger()
                            ->title('Comprovante bloqueado')
                            ->body('Comprovantes faturados, pagos ou parcialmente pagos nao podem ser excluidos.')
                            ->send();

                        return;
                    }

                    \App\Models\ProductionDelivery::where('tenant_id', $this->record->tenant_id)
                        ->where('associate_receipt_id', $this->record->id)
                        ->update(['associate_receipt_id' => null]);

                    $this->record->delete();
                    $this->redirect(AssociateReceiptResource::getUrl('index'));
                }),
        ];
    }
}
