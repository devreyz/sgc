<?php

namespace Tests\Unit;

use Tests\TestCase;

class AssociateProjectWorkspaceSimulationContractTest extends TestCase
{
    public function test_workspace_links_to_the_dedicated_simulator_page(): void
    {
        $view = file_get_contents(resource_path('views/associate/project-workspace.blade.php'));
        $recorderView = file_get_contents(resource_path('views/delivery/associate-project.blade.php'));
        $simulator = file_get_contents(resource_path('views/associate/project-simulator.blade.php'));

        self::assertStringContainsString('data-section="prices"', $view);
        self::assertStringNotContainsString('<span>Simular</span>', $view);
        self::assertStringContainsString("route('associate.projects.simulator'", $view);
        self::assertStringContainsString("route('delivery.projects.associates.simulator'", $recorderView);
        self::assertStringContainsString('Simular entregas', $recorderView);
        self::assertStringContainsString('function awPrices(data)', $view);
        self::assertStringContainsString('data-step-panel="0"', $simulator);
        self::assertStringContainsString('data-step-panel="2"', $simulator);
        self::assertStringContainsString("productFilter:'enabled'", $simulator);
        self::assertStringContainsString("cache:'no-store'", $simulator);
        self::assertStringContainsString('localStorage.setItem(historyKey', $simulator);
        self::assertStringContainsString('window.history.pushState', $simulator);
        self::assertStringContainsString('const scale = 2;', $simulator);
        self::assertStringContainsString("},'image/png');", $simulator);
        self::assertStringContainsString('const ASSOCIATE_NAME = @json($associate->display_name);', $simulator);
        self::assertStringContainsString('COTAS DE PRODUTOS PARA ENTREGA', $simulator);
        self::assertStringContainsString('PRODUTO | COTA PARA ENTREGA', $simulator);
        self::assertStringContainsString('Enviar como imagem', $simulator);
        self::assertStringContainsString('Enviar como texto', $simulator);
        self::assertStringContainsString('https://wa.me/?text=', $simulator);
        self::assertStringNotContainsString('state.selected.set(productId', strstr($simulator, 'async function load()'));
    }

    public function test_workspace_financial_summary_explains_net_delivery_value_without_percentage_copy(): void
    {
        $view = file_get_contents(resource_path('views/associate/project-workspace.blade.php'));

        self::assertStringContainsString('Valor líquido das entregas', $view);
        self::assertStringContainsString('Cota usada:', $view);
        self::assertStringContainsString('Cota total:', $view);
        self::assertStringContainsString('Entregas por destino', $view);
        self::assertStringContainsString('fee_breakdown', $view);
        self::assertStringContainsString('Detalhamento das taxas e ajustes', $view);
        self::assertStringNotContainsString('Valor disponível para entregar', $view);
        self::assertStringNotContainsString('% de ${limit} já foi utilizado.', $view);
    }

    public function test_workspace_endpoints_are_read_only_and_catalog_is_bounded(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Associate/AssociateProjectPortalController.php'));
        $route = app('router')->getRoutes()->getByName('associate.projects.data');
        $pageRoute = app('router')->getRoutes()->getByName('associate.projects.simulator');
        $recorderRoute = app('router')->getRoutes()->getByName('delivery.projects.associates.simulator');
        $recorderDataRoute = app('router')->getRoutes()->getByName('delivery.projects.associates.simulator.data');

        self::assertNotNull($route);
        self::assertNotNull($pageRoute);
        self::assertNotNull($recorderRoute);
        self::assertNotNull($recorderDataRoute);
        self::assertSame(['GET', 'HEAD'], $route->methods());
        self::assertSame(['GET', 'HEAD'], $pageRoute->methods());
        self::assertSame(['GET', 'HEAD'], $recorderRoute->methods());
        self::assertSame(['GET', 'HEAD'], $recorderDataRoute->methods());
        self::assertStringContainsString("'prices' => response()->json", $controller);
        self::assertStringContainsString("'simulator' => response()->json", $controller);
        self::assertStringContainsString('->take(250)', $controller);
        self::assertStringContainsString("->where('tenant_id', \$project->tenant_id)", $controller);
        self::assertStringContainsString("'delivery_enabled' => \$deliveryEnabled", $controller);
        self::assertStringContainsString("'delivery_enabled_total'", $controller);
        self::assertStringContainsString('$configuredProductIds = $eligible->keys()', $controller);
        self::assertStringContainsString('[$project, $associate] = $this->context($request);', $controller);
    }

    public function test_mobile_bottom_navigation_requires_an_active_item(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/bento.blade.php'));

        self::assertStringContainsString('$hasActiveBentoNavigation', $layout);
        self::assertStringContainsString('.app-nav-layer.has-no-active-item', $layout);
        self::assertStringContainsString('body.has-active-app-nav .bento-container', $layout);
    }
}
