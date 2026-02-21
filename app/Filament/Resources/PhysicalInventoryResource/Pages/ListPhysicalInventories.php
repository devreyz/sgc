<?php

namespace App\Filament\Resources\PhysicalInventoryResource\Pages;

use App\Filament\Resources\PhysicalInventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPhysicalInventories extends ListRecords
{
    protected static string $resource = PhysicalInventoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo Inventário')];
    }
}
