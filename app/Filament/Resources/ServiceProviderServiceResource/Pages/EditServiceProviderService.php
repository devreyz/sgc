<?php

namespace App\Filament\Resources\ServiceProviderServiceResource\Pages;

use App\Filament\Resources\ServiceProviderServiceResource;
use Filament\Resources\Pages\EditRecord;

class EditServiceProviderService extends EditRecord
{
    protected static string $resource = ServiceProviderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
