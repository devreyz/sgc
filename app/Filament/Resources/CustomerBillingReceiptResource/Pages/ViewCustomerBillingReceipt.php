<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Filament\Resources\CustomerBillingReceiptResource;
use App\Filament\Resources\CustomerBillingReceiptResource\Pages\Concerns\HasCustomerBillingIntegrityAction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerBillingReceipt extends ViewRecord
{
    use HasCustomerBillingIntegrityAction;

    protected static string $resource = CustomerBillingReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->integrityAction(),
            Actions\EditAction::make()
                ->visible(fn (): bool => $this->record->isEditable()),
        ];
    }
}
