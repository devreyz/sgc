<?php

namespace Tests\Feature;

use App\Enums\DeliveryConferenceStatus;
use App\Models\DeliveryConferenceSheet;
use App\Models\SalesProject;
use App\Models\User;
use App\Services\DeliveryConferenceSheetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DeliveryConferenceSheetServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['activity_log', 'delivery_conference_sheet_items', 'delivery_conference_sheets', 'receipt_number_sequences', 'production_deliveries', 'products', 'sales_project_organizations', 'sales_project_customers', 'organizations', 'customers', 'sales_projects', 'tenant_user', 'tenants', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        $this->schema();
        $this->seedScenario();
        session(['tenant_id' => 1, 'tenant_slug' => 'tenant-a']);
    }

    public function test_customer_draft_uses_only_approved_distributions_and_never_parent_delivery(): void
    {
        $sheet = $this->createCustomerDraft();
        self::assertSame([101, 104], $sheet->distributions()->orderBy('production_deliveries.id')->pluck('production_deliveries.id')->all());
        self::assertSame(DeliveryConferenceStatus::DRAFT, $sheet->status);
    }

    public function test_issued_sheet_has_deterministic_snapshot_without_financial_values(): void
    {
        $service = app(DeliveryConferenceSheetService::class);
        $issued = $service->issue($this->createCustomerDraft(), User::findOrFail(1));
        self::assertMatchesRegularExpression('/^FC-/', $issued->number);
        self::assertSame($issued->snapshot_hash, $service->currentHash($issued));
        self::assertArrayNotHasKey('unit_price', $issued->snapshot['distributions'][0]);
        self::assertArrayNotHasKey('gross_value', $issued->snapshot['distributions'][0]);
        self::assertCount(2, $issued->snapshot['rows']);
    }

    public function test_draft_can_change_period_and_refresh_its_distributions(): void
    {
        $sheet = $this->createCustomerDraft();
        DB::table('production_deliveries')->where('id', 104)->update(['delivery_date' => '2026-07-31']);
        $updated = app(DeliveryConferenceSheetService::class)->updateDraft($sheet, [
            'period_start' => '2026-08-01', 'period_end' => '2026-08-15', 'grouping_mode' => 'customer',
        ], User::findOrFail(1));

        self::assertSame([101], $updated->distributions()->pluck('production_deliveries.id')->all());
    }

    public function test_units_are_never_summed_together(): void
    {
        DB::table('products')->where('id', 2)->update(['name' => 'Banana', 'unit' => 'caixa']);
        $issued = app(DeliveryConferenceSheetService::class)->issue($this->createCustomerDraft(), User::findOrFail(1));
        self::assertCount(2, $issued->snapshot['rows']);
        self::assertSame(['caixa', 'kg'], collect($issued->snapshot['rows'])->pluck('unit')->sort()->values()->all());
    }

    public function test_organization_detailed_keeps_customers_separate_and_consolidated_mode_sums_them(): void
    {
        $service = app(DeliveryConferenceSheetService::class);
        $project = SalesProject::findOrFail(10);
        $detailed = $service->createDraft($project, [
            'organization_id' => 20, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'grouping_mode' => 'organization_detailed',
        ], User::findOrFail(1));
        $detailed = $service->issue($detailed, User::findOrFail(1));
        self::assertCount(3, $detailed->snapshot['rows']);
        self::assertSame(['Escola A', 'Escola A', 'Escola B'], collect($detailed->snapshot['rows'])->pluck('customer.name')->sort()->values()->all());

        $detailed->forceFill(['status' => DeliveryConferenceStatus::SUPERSEDED])->saveQuietly();
        $consolidated = $service->createDraft($project, [
            'organization_id' => 20, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
            'grouping_mode' => 'organization_consolidated',
        ], User::findOrFail(1));
        $consolidated = $service->issue($consolidated, User::findOrFail(1));
        self::assertCount(2, $consolidated->snapshot['rows']);
        self::assertSame('15.0000', collect($consolidated->snapshot['rows'])->firstWhere('product.id', 1)['quantity']);
    }

    public function test_correction_and_rejection_require_a_reason(): void
    {
        $service = app(DeliveryConferenceSheetService::class);
        $issued = $service->issue($this->createCustomerDraft(), User::findOrFail(1));
        $this->expectException(ValidationException::class);
        $service->review($issued, 'correction_requested', '', User::findOrFail(1));
    }

    public function test_approved_fact_is_preserved_when_material_data_becomes_stale(): void
    {
        $service = app(DeliveryConferenceSheetService::class);
        $sheet = $service->issue($this->createCustomerDraft(), User::findOrFail(1));
        $sheet = $service->review($sheet, 'approved', null, User::findOrFail(1));
        DB::table('production_deliveries')->where('id', 101)->update(['quantity' => 99]);
        self::assertFalse($service->isCurrentlyValid($sheet->fresh()));
        self::assertSame(DeliveryConferenceStatus::APPROVED, $sheet->fresh()->status);
    }

    public function test_distribution_cannot_be_emitted_in_two_active_sheets(): void
    {
        $service = app(DeliveryConferenceSheetService::class);
        $service->issue($this->createCustomerDraft(), User::findOrFail(1));
        $second = $this->createCustomerDraft();
        $this->expectException(ValidationException::class);
        $service->issue($second, User::findOrFail(1));
    }

    public function test_additive_migration_builds_sheet_and_item_constraints(): void
    {
        Schema::drop('delivery_conference_sheet_items');
        Schema::drop('delivery_conference_sheets');

        $migration = require database_path('migrations/2026_08_25_000001_create_delivery_conference_sheets.php');
        $migration->up();

        self::assertTrue(Schema::hasColumns('delivery_conference_sheets', [
            'tenant_id', 'sales_project_id', 'customer_id', 'organization_id', 'snapshot', 'snapshot_hash', 'revision',
        ]));
        self::assertTrue(Schema::hasColumns('delivery_conference_sheet_items', ['delivery_conference_sheet_id', 'distribution_id']));
    }

    public function test_pdf_uses_snapshot_and_prominently_states_no_fiscal_value(): void
    {
        $sheet = app(DeliveryConferenceSheetService::class)->issue($this->createCustomerDraft(), User::findOrFail(1));
        $html = view('pdf.delivery-conference-sheet', ['sheet' => $sheet, 'snapshot' => $sheet->snapshot])->render();

        self::assertStringContainsString('FOLHA DE CONFERÊNCIA', $html);
        self::assertStringContainsString('SEM VALOR FISCAL', $html);
        self::assertStringContainsString('OBSERVAÇÕES / CORREÇÕES', mb_strtoupper($html));
        self::assertStringNotContainsString('Valor unitário', $html);
    }

    private function createCustomerDraft(): DeliveryConferenceSheet
    {
        return app(DeliveryConferenceSheetService::class)->createDraft(SalesProject::findOrFail(10), [
            'customer_id' => 30, 'period_start' => '2026-08-01', 'period_end' => '2026-08-31', 'grouping_mode' => 'customer',
        ], User::findOrFail(1));
    }

    private function seedScenario(): void
    {
        DB::table('users')->insert(['id' => 1, 'name' => 'Operador', 'email' => 'op@example.test', 'password' => bcrypt('secret'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('tenants')->insert([['id' => 1, 'name' => 'Cooperativa A', 'slug' => 'tenant-a'], ['id' => 2, 'name' => 'Cooperativa B', 'slug' => 'tenant-b']]);
        DB::table('tenant_user')->insert(['tenant_id' => 1, 'user_id' => 1, 'tenant_name' => 'Operador', 'roles' => '[]']);
        DB::table('organizations')->insert(['id' => 20, 'tenant_id' => 1, 'name' => 'Município', 'active' => true]);
        DB::table('customers')->insert([
            ['id' => 30, 'tenant_id' => 1, 'organization_id' => 20, 'name' => 'Escola A', 'trade_name' => null, 'status' => true],
            ['id' => 31, 'tenant_id' => 1, 'organization_id' => 20, 'name' => 'Escola B', 'trade_name' => null, 'status' => true],
            ['id' => 32, 'tenant_id' => 2, 'organization_id' => null, 'name' => 'Outro tenant', 'trade_name' => null, 'status' => true],
        ]);
        DB::table('sales_projects')->insert(['id' => 10, 'tenant_id' => 1, 'customer_id' => 30, 'title' => 'PAA 2026', 'reference_year' => 2026, 'receipt_numbering_scope' => 'tenant_year', 'receipt_number_format' => '{prefix}{number}/{year}']);
        DB::table('sales_project_organizations')->insert(['sales_project_id' => 10, 'organization_id' => 20]);
        DB::table('products')->insert([['id' => 1, 'tenant_id' => 1, 'name' => 'Banana', 'unit' => 'kg'], ['id' => 2, 'tenant_id' => 1, 'name' => 'Mamão', 'unit' => 'kg']]);
        $base = ['tenant_id' => 1, 'sales_project_id' => 10, 'delivery_date' => '2026-08-10', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null];
        DB::table('production_deliveries')->insert([
            $base + ['id' => 100, 'parent_delivery_id' => null, 'customer_id' => null, 'product_id' => 1, 'quantity' => 50, 'status' => 'approved'],
            $base + ['id' => 101, 'parent_delivery_id' => 100, 'customer_id' => 30, 'product_id' => 1, 'quantity' => 10, 'status' => 'approved'],
            $base + ['id' => 102, 'parent_delivery_id' => 100, 'customer_id' => 30, 'product_id' => 1, 'quantity' => 7, 'status' => 'pending'],
            $base + ['id' => 103, 'parent_delivery_id' => 100, 'customer_id' => 30, 'product_id' => 1, 'quantity' => 3, 'status' => 'rejected'],
            $base + ['id' => 104, 'parent_delivery_id' => 100, 'customer_id' => 30, 'product_id' => 2, 'quantity' => 4, 'status' => 'approved'],
            $base + ['id' => 105, 'parent_delivery_id' => 100, 'customer_id' => 31, 'product_id' => 1, 'quantity' => 5, 'status' => 'approved'],
            ['id' => 106, 'tenant_id' => 2, 'sales_project_id' => 10, 'delivery_date' => '2026-08-10', 'parent_delivery_id' => 100, 'customer_id' => 32, 'product_id' => 1, 'quantity' => 90, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
        ]);
    }

    private function schema(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email');
            $t->string('password');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->nullable();
            $t->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('user_id');
            $t->string('tenant_name')->nullable();
            $t->json('roles')->nullable();
        });
        Schema::create('sales_projects', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->string('title');
            $t->integer('reference_year')->nullable();
            $t->string('receipt_numbering_scope')->nullable();
            $t->string('receipt_number_format')->nullable();
            $t->softDeletes();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('name');
            $t->boolean('active')->default(true);
            $t->softDeletes();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->string('name');
            $t->string('trade_name')->nullable();
            $t->boolean('status')->default(true);
            $t->softDeletes();
        });
        Schema::create('sales_project_customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sales_project_id');
            $t->unsignedBigInteger('customer_id');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('sales_project_organizations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('sales_project_id');
            $t->unsignedBigInteger('organization_id');
            $t->text('notes')->nullable();
            $t->boolean('enforce_request_limits')->default(false);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->string('name');
            $t->string('unit');
            $t->softDeletes();
        });
        Schema::create('production_deliveries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('sales_project_id');
            $t->unsignedBigInteger('parent_delivery_id')->nullable();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('billing_receipt_id')->nullable();
            $t->date('delivery_date');
            $t->decimal('quantity', 12, 4);
            $t->string('status');
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('receipt_number_sequences', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('sales_project_id')->nullable();
            $t->string('scope_key');
            $t->string('receipt_type');
            $t->integer('receipt_year');
            $t->integer('last_number')->default(0);
            $t->timestamps();
            $t->unique(['tenant_id', 'scope_key', 'receipt_type', 'receipt_year']);
        });
        Schema::create('delivery_conference_sheets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('sales_project_id');
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('organization_id')->nullable();
            $t->integer('receipt_year')->nullable();
            $t->integer('receipt_number')->nullable();
            $t->string('number')->nullable();
            $t->date('period_start');
            $t->date('period_end');
            $t->string('grouping_mode');
            $t->string('status');
            $t->integer('snapshot_version')->nullable();
            $t->json('snapshot')->nullable();
            $t->string('snapshot_hash')->nullable();
            $t->timestamp('issued_at')->nullable();
            $t->unsignedBigInteger('issued_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->text('review_note')->nullable();
            $t->timestamp('invalidated_at')->nullable();
            $t->text('invalidation_reason')->nullable();
            $t->integer('revision')->default(1);
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('delivery_conference_sheet_items', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('delivery_conference_sheet_id');
            $t->unsignedBigInteger('distribution_id');
            $t->timestamps();
            $t->unique(['delivery_conference_sheet_id', 'distribution_id']);
        });
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description');
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('event')->nullable();
            $t->string('causer_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->json('properties')->nullable();
            $t->char('batch_uuid', 36)->nullable();
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->timestamps();
        });
    }
}
