<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Models\ProductionDelivery;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCustomerBillingReceipt extends EditRecord
{
    protected static string $resource = CustomerBillingReceiptResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redireciona se o comprovante não é mais editável (foi emitido ou pago)
        if ($this->record->isLocked()) {
            Notification::make()->warning()
                ->title('Comprovante bloqueado')
                ->body('Este comprovante já foi emitido ou pago e não pode ser editado.')
                ->send();

            $this->redirect($this->getResource()::getUrl('index'));
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Nunca permite alterar campos de controle
        unset($data['tenant_id'], $data['receipt_year'], $data['receipt_number'], $data['status']);
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isEditable())
                ->before(function () {
                    // Desvincula distribuições ao excluir
                    $r = $this->record;
                    if (! empty($r->delivery_ids)) {
                        ProductionDelivery::whereIn('id', $r->delivery_ids)
                            ->where('billing_receipt_id', $r->id)
                            ->update(['billing_receipt_id' => null]);
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
