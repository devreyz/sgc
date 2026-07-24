<?php

namespace Tests\Feature;

use App\Models\Associate;
use App\Models\ProductionDelivery;
use App\Models\ProjectAssociateProductLimit;
use App\Models\ProjectDemand;
use App\Models\SalesProject;
use App\Services\AssociateProjectLimitService;
use App\Services\ProjectDistributionCustomerService;
use App\Services\TenantResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AssociateProjectLimitServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        activity()->disableLogging();

        foreach (['production_deliveries', 'project_demands', 'project_associate_product_limits', 'project_associates', 'sales_project_organizations', 'sales_project_customers', 'price_table_items', 'price_tables', 'customers', 'organizations', 'products', 'associates', 'sales_projects', 'tenants'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tenants', fn (Blueprint $t) => $this->base($t, ['name', 'slug']));
        Schema::create('sales_projects', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('tenant_id'); $t->string('title'); $t->unsignedBigInteger('customer_id')->nullable();
            $t->boolean('restrict_participants')->default(false); $t->decimal('max_total_value_per_associate', 14, 2)->nullable();
            $t->decimal('total_value', 14, 2)->nullable();
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
            $t->unsignedBigInteger('project_demand_id')->nullable(); $t->unsignedBigInteger('customer_id')->nullable();
            $t->unsignedBigInteger('parent_delivery_id')->nullable(); $t->date('delivery_date'); $t->decimal('quantity', 12, 3); $t->decimal('unit_price', 14, 4)->default(0);
            $t->decimal('cost_price_used', 14, 4)->nullable(); $t->decimal('admin_fee_percentage', 8, 2)->nullable();
            $t->decimal('gross_value', 14, 4)->default(0); $t->decimal('admin_fee_amount', 14, 4)->nullable(); $t->decimal('net_value', 14, 4)->nullable();
            $t->unsignedBigInteger('price_table_id')->nullable(); $t->string('price_source')->nullable();
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

    public function test_reception_resolves_tenant_before_saving_validation(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $this->mock(TenantResolver::class, function ($mock): void {
            $mock->shouldReceive('resolve')->once()->andReturn(1);
        });

        $delivery = ProductionDelivery::query()->create([
            'tenant_id' => $project->tenant_id,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
            'product_id' => $product,
            'delivery_date' => now()->toDateString(),
            'quantity' => 10,
            'unit_price' => 0,
            'status' => 'pending',
        ]);

        $this->assertSame((int) $project->tenant_id, (int) $delivery->tenant_id);
        $this->assertSame((int) $project->id, (int) $delivery->sales_project_id);
        $this->assertDatabaseHas('production_deliveries', [
            'id' => $delivery->id,
            'tenant_id' => $project->tenant_id,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
        ]);
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

    public function test_blocked_participant_is_rejected_in_restricted_project(): void
    {
        [$project, $associate] = $this->fixture(false);
        $project->update(['restrict_participants' => true]);
        DB::table('project_associates')->insert([
            'tenant_id' => 1,
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
            'status' => 'blocked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(AssociateProjectLimitService::class)->assertContext($project->fresh(), $associate);
            $this->fail('Participante bloqueado nao deveria acessar o projeto restrito.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
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

    public function test_simulated_product_limits_respect_associate_financial_ceiling(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $service = app(AssociateProjectLimitService::class);
        $service->setFinancialLimit($project, $associate, 400);

        $this->expectException(ValidationException::class);
        $service->setProductLimit($project->fresh(), $associate, $product, 100);
    }

    public function test_simulated_product_limits_respect_project_financial_ceiling(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $project->update(['total_value' => 450]);

        $this->expectException(ValidationException::class);
        app(AssociateProjectLimitService::class)
            ->setProductLimit($project->fresh(), $associate, $product, 100);
    }

    public function test_financial_limit_cannot_be_reduced_below_simulated_product_limits(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $service = app(AssociateProjectLimitService::class);
        $service->setProductLimit($project, $associate, $product, 100);

        $this->assertSame(500.0, $service->simulatedBudgetSummary($project, $associate)['planned_value']);

        $this->expectException(ValidationException::class);
        $service->setFinancialLimit($project, $associate, 499);
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

    public function test_delivery_model_rejects_product_outside_demand_and_associate_limits(): void
    {
        [$project, $associate, $allowedProduct] = $this->fixture(false);
        $project->update(['allow_any_product' => false]);
        DB::table('project_demands')->insert([
            'tenant_id' => 1,
            'sales_project_id' => $project->id,
            'product_id' => $allowedProduct,
            'target_quantity' => 50,
            'delivered_quantity' => 0,
            'unit_price' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => 2,
            'tenant_id' => 1,
            'name' => 'Produto nao autorizado',
            'unit' => 'kg',
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('price_table_items')->insert([
            'price_table_id' => 1,
            'product_id' => 2,
            'sale_price' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delivery = new ProductionDelivery([
            'sales_project_id' => $project->id,
            'associate_id' => $associate->id,
            'product_id' => 2,
            'delivery_date' => now(),
            'quantity' => 5,
            'status' => 'pending',
        ]);
        $delivery->tenant_id = 1;

        $this->expectException(ValidationException::class);
        $delivery->save();
    }

    public function test_sum_of_associate_product_limits_cannot_exceed_project_demand(): void
    {
        [$project, $firstAssociate, $product] = $this->fixture(false);
        DB::table('associates')->insert([
            ['id' => 2, 'tenant_id' => 1, 'cpf_cnpj' => '2', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'tenant_id' => 1, 'cpf_cnpj' => '3', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $secondAssociate = Associate::findOrFail(2);
        DB::table('project_demands')->insert([
            'tenant_id' => 1,
            'sales_project_id' => $project->id,
            'product_id' => $product,
            'target_quantity' => 100,
            'delivered_quantity' => 0,
            'unit_price' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            'tenant_id' => 1,
            'sales_project_id' => $project->id,
            'associate_id' => 3,
            'product_id' => $product,
            'delivery_date' => now(),
            'quantity' => 10,
            'unit_price' => 0,
            'gross_value' => 0,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(AssociateProjectLimitService::class);
        $service->setProductLimit($project, $firstAssociate, $product, 60);
        $service->setProductLimit($project, $secondAssociate, $product, 30);

        $summary = $service->productAllocationSummary($project, $product, $firstAssociate->id);
        $this->assertSame(100.0, $summary['project_maximum']);
        $this->assertSame(40.0, $summary['allocated_to_others']);
        $this->assertSame(10.0, $summary['unallocated_delivered_to_others']);
        $this->assertSame(60.0, $summary['available_for_associate']);

        $batchSummary = $service->productAllocationSummaries(
            $project,
            collect([$product]),
            $firstAssociate->id,
        )->get($product);
        $this->assertSame($summary, $batchSummary);

        try {
            $service->setProductLimit($project, $firstAssociate, $product, 60.001);
            $this->fail('O limite agregado acima da demanda deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('max_quantity', $exception->errors());
        }

        $this->assertSame(60.0, (float) ProjectAssociateProductLimit::query()
            ->where('associate_id', $firstAssociate->id)
            ->where('product_id', $product)
            ->value('max_quantity'));
    }

    public function test_eligible_products_only_include_items_priced_for_project_customers(): void
    {
        [$project, $associate, $pricedProduct] = $this->fixture(false);
        DB::table('products')->insert([
            ['id' => 2, 'tenant_id' => 1, 'name' => 'Polpa de Acerola', 'unit' => 'kg', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'tenant_id' => 1, 'name' => 'Acerola Processada', 'unit' => 'kg', 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = app(AssociateProjectLimitService::class);
        $eligible = $service->eligibleProducts($project->fresh(), $associate);

        $this->assertSame([$pricedProduct], $eligible->pluck('product_id')->all());

        $this->expectException(ValidationException::class);
        $service->validateDelivery($project->fresh(), $associate, 2, 1);
    }

    public function test_priced_products_are_combined_across_all_authorized_customers(): void
    {
        [$project, $associate, $firstProduct] = $this->fixture(true);
        DB::table('price_tables')->insert([
            'id' => 2, 'tenant_id' => 1, 'name' => 'Tabela B', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => 2, 'tenant_id' => 1, 'name' => 'Polpa de Acerola', 'unit' => 'kg', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('price_table_items')->insert([
            'price_table_id' => 2, 'product_id' => 2, 'sale_price' => 7,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('customers')->where('id', 2)->update(['price_table_id' => 2]);

        $eligible = app(AssociateProjectLimitService::class)
            ->eligibleProducts($project->fresh(), $associate)
            ->pluck('product_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$firstProduct, 2], $eligible);
    }

    public function test_demand_price_is_automatic_and_parent_delivery_stays_non_financial(): void
    {
        [$project, $associate, $product] = $this->fixture(false);
        $demand = new ProjectDemand([
            'sales_project_id' => $project->id,
            'product_id' => $product,
            'customer_id' => 1,
            'target_quantity' => 100,
            'unit_price' => 999,
        ]);
        $demand->tenant_id = 1;
        $demand->save();

        $this->assertSame(5.0, (float) $demand->unit_price);

        $parent = new ProductionDelivery([
            'sales_project_id' => $project->id,
            'project_demand_id' => $demand->id,
            'associate_id' => $associate->id,
            'product_id' => $product,
            'delivery_date' => now(),
            'quantity' => 10,
            'unit_price' => 999,
            'status' => 'approved',
        ]);
        $parent->tenant_id = 1;
        $parent->save();

        $this->assertSame(0.0, (float) $parent->unit_price);
        $this->assertSame(0.0, (float) $parent->net_value);
        $this->assertSame(0.0, (float) $demand->fresh()->delivered_quantity);
    }

    public function test_demand_progress_uses_only_approved_distributions_for_its_destination(): void
    {
        [$project, $associate, $product] = $this->fixture(true);
        $demand = new ProjectDemand([
            'sales_project_id' => $project->id,
            'product_id' => $product,
            'customer_id' => 1,
            'target_quantity' => 20,
        ]);
        $demand->tenant_id = 1;
        $demand->save();

        $parentId = DB::table('production_deliveries')->insertGetId([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'project_demand_id' => $demand->id,
            'associate_id' => $associate->id, 'product_id' => $product, 'delivery_date' => now(),
            'quantity' => 20, 'unit_price' => 0, 'gross_value' => 0, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('production_deliveries')->insert([
            [
                'tenant_id' => 1, 'sales_project_id' => $project->id, 'project_demand_id' => $demand->id,
                'associate_id' => $associate->id, 'product_id' => $product, 'customer_id' => 1,
                'parent_delivery_id' => $parentId, 'delivery_date' => now(), 'quantity' => 7,
                'unit_price' => 5, 'gross_value' => 35, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tenant_id' => 1, 'sales_project_id' => $project->id, 'project_demand_id' => $demand->id,
                'associate_id' => $associate->id, 'product_id' => $product, 'customer_id' => 2,
                'parent_delivery_id' => $parentId, 'delivery_date' => now(), 'quantity' => 5,
                'unit_price' => 5, 'gross_value' => 25, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'tenant_id' => 1, 'sales_project_id' => $project->id, 'project_demand_id' => $demand->id,
                'associate_id' => $associate->id, 'product_id' => $product, 'customer_id' => 1,
                'parent_delivery_id' => $parentId, 'delivery_date' => now(), 'quantity' => 3,
                'unit_price' => 5, 'gross_value' => 15, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $demand->updateDeliveredQuantity();

        $this->assertSame(7.0, (float) $demand->fresh()->delivered_quantity);
    }

    public function test_specific_customer_demand_rejects_distribution_to_another_customer(): void
    {
        [$project, $associate, $product] = $this->fixture(true);
        $demand = new ProjectDemand([
            'sales_project_id' => $project->id,
            'product_id' => $product,
            'customer_id' => 1,
            'target_quantity' => 20,
        ]);
        $demand->tenant_id = 1;
        $demand->save();

        $parentId = DB::table('production_deliveries')->insertGetId([
            'tenant_id' => 1, 'sales_project_id' => $project->id, 'project_demand_id' => $demand->id,
            'associate_id' => $associate->id, 'product_id' => $product, 'delivery_date' => now(),
            'quantity' => 20, 'unit_price' => 0, 'gross_value' => 0, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $distribution = new ProductionDelivery([
            'sales_project_id' => $project->id,
            'project_demand_id' => $demand->id,
            'associate_id' => $associate->id,
            'product_id' => $product,
            'customer_id' => 2,
            'parent_delivery_id' => $parentId,
            'delivery_date' => now(),
            'quantity' => 5,
            'unit_price' => 5,
            'status' => 'approved',
        ]);
        $distribution->tenant_id = 1;

        $this->expectException(ValidationException::class);
        $distribution->save();
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
