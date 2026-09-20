<?php

namespace Tests\Unit;

use Tests\TestCase;

class ServiceExpensePortalContractTest extends TestCase
{
    public function test_financial_summary_uses_real_obligation_columns(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Provider/ServiceProviderPortalController.php'));

        $this->assertStringContainsString('SUM(principal_amount + adjustment_amount)', $controller);
        $this->assertStringNotContainsString("->sum('total_amount')", $this->financialMethod($controller));
        $this->assertStringContainsString("whereIn('payment.status', ['confirmed', 'reversed'])", $controller);
    }

    public function test_service_expense_form_supports_optimized_attachment_previews_and_async_errors(): void
    {
        $view = file_get_contents(resource_path('views/provider/services-expenses.blade.php'));

        $this->assertStringContainsString('enctype="multipart/form-data"', $view);
        $this->assertStringContainsString('name="attachments[]"', $view);
        $this->assertStringContainsString('canvas.toBlob', $view);
        $this->assertStringContainsString("'image/webp'", $view);
        $this->assertStringContainsString('await fetch(form.action', $view);
        $this->assertStringContainsString('expense-submit-errors', $view);
    }

    public function test_expense_documents_are_tenant_scoped_and_served_inline(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Provider/ServiceProviderPortalController.php'));

        $this->assertStringContainsString("where('tenant_id', \$tenant->id)", $controller);
        $this->assertStringContainsString("where('documentable_type', Expense::class)", $controller);
        $this->assertStringContainsString("'Content-Disposition' => 'inline; filename=", $controller);
        $this->assertStringContainsString("'X-Content-Type-Options' => 'nosniff'", $controller);
    }

    private function financialMethod(string $controller): string
    {
        $start = strpos($controller, 'public function financial');
        $end = strpos($controller, 'public function payout', $start);

        return substr($controller, $start, $end - $start);
    }
}
