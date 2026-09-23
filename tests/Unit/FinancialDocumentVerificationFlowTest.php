<?php

namespace Tests\Unit;

use App\Enums\ReceiptStatus;
use App\Models\AssociateReceipt;
use App\Models\FinancialDocumentIdentity;
use App\Models\FinancialReceipt;
use App\Models\ServicePaymentPlanInstallment;
use App\Services\FinancialDocumentIdentityService;
use App\Services\FinancialDocumentPaymentService;
use App\Services\FinancialDocumentPresenter;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialDocumentVerificationFlowTest extends TestCase
{
    public function test_qr_contains_only_the_secure_receipt_url(): void
    {
        $identity = new FinancialDocumentIdentity([
            'public_id' => (string) Str::uuid(),
            'reference_code' => 'CP-TESTE123456',
        ]);
        $service = app(FinancialDocumentIdentityService::class);
        $svg = $service->qrSvg($identity);

        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertStringContainsString('/receipt/'.$identity->public_id, $service->url($identity));
        $this->assertStringNotContainsString('cpf', strtolower($service->url($identity)));
        $this->assertStringNotContainsString('amount', strtolower($service->url($identity)));
    }

    public function test_public_presentation_exposes_status_but_not_private_financial_details(): void
    {
        $receipt = new AssociateReceipt([
            'status' => ReceiptStatus::PAID,
            'total_net' => 1284.35,
            'amount_paid' => 1284.35,
            'receipt_year' => 2026,
            'receipt_number' => 152,
        ]);
        $identity = new FinancialDocumentIdentity([
            'public_id' => (string) Str::uuid(),
            'reference_code' => 'CP-TESTE123456',
            'tenant_id' => 1,
        ]);
        $identity->setRelation('documentable', $receipt);
        $identity->setRelation('checks', new Collection);

        $view = (new FinancialDocumentPresenter(app(FinancialDocumentPaymentService::class)))
            ->present($identity, null);

        $this->assertSame('paid', $view['technical_status']);
        $this->assertSame('Pago', $view['human_status']);
        $this->assertFalse($view['can_view_details']);
        $this->assertNull($view['total']);
        $this->assertNull($view['party']);
        $this->assertSame([], $view['actions']);
    }

    public function test_routes_are_uuid_scoped_rate_limited_and_financial_actions_require_authentication(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->keyBy(fn ($route) => $route->getName());

        $this->assertContains('throttle:30,1', $routes['financial-documents.show']->gatherMiddleware());
        $this->assertContains('financial.headers', $routes['financial-documents.show']->gatherMiddleware());
        $this->assertStringContainsString('{publicId}', $routes['financial-documents.show']->uri());
        foreach (['financial-documents.pay', 'financial-documents.checks.issue', 'financial-documents.checks.deliver', 'financial-documents.checks.cancel'] as $name) {
            $this->assertContains('auth', $routes[$name]->gatherMiddleware());
            $this->assertContains('throttle:10,1', $routes[$name]->gatherMiddleware());
        }
    }

    public function test_financial_flows_reuse_domain_services_locks_and_idempotency(): void
    {
        $orchestrator = file_get_contents(app_path('Services/FinancialDocumentPaymentService.php'));
        $checks = file_get_contents(app_path('Services/FinancialCheckService.php'));
        $associate = file_get_contents(app_path('Services/AssociateReceiptService.php'));
        $customer = file_get_contents(app_path('Services/CustomerBillingReceiptService.php'));
        $service = file_get_contents(app_path('Services/Services/ServicePaymentService.php'));

        $this->assertStringContainsString('AssociateReceiptService::class', $orchestrator);
        $this->assertStringContainsString('CustomerBillingReceiptService::class', $orchestrator);
        $this->assertStringContainsString('ServicePaymentService::class', $orchestrator);
        $this->assertStringContainsString('lockForUpdate()', $checks);
        $this->assertStringContainsString('delivery_operation_key', $checks);
        $this->assertStringContainsString('lockForUpdate()', $associate);
        $this->assertStringContainsString('lockForUpdate()', $customer);
        $this->assertStringContainsString('lockForUpdate()', $service);
    }

    public function test_cheque_is_not_available_as_an_immediate_payment_method(): void
    {
        $orchestrator = file_get_contents(app_path('Services/FinancialDocumentPaymentService.php'));
        $associateResource = file_get_contents(app_path('Filament/Resources/AssociateReceiptResource.php'));
        $customerResource = file_get_contents(app_path('Filament/Resources/CustomerBillingReceiptResource.php'));
        $serviceView = file_get_contents(resource_path('views/services/management-show.blade.php'));

        $this->assertStringContainsString("=== 'cheque'", $orchestrator);
        $this->assertStringContainsString('PaymentMethod::CHEQUE', $associateResource);
        $this->assertStringContainsString('PaymentMethod::CHEQUE', $customerResource);
        $this->assertStringNotContainsString('<option value="cheque">', $serviceView);
        $this->assertStringContainsString('somente na entrega', strtolower($serviceView));
    }

    public function test_pdf_templates_share_the_verifiable_qr_partial(): void
    {
        foreach (['project-associate-receipt', 'associate-portal-receipt', 'customer-billing-receipt', 'customer-organization-receipt', 'financial-receipt'] as $template) {
            $this->assertStringContainsString(
                'pdf.partials.financial-document-qr',
                file_get_contents(resource_path("views/pdf/{$template}.blade.php")),
            );
        }
        $this->assertContains(FinancialReceipt::class, FinancialDocumentIdentityService::SUPPORTED_TYPES);
        $this->assertContains(ServicePaymentPlanInstallment::class, FinancialDocumentIdentityService::SUPPORTED_TYPES);
        $this->assertStringContainsString(
            'pdf.partials.financial-document-qr',
            file_get_contents(resource_path('views/pdf/service-order.blade.php')),
        );
    }

    public function test_new_tables_explicitly_use_innodb_and_do_not_store_financial_balances(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_19_000004_create_financial_document_identities.php'));

        $this->assertSame(2, substr_count($migration, "->engine = 'InnoDB'"));
        $this->assertStringContainsString("uuid('public_id')->unique()", $migration);
        $this->assertStringNotContainsString("decimal('balance'", $migration);
        $this->assertStringNotContainsString("decimal('amount_paid'", $migration);
    }
}
