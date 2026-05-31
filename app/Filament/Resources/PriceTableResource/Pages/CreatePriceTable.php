<?php

namespace App\Filament\Resources\PriceTableResource\Pages;

use App\Filament\Resources\PriceTableResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePriceTable extends CreateRecord
{
    protected static string $resource = PriceTableResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id']  = session('tenant_id');
        $data['created_by'] = auth()->id();
        return $data;
    }
}
