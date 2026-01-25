<?php

namespace App\Filament\Resources\ProductionDeliveryResource\Pages;

use App\Filament\Resources\ProductionDeliveryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionDelivery extends CreateRecord
{
    protected static string $resource = ProductionDeliveryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Entrega registrada com sucesso!';
    }
}
