<?php

namespace App\Filament\Resources\PriceTableResource\Pages;

use App\Filament\Resources\PriceTableResource;
use App\Models\PriceTable;
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

    protected function afterCreate(): void
    {
        if ($this->record->active && ! PriceTable::query()->where('tenant_id', $this->record->tenant_id)->where('active', true)->where('is_pdv_default', true)->exists()) {
            PriceTable::setPdvDefault((int) $this->record->tenant_id, (int) $this->record->id);
        }
    }
}
