<?php

namespace Tests\Unit;

use App\Support\PortalNavigation;
use Tests\TestCase;

class PortalNavigationTest extends TestCase
{
    public function test_it_builds_tenant_aware_delivery_navigation(): void
    {
        $navigation = PortalNavigation::make('delivery', 'register', 'organizacao-principal');
        $items = collect($navigation['items'])->keyBy('key');

        $this->assertSame('register', $navigation['active']);
        $this->assertSame('link', $items['register']['type']);
        $this->assertStringEndsWith(
            '/organizacao-principal/delivery/register',
            $items['register']['url'],
        );
        $this->assertStringEndsWith(
            '/organizacao-principal/delivery/sheet',
            $items['sheets']['url'],
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
