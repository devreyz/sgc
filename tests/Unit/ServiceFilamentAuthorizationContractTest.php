<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceFilamentAuthorizationContractTest extends TestCase
{
    #[Test]
    public function shield_exposes_service_domain_permissions(): void
    {
        $this->assertTrue(config('filament-shield.entities.custom_permissions'));
    }

    #[Test]
    public function filament_uses_native_resources_backed_by_the_new_service_foundation(): void
    {
        $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
        $catalog = file_get_contents(app_path('Filament/Resources/ServiceResource/Pages/CreateService.php'));
        $versions = file_get_contents(app_path('Filament/Resources/ServiceVersionResource.php'));
        $orders = file_get_contents(app_path('Filament/Resources/ServiceOrderResource/Pages/CreateServiceOrder.php'));
        $eligibility = file_get_contents(app_path('Filament/Resources/ServiceProviderServiceResource.php'));
        $creator = file_get_contents(app_path('Services/Services/CreateServiceOrder.php'));
        $portal = file_get_contents(app_path('Http/Controllers/Provider/ServiceProviderPortalController.php'));

        $this->assertStringNotContainsString('NavigationItem::make', $provider);
        $this->assertStringContainsString('ServiceCatalogService::class', $catalog);
        $this->assertStringContainsString("checkPermissionTo('manage_service_catalog')", $versions);
        $this->assertStringContainsString('CreateServiceOrderService::class', $orders);
        $this->assertStringContainsString("checkPermissionTo('manage_service_providers')", $eligibility);
        $this->assertStringContainsString('ServiceProviderService::query()', $creator);
        $this->assertStringContainsString("where('status', true)", $creator);
        $this->assertStringContainsString("whereHas('service.serviceProviders'", $portal);
    }
}
