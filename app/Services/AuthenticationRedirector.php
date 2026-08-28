<?php

namespace App\Services;

use App\Models\TenantUser;
use App\Models\Tenant;
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
        $notificationTarget = $this->safeNotificationTarget($user);
        $this->clearTenantSelection();

        if ($notificationTarget !== null) {
            return $notificationTarget;
        }

        if ($user->isSuperAdmin()) {
            return route('home');
        }

        return route('tenant.select');
    }

    private function safeNotificationTarget(User $user): ?string
    {
        $intended = (string) session('url.intended', '');
        $path = parse_url($intended, PHP_URL_PATH);
        if (! is_string($path)
            || preg_match('#^/([^/]+)/notifications/([0-9a-f-]{36})/open$#i', $path, $matches) !== 1) {
            return null;
        }

        $tenant = Tenant::query()->where('slug', $matches[1])->where('active', true)->first();
        if (! $tenant || (! $user->isSuperAdmin() && ! TenantUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', true)
            ->exists())) {
            return null;
        }

        return $path;
    }

    public function clearTenantSelection(): void
    {
        session()->forget(['tenant_id', 'tenant_slug', 'url.intended']);
    }
}
