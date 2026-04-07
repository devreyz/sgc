<?php

namespace App\Filament\Resources\AssociateReceiptResource\Pages;

use App\Filament\Resources\AssociateReceiptResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssociateReceipt extends EditRecord
{
    protected static string $resource = AssociateReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
