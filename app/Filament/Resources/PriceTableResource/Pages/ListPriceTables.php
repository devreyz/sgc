<?php

namespace App\Filament\Resources\PriceTableResource\Pages;

use App\Filament\Resources\PriceTableResource;
use Filament\Resources\Pages\ListRecords;

class ListPriceTables extends ListRecords
{
    protected static string $resource = PriceTableResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()];
    }
}
