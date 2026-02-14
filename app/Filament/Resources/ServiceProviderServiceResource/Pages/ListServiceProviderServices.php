<?php

namespace App\Filament\Resources\ServiceProviderServiceResource\Pages;

use App\Filament\Resources\ServiceProviderServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceProviderServices extends ListRecords
{
    protected static string $resource = ServiceProviderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
