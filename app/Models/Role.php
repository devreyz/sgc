<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'guard_name',
        'team_id',
    ];

    /**
     * Override BelongsToTenant boot to allow NULL tenant_id for global roles
     */
    protected static function bootBelongsToTenant(): void
    {
        // Add global scope to automatically filter by tenant
        static::addGlobalScope(new \App\Scopes\TenantScope);

        // Automatically set tenant_id on create (optional for roles)
        static::creating(function ($model) {
            // Skip if tenant_id is already explicitly set or null (global role)
            if (isset($model->tenant_id)) {
                return;
            }

            // Get current tenant from container (optional for roles)
            $tenantId = app('tenant.id');

            // Roles can be global (tenant_id = null) or tenant-specific
            if ($tenantId) {
                $model->tenant_id = $tenantId;
            }
            // If no tenant, leave as null (global role)
        });
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // When querying roles, always scope by tenant
        static::addGlobalScope('tenant', function ($builder) {
            if (!auth()->check()) {
                return;
            }

            $user = auth()->user();

            // Super admin sees all roles
            if ($user->is_super_admin) {
                return;
            }

            // Regular users see only their tenant's roles
            $tenantId = session('tenant_id');
            if ($tenantId) {
                $builder->where('roles.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Find role by name for current tenant
     */
    public static function findByName(string $name, $guardName = null): self
    {
        $guardName = $guardName ?? config('auth.defaults.guard');
        $tenantId = session('tenant_id');

        return static::where('name', $name)
            ->where('guard_name', $guardName)
            ->where('roles.tenant_id', $tenantId)
            ->firstOrFail();
    }

    /**
     * Create a role for a specific tenant
     */
    public static function createForTenant(string $name, int $tenantId, $guardName = null): self
    {
        $guardName = $guardName ?? config('auth.defaults.guard');

        return static::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'guard_name' => $guardName,
        ]);
    }
}
