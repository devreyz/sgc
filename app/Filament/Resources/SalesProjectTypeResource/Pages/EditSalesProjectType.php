<?php

namespace App\Filament\Resources\SalesProjectTypeResource\Pages;

use App\Filament\Resources\SalesProjectTypeResource;
use App\Models\SalesProject;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesProjectType extends EditRecord
{
    protected static string $resource = SalesProjectTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn (): bool => SalesProject::query()
                    ->where('tenant_id', $this->record->tenant_id)
                    ->where('type', $this->record->slug)
                    ->exists()),
        ];
    }
}
