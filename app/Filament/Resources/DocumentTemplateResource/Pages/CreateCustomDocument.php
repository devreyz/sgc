<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\CustomDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomDocument extends CreateRecord
{
    protected static string $resource = CustomDocumentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['template_category'] = 'custom';
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return CustomDocumentResource::getUrl('index');
    }
}
