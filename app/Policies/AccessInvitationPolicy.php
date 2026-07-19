<?php

namespace App\Policies;

use App\Models\AccessInvitation;
use App\Models\Associate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantSecurityAuthorization;
use Throwable;

class AccessInvitationPolicy
{
    public function create(User $user, Associate $associate, Tenant $tenant): bool
    {
        try {
            app(TenantSecurityAuthorization::class)->authorizeAssociate(
                $user,
                $tenant->id,
                $associate->id,
                'access-links.create'
            );

            return (int) $associate->tenant_id === (int) $tenant->id;
        } catch (Throwable) {
            return false;
        }
    }

    public function view(User $user, AccessInvitation $invitation, Tenant $tenant): bool
    {
        try {
            app(TenantSecurityAuthorization::class)->authorize($user, $tenant->id, 'access-links.view');

            return (int) $invitation->tenant_id === (int) $tenant->id;
        } catch (Throwable) {
            return false;
        }
    }

    public function revoke(User $user, AccessInvitation $invitation, Tenant $tenant): bool
    {
        try {
            app(TenantSecurityAuthorization::class)->authorize($user, $tenant->id, 'access-links.revoke');

            return (int) $invitation->tenant_id === (int) $tenant->id;
        } catch (Throwable) {
            return false;
        }
    }
}
