<?php

namespace Tests\Unit;

use Tests\TestCase;

class ServiceProviderPortalManagementTest extends TestCase
{
    public function test_provider_management_routes_are_protected_and_rate_limited(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        foreach (['services.providers.index', 'services.providers.create', 'services.providers.store', 'services.providers.edit', 'services.providers.update'] as $name) {
            $this->assertContains('auth', $routes[$name]->gatherMiddleware());
            $this->assertStringContainsString('services-management/providers', $routes[$name]->uri());
        }
        $this->assertContains('throttle:20,1', $routes['services.providers.store']->gatherMiddleware());
        $this->assertContains('throttle:20,1', $routes['services.providers.update']->gatherMiddleware());
    }

    public function test_controller_is_tenant_scoped_and_reuses_provider_eligibility(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Services/ServiceProviderManagementController.php'));

        $this->assertStringContainsString("checkPermissionTo('manage_service_providers')", $controller);
        $this->assertStringContainsString("where('tenant_id', \$tenant->id)", $controller);
        $this->assertStringContainsString('ServiceProviderService::withoutGlobalScopes()', $controller);
        $this->assertStringContainsString("->whereNotIn('service_id', \$serviceIds)->update(['status' => false", $controller);
        $this->assertStringNotContainsString('provider_hourly_rate', $controller);
        $this->assertStringNotContainsString('provider_daily_rate', $controller);
    }

    public function test_portal_explains_the_boundary_between_eligibility_and_pricing(): void
    {
        $form = file_get_contents(resource_path('views/services/providers-form.blade.php'));
        $navigation = file_get_contents(app_path('Support/PortalNavigation.php'));

        $this->assertStringContainsString('Serviços habilitados', $form);
        $this->assertStringContainsString('Valores e fórmulas de remuneração', $form);
        $this->assertStringContainsString('await fetch(', $form);
        $this->assertStringContainsString("'providers', 'label' => 'Prestadores'", $navigation);
    }
}
