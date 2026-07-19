<?php

namespace App\Filament\Resources\TenantUserResource\Pages;

use App\Filament\Resources\TenantUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditTenantUser extends EditRecord
{
    protected static string $resource = TenantUserResource::class;

    protected function getHeaderActions(): array
    {
        // Sem DeleteAction - vínculos nunca são deletados
        return [
            Actions\Action::make('manageAccess')
                ->label('Seguranca e acesso')
                ->icon('heroicon-o-key')
                ->url(fn (): string => route('security.members.access.index', [
                    'tenant' => \App\Models\Tenant::query()->whereKey($this->record->tenant_id)->value('slug'),
                    'membership' => $this->record->id,
                ])),
        ];
    }

    /**
     * Hash da senha se foi alterada.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['tenant_password'])) {
            $data['tenant_password'] = Hash::make($data['tenant_password']);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
