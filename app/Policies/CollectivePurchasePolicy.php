<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CollectivePurchase;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollectivePurchasePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_collective::purchase');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CollectivePurchase $collectivePurchase): bool
    {
        return $user->can('view_collective::purchase');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_collective::purchase');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CollectivePurchase $collectivePurchase): bool
    {
        return $user->can('update_collective::purchase');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CollectivePurchase $collectivePurchase): bool
    {
        return $user->can('delete_collective::purchase');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_collective::purchase');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, CollectivePurchase $collectivePurchase): bool
    {
        return $user->can('force_delete_collective::purchase');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_collective::purchase');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, CollectivePurchase $collectivePurchase): bool
    {
        return $user->can('restore_collective::purchase');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_collective::purchase');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, CollectivePurchase $collectivePurchase): bool
    {
        return $user->can('replicate_collective::purchase');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_collective::purchase');
    }
}
