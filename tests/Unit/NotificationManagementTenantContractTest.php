<?php

namespace Tests\Unit;

use Tests\TestCase;

class NotificationManagementTenantContractTest extends TestCase
{
    public function test_preferences_are_loaded_and_saved_in_the_same_tenant_scope(): void
    {
        $page = file_get_contents(app_path('Filament/Pages/NotificationManagement.php'));

        self::assertStringContainsString('public int $preferencesTenantId = 0', $page);
        self::assertStringContainsString('$this->loadTenantPreferences()', $page);
        self::assertStringContainsString('[\'tenant_id\' => $tenantId, \'event_key\' => $eventKey]', $page);
        self::assertStringContainsString('currentPreferencesTenantId()', $page);
        self::assertStringContainsString('$this->preferencesTenantId === $sessionTenantId', $page);
    }
}
