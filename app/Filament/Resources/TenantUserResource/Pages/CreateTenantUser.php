<?php

namespace App\Filament\Resources\TenantUserResource\Pages;

use App\Filament\Resources\TenantUserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateTenantUser extends CreateRecord
{
    protected static string $resource = TenantUserResource::class;

    /**
     * Regras de criação de membro:
     *
     * 1) Admin informa APENAS "nome do membro na organização" (user_name) + email.
     * 2) Se o email NÃO existe → cria User global com esse nome.
     *    O tenant_name fica nulo (membro usa o nome global dele).
     * 3) Se o email JÁ existe → reutiliza o User existente.
     *    O nome digitado é salvo como tenant_name (identidade local na organização).
     *    O nome global do User NÃO é alterado.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $email    = trim($data['user_email'] ?? '');
        $nameInput = trim($data['user_name'] ?? '');
        $password = $data['tenant_password'] ?? null;

        // Remover campos temporários que não pertencem ao model
        unset($data['user_email'], $data['user_name'], $data['_existing_user']);

        if (! $email) {
            throw new \Exception('O e-mail é obrigatório para criar um membro.');
        }

        $existingUser = User::withTrashed()->where('email', $email)->first();

        if (! $existingUser) {
            // Cria novo usuário global com o mesmo nome informado
            $existingUser = User::create([
                'name'     => $nameInput,
                'email'    => $email,
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'status'   => true,
            ]);

            // tenant_name fica nulo: membro usará o nome global
            $data['tenant_name'] = null;
        } else {
            // Restaura se estava soft-deleted
            if ($existingUser->trashed()) {
                $existingUser->restore();
            }

            // Nome digitado vira identidade local na organização
            $data['tenant_name'] = $nameInput ?: null;
        }

        $data['user_id']   = $existingUser->id;
        $data['tenant_id'] = session('tenant_id');

        // Hash da senha de acesso ao tenant
        if ($password) {
            $data['tenant_password'] = Hash::make($password);
        } else {
            unset($data['tenant_password']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        activity()
            ->causedBy(auth()->user())
            ->performedOn($record)
            ->withProperties([
                'tenant_id'   => session('tenant_id'),
                'user_id'     => $record->user_id,
                'tenant_name' => $record->tenant_name,
                'email'       => $record->user?->email,
                'is_admin'    => $record->is_admin,
            ])
            ->log('member.create');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

