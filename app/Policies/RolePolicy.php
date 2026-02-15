<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_role');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('view_role');
    }

    /**
     * Determine whether the user can create models.
     * 
     * IMPORTANTE: Apenas super admins podem criar roles.
     * Admins de organizações só podem ATRIBUIR roles existentes aos usuários.
     */
    public function create(User $user): bool
    {
        // Apenas super admins podem criar roles
        if (!$user->isSuperAdmin()) {
            return false;
        }
        
        return $user->can('create_role');
    }

    /**
     * Determine whether the user can update the model.
     * 
     * IMPORTANTE: Apenas super admins podem editar roles.
     * Admins de organizações só podem ATRIBUIR roles existentes aos usuários.
     */
    public function update(User $user, Role $role): bool
    {
        // Apenas super admins podem editar roles
        if (!$user->isSuperAdmin()) {
            return false;
        }
        
        // Super admins são gerenciados apenas no painel super-admin
        if ($role->name === 'super_admin' && !$user->isSuperAdmin()) {
            return false;
        }

        // Admins não podem alterar a role 'admin' (suas próprias permissions)
        if ($role->name === 'admin' && !$user->isSuperAdmin()) {
            return false;
        }

        return $user->can('update_role');
    }

    /**
     * Determine whether the user can delete the model.
     * 
     * IMPORTANTE: Apenas super admins podem deletar roles.
     */
    public function delete(User $user, Role $role): bool
    {
        // Apenas super admins podem deletar roles
        if (!$user->isSuperAdmin()) {
            return false;
        }
        
        // Não pode deletar super_admin ou admin
        if (in_array($role->name, ['super_admin', 'admin'])) {
            return false;
        }

        return $user->can('delete_role');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_role');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        // Nunca permitir force delete de super_admin ou admin
        if (in_array($role->name, ['super_admin', 'admin'])) {
            return false;
        }

        return $user->can('force_delete_role');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_role');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->can('restore_role');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_role');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Role $role): bool
    {
        return $user->can('replicate_role');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_role');
    }
}
