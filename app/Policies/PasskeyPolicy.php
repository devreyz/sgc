<?php

namespace App\Policies;

use App\Models\Passkey;
use App\Models\TenantUser;
use App\Models\User;
use Spatie\Permission\Models\Role;

class PasskeyPolicy
{
    public function manageOwn(User $user): bool
    {
        return $this->canManageOwn($user);
    }

    public function revoke(User $user, Passkey $passkey): bool
    {
        return $this->canManageOwn($user) && (int) $passkey->user_id === (int) $user->id;
    }

    private function canManageOwn(User $user): bool
    {
        if (! $user->status) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return TenantUser::query()
            ->where('user_id', $user->id)
            ->where('status', true)
            ->where(function ($query): void {
                $query->where('is_admin', true)
                    ->orWhere(function ($roleQuery): void {
                        $roles = Role::query()
                            ->whereHas('permissions', fn ($permissionQuery) => $permissionQuery
                                ->where('name', 'passkeys.manage-own'))
                            ->pluck('name');

                        foreach ($roles as $role) {
                            $roleQuery->orWhereJsonContains('roles', $role);
                        }
                    });
            })
            ->exists();
    }
}
