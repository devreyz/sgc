<?php

namespace App\Filament\Resources\CustomerBillingReceiptResource\Pages;

use App\Filament\Resources\CustomerBillingReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerBillingReceipts extends ListRecords
{
    protected static string $resource = CustomerBillingReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
