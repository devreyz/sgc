<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;

class DrivePortalAccess
{
    public function level(User $user, Tenant $tenant): ?string
    {
        if ((int) session('tenant_id') !== (int) $tenant->id) {
            return null;
        }

        $membership = TenantUser::query()->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)->where('status', true)->first();
        if (! $membership) {
            return null;
        }

        if ($membership->is_admin || in_array('admin', $membership->roles ?? [], true)) {
            return 'manage';
        }

        if ($user->checkPermissionTo('manage_drive_portal')) {
            return 'manage';
        }

        return $user->checkPermissionTo('view_drive_portal') ? 'view' : null;
    }
}
