<?php

namespace App\Filament\Resources\CashMovementResource\Pages;

use App\Filament\Resources\CashMovementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCashMovement extends CreateRecord
{
    protected static string $resource = CashMovementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(fn (): Model => static::getModel()::create($data), attempts: 3);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
