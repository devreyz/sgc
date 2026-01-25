<?php

namespace App\Filament\Resources\MaintenanceTypeResource\Pages;

use App\Filament\Resources\MaintenanceTypeResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditMaintenanceType extends EditRecord
{
    protected static string $resource = MaintenanceTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
