<?php

namespace Tests\Unit;

use Tests\TestCase;

class DeliveryDistributionPreferenceContractTest extends TestCase
{
    public function test_distribution_customer_fields_are_persisted_until_explicit_restore(): void
    {
        $view = file_get_contents(resource_path('views/components/delivery/dist-modal.blade.php'));

        self::assertStringContainsString('sgc.dist-modal.customers.', $view);
        self::assertStringContainsString('persistCustomerState()', $view);
        self::assertStringContainsString('window.localStorage?.setItem', $view);
        self::assertStringContainsString('window.localStorage?.removeItem', $view);
        self::assertStringContainsString('restoreDefaultCustomers()', $view);
        self::assertStringNotContainsString('_customerStates.delete(_customerStateKey)', $view);
    }

    public function test_both_delivery_panels_expose_the_partial_adjustment_flow(): void
    {
        $register = file_get_contents(resource_path('views/delivery/register.blade.php'));
        $project = file_get_contents(resource_path('views/delivery/project-deliveries.blade.php'));
        $routes = file_get_contents(base_path('routes/web.php'));

        foreach ([$register, $project] as $view) {
            self::assertStringContainsString('quantity-adjustment-modal', $view);
            self::assertStringContainsString('btn-return-delivery', $view);
            self::assertStringContainsString('delivery-quantity-adjusted', $view);
        }
        self::assertStringContainsString("/deliveries/{delivery}/return", $routes);
    }
}
