<?php

namespace App\Filament\Resources\ServiceOrderPaymentResource\Pages;

use App\Filament\Resources\ServiceOrderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceOrderPayments extends ListRecords
{
    protected static string $resource = ServiceOrderPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
