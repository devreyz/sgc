<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TenantResolver
{
    /**
     * Resolve the current tenant ID.
     * Priority:
     * 1. Session (tenant_id)
     * 2. null when the user has not explicitly selected a tenant
     */
    public function resolve(): ?int
    {
        // Super admin: allow access without tenant restriction
        if (Auth::check() && Auth::user()->isSuperAdmin()) {
            return session('tenant_id');
        }

        // Check session first
        $tenantId = session('tenant_id');

        if ($tenantId && $this->validateTenant($tenantId)) {
            return $tenantId;
        }

        return null;
    }

    /**
     * Validate if tenant exists and is active.
     */
    public function validateTenant(int $tenantId): bool
    {
        return Cache::remember("tenant.{$tenantId}.active", 300, function () use ($tenantId) {
            return Tenant::where('id', $tenantId)
                ->where('active', true)
                ->exists();
        });
    }

    /**
     * Set the current tenant.
     */
    public function setTenant(int $tenantId): void
    {
        // Validate tenant
        if (! $this->validateTenant($tenantId)) {
            throw new \Exception('Tenant inválido ou inativo.');
        }

        // Validate user has access
        if (Auth::check() && ! Auth::user()->isSuperAdmin()) {
            $user = Auth::user();

            if (! $user->belongsToTenant($tenantId)) {
                throw new \Exception('Você não tem acesso a esta organização.');
            }
        }

        $tenant = Tenant::query()->findOrFail($tenantId);
        session([
            'tenant_id' => $tenantId,
            'tenant_slug' => $tenant->slug,
        ]);
    }

    /**
     * Clear the current tenant.
     */
    public function clearTenant(): void
    {
        session()->forget(['tenant_id', 'tenant_slug']);
    }

    /**
     * Get current tenant instance.
     */
    public function current(): ?Tenant
    {
        $tenantId = $this->resolve();

        if (! $tenantId) {
            return null;
        }

        return Cache::remember("tenant.{$tenantId}", 300, function () use ($tenantId) {
            return Tenant::find($tenantId);
        });
    }

    /**
     * Check if user has access to tenant.
     */
    public function userHasAccess(User $user, int $tenantId): bool
    {
        // Super admin has access to all tenants
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenantId);
    }

    /**
     * Get available tenants for current user.
     */
    public function getAvailableTenants(): array
    {
        return $this->getAvailableTenantModels()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ])
            ->all();
    }

    /** @return Collection<int, Tenant> */
    public function getAvailableTenantModels(): Collection
    {
        if (! Auth::check()) {
            return collect();
        }

        $user = Auth::user();

        // Super admin can see all tenants
        if ($user->isSuperAdmin()) {
            return Tenant::active()
                ->orderBy('name')
                ->get();
        }

        // Regular users see only their tenants
        return $user->tenants()
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Return only an explicitly selected and still authorized tenant.
     */
    public function autoSelectTenant(): ?int
    {
        if (! Auth::check()) {
            return null;
        }

        $user = Auth::user();

        // Super admin: don't auto-select
        if ($user->isSuperAdmin()) {
            return session('tenant_id');
        }

        // Check if already has tenant in session
        $currentTenantId = session('tenant_id');
        if ($currentTenantId && $this->userHasAccess($user, $currentTenantId)) {
            return $currentTenantId;
        }

        $this->clearTenant();

        return null;
    }
}
