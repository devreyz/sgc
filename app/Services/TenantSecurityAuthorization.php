<?php

namespace App\Services;

use App\Models\Associate;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role;

class TenantSecurityAuthorization
{
    public function authorize(User $user, int $tenantId, string $permission): TenantUser
    {
        if ($user->isSuperAdmin()) {
            return TenantUser::query()
                ->where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->first() ?? new TenantUser([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'status' => true,
                    'is_admin' => true,
                ]);
        }

        $membership = TenantUser::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('status', true)
            ->first();

        if (! $membership) {
            throw new AuthorizationException('Acesso nao autorizado.');
        }

        $roles = array_values(array_filter((array) $membership->roles));
        $hasPermission = $membership->is_admin || Role::query()
            ->whereIn('name', $roles)
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();

        if (! $hasPermission) {
            throw new AuthorizationException('Acesso nao autorizado.');
        }

        return $membership;
    }

    public function authorizeAssociate(User $user, int $tenantId, int|string $associateId, string $permission): Associate
    {
        $this->authorize($user, $tenantId, $permission);

        return Associate::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($associateId)
            ->firstOrFail();
    }
}
