<?php

namespace App\Filament\Resources\PdfLayoutTemplateResource\Pages;

use App\Filament\Resources\PdfLayoutTemplateResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;

class EditPdfLayoutTemplate extends EditRecord
{
    protected static string $resource = PdfLayoutTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
