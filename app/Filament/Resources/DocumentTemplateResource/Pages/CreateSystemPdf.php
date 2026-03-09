<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\SystemPdfResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSystemPdf extends CreateRecord
{
    protected static string $resource = SystemPdfResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['template_category'] = 'system';
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return SystemPdfResource::getUrl('index');
    }
}
