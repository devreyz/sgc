<?php

namespace App\Services;

use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * EmailSwapService — Gerencia troca de email de membros de organizações.
 *
 * FLUXO:
 * 1. Admin solicita alteração de email de um membro (TenantUser)
 * 2. O serviço verifica se já existe um User com o novo email
 * 3. Se existir: reutiliza esse User
 * 4. Se não existir: cria novo User com o novo email
 * 5. Atualiza APENAS tenant_user.user_id para o novo User
 *
 * O QUE NÃO MUDA:
 * - tenant_user.id (imutável, referenciado pelo histórico)
 * - Dados do Associate ou ServiceProvider (continuam apontando para user_id via tenant scope)
 * - Histórico financeiro (referencia associate_id, service_provider_id — não user_id)
 * - Logs (mantêm o causer_id original de cada ação)
 *
 * O QUE MUDA:
 * - tenant_user.user_id → novo User
 * - O email antigo PERDE acesso à organização (se não tiver outro vínculo)
 * - O novo email GANHA acesso à organização
 * - O Associate.user_id e ServiceProvider.user_id são atualizados para o novo user
 *
 * AUDITORIA:
 * - Todo o processo é logado no activity_log
 * - O admin que executou a ação é registrado
 */
class EmailSwapService
{
    /**
     * Executa a troca de email de um membro da organização.
     *
     * @param TenantUser $tenantUser O vínculo do membro
     * @param string $newEmail O novo email desejado
     * @param int|null $performedBy ID do usuário que está executando (admin)
     * @return array Resultado da operação
     *
     * @throws \RuntimeException Se o novo email já está vinculado à mesma organização
     */
    public function swap(TenantUser $tenantUser, string $newEmail, ?int $performedBy = null): array
    {
        $newEmail = strtolower(trim($newEmail));
        $oldUser = $tenantUser->user;
        $tenantId = $tenantUser->tenant_id;

        // Validar que o email realmente mudou
        if ($oldUser->email === $newEmail) {
            return [
                'success' => false,
                'message' => 'O novo email é igual ao email atual.',
            ];
        }

        // Verificar se o novo email já está vinculado à MESMA organização
        $existingMembership = TenantUser::where('tenant_id', $tenantId)
            ->whereHas('user', fn ($q) => $q->where('email', $newEmail))
            ->where('id', '!=', $tenantUser->id)
            ->first();

        if ($existingMembership) {
            return [
                'success' => false,
                'message' => 'Este email já está vinculado a outro membro desta organização.',
            ];
        }

        return DB::transaction(function () use ($tenantUser, $newEmail, $oldUser, $tenantId, $performedBy) {
            // 1. Buscar ou criar User com o novo email
            $newUser = User::withTrashed()->where('email', $newEmail)->first();
            $userAction = 'reutilizado';

            if (!$newUser) {
                $newUser = User::create([
                    'name' => $oldUser->getRawOriginal('name'),
                    'email' => $newEmail,
                    'password' => Hash::make(Str::random(32)), // Senha temporária segura
                    'status' => true,
                ]);
                $userAction = 'criado';
            } elseif ($newUser->trashed()) {
                // Se o User existia mas estava soft-deleted, restaurar
                $newUser->restore();
                $userAction = 'restaurado';
            }

            $oldUserId = $tenantUser->user_id;

            // 2. Atualizar o vínculo para apontar para o novo User
            // Usamos update direto para bypear proteções do model (user_id é controlado pelo serviço)
            DB::table('tenant_user')
                ->where('id', $tenantUser->id)
                ->update(['user_id' => $newUser->id, 'updated_at' => now()]);

            // 3. Atualizar Associate.user_id se existir
            $associateUpdated = DB::table('associates')
                ->where('user_id', $oldUserId)
                ->where('tenant_id', $tenantId)
                ->update(['user_id' => $newUser->id]);

            // 4. Atualizar ServiceProvider.user_id se existir
            $providerUpdated = DB::table('service_providers')
                ->where('user_id', $oldUserId)
                ->where('tenant_id', $tenantId)
                ->update(['user_id' => $newUser->id]);

            // 5. Registrar log de auditoria
            activity('email_swap')
                ->performedOn($tenantUser)
                ->causedBy($performedBy ? User::find($performedBy) : auth()->user())
                ->withProperties([
                    'tenant_id' => $tenantId,
                    'tenant_user_id' => $tenantUser->id,
                    'old_user_id' => $oldUserId,
                    'new_user_id' => $newUser->id,
                    'old_email' => $oldUser->email,
                    'new_email' => $newEmail,
                    'new_user_action' => $userAction,
                    'associate_updated' => $associateUpdated > 0,
                    'provider_updated' => $providerUpdated > 0,
                ])
                ->log("Troca de email: {$oldUser->email} → {$newEmail} (User {$userAction})");

            Log::info('EmailSwap executado', [
                'tenant_id' => $tenantId,
                'tenant_user_id' => $tenantUser->id,
                'old_user_id' => $oldUserId,
                'new_user_id' => $newUser->id,
                'old_email' => $oldUser->email,
                'new_email' => $newEmail,
                'performed_by' => $performedBy ?? auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => "Email alterado com sucesso. User {$userAction}.",
                'old_email' => $oldUser->email,
                'new_email' => $newEmail,
                'new_user_id' => $newUser->id,
                'user_action' => $userAction,
            ];
        });
    }
}
