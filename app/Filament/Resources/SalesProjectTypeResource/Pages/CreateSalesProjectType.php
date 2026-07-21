<?php

namespace App\Filament\Resources\SalesProjectTypeResource\Pages;

use App\Filament\Resources\SalesProjectTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesProjectType extends CreateRecord
{
    protected static string $resource = SalesProjectTypeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = (int) session('tenant_id');
        $data['created_by'] = auth()->id();

        return $data;
    }
}
