<?php

namespace Tests\Unit;

use App\Models\CustomerBillingReceipt;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\CustomerBillingProjectContextService;
use App\Services\CustomerBillingReceiptService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerBillingMultiProjectSnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('customer_project_fees');
        Schema::create('customer_project_fees', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->string('name');
            $table->string('receipt_column_name')->nullable();
            $table->string('type')->default('percentage');
            $table->string('nature')->default('discount');
            $table->decimal('value', 12, 4);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_customer_billing_uses_the_canonical_delivery_project_relationship(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/CustomerBillingReceiptResource.php'));
        $export = file_get_contents(app_path('Exports/CustomerBillingReceiptExport.php'));

        $this->assertStringContainsString("with(['salesProject:id,title'", $resource);
        $this->assertStringContainsString("with(['salesProject:id,title'", $export);
        $this->assertStringNotContainsString('$d->project', $resource);
        $this->assertStringNotContainsString('$d->project', $export);
        $this->assertSame('sales_project_id', (new ProductionDelivery)->salesProject()->getForeignKeyName());
    }

    public function test_it_calculates_each_project_with_its_own_customer_fees(): void
    {
        DB::table('customer_project_fees')->insert([
            $this->fee(1, 10, 'Taxa Janeiro', 10),
            $this->fee(2, 20, 'Taxa Fevereiro', 20),
        ]);

        $january = $this->project(10, 'PNAE Janeiro');
        $february = $this->project(20, 'PNAE Fevereiro');
        $distributions = collect([
            $this->distribution(100, 10, '2026-01-15'),
            $this->distribution(200, 20, '2026-02-15'),
        ]);

        $snapshot = app(CustomerBillingReceiptService::class)
            ->computeSnapshotForProjects($distributions, collect([$january, $february]));

        $this->assertSame(0, bccomp('200.00000000', $snapshot['total_gross'], 8));
        $this->assertSame(0, bccomp('30.00000000', $snapshot['total_fees'], 8));
        $this->assertSame(0, bccomp('170.00000000', $snapshot['total_net'], 8));
        $this->assertSame([10, 20], $snapshot['fee_snapshot']['project_ids']);
        $this->assertSame('2026-01-15', $snapshot['fee_snapshot']['project_snapshots']['10']['period_from']);
        $this->assertSame('2026-02-15', $snapshot['fee_snapshot']['project_snapshots']['20']['period_to']);
        $this->assertCount(2, $snapshot['fee_snapshot']['fees']);
    }

    public function test_it_freezes_one_receipt_with_distributions_from_two_projects(): void
    {
        $this->createFreezeSchema();
        DB::table('sales_projects')->insert([
            $this->projectRow(10, 'PNAE Janeiro', '2026-01-01', '2026-01-31'),
            $this->projectRow(20, 'PNAE Fevereiro', '2026-02-01', '2026-02-28'),
        ]);
        DB::table('customers')->insert([
            'id' => 50, 'tenant_id' => 1, 'name' => 'Escola Central', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customer_billing_receipts')->insert([
            'id' => 70, 'tenant_id' => 1, 'sales_project_id' => 10, 'customer_id' => 50,
            'receipt_year' => 2026, 'receipt_number' => 1, 'issued_at' => '2026-03-01',
            'status' => 'draft', 'amount_paid' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customer_billing_receipt_projects')->insert([
            $this->pivotRow(70, 10),
            $this->pivotRow(70, 20),
        ]);
        DB::table('production_deliveries')->insert([
            $this->deliveryRow(101, 10, null, null, '2026-01-15', 0, 0),
            $this->deliveryRow(102, 10, 101, 50, '2026-01-15', 10, 10),
            $this->deliveryRow(201, 20, null, null, '2026-02-15', 0, 0),
            $this->deliveryRow(202, 20, 201, 50, '2026-02-15', 5, 20),
        ]);

        $receipt = CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(70);
        $anchor = SalesProject::withoutGlobalScopes()->findOrFail(10);
        $distributions = ProductionDelivery::withoutGlobalScopes()->whereIn('id', [102, 202])->get();
        app(CustomerBillingReceiptService::class)->freezeReceipt($receipt, $distributions, $anchor);

        $fresh = $receipt->fresh();
        $this->assertSame([10, 20], $fresh->projectIds());
        $this->assertSame('2026-01-15', $fresh->from_date->format('Y-m-d'));
        $this->assertSame('2026-02-15', $fresh->to_date->format('Y-m-d'));
        $this->assertSame(0, bccomp('200.0000', $fresh->total_gross, 4));
        $this->assertSame([70], ProductionDelivery::withoutGlobalScopes()->whereIn('id', [102, 202])
            ->pluck('billing_receipt_id')->unique()->values()->all());
    }

    public function test_it_rejects_projects_of_different_types(): void
    {
        $this->createFreezeSchema();
        DB::table('sales_projects')->insert([
            $this->projectRow(10, 'PNAE Janeiro', '2026-01-01', '2026-01-31'),
            array_merge($this->projectRow(20, 'PAA Fevereiro', '2026-02-01', '2026-02-28'), ['type' => 'paa']),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mesmo tipo');

        app(CustomerBillingProjectContextService::class)->projects(1, [10, 20]);
    }

    public function test_discarding_a_draft_releases_every_currently_linked_distribution(): void
    {
        $this->createFreezeSchema();
        DB::table('sales_projects')->insert([
            $this->projectRow(10, 'PNAE Janeiro', '2026-01-01', '2026-01-31'),
            $this->projectRow(20, 'PNAE Fevereiro', '2026-02-01', '2026-02-28'),
        ]);
        DB::table('customers')->insert([
            'id' => 50, 'tenant_id' => 1, 'name' => 'Escola Central', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customer_billing_receipts')->insert([
            'id' => 70, 'tenant_id' => 1, 'sales_project_id' => 10, 'customer_id' => 50,
            'receipt_year' => 2026, 'receipt_number' => 1, 'issued_at' => '2026-03-01',
            // Simula um snapshot legado incompleto: a segunda distribuição só
            // existe no vínculo ativo billing_receipt_id.
            'delivery_ids' => json_encode([102]), 'status' => 'draft', 'amount_paid' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customer_billing_receipt_projects')->insert([
            $this->pivotRow(70, 10),
            $this->pivotRow(70, 20),
        ]);
        DB::table('production_deliveries')->insert([
            array_merge($this->deliveryRow(101, 10, null, null, '2026-01-15', 0, 0), ['billing_receipt_id' => null]),
            array_merge($this->deliveryRow(102, 10, 101, 50, '2026-01-15', 10, 10), ['billing_receipt_id' => 70]),
            array_merge($this->deliveryRow(201, 20, null, null, '2026-02-15', 0, 0), ['billing_receipt_id' => null]),
            array_merge($this->deliveryRow(202, 20, 201, 50, '2026-02-15', 5, 20), ['billing_receipt_id' => 70]),
        ]);

        app(CustomerBillingReceiptService::class)->discardDraftReceipt(
            CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(70),
        );

        $this->assertDatabaseMissing('customer_billing_receipts', ['id' => 70]);
        $this->assertDatabaseMissing('customer_billing_receipt_projects', ['customer_billing_receipt_id' => 70]);
        $this->assertSame([null], ProductionDelivery::withoutGlobalScopes()
            ->whereIn('id', [102, 202])
            ->pluck('billing_receipt_id')->unique()->values()->all());
    }

    public function test_discarding_an_emitted_receipt_is_blocked_without_releasing_distributions(): void
    {
        $this->createFreezeSchema();
        DB::table('sales_projects')->insert($this->projectRow(10, 'PNAE Janeiro', '2026-01-01', '2026-01-31'));
        DB::table('customer_billing_receipts')->insert([
            'id' => 70, 'tenant_id' => 1, 'sales_project_id' => 10,
            'receipt_year' => 2026, 'receipt_number' => 1, 'issued_at' => '2026-03-01',
            'status' => 'pending_payment', 'amount_paid' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            array_merge($this->deliveryRow(101, 10, null, null, '2026-01-15', 0, 0), ['billing_receipt_id' => null]),
            array_merge($this->deliveryRow(102, 10, 101, 50, '2026-01-15', 10, 10), ['billing_receipt_id' => 70]),
        ]);

        try {
            app(CustomerBillingReceiptService::class)->discardDraftReceipt(
                CustomerBillingReceipt::withoutGlobalScopes()->findOrFail(70),
            );
            $this->fail('Uma cobrança emitida não pode ser excluída.');
        } catch (\DomainException $exception) {
            $this->assertStringContainsString('rascunho', $exception->getMessage());
        }

        $this->assertDatabaseHas('customer_billing_receipts', ['id' => 70]);
        $this->assertDatabaseHas('production_deliveries', ['id' => 102, 'billing_receipt_id' => 70]);
    }

    public function test_migration_backfills_the_primary_project_for_existing_receipts(): void
    {
        foreach (['customer_billing_receipt_projects', 'customer_billing_receipts', 'sales_projects', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('sales_projects', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('customer_billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id')->nullable();
        });
        DB::table('tenants')->insert(['id' => 1]);
        DB::table('sales_projects')->insert(['id' => 10]);
        DB::table('customer_billing_receipts')->insert(['id' => 70, 'tenant_id' => 1, 'sales_project_id' => 10]);

        // Simula o estado deixado pelo MySQL quando a criação da tabela foi
        // confirmada, mas uma FK falhou antes de a migration ser registrada.
        Schema::create('customer_billing_receipt_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_billing_receipt_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_09_02_000001_create_customer_billing_receipt_projects_table.php');
        $migration->up();

        $this->assertDatabaseHas('customer_billing_receipt_projects', [
            'tenant_id' => 1,
            'customer_billing_receipt_id' => 70,
            'sales_project_id' => 10,
        ]);
    }

    private function createFreezeSchema(): void
    {
        foreach (['customer_billing_receipt_projects', 'production_deliveries', 'customer_billing_receipts', 'customers', 'sales_projects'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::create('sales_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->string('type');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('admin_fee_percentage', 8, 4)->default(0);
            $table->timestamps();
            $table->softDeletes();
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
        Schema::create('customer_billing_receipts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->date('issued_at');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('status');
            $table->decimal('total_gross', 14, 4)->nullable();
            $table->decimal('total_fees', 14, 4)->nullable();
            $table->decimal('total_net', 14, 4)->nullable();
            $table->json('fee_snapshot')->nullable();
            $table->json('delivery_ids')->nullable();
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('customer_billing_receipt_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('customer_billing_receipt_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->timestamps();
            $table->unique(['customer_billing_receipt_id', 'sales_project_id']);
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
            $table->decimal('gross_value', 14, 4)->nullable();
            $table->string('status');
            $table->unsignedBigInteger('billing_receipt_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function projectRow(int $id, string $title, string $start, string $end): array
    {
        return ['id' => $id, 'tenant_id' => 1, 'title' => $title, 'type' => 'pnae', 'start_date' => $start,
            'end_date' => $end, 'admin_fee_percentage' => 0, 'created_at' => now(), 'updated_at' => now()];
    }

    private function pivotRow(int $receiptId, int $projectId): array
    {
        return ['tenant_id' => 1, 'customer_billing_receipt_id' => $receiptId, 'sales_project_id' => $projectId,
            'created_at' => now(), 'updated_at' => now()];
    }

    private function deliveryRow(int $id, int $projectId, ?int $parentId, ?int $customerId, string $date, float $quantity, float $price): array
    {
        return ['id' => $id, 'tenant_id' => 1, 'sales_project_id' => $projectId, 'associate_id' => 9,
            'parent_delivery_id' => $parentId, 'customer_id' => $customerId, 'product_id' => 99,
            'delivery_date' => $date, 'quantity' => $quantity, 'unit_price' => $price,
            'gross_value' => $quantity * $price, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()];
    }

    private function project(int $id, string $title): SalesProject
    {
        $project = new SalesProject;
        $project->setRawAttributes([
            'id' => $id,
            'tenant_id' => 1,
            'title' => $title,
            'type' => 'pnae',
            'admin_fee_percentage' => 0,
        ], true);
        $project->exists = true;

        return $project;
    }

    private function distribution(int $id, int $projectId, string $date): ProductionDelivery
    {
        $distribution = new ProductionDelivery;
        $distribution->setRawAttributes([
            'id' => $id,
            'tenant_id' => 1,
            'sales_project_id' => $projectId,
            'quantity' => 10,
            'unit_price' => 10,
            'gross_value' => 100,
            'delivery_date' => $date,
            'status' => 'approved',
        ], true);
        $distribution->setAttribute('delivery_date', Carbon::parse($date));

        return $distribution;
    }

    private function fee(int $id, int $projectId, string $name, float $value): array
    {
        return [
            'id' => $id,
            'tenant_id' => 1,
            'sales_project_id' => $projectId,
            'name' => $name,
            'type' => 'percentage',
            'nature' => 'discount',
            'value' => $value,
            'active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
