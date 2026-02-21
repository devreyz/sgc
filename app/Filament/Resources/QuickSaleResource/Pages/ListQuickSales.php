<?php

namespace App\Filament\Resources\QuickSaleResource\Pages;

use App\Filament\Resources\QuickSaleResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListQuickSales extends ListRecords
{
    protected static string $resource = QuickSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Venda Rápida'),
        ];
    }
}
