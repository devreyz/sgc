<?php

namespace App\Services;

use App\Models\TenantUser;
use App\Models\User;

class AuthenticationRedirector
{
    public function pathFor(User $user): string
    {
        session()->forget(['tenant_id', 'tenant_slug']);

        if ($user->isSuperAdmin()) {
            return route('home');
        }

        $memberships = TenantUser::query()
            ->where('user_id', $user->id)
            ->where('status', true)
            ->whereHas('tenant', fn ($query) => $query->where('active', true))
            ->with('tenant:id,slug')
            ->get();

        if ($memberships->count() !== 1) {
            return route('tenant.select');
        }

        $membership = $memberships->first();
        session([
            'tenant_id' => $membership->tenant_id,
            'tenant_slug' => $membership->tenant?->slug,
        ]);

        return route('home');
    }
}
