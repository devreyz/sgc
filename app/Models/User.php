<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'google_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * Activity Log configuration
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Check if user can access Filament panel
     * Priority: super_admin, admin, financeiro can always access
     * Block ONLY if user has exclusively associado or service_provider roles
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->status) {
            return false;
        }

        // Super admin, admin, and financeiro always have access
        if ($this->hasAnyRole(['super_admin', 'admin', 'financeiro'])) {
            return true;
        }

        // Block if user only has portal roles (no admin access)
        return false;
    }

    /**
     * Check if user is super admin
     * This allows Filament Shield to bypass all permission checks
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Get all tenants this user belongs to.
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot('is_admin', 'roles', 'tenant_name', 'tenant_password')
            ->withTimestamps();
    }

    /**
     * Get the user's name for the current tenant context.
     * Falls back to global name if no tenant context or tenant_name not set.
     */
    public function getTenantName(?int $tenantId = null): string
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (!$tenantId) {
            return $this->name;
        }

        $pivot = $this->tenants()->where('tenant_id', $tenantId)->first()?->pivot;

        return $pivot?->tenant_name ?? $this->name;
    }

    /**
     * Accessor for display_name - returns tenant name in tenant context
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->getTenantName();
    }

    /**
     * Override name accessor to return tenant_name when in tenant context
     * (unless in super-admin panel)
     */
    public function getNameAttribute($value): string
    {
        // Check if we're in super-admin panel - use global name
        if (str_contains(request()->path(), 'super-admin')) {
            return $value;
        }

        // In tenant context, return tenant_name if available
        $tenantId = session('tenant_id');
        if ($tenantId) {
            $pivot = $this->tenants()->where('tenant_id', $tenantId)->first()?->pivot;
            if ($pivot?->tenant_name) {
                return $pivot->tenant_name;
            }
        }

        // Fallback to global name
        return $value;
    }

    /**
     * Get tenants where this user is an admin.
     */
    public function adminTenants(): BelongsToMany
    {
        return $this->tenants()->wherePivot('is_admin', true);
    }

    /**
     * Check if user is admin of a specific tenant.
     */
    public function isTenantAdmin(?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return false;
        }

        return $this->tenants()
            ->wherePivot('tenant_id', $tenantId)
            ->wherePivot('is_admin', true)
            ->exists();
    }

    /**
     * Check if user belongs to a specific tenant.
     */
    public function belongsToTenant(?int $tenantId = null): bool
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return false;
        }

        return $this->tenants()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Get current active tenant for this user.
     */
    public function currentTenant(): ?Tenant
    {
        $tenantId = session('tenant_id');

        if (! $tenantId) {
            return null;
        }

        return $this->tenants()->find($tenantId);
    }

    /**
     * Get roles for a specific tenant.
     */
    public function getRolesForTenant(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return [];
        }

        $pivot = $this->tenants()->where('tenant_id', $tenantId)->first();

        if (! $pivot) {
            return [];
        }

        $roles = $pivot->pivot->roles ?? null;

        if (is_string($roles)) {
            return json_decode($roles, true) ?? [];
        }

        return $roles ?? [];
    }

    /**
     * Check if user has a specific role in a specific tenant.
     * Override Spatie's hasRole to use tenant context.
     */
    public function hasRoleInTenant(string|array $roles, ?int $tenantId = null): bool
    {
        // Super admin has all roles globally
        if ($this->hasRole('super_admin')) {
            return true;
        }

        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return false;
        }

        $tenantRoles = $this->getRolesForTenant($tenantId);

        if (is_string($roles)) {
            return in_array($roles, $tenantRoles);
        }

        return ! empty(array_intersect($roles, $tenantRoles));
    }

    /**
     * Assign role to user for a specific tenant.
     */
    public function assignRoleToTenant(string|array $roles, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return;
        }

        $roles = is_string($roles) ? [$roles] : $roles;
        $currentRoles = $this->getRolesForTenant($tenantId);

        $updatedRoles = array_unique(array_merge($currentRoles, $roles));

        $this->tenants()->updateExistingPivot($tenantId, [
            'roles' => json_encode($updatedRoles),
        ]);
    }

    /**
     * Remove role from user for a specific tenant.
     */
    public function removeRoleFromTenant(string|array $roles, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return;
        }

        $roles = is_string($roles) ? [$roles] : $roles;
        $currentRoles = $this->getRolesForTenant($tenantId);

        $updatedRoles = array_diff($currentRoles, $roles);

        $this->tenants()->updateExistingPivot($tenantId, [
            'roles' => json_encode(array_values($updatedRoles)),
        ]);
    }

    /**
     * Sync roles for user in a specific tenant.
     */
    public function syncRolesForTenant(array $roles, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?? session('tenant_id');

        if (! $tenantId) {
            return;
        }

        $this->tenants()->updateExistingPivot($tenantId, [
            'roles' => json_encode($roles),
        ]);
    }

    /**
     * Check if user can access any tenant.
     */
    public function hasAnyTenant(): bool
    {
        // Super admin can access any tenant
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->tenants()->exists();
    }

    /**
     * Get the associate profile for the user.
     */
    public function associate(): HasOne
    {
        return $this->hasOne(Associate::class);
    }

    /**
     * Get the service provider profile for the user.
     */
    public function serviceProvider(): HasOne
    {
        return $this->hasOne(ServiceProvider::class);
    }

    /**
     * Check if user is an associate
     */
    public function isAssociate(): bool
    {
        return $this->associate !== null;
    }

    /**
     * Check if user is a service provider
     */
    public function isServiceProvider(): bool
    {
        return $this->serviceProvider !== null;
    }

    /**
     * Check if user has both associate and provider profiles
     */
    public function hasSharedWallet(): bool
    {
        return $this->isAssociate() && $this->isServiceProvider();
    }

    /**
     * Get the ledger entries for the user's associate profile.
     */
    public function ledgerEntries(): HasManyThrough
    {
        return $this->hasManyThrough(
            AssociateLedger::class,
            Associate::class,
            'user_id',
            'associate_id'
        );
    }

    /**
     * Get expenses created by this user
     */
    public function createdExpenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /**
     * Get service orders where this user is the operator
     */
    public function operatedServiceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class, 'operator_id');
    }
}
