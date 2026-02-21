<?php

namespace App\Filament\Resources\PhysicalInventoryResource\Pages;

use App\Filament\Resources\PhysicalInventoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePhysicalInventory extends CreateRecord
{
    protected static string $resource = PhysicalInventoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
