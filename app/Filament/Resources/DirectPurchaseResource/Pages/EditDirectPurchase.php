<?php

namespace App\Filament\Resources\DirectPurchaseResource\Pages;

use App\Filament\Resources\DirectPurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDirectPurchase extends EditRecord
{
    protected static string $resource = DirectPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
