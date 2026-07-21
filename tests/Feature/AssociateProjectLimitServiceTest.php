<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\SalesProject;
use App\Services\AssociateProjectLimitService;
use App\Services\ProjectDistributionCustomerService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class AssociateProjectLimitServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['production_deliveries', 'project_demands', 'project_associate_product_limits', 'project_associates', 'sales_project_organizations', 'sales_project_customers', 'price_table_items', 'price_tables', 'customers', 'organizations', 'products', 'associates', 'sales_projects', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tenants', fn (Blueprint $t) => $this->base($t, ['name', 'slug']));
        Schema::create('sales_projects', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('title'); $t->unsignedBigInteger('customer_id')->nullable();
            $t->boolean('restrict_participants')->default(false); $t->decimal('max_total_value_per_associate', 14, 2)->nullable();
            $t->string('status')->default('active'); $t->boolean('allow_any_product')->default(true); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $t->string('trade_name')->nullable();
            $t->unsignedBigInteger('price_table_id')->nullable(); $t->unsignedBigInteger('organization_id')->nullable(); $t->boolean('status')->default(true); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('organizations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $t->string('short_name')->nullable(); $t->boolean('active')->default(true); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('sales_project_customers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('sales_project_id'); $t->unsignedBigInteger('customer_id'); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('sales_project_organizations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('sales_project_id'); $t->unsignedBigInteger('organization_id'); $t->text('notes')->nullable();
            $t->boolean('enforce_request_limits')->default(false); $t->timestamps();
        });
        Schema::create('associates', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('user_id')->nullable(); $t->string('cpf_cnpj')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $t->string('unit')->default('kg'); $t->boolean('status')->default(true); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('price_tables', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('name'); $t->boolean('active')->default(true); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('price_table_items', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('price_table_id'); $t->unsignedBigInteger('product_id');
            $t->decimal('sale_price', 14, 4); $t->decimal('cost_price', 14, 4)->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('project_associates', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('sales_project_id'); $t->unsignedBigInteger('associate_id');
            $t->decimal('financial_limit', 14, 2)->nullable(); $t->string('status')->default('active'); $t->text('notes')->nullable();
            $t->date('valid_from')->nullable(); $t->date('valid_until')->nullable(); $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('project_associate_product_limits', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('sales_project_id'); $t->unsignedBigInteger('associate_id'); $t->unsignedBigInteger('product_id');
            $t->decimal('max_quantity', 12, 4); $t->decimal('reference_unit_price', 14, 4)->nullable(); $t->string('status')->default('active');
            $t->text('notes')->nullable(); $t->timestamp('archived_at')->nullable(); $t->unsignedBigInteger('archived_by')->nullable(); $t->string('archive_reason')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->unsignedBigInteger('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('project_demands', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('sales_project_id'); $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('customer_id')->nullable(); $t->decimal('target_quantity', 12, 3); $t->decimal('delivered_quantity', 12, 3)->default(0);
            $t->decimal('unit_price', 14, 4)->default(0); $t->date('delivery_start')->nullable(); $t->date('delivery_end')->nullable();
            $t->string('frequency')->nullable(); $t->text('notes')->nullable(); $t->timestamps(); $t->softDeletes();
        });
        Schema::create('production_deliveries', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->unsignedBigInteger('sales_project_id'); $t->unsignedBigInteger('associate_id'); $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('parent_delivery_id')->nullable(); $t->date('delivery_date'); $t->decimal('quantity', 12, 3); $t->decimal('unit_price', 14, 4)->default(0);
            $t->decimal('gross_value', 14, 4)->default(0); $t->decimal('admin_fee_amount', 14, 4)->nullable(); $t->decimal('net_value', 14, 4)->nullable();
            $t->string('status')->default('pending'); $t->timestamps(); $t->softDeletes();
        });
    }

    public function test_single_customer_uses_product_limit_and_parent_delivery_does_not_consume_financial_limit(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        DB::table('project_associate_product_limits')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id,
            'product_id' => $product, 'max_quantity' => 100, 'reference_unit_price' => 5, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id, 'product_id' => $product,
            'delivery_date' => now(), 'quantity' => 80, 'unit_price' => 50, 'gross_value' => 4000, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(AssociateProjectLimitService::class);
        $this->assertSame('single_customer', $service->projectMode($project)['mode']);
        $this->assertSame(0.0, $service->consumedFinancialValue($project, $associate));
        $service->validateDelivery($project, $associate, $product, 20);

        $this->expectException(ValidationException::class);
        $service->validateDelivery($project, $associate, $product, 20.001);
    }

    public function test_multiple_customers_disable_product_limits_and_financial_limit_uses_distributions(): void
    {
        [$project, $associate, $product] = $this->fixture(true);
        DB::table('project_associates')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id,
            'financial_limit' => 500, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $parentId = DB::table('production_deliveries')->insertGetId([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id, 'product_id' => $product,
            'delivery_date' => now(), 'quantity' => 100, 'unit_price' => 99, 'gross_value' => 9900, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id, 'product_id' => $product,
            'parent_delivery_id' => $parentId, 'delivery_date' => now(), 'quantity' => 40, 'unit_price' => 10, 'gross_value' => 400,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(AssociateProjectLimitService::class);
        $this->assertSame('multiple_customers', $service->projectMode($project)['mode']);
        $this->assertFalse($service->projectMode($project)['allows_product_limits']);
        $this->assertSame(400.0, $service->consumedFinancialValue($project, $associate));
        $service->validateDistribution($project, $associate, 100);

        $this->expectException(ValidationException::class);
        $service->validateDistribution($project, $associate, 100.01);
    }

    public function test_cross_tenant_context_is_rejected(): void
    {
        [$project] = $this->fixture(false);
        $other = new Associate(['cpf_cnpj' => 'x']);
        $other->forceFill(['id' => 99, 'tenant_id' => 2]);

        $this->expectException(NotFoundHttpException::class);
        app(AssociateProjectLimitService::class)->assertContext($project, $other);
    }

    public function test_association_creation_always_writes_project_associate_and_tenant_keys(): void
    {
        [$project, $associate] = $this->fixture(false);

        $link = app(AssociateProjectLimitService::class)->association($project, $associate, true);

        $this->assertSame(1, (int) $link->tenant_id);
        $this->assertSame($project->id, (int) $link->sales_project_id);
        $this->assertSame($associate->id, (int) $link->associate_id);
        $this->assertDatabaseHas('project_associates', [
            'tenant_id' => 1,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
            'status' => 'active',
        ]);
    }

    public function test_individual_product_limit_works_without_project_demand(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $project->update(['allow_any_product' => false]);
        DB::table('project_associate_product_limits')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id,
            'product_id' => $product, 'max_quantity' => 25, 'reference_unit_price' => 5, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(AssociateProjectLimitService::class);
        $eligible = $service->eligibleProducts($project->fresh(), $associate);

        $this->assertSame([$product], $eligible->pluck('product_id')->all());
        $this->assertSame(25.0, $eligible->first()['associate_remaining']);
        $service->validateDelivery($project->fresh(), $associate, $product, 25);
    }

    public function test_project_demand_remains_the_general_ceiling(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $project->update(['allow_any_product' => false]);
        DB::table('project_demands')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'product_id' => $product,
            'target_quantity' => 50, 'delivered_quantity' => 0, 'unit_price' => 5,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_associate_product_limits')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id,
            'product_id' => $product, 'max_quantity' => 100, 'reference_unit_price' => 5, 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'associate_id' => $associate->id, 'product_id' => $product,
            'delivery_date' => now(), 'quantity' => 45, 'unit_price' => 0, 'gross_value' => 0, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(AssociateProjectLimitService::class);
        $service->validateDelivery($project->fresh(), $associate, $product, 5);

        $this->expectException(ValidationException::class);
        $service->validateDelivery($project->fresh(), $associate, $product, 5.001);
    }

    public function test_distribution_customers_are_scoped_to_project_customers_and_organizations(): void
    {
        [$project] = $this->fixture(false);
        DB::table('organizations')->insert([
            ['id' => 1, 'tenant_id' => 1, 'name' => 'Organizacao A', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'tenant_id' => 1, 'name' => 'Organizacao B', 'active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('customers')->insert([
            ['id' => 2, 'tenant_id' => 1, 'name' => 'Cliente A2', 'organization_id' => 1, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'tenant_id' => 1, 'name' => 'Cliente B1', 'organization_id' => 2, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'tenant_id' => 1, 'name' => 'Cliente individual adicional', 'organization_id' => null, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'tenant_id' => 1, 'name' => 'Cliente individual nao vinculado', 'organization_id' => null, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'tenant_id' => 2, 'name' => 'Cliente de outro tenant', 'organization_id' => null, 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('sales_project_customers')->insert([
            'sales_project_id' => $project->id, 'customer_id' => 4, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_project_organizations')->insert([
            'sales_project_id' => $project->id, 'organization_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $service = app(ProjectDistributionCustomerService::class);
        $ids = $service->ids($project->fresh());

        $this->assertSame([1, 2, 4], $ids->sort()->values()->all());
        $this->assertFalse($ids->contains(3));
        $this->assertFalse($ids->contains(5));
        $this->assertFalse($ids->contains(6));

        $service->assertAllowed($project->fresh(), [1, 2, 4]);

        $this->expectException(ValidationException::class);
        $service->assertAllowed($project->fresh(), [5]);
    }

    public function test_project_without_customers_or_organizations_does_not_expose_tenant_customers(): void
    {
        [$project] = $this->fixture(false);
        $project->update(['customer_id' => null]);
        DB::table('organizations')->insert([
            'id' => 1, 'tenant_id' => 1, 'name' => 'Organizacao A', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customers')->insert([
            'id' => 2, 'tenant_id' => 1, 'name' => 'Cliente organizacional',
            'organization_id' => 1, 'status' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([], app(ProjectDistributionCustomerService::class)->ids($project->fresh())->all());
    }

    private function fixture(bool $withSecondCustomer): array
    {
        session(['tenant_id' => 1]);
        DB::table('tenants')->insert(['id' => 1, 'name' => 'Tenant', 'slug' => 'tenant', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('price_tables')->insert(['id' => 1, 'tenant_id' => 1, 'name' => 'Tabela', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('products')->insert(['id' => 1, 'tenant_id' => 1, 'name' => 'Banana', 'unit' => 'kg', 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('price_table_items')->insert(['price_table_id' => 1, 'product_id' => 1, 'sale_price' => 5, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('customers')->insert(['id' => 1, 'tenant_id' => 1, 'name' => 'Cliente A', 'price_table_id' => 1, 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sales_projects')->insert(['id' => 1, 'tenant_id' => 1, 'title' => 'Projeto', 'customer_id' => 1, 'status' => 'active', 'allow_any_product' => true, 'restrict_participants' => false, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('associates')->insert(['id' => 1, 'tenant_id' => 1, 'cpf_cnpj' => '1', 'created_at' => now(), 'updated_at' => now()]);

        if ($withSecondCustomer) {
            DB::table('customers')->insert(['id' => 2, 'tenant_id' => 1, 'name' => 'Cliente B', 'price_table_id' => 1, 'status' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('sales_project_customers')->insert(['sales_project_id' => 1, 'customer_id' => 2, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [SalesProject::findOrFail(1), Associate::findOrFail(1), 1];
    }

    private function base(Blueprint $table, array $strings): void
    {
        $table->id();
        foreach ($strings as $string) {
            $table->string($string);
        }
        $table->timestamps();
    }
}
