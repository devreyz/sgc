<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ServiceProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceProviderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage_service_providers');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceProvider $serviceProvider): bool
    {
        return (int)$serviceProvider->tenant_id === (int)session('tenant_id') && $user->checkPermissionTo('manage_service_providers');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage_service_providers');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceProvider $serviceProvider): bool
    {
        return (int)$serviceProvider->tenant_id === (int)session('tenant_id') && $user->checkPermissionTo('manage_service_providers');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceProvider $serviceProvider): bool
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
    public function forceDelete(User $user, ServiceProvider $serviceProvider): bool
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
    public function restore(User $user, ServiceProvider $serviceProvider): bool
    {
        return $user->can('restore_service::provider');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_service::provider');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, ServiceProvider $serviceProvider): bool
    {
        return $user->can('replicate_service::provider');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_service::provider');
    }
}
