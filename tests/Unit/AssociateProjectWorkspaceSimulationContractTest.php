<?php

namespace Tests\Unit;

use Tests\TestCase;

class AssociateProjectWorkspaceSimulationContractTest extends TestCase
{
    public function test_workspace_has_frontend_price_and_simulation_sections(): void
    {
        $view = file_get_contents(resource_path('views/associate/project-workspace.blade.php'));

        self::assertStringContainsString('data-section="prices"', $view);
        self::assertStringContainsString('data-section="simulator"', $view);
        self::assertStringContainsString('function awPrices(data)', $view);
        self::assertStringContainsString('function awSimulator(data)', $view);
        self::assertStringContainsString('function awSimQuantity(productId, value)', $view);
        self::assertStringContainsString('simulation-picker-list', $view);
        self::assertStringContainsString('Produtos liberados para entrega', $view);
        self::assertStringContainsString('Outros produtos para simular', $view);
        self::assertStringNotContainsString('const candidates = configured.length', $view);
    }

    public function test_workspace_endpoints_are_read_only_and_catalog_is_bounded(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Associate/AssociateProjectPortalController.php'));
        $route = app('router')->getRoutes()->getByName('associate.projects.data');

        self::assertNotNull($route);
        self::assertSame(['GET', 'HEAD'], $route->methods());
        self::assertStringContainsString("'prices' => response()->json", $controller);
        self::assertStringContainsString("'simulator' => response()->json", $controller);
        self::assertStringContainsString('->take(250)', $controller);
        self::assertStringContainsString("->where('tenant_id', \$project->tenant_id)", $controller);
        self::assertStringContainsString("'delivery_enabled' => \$deliveryEnabled", $controller);
        self::assertStringContainsString("'delivery_enabled_total'", $controller);
    }
}
