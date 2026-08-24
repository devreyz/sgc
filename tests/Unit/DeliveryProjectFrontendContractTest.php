<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeliveryProjectFrontendContractTest extends TestCase
{
    public function test_project_delivery_data_route_is_project_scoped_and_read_only(): void
    {
        $route = app('router')->getRoutes()->getByName('delivery.projects.deliveries-data');

        self::assertNotNull($route);
        self::assertSame('{tenant}/delivery/projects/{project}/deliveries-data', $route->uri());
        self::assertSame(['GET', 'HEAD'], $route->methods());
    }

    public function test_project_delivery_view_is_a_frontend_rendered_shell(): void
    {
        $view = file_get_contents(resource_path('views/delivery/project-deliveries.blade.php'));

        self::assertStringContainsString('id="pd-delivery-skeleton"', $view);
        self::assertStringContainsString('function loadDeliveryPage', $view);
        self::assertStringContainsString('/deliveries-data?', $view);
        self::assertStringNotContainsString('/fragment', $view);
        self::assertStringNotContainsString('@foreach($deliveries', $view);
        self::assertStringNotContainsString('@if($deliveries->isEmpty())', $view);
    }

    public function test_register_uses_the_same_delivery_card_component_styles(): void
    {
        $view = file_get_contents(resource_path('views/delivery/register.blade.php'));

        self::assertStringContainsString("@include('delivery.partials.project-delivery-mobile-card'", $view);
        self::assertStringContainsString('mobile-card delivery-card-v2', $view);
        self::assertStringContainsString('dc-meter dc-distribution', $view);
    }
}
