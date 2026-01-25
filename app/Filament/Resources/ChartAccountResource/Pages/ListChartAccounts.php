<?php

namespace App\Filament\Resources\ChartAccountResource\Pages;

use App\Filament\Resources\ChartAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChartAccounts extends ListRecords
{
    protected static string $resource = ChartAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
