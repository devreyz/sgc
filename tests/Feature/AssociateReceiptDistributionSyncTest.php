<?php

namespace Tests\Feature;

use App\Enums\ReceiptStatus;
use App\Models\AssociateReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\AssociateReceiptService;
use App\Services\ProjectFinancialCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AssociateReceiptDistributionSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['activity_log', 'production_deliveries', 'associate_receipts'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('associate_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->date('issued_at');
            $table->json('delivery_ids')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_gross', 14, 4)->nullable();
            $table->decimal('total_fees', 14, 4)->nullable();
            $table->decimal('total_net', 14, 4)->nullable();
            $table->json('fee_snapshot')->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamp('obsolete_at')->nullable();
            $table->unsignedBigInteger('obsolete_by')->nullable();
            $table->text('obsolete_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('production_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('gross_value', 14, 4)->nullable();
            $table->string('status')->default('approved');
            $table->boolean('paid')->default(false);
            $table->string('billing_status')->default('unbilled');
            $table->unsignedBigInteger('associate_receipt_id')->nullable();
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->unsignedBigInteger('distribution_billing_id')->nullable();
            $table->unsignedBigInteger('project_payment_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('event')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });

        Queue::fake();
    }

    public function test_replacing_distributions_releases_removed_items_and_keeps_both_references_equal(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 10,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'receipt_year' => 2026,
            'receipt_number' => 1,
            'issued_at' => '2026-07-26',
            'delivery_ids' => json_encode([1, 2]),
            'status' => ReceiptStatus::PENDING_PAYMENT->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1, 2, 3] as $id) {
            DB::table('production_deliveries')->insert([
                'id' => 100 + $id,
                'tenant_id' => 1,
                'sales_project_id' => 20,
                'associate_id' => 30,
                'parent_delivery_id' => null,
                'customer_id' => null,
                'product_id' => 40,
                'quantity' => 10,
                'unit_price' => 0,
                'gross_value' => 0,
                'status' => 'approved',
                'paid' => false,
                'billing_status' => 'unbilled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('production_deliveries')->insert([
                'id' => $id,
                'tenant_id' => 1,
                'sales_project_id' => 20,
                'associate_id' => 30,
                'parent_delivery_id' => 100 + $id,
                'customer_id' => 200,
                'product_id' => 40,
                'quantity' => 10,
                'unit_price' => 5,
                'gross_value' => 50,
                'paid' => false,
                'billing_status' => 'unbilled',
                'associate_receipt_id' => $id < 3 ? 10 : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $calculator = Mockery::mock(ProjectFinancialCalculator::class);
        $calculator->shouldReceive('calculate')->twice()->andReturn([
            'fees' => [],
            'total_fee' => '0',
            'net' => '50',
        ]);

        $project = new SalesProject;
        $project->setRawAttributes([
            'id' => 20,
            'tenant_id' => 1,
            'admin_fee_percentage' => 0,
        ], true);
        $project->exists = true;

        $receipt = AssociateReceipt::query()->findOrFail(10);
        $result = AssociateReceipt::withoutEvents(
            fn () => (new AssociateReceiptService($calculator))->replaceDistributions(
                $receipt,
                [2, 3],
                $project,
                true,
                'Selecao administrativa alterada.'
            )
        );

        $this->assertSame([3], $result['added']);
        $this->assertSame([1], $result['removed']);
        $this->assertNull(ProductionDelivery::query()->findOrFail(1)->associate_receipt_id);
        $this->assertSame(10, ProductionDelivery::query()->findOrFail(2)->associate_receipt_id);
        $this->assertSame(10, ProductionDelivery::query()->findOrFail(3)->associate_receipt_id);

        $receipt->refresh();
        $this->assertSame([2, 3], $receipt->delivery_ids);
        $this->assertSame(ReceiptStatus::OBSOLETE, $receipt->status);
        $this->assertSame('100.0000', $receipt->total_gross);
        $this->assertSame('100.0000', $receipt->total_net);
    }

    public function test_customer_side_state_does_not_block_producer_receipt(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 11,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'receipt_year' => 2026,
            'receipt_number' => 2,
            'issued_at' => '2026-07-27',
            'status' => ReceiptStatus::DRAFT->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'id' => 104,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'parent_delivery_id' => null,
            'customer_id' => null,
            'product_id' => 40,
            'quantity' => 10,
            'unit_price' => 0,
            'gross_value' => 0,
            'status' => 'approved',
            'paid' => false,
            'billing_status' => 'unbilled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'id' => 4,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'parent_delivery_id' => 104,
            'customer_id' => 200,
            'product_id' => 40,
            'quantity' => 10,
            'unit_price' => 5,
            'gross_value' => 50,
            'paid' => false,
            'billing_status' => 'billed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $calculator = Mockery::mock(ProjectFinancialCalculator::class);
        $calculator->shouldReceive('calculate')->once()->andReturn([
            'fees' => [],
            'total_fee' => '0',
            'net' => '50',
        ]);

        $project = new SalesProject;
        $project->setRawAttributes([
            'id' => 20,
            'tenant_id' => 1,
            'admin_fee_percentage' => 0,
        ], true);
        $project->exists = true;

        AssociateReceipt::withoutEvents(
            fn () => (new AssociateReceiptService($calculator))->freezeReceipt(
                AssociateReceipt::query()->findOrFail(11),
                ProductionDelivery::query()->whereKey(4)->get(),
                $project,
            )
        );

        $this->assertSame(11, ProductionDelivery::query()->findOrFail(4)->associate_receipt_id);
        $this->assertSame([4], AssociateReceipt::query()->findOrFail(11)->delivery_ids);
    }

    public function test_cancelling_receipt_preserves_snapshot_and_really_releases_distributions(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 12, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
            'receipt_year' => 2026, 'receipt_number' => 3, 'issued_at' => '2026-10-06',
            'delivery_ids' => json_encode([5, 6]), 'status' => ReceiptStatus::PENDING_PAYMENT->value,
            'total_gross' => 100, 'total_fees' => 10, 'total_net' => 90,
            'fee_snapshot' => json_encode(['total_fee' => '10']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([5, 6] as $id) {
            DB::table('production_deliveries')->insert([
                'id' => $id, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
                'parent_delivery_id' => 100 + $id, 'customer_id' => 200, 'product_id' => 40,
                'quantity' => 10, 'unit_price' => 5, 'gross_value' => 50, 'status' => 'approved',
                'paid' => false, 'billing_status' => 'unbilled', 'associate_receipt_id' => 12,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $calculator = Mockery::mock(ProjectFinancialCalculator::class);
        $result = AssociateReceipt::withoutEvents(fn () => (new AssociateReceiptService($calculator))
            ->cancelAndReleaseDistributions(AssociateReceipt::query()->findOrFail(12), 'Reemissao consolidada.'));

        $this->assertSame([5, 6], $result['released']);
        $this->assertSame(0, ProductionDelivery::query()->whereNotNull('associate_receipt_id')->count());
        $receipt = AssociateReceipt::query()->findOrFail(12);
        $this->assertSame(ReceiptStatus::CANCELLED, $receipt->status);
        $this->assertSame([5, 6], $receipt->delivery_ids);
        $this->assertSame('90.0000', $receipt->total_net);
        $this->assertSame(['total_fee' => '10'], $receipt->fee_snapshot);
    }

    public function test_receipt_with_billed_distribution_cannot_be_cancelled_or_released(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 13, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
            'receipt_year' => 2026, 'receipt_number' => 4, 'issued_at' => '2026-10-06',
            'delivery_ids' => json_encode([7]), 'status' => ReceiptStatus::PENDING_PAYMENT->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'id' => 7, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
            'parent_delivery_id' => 107, 'customer_id' => 200, 'product_id' => 40,
            'quantity' => 10, 'unit_price' => 5, 'gross_value' => 50, 'status' => 'approved',
            'paid' => false, 'billing_status' => 'billed', 'associate_receipt_id' => 13,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        try {
            AssociateReceipt::withoutEvents(fn () => (new AssociateReceiptService(Mockery::mock(ProjectFinancialCalculator::class)))
                ->cancelAndReleaseDistributions(AssociateReceipt::query()->findOrFail(13), 'Tentativa invalida.'));
        } finally {
            $this->assertSame(13, ProductionDelivery::query()->findOrFail(7)->associate_receipt_id);
            $this->assertSame(ReceiptStatus::PENDING_PAYMENT, AssociateReceipt::query()->findOrFail(13)->status);
        }
    }
}
