<?php

namespace App\Filament\Resources\SalesProjectTypeResource\Pages;

use App\Filament\Resources\SalesProjectTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesProjectTypes extends ListRecords
{
    protected static string $resource = SalesProjectTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
