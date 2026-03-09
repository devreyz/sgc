<?php

namespace App\Filament\Resources\PdvCustomerResource\Pages;

use App\Filament\Resources\PdvCustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPdvCustomers extends ListRecords
{
    protected static string $resource = PdvCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
