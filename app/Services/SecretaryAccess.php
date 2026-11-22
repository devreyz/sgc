<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

class SecretaryAccess
{
    public const PERMISSIONS = [
        'view' => 'view_secretary_portal',
        'create' => 'create_secretary_documents',
        'edit' => 'edit_secretary_documents',
        'issue' => 'issue_secretary_documents',
        'cancel' => 'cancel_secretary_documents',
        'templates' => 'manage_secretary_templates',
    ];

    public function allows(User $user, Tenant $tenant, string $action = 'view'): bool
    {
        if ((int) session('tenant_id') !== (int) $tenant->id ||
            ! $user->tenants()->where('tenants.id', $tenant->id)->wherePivot('status', true)->exists()) {
            return false;
        }

        return $user->hasRoleInTenant('admin', $tenant->id)
            || $user->checkPermissionTo(self::PERMISSIONS[$action], 'web');
    }
}
