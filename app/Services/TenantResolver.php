<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class TenantResolver
{
    /**
     * Resolve current tenant ID
     */
    public function resolve(): ?int
    {
        // 1. Check session first (highest priority)
        if ($tenantId = Session::get('tenant_id')) {
            return $this->validateTenant($tenantId);
        }

        // 2. Try to resolve from authenticated user
        if ($tenantId = $this->resolveFromUser()) {
            return $tenantId;
        }

        return null;
    }

    /**
     * Resolve tenant from authenticated user
     */
    protected function resolveFromUser(): ?int
    {
        if (!auth()->check()) {
            return null;
        }

        $user = auth()->user();

        // Super admin can access without tenant in some contexts
        if ($this->isSuperAdmin($user)) {
            return Session::get('tenant_id');
        }

        // Get user's tenants
        $tenants = $user->tenants;

        if ($tenants->isEmpty()) {
            return null;
        }

        // If user has only one tenant, use it automatically
        if ($tenants->count() === 1) {
            $tenantId = $tenants->first()->id;
            $this->setTenant($tenantId);
            return $tenantId;
        }

        // If multiple tenants, require explicit selection
        return null;
    }

    /**
     * Validate if tenant exists and user has access
     */
    protected function validateTenant(?int $tenantId): ?int
    {
        if (!$tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            Session::forget('tenant_id');
            return null;
        }

        // Super admin can access any tenant
        if (auth()->check() && $this->isSuperAdmin(auth()->user())) {
            return $tenantId;
        }

        // Check if user belongs to this tenant
        if (auth()->check() && !$tenant->hasUser(auth()->user())) {
            Session::forget('tenant_id');
            return null;
        }

        return $tenantId;
    }

    /**
     * Set the current tenant
     */
    public function setTenant(?int $tenantId): void
    {
        if ($tenantId) {
            Session::put('tenant_id', $tenantId);
            app()->instance('tenant.id', $tenantId);
        } else {
            Session::forget('tenant_id');
            app()->forgetInstance('tenant.id');
        }
    }

    /**
     * Get current tenant model
     */
    public function current(): ?Tenant
    {
        $tenantId = $this->resolve();

        if (!$tenantId) {
            return null;
        }

        return Tenant::find($tenantId);
    }

    /**
     * Check if user is super admin
     */
    protected function isSuperAdmin(User $user): bool
    {
        // Check flag
        if (isset($user->is_super_admin) && $user->is_super_admin) {
            return true;
        }

        // Check role
        // Avoid Role global scope which may introduce ambiguous tenant_id joins.
        // Query the user's roles directly without Role model global scopes.
        if (method_exists($user, 'roles')) {
            try {
                if ($user->roles()->withoutGlobalScopes()->where('roles.name', 'super_admin')->exists()) {
                    return true;
                }
            } catch (\Exception $e) {
                // Fallback to hasRole if something unexpected happens
                if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all tenants available to current user
     */
    public function availableTenants(): \Illuminate\Support\Collection
    {
        if (!auth()->check()) {
            return collect();
        }

        $user = auth()->user();

        // Super admin sees all tenants
        if ($this->isSuperAdmin($user)) {
            return Tenant::all();
        }

        // Regular users see only their tenants
        return $user->tenants;
    }
}
