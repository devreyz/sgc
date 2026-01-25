<?php

namespace App\Filament\Resources\MaintenanceTypeResource\Pages;

use App\Filament\Resources\MaintenanceTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMaintenanceType extends CreateRecord
{
    protected static string $resource = MaintenanceTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
