<?php

namespace App\Filament\SuperAdmin\Resources\UserTenantResource\Pages;

use App\Filament\SuperAdmin\Resources\UserTenantResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditUserTenant extends EditRecord
{
    protected static string $resource = UserTenantResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Remove password from form data when editing
        unset($data['password']);
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['password']) && filled($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        // Ensure we don't attempt to create empty Tenant records from the Repeater
        if (isset($data['tenantRelations']) && is_array($data['tenantRelations'])) {
            $data['tenantRelations'] = array_values(array_filter($data['tenantRelations'], function ($item) {
                return isset($item['id']) && filled($item['id']);
            }));
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
