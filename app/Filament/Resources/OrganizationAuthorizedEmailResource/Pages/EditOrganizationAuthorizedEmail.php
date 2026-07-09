<?php

namespace App\Filament\Resources\OrganizationAuthorizedEmailResource\Pages;

use App\Filament\Resources\OrganizationAuthorizedEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizationAuthorizedEmail extends EditRecord
{
    protected static string $resource = OrganizationAuthorizedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
