<?php

namespace App\Filament\Resources\OrganizationAuthorizedEmailResource\Pages;

use App\Filament\Resources\OrganizationAuthorizedEmailResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationAuthorizedEmails extends ListRecords
{
    protected static string $resource = OrganizationAuthorizedEmailResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
