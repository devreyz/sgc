<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait
     */
    protected static function bootBelongsToTenant(): void
    {
        // Add global scope to automatically filter by tenant
        static::addGlobalScope(new TenantScope);

        // Automatically set tenant_id on create
        static::creating(function ($model) {
            // Skip if tenant_id is already set (for seeding, migrations, etc)
            if ($model->tenant_id) {
                return;
            }

            // Get current tenant from container
            $tenantId = app('tenant.id');

            if (!$tenantId) {
                throw new \Exception('No active tenant. Cannot create ' . get_class($model));
            }

            $model->tenant_id = $tenantId;
        });
    }

    /**
     * Relationship to tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to a specific tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where($this->getQualifiedTenantColumn(), $tenantId);
    }

    /**
     * Get the fully qualified tenant column name
     */
    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable() . '.tenant_id';
    }
}
