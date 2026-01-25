<?php

namespace App\Filament\Resources\ServiceOrderResource\Pages;

use App\Filament\Resources\ServiceOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceOrder extends CreateRecord
{
    protected static string $resource = ServiceOrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate order number
        $lastOrder = \App\Models\ServiceOrder::orderBy('id', 'desc')->first();
        $data['number'] = 'OS-' . str_pad(($lastOrder ? $lastOrder->id + 1 : 1), 6, '0', STR_PAD_LEFT);
        $data['created_by'] = auth()->id();
        
        return $data;
    }
}
