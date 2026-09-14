<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SuperAdminHubContractTest extends TestCase
{
    #[Test]
    public function global_super_admin_hub_always_receives_nullable_tenant_context(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/HubController.php'));
        $view = file_get_contents(resource_path('views/hub.blade.php'));

        $this->assertStringContainsString("'currentTenant' => null", $controller);
        $this->assertStringContainsString("'unreadNotifications' => 0", $controller);
        $this->assertStringContainsString('$user->isSuperAdmin()', $controller);
        $this->assertStringContainsString('$currentTenant = $currentTenant ?? null;', $view);
        $this->assertStringContainsString('$isSystemContext = $hasSuperAdmin && ! $currentTenant;', $view);
    }
}
