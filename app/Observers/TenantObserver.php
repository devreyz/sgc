<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Services\NotificationPreferenceDefaults;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        app(NotificationPreferenceDefaults::class)
            ->applyForTenant((int) $tenant->id, null, false);
    }
}
