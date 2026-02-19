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
     * Processa o lookup/criação de User e injeta tenant_id.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extrair dados temporários do form
        $email = $data['user_email'] ?? null;
        $name = $data['user_name'] ?? null;
        $password = $data['tenant_password'] ?? null;

        // Remover campos temporários
        unset($data['user_email'], $data['user_name'], $data['_existing_user']);

        if (!$email) {
            throw new \Exception('Email é obrigatório.');
        }

        // Buscar ou criar User
        $user = User::withTrashed()->where('email', $email)->first();

        if (!$user) {
            // Criar novo usuário global (com senha aleatória forte, pois a real fica no tenant_password)
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
                'status' => true,
            ]);
        } elseif ($user->trashed()) {
            // Restaurar se estava soft-deleted
            $user->restore();
        }

        // Associar ao tenant_user
        $data['user_id'] = $user->id;
        $data['tenant_id'] = session('tenant_id');

        // Hash da senha (se fornecida)
        if ($password) {
            $data['tenant_password'] = Hash::make($password);
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
