<?php

namespace App\Filament\Resources\StockReceiptResource\Pages;

use App\Filament\Resources\StockReceiptResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStockReceipt extends CreateRecord
{
    protected static string $resource = StockReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
