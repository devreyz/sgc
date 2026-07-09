<?php

namespace App\Policies;

use App\Models\OrganizationAuthorizedEmail;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrganizationAuthorizedEmailPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $this->canUseResource($user, 'view_any');
    }

    public function view(User $user, OrganizationAuthorizedEmail $organizationAuthorizedEmail): bool
    {
        return $this->canAccessRecord($user, $organizationAuthorizedEmail)
            && $this->canUseResource($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->canUseResource($user, 'create');
    }

    public function update(User $user, OrganizationAuthorizedEmail $organizationAuthorizedEmail): bool
    {
        return $this->canAccessRecord($user, $organizationAuthorizedEmail)
            && $this->canUseResource($user, 'update');
    }

    public function delete(User $user, OrganizationAuthorizedEmail $organizationAuthorizedEmail): bool
    {
        return $this->canAccessRecord($user, $organizationAuthorizedEmail)
            && $this->canUseResource($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->canUseResource($user, 'delete_any');
    }

    public function restore(User $user, OrganizationAuthorizedEmail $organizationAuthorizedEmail): bool
    {
        return $this->canAccessRecord($user, $organizationAuthorizedEmail)
            && $this->canUseResource($user, 'restore');
    }

    public function restoreAny(User $user): bool
    {
        return $this->canUseResource($user, 'restore_any');
    }

    public function forceDelete(User $user, OrganizationAuthorizedEmail $organizationAuthorizedEmail): bool
    {
        return $this->canAccessRecord($user, $organizationAuthorizedEmail)
            && $this->canUseResource($user, 'force_delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canUseResource($user, 'force_delete_any');
    }

    public function replicate(User $user, OrganizationAuthorizedEmail $organizationAuthorizedEmail): bool
    {
        return $this->canAccessRecord($user, $organizationAuthorizedEmail)
            && $this->canUseResource($user, 'replicate');
    }

    public function reorder(User $user): bool
    {
        return $this->canUseResource($user, 'reorder');
    }

    private function canAccessRecord(User $user, OrganizationAuthorizedEmail $record): bool
    {
        return $user->isSuperAdmin()
            || (session('tenant_id') && (int) $record->tenant_id === (int) session('tenant_id'));
    }

    private function canUseResource(User $user, string $ability): bool
    {
        if ($user->isSuperAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return $user->getAllPermissions()
            ->pluck('name')
            ->contains(fn (string $permission): bool => str_starts_with($permission, $ability.'_organization_authorized_email'));
    }
}
