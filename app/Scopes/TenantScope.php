<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Get current tenant from container
        $tenantId = app('tenant.id');

        // CRITICAL: Always require tenant_id to prevent data leaks
        // Even super admins must select a tenant to view data
        if ($tenantId) {
            $builder->where($model->getQualifiedTenantColumn(), $tenantId);
        } else {
            // If no tenant selected, return no results (prevents data leak)
            // This forces tenant selection before viewing any data
            $builder->whereRaw('1 = 0');
        }
    }

    /**
     * Check if current user is super admin
     */
    protected function isSuperAdmin(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $user = auth()->user();

        // Check if user has is_super_admin flag
        if (isset($user->is_super_admin) && $user->is_super_admin) {
            return true;
        }

        // Fallback to role check
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return false;
    }
}
