<?php

namespace Tests\Feature;

use App\Enums\ReceiptStatus;
use App\Models\AssociateReceipt;
use App\Services\AssociateFinancialSummaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AssociatePortalFinancialTruthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['associate_receipt_payments', 'production_deliveries', 'associate_receipts'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('associate_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedSmallInteger('receipt_year')->default(2026);
            $table->unsignedInteger('receipt_number')->default(1);
            $table->date('issued_at')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_gross', 14, 4)->default(0);
            $table->decimal('total_fees', 14, 4)->default(0);
            $table->decimal('total_net', 14, 4)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('production_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->decimal('unit_price', 12, 4)->default(0);
            $table->decimal('gross_value', 14, 4)->default(0);
            $table->decimal('admin_fee_amount', 14, 4)->nullable();
            $table->decimal('net_value', 14, 4)->nullable();
            $table->string('status')->default('pending');
            $table->string('billing_status')->nullable();
            $table->unsignedBigInteger('associate_receipt_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('associate_receipt_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('associate_receipt_id');
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->timestamps();
        });
    }

    public function test_member_portal_financial_summary_uses_only_approved_distributions(): void
    {
        $this->insertDelivery(1, null, 0, 0, 'approved');
        $this->insertDelivery(2, 1, 120, 108, 'approved', 12);
        $this->insertDelivery(3, 1, 90, 81, 'rejected', 9);
        $this->insertDelivery(4, 1, 999, 899.10, 'approved', 99.90, tenantId: 2);

        $summary = app(AssociateFinancialSummaryService::class)->summary(1, 30, 20);

        $this->assertSame(1, $summary['distribution_count']);
        $this->assertSame(120.0, $summary['total_gross']);
        $this->assertSame(12.0, $summary['total_fees']);
        $this->assertSame(108.0, $summary['total_net']);
        $this->assertSame(108.0, $summary['unbilled']);
    }

    public function test_receipt_display_is_recalculated_from_distributions_and_payments(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 10,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'status' => ReceiptStatus::PARTIALLY_PAID->value,
            'total_gross' => 0,
            'total_fees' => 0,
            'total_net' => 0,
            'amount_paid' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertDelivery(2, 1, 120, 108, 'approved', 12, receiptId: 10);
        DB::table('associate_receipt_payments')->insert([
            'tenant_id' => 1,
            'associate_receipt_id' => 10,
            'amount' => 40,
            'payment_date' => '2026-08-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totals = app(AssociateFinancialSummaryService::class)
            ->receiptTotals(AssociateReceipt::query()->findOrFail(10));

        $this->assertSame(120.0, $totals['gross']);
        $this->assertSame(12.0, $totals['fees']);
        $this->assertSame(108.0, $totals['net']);
        $this->assertSame(40.0, $totals['paid']);
        $this->assertSame(68.0, $totals['remaining']);
        $this->assertSame(1, $totals['distribution_count']);

        $summary = app(AssociateFinancialSummaryService::class)->summary(1, 30, 20);
        $this->assertSame(108.0, $summary['receipt_issued']);
        $this->assertSame(40.0, $summary['receipt_paid']);
        $this->assertSame(68.0, $summary['receivable']);
        $this->assertSame(40.0, $summary['paid']);
        $this->assertSame(0.0, $summary['unbilled']);
    }

    private function insertDelivery(
        int $id,
        ?int $parentId,
        float $gross,
        float $net,
        string $status,
        float $fee = 0,
        int $tenantId = 1,
        ?int $receiptId = null,
    ): void {
        DB::table('production_deliveries')->insert([
            'id' => $id,
            'tenant_id' => $tenantId,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'parent_delivery_id' => $parentId,
            'quantity' => $parentId ? 10 : 20,
            'unit_price' => $parentId ? $gross / 10 : 0,
            'gross_value' => $gross,
            'admin_fee_amount' => $fee,
            'net_value' => $net,
            'status' => $status,
            'billing_status' => 'unbilled',
            'associate_receipt_id' => $receiptId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
