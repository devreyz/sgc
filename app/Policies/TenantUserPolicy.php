<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TenantUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantUserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_tenant::user');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TenantUser $tenantUser): bool
    {
        return $user->can('view_tenant::user');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_tenant::user');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TenantUser $tenantUser): bool
    {
        return $user->can('update_tenant::user');
    }

    /**
     * Vínculos NUNCA podem ser apagados, apenas desativados.
     */
    public function delete(User $user, TenantUser $tenantUser): bool
    {
        return false;
    }

    /**
     * Vínculos NUNCA podem ser apagados em massa.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Vínculos NUNCA podem ser apagados permanentemente.
     */
    public function forceDelete(User $user, TenantUser $tenantUser): bool
    {
        return false;
    }

    /**
     * Vínculos NUNCA podem ser apagados permanentemente em massa.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, TenantUser $tenantUser): bool
    {
        return $user->can('restore_tenant::user');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_tenant::user');
    }

    /**
     * Vínculos não podem ser replicados.
     */
    public function replicate(User $user, TenantUser $tenantUser): bool
    {
        return false;
    }

    /**
     * Vínculos não podem ser reordenados.
     */
    public function reorder(User $user): bool
    {
        return false;
    }
}
