<?php

namespace Tests\Feature;

use App\Services\FinancialIntegrityAuditService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinancialIntegrityAuditServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'production_deliveries', 'associate_receipts', 'customer_billing_receipts',
            'associate_receipt_payments', 'customer_receipt_payments',
        ] as $table) {
            Schema::dropIfExists($table);
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
            $table->string('billing_status')->default('unbilled');
            $table->unsignedBigInteger('associate_receipt_id')->nullable();
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        foreach (['associate_receipts', 'customer_billing_receipts'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('sales_project_id');
                $table->json('delivery_ids')->nullable();
                $table->string('status');
                $table->decimal('total_gross', 14, 4)->default(0);
                $table->decimal('total_net', 14, 4)->default(0);
                $table->decimal('amount_paid', 14, 2)->default(0);
                $table->timestamps();
            });
        }
        foreach ([
            'associate_receipt_payments' => 'associate_receipt_id',
            'customer_receipt_payments' => 'customer_billing_receipt_id',
        ] as $tableName => $foreignKey) {
            Schema::create($tableName, function (Blueprint $table) use ($foreignKey): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger($foreignKey);
                $table->decimal('amount', 10, 2);
                $table->timestamps();
            });
        }
    }

    public function test_auditor_reports_divergences_without_changing_data(): void
    {
        DB::table('associate_receipts')->insert([
            'id' => 10, 'tenant_id' => 1, 'sales_project_id' => 20, 'delivery_ids' => json_encode([999]),
            'status' => 'paid', 'total_gross' => 50, 'total_net' => 45, 'amount_paid' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            [
                'id' => 100, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
                'parent_delivery_id' => null, 'customer_id' => null, 'product_id' => 40,
                'quantity' => 10, 'unit_price' => 0, 'gross_value' => 0,
                'associate_receipt_id' => null, 'billing_receipt_id' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 1, 'tenant_id' => 1, 'sales_project_id' => 20, 'associate_id' => 30,
                'parent_delivery_id' => 100, 'customer_id' => 200, 'product_id' => 40,
                'quantity' => 10, 'unit_price' => 5, 'gross_value' => 50,
                'associate_receipt_id' => 10, 'billing_receipt_id' => 999,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toJson();

        $result = app(FinancialIntegrityAuditService::class)->audit(1, 20);
        $codes = collect($result['issues'])->pluck('code');

        $this->assertTrue($codes->contains('missing_customer_billing_receipt'));
        $this->assertTrue($codes->contains('snapshot_fk_mismatch'));
        $this->assertTrue($codes->contains('paid_without_sufficient_payments'));
        $this->assertSame('critical', $result['issues'][0]['severity']);
        $aggregate = collect($result['aggregates'])->firstWhere('code', 'missing_customer_billing_receipt');
        $this->assertSame(1, $aggregate['count']);
        $this->assertSame([1], $aggregate['tenants']);
        $this->assertSame([20], $aggregate['projects']);
        $this->assertSame('B/E/F', $aggregate['classification']);
        $this->assertSame($before, DB::table('production_deliveries')->orderBy('id')->get()->toJson());
    }
}
