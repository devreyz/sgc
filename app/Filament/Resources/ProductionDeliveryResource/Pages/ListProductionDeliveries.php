<?php

namespace App\Filament\Resources\ProductionDeliveryResource\Pages;

use App\Filament\Resources\ProductionDeliveryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProductionDeliveries extends ListRecords
{
    protected static string $resource = ProductionDeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nova Entrega'),
        ];
    }
}
