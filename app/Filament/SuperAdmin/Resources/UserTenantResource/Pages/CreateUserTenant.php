<?php

namespace App\Filament\SuperAdmin\Resources\UserTenantResource\Pages;

use App\Filament\SuperAdmin\Resources\UserTenantResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUserTenant extends CreateRecord
{
    protected static string $resource = UserTenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['password']) && filled($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        // Filter out repeater items without tenant id to avoid creating empty Tenants
        if (isset($data['tenantRelations']) && is_array($data['tenantRelations'])) {
            $data['tenantRelations'] = array_values(array_filter($data['tenantRelations'], function ($item) {
                return isset($item['id']) && filled($item['id']);
            }));
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
