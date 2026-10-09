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

    public function test_register_calculates_product_substitution_with_existing_customer_prices(): void
    {
        $view = file_get_contents(resource_path('views/delivery/register.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Delivery/DeliveryRegistrationController.php'));
        $priceRoute = app('router')->getRoutes()->getByName('delivery.sheet.products');

        self::assertNotNull($priceRoute);
        self::assertSame(['GET', 'HEAD'], $priceRoute->methods());
        self::assertStringContainsString('id="product-substitution-trigger"', $view);
        self::assertStringContainsString('id="modal-product-substitution"', $view);
        self::assertStringContainsString('id="product-substitution-advanced"', $view);
        self::assertStringContainsString('id="product-substitution-actual-customer"', $view);
        self::assertStringContainsString("'/delivery/sheet/products/' + customerId", $view);
        self::assertStringContainsString('PRODUCT_SUBSTITUTION.targetProducts.find', $view);
        self::assertStringContainsString('PRODUCT_SUBSTITUTION.actualProducts.find', $view);
        self::assertStringContainsString('actualCustomerId === targetCustomerId', $view);
        $registerMethod = strpos($controller, 'public function register(Request $request)');
        $referenceDefinition = strpos($controller, '$customerPriceReferences = $customers->map', $registerMethod);
        $registerReturn = strpos($controller, "return view('delivery.register'", $registerMethod);
        self::assertNotFalse($referenceDefinition);
        self::assertLessThan($registerReturn, $referenceDefinition);
        self::assertStringContainsString('const rawTargetQuantity = actualTotal / target.price;', $view);
        self::assertStringContainsString('const targetQuantity = Number(rawTargetQuantity.toFixed(3));', $view);
        self::assertStringContainsString('Substituição: entregue ${productSubstitutionQuantity', $view);
        self::assertStringContainsString(' no lugar de ${productSubstitutionQuantity(calculation.targetQuantity)', $view);
        self::assertStringContainsString('valor equivalente ${money(calculation.actualTotal)}.', $view);
        self::assertStringContainsString("notes             : $('f-notes').value.trim() || null", $view);
        self::assertStringNotContainsString('substitution_product_id', $view);
    }

    public function test_delivery_notes_remain_visible_after_approval_and_safe_during_editing(): void
    {
        $registerView = file_get_contents(resource_path('views/delivery/register.blade.php'));
        $listingView = file_get_contents(resource_path('views/delivery/project-deliveries.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Delivery/DeliveryRegistrationController.php'));

        self::assertLessThan(
            strpos($registerView, 'if (isPending)', strpos($registerView, 'function buildActionsHtml')),
            strpos($registerView, 'let buttons = item.notes', strpos($registerView, 'function buildActionsHtml')),
        );
        self::assertLessThan(
            strpos($listingView, "if (item.status_value === 'pending')", strpos($listingView, 'function pdActions')),
            strpos($listingView, 'let html = item.notes', strpos($listingView, 'function pdActions')),
        );
        self::assertStringContainsString("const notes = rowEl?.querySelector('.delivery-note-trigger')?.outerHTML || '';", $listingView);
        self::assertStringContainsString("const notes = cardEl?.querySelector('.delivery-note-trigger')?.outerHTML || '';", $listingView);
        self::assertStringContainsString('notes: item.notes || null', $registerView);
        self::assertStringContainsString("'notes' => array_key_exists('notes', \$validated)", $controller);
        self::assertStringContainsString(': $delivery->notes', $controller);
    }

    public function test_integrity_center_refreshes_from_the_server_after_mutations(): void
    {
        $view = file_get_contents(resource_path('views/delivery/project-deliveries.blade.php'));

        self::assertStringContainsString('async function refreshIntegrityCenter()', $view);
        self::assertStringContainsString('await syncDeliveryAfterMutation(deliveryId', $view);
        self::assertStringContainsString('refreshIntegrityCenter(),', $view);
        self::assertStringContainsString("cache: 'no-store'", $view);
    }

    public function test_delivery_deletions_use_server_bound_confirmation_and_native_passkeys(): void
    {
        $view = file_get_contents(resource_path('views/delivery/project-deliveries.blade.php'));
        $modal = file_get_contents(resource_path('views/components/delivery/dist-modal.blade.php'));
        $service = file_get_contents(app_path('Services/DestructiveActionConfirmationService.php'));

        self::assertStringContainsString('window.SgcConfirmDestructiveAction', $view);
        self::assertStringContainsString('nativeAuth.passkeySignIn', $view);
        self::assertStringContainsString('prepareDeletionPasskey', $view);
        self::assertStringContainsString("confirmLabel: 'Confirmar com passkey'", $view);
        self::assertStringContainsString('window.SgcPreparedPasskey.authenticate(preparedOptions)', $view);
        self::assertStringContainsString("'X-SGC-Platform': 'android'", $view);
        self::assertStringContainsString("'passkey_required' => \$passkeyAvailable", $service);
        self::assertStringContainsString("'code' => \$passkeyAvailable ? null : \$code", $service);
        self::assertStringNotContainsString('1 + 1 = ?', $modal);
        self::assertStringNotContainsString('math_answer', $modal);

        foreach (['delivery.deliveries.confirmation', 'delivery.distributions.confirmation'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            self::assertNotNull($route);
            self::assertSame(['POST'], $route->methods());
        }
    }

    public function test_register_revalidates_distributions_and_uses_the_same_passkey_confirmation(): void
    {
        $view = file_get_contents(resource_path('views/delivery/register.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Delivery/DeliveryRegistrationController.php'));
        $model = file_get_contents(app_path('Models/ProductionDelivery.php'));

        self::assertStringContainsString('window.SgcConfirmDestructiveAction', $view);
        self::assertStringContainsString("confirmLabel: 'Confirmar com passkey'", $view);
        self::assertStringContainsString('window.SgcPreparedPasskey.authenticate(preparedOptions)', $view);
        self::assertStringContainsString("cache: 'no-store'", $view);
        self::assertStringContainsString('revalidateRegisterAfterExternalChange', $view);
        self::assertStringContainsString("'Cache-Control', 'no-store, no-cache, must-revalidate, private'", $controller);
        self::assertStringContainsString('$reservedByAssociateReceipt', $model);
    }
}
