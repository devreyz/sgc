<?php

namespace App\Policies\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait TenantAwarePolicy
{
    /**
     * Check if user can access the model based on tenant.
     * Super admins bypass this check.
     */
    protected function canAccessTenant(User $user, ?Model $model = null): bool
    {
        // Super admin can access all tenants
        if ($user->isSuperAdmin()) {
            return true;
        }

        // If no model provided, just check if user has a tenant
        if (!$model) {
            return session('tenant_id') !== null;
        }

        // Check if model belongs to current tenant
        if (!method_exists($model, 'getAttribute') || !$model->getAttribute('tenant_id')) {
            return true; // Model doesn't have tenant_id, allow access
        }

        $currentTenantId = session('tenant_id');
        $modelTenantId = $model->getAttribute('tenant_id');

        return $currentTenantId && $currentTenantId == $modelTenantId;
    }

    /**
     * Perform pre-authorization checks for all policy methods.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Super admin bypasses all checks
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Require valid tenant for all operations (except for super admin panel)
        if (!session('tenant_id') && !request()->is('super-admin*')) {
            return false;
        }

        return null;
    }

    /**
     * Override view policy to include tenant check.
     */
    public function view(User $user, Model $model): bool
    {
        return $this->canAccessTenant($user, $model) && 
               ($this->baseView($user, $model) ?? true);
    }

    /**
     * Override update policy to include tenant check.
     */
    public function update(User $user, Model $model): bool
    {
        return $this->canAccessTenant($user, $model) && 
               ($this->baseUpdate($user, $model) ?? true);
    }

    /**
     * Override delete policy to include tenant check.
     */
    public function delete(User $user, Model $model): bool
    {
        return $this->canAccessTenant($user, $model) && 
               ($this->baseDelete($user, $model) ?? true);
    }

    /**
     * Override restore policy to include tenant check.
     */
    public function restore(User $user, Model $model): bool
    {
        return $this->canAccessTenant($user, $model) && 
               ($this->baseRestore($user, $model) ?? true);
    }

    /**
     * Override forceDelete policy to include tenant check.
     */
    public function forceDelete(User $user, Model $model): bool
    {
        return $this->canAccessTenant($user, $model) && 
               ($this->baseForceDelete($user, $model) ?? true);
    }

    /**
     * Base view permission (can be overridden by policies).
     */
    protected function baseView(User $user, Model $model): ?bool
    {
        return null; // Let Filament Shield handle this
    }

    /**
     * Base update permission (can be overridden by policies).
     */
    protected function baseUpdate(User $user, Model $model): ?bool
    {
        return null; // Let Filament Shield handle this
    }

    /**
     * Base delete permission (can be overridden by policies).
     */
    protected function baseDelete(User $user, Model $model): ?bool
    {
        return null; // Let Filament Shield handle this
    }

    /**
     * Base restore permission (can be overridden by policies).
     */
    protected function baseRestore(User $user, Model $model): ?bool
    {
        return null; // Let Filament Shield handle this
    }

    /**
     * Base forceDelete permission (can be overridden by policies).
     */
    protected function baseForceDelete(User $user, Model $model): ?bool
    {
        return null; // Let Filament Shield handle this
    }
}
