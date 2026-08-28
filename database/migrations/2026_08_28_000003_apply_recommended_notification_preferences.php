<?php

use App\Support\NotificationEventCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants') || ! Schema::hasTable('notification_event_preferences')) {
            return;
        }

        DB::table('tenants')->whereNull('deleted_at')->select('id')->orderBy('id')->each(function (object $tenant): void {
            foreach (NotificationEventCatalog::all() as $eventKey => $definition) {
                DB::table('notification_event_preferences')->updateOrInsert(
                    ['tenant_id' => $tenant->id, 'event_key' => $eventKey],
                    [
                        'database_enabled' => (bool) $definition['databaseDefault'],
                        'push_enabled' => (bool) ($definition['pushAllowed'] && $definition['pushDefault']),
                        'priority' => $definition['priority'],
                        'recipient_roles' => json_encode($definition['roles'], JSON_UNESCAPED_UNICODE),
                        'updated_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        });
    }

    public function down(): void
    {
        // Preferências podem ter sido personalizadas após a implantação e não devem ser apagadas.
    }
};
