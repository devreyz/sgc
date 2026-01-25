<?php

namespace App\Filament\Resources\CollectivePurchaseResource\Pages;

use App\Filament\Resources\CollectivePurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCollectivePurchase extends ViewRecord
{
    protected static string $resource = CollectivePurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
