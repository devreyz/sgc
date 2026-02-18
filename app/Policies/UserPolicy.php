<?php

namespace App\Policies;

use App\Models\User;

use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user::tenant');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function view(User $user): bool
    {
        return $user->can('view_user::tenant');
    }

    /**
     * Determine whether the user can create models.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('create_user::tenant');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function update(User $user): bool
    {
        return $user->can('update_user::tenant');
    }

    /**
     * Usuários NUNCA podem ser apagados.
     */
    public function delete(User $user): bool
    {
        return false;
    }

    /**
     * Usuários NUNCA podem ser apagados em massa.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Usuários NUNCA podem ser apagados permanentemente.
     */
    public function forceDelete(User $user): bool
    {
        return false;
    }

    /**
     * Usuários NUNCA podem ser apagados permanentemente em massa.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function restore(User $user): bool
    {
        return $user->can('restore_user::tenant');
    }

    /**
     * Determine whether the user can bulk restore.
     *
     * @param  \App\Models\User  $user
     * @return bool
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_user::tenant');
    }

    /**
     * Usuários não podem ser replicados.
     */
    public function replicate(User $user): bool
    {
        return false;
    }

    /**
     * Usuários não podem ser reordenados.
     */
    public function reorder(User $user): bool
    {
        return false;
    }
}
