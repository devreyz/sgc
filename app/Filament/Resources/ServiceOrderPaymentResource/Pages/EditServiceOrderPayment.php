<?php

namespace App\Filament\Resources\ServiceOrderPaymentResource\Pages;

use App\Filament\Resources\ServiceOrderPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceOrderPayment extends EditRecord
{
    protected static string $resource = ServiceOrderPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
