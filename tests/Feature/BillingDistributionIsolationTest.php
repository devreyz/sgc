<?php

namespace Tests\Feature;

use App\Services\CustomerBillingSelectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BillingDistributionIsolationTest extends TestCase
{
    use \Tests\Concerns\BuildsDeliveryReportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDeliveryReportSchema();
        Schema::table('production_deliveries', fn (Blueprint $table) => $table->unsignedBigInteger('billing_receipt_id')->nullable());
        session(['tenant_id' => 1]);
    }

    private function select(array $ids = [90, 91, 92], ?int $customerId = 80, ?int $organizationId = null): array
    {
        return app(CustomerBillingSelectionService::class)->selectDistributionIds(1, $ids, [10], $customerId, $organizationId, null, null);
    }

    public function test_only_distributions_of_requested_client_enter_billing(): void
    {
        $this->assertSame([91], $this->select()['selected_ids']);
        $this->assertSame([92], $this->select([91, 92], 81)['selected_ids']);
        $this->assertSame([91, 92], $this->select([91, 92], null, 70)['selected_ids']);
        $this->assertSame([], $this->select([91, 92], null, null)['selected_ids']);
    }

    public function test_foreign_or_deleted_recipient_cannot_be_billed_via_direct_customer_id(): void
    {
        DB::table('customers')->insert(['id' => 85, 'tenant_id' => 2, 'name' => 'Outro tenant']);
        DB::table('production_deliveries')->where('id', 91)->update(['customer_id' => 85]);
        $this->assertSame([], $this->select([91], 85)['selected_ids']);
        DB::table('production_deliveries')->where('id', 91)->update(['customer_id' => 80]);
        DB::table('customers')->where('id', 80)->update(['deleted_at' => now()]);
        $this->assertSame([], $this->select([91], 80)['selected_ids']);
    }

    public function test_parent_product_producer_and_approval_must_match_the_financial_fact(): void
    {
        DB::table('production_deliveries')->where('id', 91)->update(['product_id' => 60]);
        $this->assertSame([], $this->select([91])['selected_ids']);
        DB::table('production_deliveries')->where('id', 91)->update(['product_id' => 50, 'associate_id' => 40]);
        $this->assertSame([], $this->select([91])['selected_ids']);
        DB::table('production_deliveries')->where('id', 91)->update(['associate_id' => 30]);
        DB::table('production_deliveries')->where('id', 90)->update(['status' => 'pending']);
        $result = $this->select([91]);
        $this->assertSame([], $result['selected_ids']);
        $this->assertSame(1, $result['reasons']['entrega_pai_nao_aprovada']);
    }

    public function test_receipt_reservations_and_other_tenant_distributions_are_excluded(): void
    {
        DB::table('production_deliveries')->where('id', 91)->update(['billing_receipt_id' => 123]);
        $this->assertSame([], $this->select([91, 99])['selected_ids']);
        $result = $this->select([91, 99]);
        $this->assertSame(1, $result['reasons']['ja_faturada']);
        $this->assertSame(1, $result['reasons']['nao_encontrada_no_tenant']);
    }
}
