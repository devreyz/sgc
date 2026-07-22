<?php

namespace Tests\Unit;

use App\Support\PortalNavigation;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalNavigationTest extends TestCase
{
    public function test_it_builds_tenant_aware_delivery_navigation(): void
    {
        $navigation = PortalNavigation::make('delivery', 'projects', 'organizacao-principal');
        $items = collect($navigation['items'])->keyBy('key');

        $this->assertSame('projects', $navigation['active']);
        $this->assertArrayNotHasKey('register', $items);
        $this->assertArrayNotHasKey('deliveries', $items);
        $this->assertSame('link', $items['projects']['type']);
        $this->assertStringEndsWith(
            '/organizacao-principal/delivery/projects',
            $items['projects']['url'],
        );
        $this->assertStringEndsWith(
            '/organizacao-principal/delivery/sheet',
            $items['printables']['url'],
        );
    }

    public function test_delivery_creation_routes_are_project_scoped(): void
    {
        $this->assertFalse(Route::has('delivery.all-deliveries'));
        $this->assertSame(
            '{tenant}/delivery/projects/{project}/register',
            Route::getRoutes()->getByName('delivery.store')->uri(),
        );
    }

    public function test_layout_navigation_component_accepts_link_and_action_arrays(): void
    {
        $view = $this->view('components.portal.nav', [
            'portal' => 'custom',
            'active' => 'home',
            'items' => [
                ['key' => 'home', 'label' => 'Inicio', 'type' => 'link', 'url' => '/inicio'],
                ['key' => 'filter', 'label' => 'Filtrar', 'type' => 'button', 'action' => 'open-filters'],
            ],
        ]);

        $view->assertSee('href="/inicio"', false);
        $view->assertSee('aria-current="page"', false);
        $view->assertSee('data-nav-event="open-filters"', false);
    }
}
