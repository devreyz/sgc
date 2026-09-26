<?php

namespace Tests\Feature;

use App\Models\AssociateReceipt;
use App\Services\CustomerBillingSelectionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerBillingSelectionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['financial_document_identities', 'production_deliveries', 'associate_receipts', 'customers'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('associate_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedSmallInteger('receipt_year')->default(2026);
            $table->unsignedInteger('receipt_number')->default(1);
            $table->string('status')->default('pending_payment');
            $table->timestamps();
        });
        Schema::create('financial_document_identities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->uuid('public_id');
            $table->string('reference_code');
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('production_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->date('delivery_date');
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->string('status');
            $table->unsignedBigInteger('associate_receipt_id')->nullable();
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('customers')->insert([
            ['id' => 10, 'tenant_id' => 1, 'organization_id' => 100, 'name' => 'Unidade Fictícia A', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'tenant_id' => 1, 'organization_id' => 200, 'name' => 'Unidade Fictícia B', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('associate_receipts')->insert([
            'id' => 50, 'tenant_id' => 1, 'sales_project_id' => 300, 'associate_id' => 400,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('financial_document_identities')->insert([
            'tenant_id' => 1,
            'public_id' => '11111111-1111-4111-8111-111111111111',
            'reference_code' => 'CP-FAKE00000001',
            'documentable_type' => (new AssociateReceipt)->getMorphClass(),
            'documentable_id' => 50,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $rows = [];
        for ($id = 1; $id <= 18; $id++) {
            $rows[] = [
                'id' => $id, 'tenant_id' => 1, 'sales_project_id' => 300, 'associate_id' => 400,
                'parent_delivery_id' => 900, 'customer_id' => $id <= 15 ? 10 : 20, 'product_id' => 500,
                'delivery_date' => '2026-09-15', 'quantity' => 1, 'unit_price' => 10, 'status' => 'approved',
                'associate_receipt_id' => 50, 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('production_deliveries')->insert($rows);
    }

    public function test_mixed_receipt_selects_only_compatible_organization_distributions(): void
    {
        $result = app(CustomerBillingSelectionService::class)->selectFromAssociateReceiptCodes(
            1, ['CP-FAKE00000001'], [300], null, 100, '2026-09-01', '2026-09-30',
        );

        self::assertCount(15, $result['selected_ids']);
        self::assertSame(3, $result['excluded_count']);
        self::assertSame(3, $result['reasons']['outro_destinatario']);
    }

    public function test_repeating_same_receipt_is_idempotent_and_already_billed_item_is_ignored(): void
    {
        DB::table('production_deliveries')->where('id', 1)->update(['billing_receipt_id' => 999]);
        $result = app(CustomerBillingSelectionService::class)->selectFromAssociateReceiptCodes(
            1, ['CP-FAKE00000001', 'CP-FAKE00000001'], [300], null, 100, null, null,
        );

        self::assertCount(14, $result['selected_ids']);
        self::assertSame(count($result['selected_ids']), count(array_unique($result['selected_ids'])));
        self::assertSame(1, $result['reasons']['ja_faturada']);
    }

    public function test_qr_identity_from_another_tenant_is_not_disclosed(): void
    {
        $result = app(CustomerBillingSelectionService::class)->selectFromAssociateReceiptCodes(
            2, ['CP-FAKE00000001'], [300], null, 100, null, null,
        );

        self::assertSame([], $result['selected_ids']);
        self::assertSame(0, $result['receipt_count']);
        self::assertSame(0, $result['candidate_count']);
    }
}
