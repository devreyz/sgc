<?php

namespace App\Services;

use App\Models\NotificationEventPreference;
use App\Support\NotificationEventCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationPreferenceDefaults
{
    public function applyForTenant(int $tenantId, ?int $updatedBy = null, bool $overwrite = true): int
    {
        if ($tenantId <= 0 || ! Schema::hasTable('notification_event_preferences')) {
            return 0;
        }

        return DB::transaction(function () use ($tenantId, $updatedBy, $overwrite): int {
            $applied = 0;
            foreach (NotificationEventCatalog::all() as $eventKey => $definition) {
                $values = [
                    'database_enabled' => (bool) $definition['databaseDefault'],
                    'push_enabled' => (bool) ($definition['pushAllowed'] && $definition['pushDefault']),
                    'priority' => $definition['priority'],
                    'recipient_roles' => $definition['roles'],
                    'updated_by' => $updatedBy,
                ];

                if ($overwrite) {
                    NotificationEventPreference::query()->updateOrCreate(
                        ['tenant_id' => $tenantId, 'event_key' => $eventKey],
                        $values,
                    );
                    $applied++;

                    continue;
                }

                $preference = NotificationEventPreference::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'event_key' => $eventKey],
                    $values,
                );
                if ($preference->wasRecentlyCreated) {
                    $applied++;
                }
            }

            return $applied;
        });
    }
}
