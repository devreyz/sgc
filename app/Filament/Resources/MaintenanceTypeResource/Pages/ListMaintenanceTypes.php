<?php

namespace App\Filament\Resources\MaintenanceTypeResource\Pages;

use App\Filament\Resources\MaintenanceTypeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListMaintenanceTypes extends ListRecords
{
    protected static string $resource = MaintenanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Novo Tipo'),
        ];
    }
}
