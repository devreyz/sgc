<?php

namespace App\Filament\Resources\FinancialReceiptResource\Pages;

use App\Filament\Resources\FinancialReceiptResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialReceipt extends CreateRecord
{
    protected static string $resource = FinancialReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void { $this->record->recalculateTotal(); }
    protected function getRedirectUrl(): string { return static::getResource()::getUrl('edit', ['record' => $this->record]); }
}
