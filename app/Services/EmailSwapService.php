<?php

namespace App\Services;

use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
     * @param  TenantUser  $tenantUser  O vínculo do membro
     * @param  string  $newEmail  O novo email desejado
     * @param  int|null  $performedBy  ID do usuário que está executando (admin)
     * @return array Resultado da operação
     *
     * @throws \RuntimeException Se o novo email já está vinculado à mesma organização
     */
    public function swap(TenantUser $tenantUser, string $newEmail, ?int $performedBy = null): array
    {
        $newEmail = mb_strtolower(trim($newEmail));
        $tenantId = (int) $tenantUser->tenant_id;
        $oldUser = $tenantUser->user;

        if (! filter_var($newEmail, FILTER_VALIDATE_EMAIL) || mb_strlen($newEmail) > 255) {
            return ['success' => false, 'message' => 'Informe um email valido.'];
        }

        if (! $oldUser) {
            return ['success' => false, 'message' => 'A conta atual deste membro nao foi localizada.'];
        }

        if ($performedBy && (int) session('tenant_id') !== $tenantId
            && ! User::query()->whereKey($performedBy)->first()?->isSuperAdmin()) {
            return ['success' => false, 'message' => 'Nao foi possivel alterar o acesso desta organizacao.'];
        }

        // Validar que o email realmente mudou
        if (mb_strtolower((string) $oldUser->email) === $newEmail) {
            return [
                'success' => false,
                'message' => 'O novo email é igual ao email atual.',
            ];
        }

        // Verificar se o novo email já está vinculado à MESMA organização
        $existingMembership = TenantUser::where('tenant_id', $tenantId)
            ->whereHas('user', fn ($q) => $q->whereRaw('LOWER(email) = ?', [$newEmail]))
            ->where('id', '!=', $tenantUser->id)
            ->first();

        if ($existingMembership) {
            return [
                'success' => false,
                'message' => 'Este email já está vinculado a outro membro desta organização.',
            ];
        }

        return DB::transaction(function () use ($tenantUser, $newEmail, $oldUser, $tenantId, $performedBy) {
            $lockedMembership = TenantUser::query()
                ->whereKey($tenantUser->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $lockedMembership->user_id === (int) $oldUser->id, 409, 'O acesso foi alterado por outra operacao.');

            // 1. Buscar ou criar User com o novo email
            $newUser = User::withTrashed()
                ->whereRaw('LOWER(email) = ?', [$newEmail])
                ->lockForUpdate()
                ->first();
            $userAction = 'reutilizado';

            if (! $newUser) {
                $newUser = User::create([
                    'name' => trim((string) $lockedMembership->tenant_name) ?: null,
                    'email' => $newEmail,
                    'password' => Hash::make(Str::random(32)), // Senha temporária segura
                    'status' => true,
                ]);
                $userAction = 'criado';
            } elseif ($newUser->trashed() || ! $newUser->status) {
                return [
                    'success' => false,
                    'message' => 'A conta deste email esta desativada. A reativacao global deve ser revisada antes da troca.',
                ];
            }

            $duplicate = TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $newUser->id)
                ->whereKeyNot($lockedMembership->id)
                ->lockForUpdate()
                ->exists();
            if ($duplicate) {
                return ['success' => false, 'message' => 'Este email ja pertence a outro membro desta organizacao.'];
            }

            $oldUserId = $tenantUser->user_id;

            // 2. Atualizar o vínculo para apontar para o novo User
            // Usamos update direto para bypear proteções do model (user_id é controlado pelo serviço)
            DB::table('tenant_user')
                ->where('id', $lockedMembership->id)
                ->where('tenant_id', $tenantId)
                ->where('user_id', $oldUserId)
                ->update(['user_id' => $newUser->id, 'updated_at' => now()]);

            // 2.1. Adicionar ao histórico de emails
            $emailHistory = $lockedMembership->email_history ?? [];
            $emailHistory[] = [
                'email' => $oldUser->email,
                'changed_at' => now()->toDateTimeString(),
                'changed_by' => $performedBy ?? auth()->id(),
                'new_email' => $newEmail,
            ];
            DB::table('tenant_user')
                ->where('id', $lockedMembership->id)
                ->update(['email_history' => json_encode($emailHistory)]);

            // 3. Atualizar Associate.user_id se existir
            $associateUpdated = Schema::hasTable('associates')
                ? DB::table('associates')->where('user_id', $oldUserId)->where('tenant_id', $tenantId)
                    ->update(['user_id' => $newUser->id])
                : 0;

            // 4. Atualizar ServiceProvider.user_id se existir
            $providerUpdated = Schema::hasTable('service_providers')
                ? DB::table('service_providers')->where('user_id', $oldUserId)->where('tenant_id', $tenantId)
                    ->update(['user_id' => $newUser->id])
                : 0;

            $hasGoogle = filled($newUser->google_id)
                || $newUser->oauthAccounts()->where('provider', 'google')->exists();
            $hasPasskey = $newUser->passkeys()->exists();
            $requiresAccessSetup = ! $hasGoogle && ! $hasPasskey;

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
                'old_email_hash' => hash('sha256', mb_strtolower((string) $oldUser->email)),
                'new_email_hash' => hash('sha256', $newEmail),
                'performed_by' => $performedBy ?? auth()->id(),
            ]);

            return [
                'success' => true,
                'message' => $userAction === 'criado'
                    ? 'Email alterado e nova conta de acesso preparada.'
                    : 'Email alterado e acesso transferido para a conta existente.',
                'old_email' => $oldUser->email,
                'new_email' => $newEmail,
                'new_user_id' => $newUser->id,
                'user_action' => $userAction,
                'requires_access_setup' => $requiresAccessSetup,
            ];
        });
    }
}
