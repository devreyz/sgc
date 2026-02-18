<?php

namespace App\Filament\Resources\CollectivePurchaseResource\Pages;

use App\Filament\Resources\CollectivePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCollectivePurchase extends EditRecord
{
    protected static string $resource = CollectivePurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
