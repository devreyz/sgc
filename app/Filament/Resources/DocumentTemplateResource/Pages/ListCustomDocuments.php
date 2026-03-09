<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\CustomDocumentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomDocuments extends ListRecords
{
    protected static string $resource = CustomDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
