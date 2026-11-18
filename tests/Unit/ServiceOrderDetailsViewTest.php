<?php

namespace Tests\Unit;

use App\Models\ServiceExecution;
use App\Models\ServiceOrder;
use App\Models\Tenant;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ServiceOrderDetailsViewTest extends TestCase
{
    public function test_submitted_order_form_renders_with_only_the_middleware_tenant(): void
    {
        $html = $this->renderDetails('submitted');
        $this->assertStringContainsString('/ascoppa/services-management/orders/11', $html);
        $this->assertStringContainsString('name="details_token"', $html);
        $this->assertStringContainsString('Beneficiário de teste', $html);
    }

    public function test_draft_order_links_use_the_middleware_tenant_without_an_explicit_tenant_variable(): void
    {
        $html = $this->renderDetails('draft');
        $this->assertStringContainsString('/ascoppa/services-management/orders/11/edit', $html);
        $this->assertStringContainsString('Serviço, prestador e tarifa', $html);
    }

    public function test_explicit_tenant_takes_precedence_over_shared_view_context(): void
    {
        $html = $this->renderDetails('submitted', [
            'tenant' => new Tenant(['slug' => 'outra-associacao']),
        ]);
        $this->assertStringContainsString('/outra-associacao/services-management/orders/11', $html);
        $this->assertStringNotContainsString('/ascoppa/services-management/orders/11', $html);
    }

    private function renderDetails(string $status, array $context = []): string
    {
        $order = new ServiceOrder([
            'number' => 'OS-TESTE', 'beneficiary_snapshot' => ['name' => 'Beneficiário de teste'],
            'order_data' => ['notes' => 'Registro antigo'],
        ]);
        $order->id = 11;
        $order->setRelation('execution', new ServiceExecution(['status' => $status, 'lock_version' => 2]));

        return view('services._order-details-form', $context + [
            'order' => $order,
            'currentTenant' => new Tenant(['slug' => 'ascoppa']),
            'errors' => new ViewErrorBag,
        ])->render();
    }
}
