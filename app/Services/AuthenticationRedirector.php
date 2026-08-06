<?php

namespace App\Services;

use App\Models\TenantUser;
use App\Models\User;

class AuthenticationRedirector
{
    public function pathFor(User $user): string
    {
        if ($user->isSuperAdmin()) {
            return route('home');
        }

        $tenantId = (int) session('tenant_id');
        if ($tenantId > 0 && TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('status', true)
            ->whereHas('tenant', fn ($query) => $query->where('active', true))
            ->exists()) {
            return route('home');
        }

        $this->clearTenantSelection();

        return route('tenant.select');
    }

    public function pathAfterLogin(User $user): string
    {
        $this->clearTenantSelection();

        if ($user->isSuperAdmin()) {
            return route('home');
        }

        return route('tenant.select');
    }

    public function clearTenantSelection(): void
    {
        session()->forget(['tenant_id', 'tenant_slug', 'url.intended']);
    }
}
