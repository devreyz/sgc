<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ServiceOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view_service_management') || $user->checkPermissionTo('view_own_service_orders');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceOrder $serviceOrder): bool
    {
        return (int) $serviceOrder->tenant_id === (int) session('tenant_id')
            && ($user->checkPermissionTo('view_service_management') || $serviceOrder->serviceProvider?->user_id === $user->id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->checkPermissionTo('create_service_order');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceOrder $serviceOrder): bool
    {
        return (int) $serviceOrder->tenant_id === (int) session('tenant_id') && $user->checkPermissionTo('edit_service_order');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceOrder $serviceOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, ServiceOrder $serviceOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, ServiceOrder $serviceOrder): bool
    {
        return $user->can('restore_service::order');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_service::order');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ServiceOrder $serviceOrder): bool
    {
        return $user->can('replicate_service::order');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_service::order');
    }
}
