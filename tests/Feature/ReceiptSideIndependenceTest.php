<?php

namespace Tests\Feature;

use App\Models\AssociateReceipt;
use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\AssociateReceiptService;
use App\Services\CustomerBillingReceiptService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReceiptSideIndependenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'activity_log',
            'customer_project_fees',
            'project_fees',
            'production_deliveries',
            'customer_billing_receipts',
            'associate_receipts',
            'organizations',
            'customers',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedSmallInteger('receipt_year')->default(2026);
            $table->unsignedInteger('receipt_number')->default(1);
            $table->date('issued_at')->nullable();
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

        Schema::create('customer_billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedSmallInteger('receipt_year')->default(2026);
            $table->unsignedInteger('receipt_number')->default(1);
            $table->date('issued_at')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->string('status')->default('draft');
            $table->decimal('total_gross', 14, 4)->nullable();
            $table->decimal('total_fees', 14, 4)->nullable();
            $table->decimal('total_net', 14, 4)->nullable();
            $table->json('fee_snapshot')->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['project_fees', 'customer_project_fees'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('sales_project_id');
                $table->string('name');
                $table->string('type')->default('percentage');
                $table->string('nature')->default('discount');
                $table->decimal('value', 12, 4)->default(0);
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('production_deliveries', function (Blueprint $table): void {
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

        Schema::create('activity_log', function (Blueprint $table): void {
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

        DB::table('customers')->insert([
            'id' => 200,
            'tenant_id' => 1,
            'name' => 'Cliente A',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Queue::fake();
        session(['tenant_id' => 1]);
    }

    public function test_producer_receipt_then_customer_receipt_can_share_distribution(): void
    {
        [$project, $associateReceipt, $customerReceipt, $distribution] = $this->fixture();

        app(AssociateReceiptService::class)->freezeReceipt($associateReceipt, collect([$distribution]), $project);
        app(CustomerBillingReceiptService::class)->freezeReceipt($customerReceipt, collect([$distribution]), $project);

        $distribution->refresh();
        $this->assertSame($associateReceipt->id, $distribution->associate_receipt_id);
        $this->assertSame($customerReceipt->id, $distribution->billing_receipt_id);
    }

    public function test_customer_receipt_then_producer_receipt_can_share_distribution(): void
    {
        [$project, $associateReceipt, $customerReceipt, $distribution] = $this->fixture();

        app(CustomerBillingReceiptService::class)->freezeReceipt($customerReceipt, collect([$distribution]), $project);
        app(AssociateReceiptService::class)->freezeReceipt($associateReceipt, collect([$distribution->fresh()]), $project);

        $distribution->refresh();
        $this->assertSame($associateReceipt->id, $distribution->associate_receipt_id);
        $this->assertSame($customerReceipt->id, $distribution->billing_receipt_id);
    }

    public function test_second_producer_receipt_is_rejected(): void
    {
        [$project, $first, , $distribution] = $this->fixture();
        $second = $this->associateReceipt(11);
        app(AssociateReceiptService::class)->freezeReceipt($first, collect([$distribution]), $project);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outro comprovante');
        app(AssociateReceiptService::class)->freezeReceipt($second, collect([$distribution->fresh()]), $project);
    }

    public function test_second_customer_receipt_is_rejected(): void
    {
        [$project, , $first, $distribution] = $this->fixture();
        $second = $this->customerReceipt(21);
        app(CustomerBillingReceiptService::class)->freezeReceipt($first, collect([$distribution]), $project);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outra cobranca');
        app(CustomerBillingReceiptService::class)->freezeReceipt($second, collect([$distribution->fresh()]), $project);
    }

    public function test_cross_tenant_distribution_is_rejected_without_disclosing_it(): void
    {
        [$project, $associateReceipt, , $distribution] = $this->fixture();
        DB::table('production_deliveries')->where('id', $distribution->id)->update(['tenant_id' => 2]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nao existem mais');
        app(AssociateReceiptService::class)->freezeReceipt(
            $associateReceipt,
            collect([$distribution]),
            $project,
        );
    }

    public function test_receipt_models_scope_queries_to_current_tenant(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 50,
            'tenant_id' => 2,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('customer_billing_receipts')->insert([
            'id' => 60,
            'tenant_id' => 2,
            'sales_project_id' => 20,
            'customer_id' => 200,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertNull(AssociateReceipt::query()->find(50));
        $this->assertNull(CustomerBillingReceipt::query()->find(60));
        $this->assertNotNull(AssociateReceipt::withoutGlobalScopes()->find(50));
        $this->assertNotNull(CustomerBillingReceipt::withoutGlobalScopes()->find(60));
    }

    public function test_producer_snapshot_rolls_back_when_distribution_link_fails(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('A injecao desta falha usa trigger SQLite; o cenario MySQL possui suite dedicada.');
        }

        [$project, $receipt, , $distribution] = $this->fixture();
        DB::unprepared("CREATE TRIGGER fail_associate_link BEFORE UPDATE OF associate_receipt_id ON production_deliveries WHEN NEW.associate_receipt_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'forced link failure'); END");

        try {
            app(AssociateReceiptService::class)->freezeReceipt($receipt, collect([$distribution]), $project);
            $this->fail('A falha de vinculo deveria cancelar o snapshot.');
        } catch (\Throwable) {
            // As assercoes abaixo comprovam o rollback integral.
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_associate_link');
        }

        $this->assertSame('draft', $receipt->fresh()->status->value);
        $this->assertNull($receipt->fresh()->total_net);
        $this->assertNull($distribution->fresh()->associate_receipt_id);
    }

    public function test_customer_snapshot_rolls_back_when_distribution_link_fails(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('A injecao desta falha usa trigger SQLite; o cenario MySQL possui suite dedicada.');
        }

        [$project, , $receipt, $distribution] = $this->fixture();
        DB::unprepared("CREATE TRIGGER fail_customer_link BEFORE UPDATE OF billing_receipt_id ON production_deliveries WHEN NEW.billing_receipt_id IS NOT NULL BEGIN SELECT RAISE(ABORT, 'forced link failure'); END");

        try {
            app(CustomerBillingReceiptService::class)->freezeReceipt($receipt, collect([$distribution]), $project);
            $this->fail('A falha de vinculo deveria cancelar a cobranca.');
        } catch (\Throwable) {
            // As assercoes abaixo comprovam o rollback integral.
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS fail_customer_link');
        }

        $this->assertSame('draft', $receipt->fresh()->status->value);
        $this->assertNull($receipt->fresh()->total_net);
        $this->assertNull($distribution->fresh()->billing_receipt_id);
    }

    private function fixture(): array
    {
        $project = new SalesProject;
        $project->setRawAttributes([
            'id' => 20,
            'tenant_id' => 1,
            'admin_fee_percentage' => 0,
        ], true);
        $project->exists = true;
        $project->setRelation('fees', collect());

        $associateReceipt = $this->associateReceipt(10);
        $customerReceipt = $this->customerReceipt(20);

        DB::table('production_deliveries')->insert([
            'id' => 100,
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'id' => 1,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'parent_delivery_id' => 100,
            'customer_id' => 200,
            'product_id' => 40,
            'quantity' => 10,
            'unit_price' => 5,
            'gross_value' => 50,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$project, $associateReceipt, $customerReceipt, ProductionDelivery::findOrFail(1)];
    }

    private function associateReceipt(int $id): AssociateReceipt
    {
        DB::table('associate_receipts')->insert([
            'id' => $id,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'associate_id' => 30,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AssociateReceipt::findOrFail($id);
    }

    private function customerReceipt(int $id): CustomerBillingReceipt
    {
        DB::table('customer_billing_receipts')->insert([
            'id' => $id,
            'tenant_id' => 1,
            'sales_project_id' => 20,
            'customer_id' => 200,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CustomerBillingReceipt::findOrFail($id);
    }
}
