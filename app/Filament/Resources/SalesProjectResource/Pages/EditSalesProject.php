<?php

namespace App\Filament\Resources\SalesProjectResource\Pages;

use App\Filament\Resources\SalesProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesProject extends EditRecord
{
    protected static string $resource = SalesProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
