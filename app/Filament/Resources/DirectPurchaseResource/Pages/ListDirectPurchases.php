<?php

namespace App\Filament\Resources\DirectPurchaseResource\Pages;

use App\Filament\Resources\DirectPurchaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDirectPurchases extends ListRecords
{
    protected static string $resource = DirectPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
