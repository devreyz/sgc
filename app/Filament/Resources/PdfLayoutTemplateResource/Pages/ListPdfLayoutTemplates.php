<?php

namespace App\Filament\Resources\PdfLayoutTemplateResource\Pages;

use App\Filament\Resources\PdfLayoutTemplateResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListPdfLayoutTemplates extends ListRecords
{
    protected static string $resource = PdfLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Novo Layout'),
        ];
    }
}
