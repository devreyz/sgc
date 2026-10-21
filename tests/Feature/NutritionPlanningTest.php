<?php

namespace Tests\Feature;

use App\Exports\ConsumptionReportExport;
use App\Models\BeneficiaryGroup;
use App\Models\DistributionConsumptionProfile;
use App\Models\FoodProfile;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use App\Services\Planning\ConsumptionReportService;
use App\Services\Planning\NutritionCatalogService;
use App\Services\Planning\NutritionReportService;
use App\Services\Planning\ProjectPlanningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class NutritionPlanningTest extends TestCase
{
    use \Tests\Concerns\BuildsDeliveryReportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDeliveryReportSchema();
        activity()->disableLogging();
        session(['tenant_id' => 1]);
        (require database_path('migrations/2026_10_18_000001_create_project_planning_tables.php'))->up();
        (require database_path('migrations/2026_10_18_000002_create_project_nutrition_tables.php'))->up();
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('shelf_life_days')->nullable());
        Schema::table('production_deliveries', fn (Blueprint $table) => $table->unsignedBigInteger('stock_movement_id')->nullable());
        DB::table('production_deliveries')->where('id', 91)->update(['delivery_date' => '2026-09-29', 'quantity' => 350]);
        DB::table('production_deliveries')->where('id', 92)->update(['delivery_date' => '2026-10-06', 'quantity' => 360]);
        DB::table('production_deliveries')->where('id', 90)->update(['quantity' => 1000]);
        Schema::create('customer_billing_receipts', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sales_project_id'); $table->decimal('total_gross', 16, 4); $table->decimal('total_fees', 16, 4);
            $table->decimal('total_net', 16, 4); $table->json('delivery_ids'); $table->string('status'); $table->string('receipt_label')->nullable();
            $table->unsignedInteger('receipt_year')->default(2026); $table->unsignedInteger('receipt_number')->default(1); $table->timestamps();
        });
        DB::table('customer_billing_receipts')->insert(['id' => 1, 'tenant_id' => 1, 'customer_id' => 80, 'sales_project_id' => 10,
            'total_gross' => '1750', 'total_fees' => '10', 'total_net' => '1740', 'delivery_ids' => '[91]', 'status' => 'draft']);
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('associate_receipt_id')->nullable(); $table->unsignedBigInteger('billing_receipt_id')->nullable();
        });
    }

    private function project(): SalesProject { return SalesProject::findOrFail(10); }
    private function filters(array $overrides = []): array
    {
        return array_replace(['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'analysis_mode' => 'nutrition', 'competency' => 'consumption', 'type' => 'product'], $overrides);
    }
    private function group(int $customer = 80): BeneficiaryGroup
    {
        return app(ProjectPlanningService::class)->createGroup($this->project(), ['name' => 'Grupo '.$customer, 'customer_id' => $customer]);
    }
    private function audience(int $id = 91, ?int $groupId = null, int $people = 500): DistributionConsumptionProfile
    {
        return app(ProjectPlanningService::class)->saveConsumption($this->project(), $id,
            ['count_method' => 'served_people', 'served_people' => $people, 'coverage_days' => 7, 'beneficiary_group_id' => $groupId]);
    }
    private function composition(string $amount = '1.2', string $fraction = '0.8', string $from = '2026-01-01'): FoodProfile
    {
        $catalog = app(NutritionCatalogService::class);
        $nutrient = \App\Models\Nutrient::where('tenant_id', 1)->where('code', 'protein_g')->first()
            ?? $catalog->createNutrient($this->project(), ['code' => 'protein_g', 'name' => 'Proteína', 'unit' => 'g']);
        return $catalog->createFoodProfile($this->project(), ['product_id' => 50, 'name' => 'Composição teste',
            'reference_quantity_g' => '100', 'edible_fraction' => $fraction, 'source' => 'Fixture', 'source_version' => '2026',
            'effective_from' => $from, 'values' => [['nutrient_id' => $nutrient->id, 'amount' => $amount]]]);
    }

    public function test_nutrition_does_not_mutate_distribution_or_frozen_financial_truth(): void
    {
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toJson();
        $billing = DB::table('customer_billing_receipts')->get()->toJson();
        $this->audience(91, $this->group()->id);
        $this->composition();
        $report = app(NutritionReportService::class)->build($this->project(), $this->filters(['customer_ids' => [80]]));
        $this->assertSame([91], array_column($report['rows'], 'id'));
        $nutrient = $report['nutrition']['groups'][0]['nutrients']['protein_g'];
        $this->assertSame(0, bccomp('2400', $nutrient['known_total'], 16));
        $this->assertSame(0, bccomp('0.96', $nutrient['per_person_day'], 16));
        $this->assertSame($before, DB::table('production_deliveries')->orderBy('id')->get()->toJson());
        $this->assertSame($billing, DB::table('customer_billing_receipts')->get()->toJson());
        // Physical billing remains 350 kg, while the consumption allocation is 250 kg.
        $this->assertSame(0, bccomp('350', (string) DB::table('production_deliveries')->where('id', 91)->value('quantity'), 8));
        $this->assertSame(0, bccomp('250000', $report['rows'][0]['quantity_g'], 8));
    }

    public function test_financial_consolidator_keeps_full_physical_quantity_and_customer_fees(): void
    {
        Schema::create('customer_project_fees', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('sales_project_id');
            $table->string('name'); $table->string('type'); $table->string('nature'); $table->decimal('value', 12, 4);
            $table->boolean('active'); $table->unsignedInteger('sort_order')->default(0); $table->timestamps(); $table->softDeletes();
        });
        DB::table('customer_project_fees')->insert(['tenant_id' => 1, 'sales_project_id' => 10, 'name' => 'Taxa cliente',
            'type' => 'percentage', 'nature' => 'discount', 'value' => 10, 'active' => true]);
        $project = $this->project();
        $consolidator = app(\App\Services\CustomerBillingLineConsolidator::class);
        $distributions = ProductionDelivery::where('tenant_id', 1)->where('sales_project_id', 10)->where('customer_id', 80)->with('product')->get();
        $before = $consolidator->consolidate($distributions, $project);
        $this->audience(); $this->composition();
        app(NutritionReportService::class)->build($project, $this->filters(['customer_ids' => [80]]));
        $after = $consolidator->consolidate($distributions->fresh(['product']), $project);
        $this->assertSame($before, $after);
        $this->assertSame('1750.00', $after['total_gross']);
        $this->assertSame('175.00', $after['total_fees']);
        $this->assertSame('1575.00', $after['total_net']);
        $this->assertSame('350', $after['lines'][0]['quantity']);
        $this->assertSame([91], $after['lines'][0]['distribution_ids']);
    }

    public function test_client_filters_and_group_filters_do_not_mix_other_recipients(): void
    {
        $a = $this->group(80); $b = $this->group(81);
        $this->audience(91, $a->id); $this->audience(92, $b->id, 520); $this->composition();
        $service = app(NutritionReportService::class);
        $report = $service->build($this->project(), $this->filters(['customer_ids' => [80], 'beneficiary_group_ids' => [$a->id]]));
        $this->assertSame([91], array_column($report['rows'], 'id'));
        $this->assertSame([80], array_column($report['nutrition']['groups'], 'customer_id'));
        $empty = $service->build($this->project(), $this->filters(['customer_ids' => [80], 'beneficiary_group_ids' => [$b->id]]));
        $this->assertSame([], $empty['rows']);
        $this->assertSame([], $empty['nutrition']['groups']);
        $this->assertStringNotContainsString('Escola B', json_encode($report));
    }

    public function test_foreign_tenant_filter_is_rejected(): void
    {
        DB::table('customers')->insert(['id' => 85, 'tenant_id' => 2, 'name' => 'Privado']);
        $this->expectException(ValidationException::class);
        app(NutritionReportService::class)->build($this->project(), $this->filters(['customer_ids' => [85]]));
    }

    public function test_foreign_nutrient_cannot_be_added_to_tenant_product(): void
    {
        $id = DB::table('nutrients')->insertGetId(['tenant_id' => 2, 'code' => 'private', 'name' => 'Outro', 'unit' => 'g']);
        $this->expectException(ValidationException::class);
        app(NutritionCatalogService::class)->createFoodProfile($this->project(), ['product_id' => 50, 'name' => 'Teste',
            'reference_quantity_g' => '100', 'edible_fraction' => '1', 'source' => 'Teste', 'source_version' => '1',
            'effective_from' => '2026-01-01', 'values' => [['nutrient_id' => $id, 'amount' => '1']]]);
    }

    public function test_customer_reassignment_requires_explicit_audience_confirmation(): void
    {
        $profile = $this->audience();
        $this->assertSame(80, (int) $profile->customer_id_snapshot);
        $delivery = ProductionDelivery::findOrFail(91);
        $delivery->customer_id = 81;
        $delivery->save();
        $report = app(ConsumptionReportService::class)->build($this->project(), array_replace($this->filters(), ['analysis_mode' => 'consumption', 'customer_ids' => [81]]));
        $row = collect($report['rows'])->firstWhere('id', 91);
        $this->assertSame('changed_distribution_context', $row['reason']);
        $this->assertNull($row['person_days']);
        $this->audience(91, null, 510);
        $this->assertSame(81, (int) $profile->fresh()->customer_id_snapshot);
    }

    public function test_legacy_audience_context_is_captured_before_reassignment(): void
    {
        $profile = $this->audience();
        DB::table('distribution_consumption_profiles')->where('id', $profile->id)->update(['customer_id_snapshot' => null, 'product_id_snapshot' => null]);
        ProductionDelivery::findOrFail(91)->update(['customer_id' => 81]);
        $this->assertSame(80, (int) $profile->fresh()->customer_id_snapshot);
    }

    public function test_composition_selection_uses_distribution_date_and_snapshot_is_immutable(): void
    {
        $this->audience(); $this->composition('1.2');
        $this->composition('9', '1', '2026-10-01');
        $service = app(NutritionReportService::class);
        $report = $service->build($this->project(), $this->filters(['customer_ids' => [80]]));
        $this->assertSame(1, (int) $report['rows'][0]['nutrition']['trace']['food_profile']['version']);
        $snapshot = app(ConsumptionReportService::class)->snapshot($this->project(), $report);
        $this->assertSame(2, (int) $snapshot->calculation_version);
        $this->composition('30', '1', '2026-01-01');
        $this->assertSame($report, $snapshot->fresh()->result);
        $this->expectException(\LogicException::class);
        FoodProfile::first()->update(['edible_fraction' => '1']);
    }

    public function test_targets_cannot_use_group_of_another_client(): void
    {
        $group = $this->group(80); $this->composition();
        $this->expectException(ValidationException::class);
        app(NutritionCatalogService::class)->createTargetSet($this->project(), [
            'customer_id' => 81, 'beneficiary_group_id' => $group->id, 'name' => 'Meta', 'source_type' => 'internal',
            'source_reference' => 'Teste', 'effective_from' => '2026-01-01',
            'targets' => [['nutrient_id' => 1, 'min_value' => '1', 'severity' => 'warning']],
        ]);
    }

    public function test_targets_only_evaluate_their_client_and_group_and_unknown_values_are_not_compliant(): void
    {
        $group = $this->group(80); $this->audience(91, $group->id); $this->audience(92); $this->composition();
        $catalog = app(NutritionCatalogService::class);
        $iron = $catalog->createNutrient($this->project(), ['code' => 'iron_mg', 'name' => 'Ferro', 'unit' => 'mg']);
        $catalog->createTargetSet($this->project(), ['customer_id' => 80, 'beneficiary_group_id' => $group->id,
            'name' => 'Meta teste', 'source_type' => 'internal', 'source_reference' => 'Fixture', 'effective_from' => '2026-01-01',
            'targets' => [['nutrient_id' => 1, 'min_value' => '1', 'severity' => 'warning'],
                ['nutrient_id' => $iron->id, 'min_value' => '1', 'severity' => 'critical']]]);
        $report = app(NutritionReportService::class)->build($this->project(), $this->filters());
        $this->assertCount(2, $report['nutrition']['evaluations']);
        $this->assertSame([80], array_values(array_unique(array_column($report['nutrition']['evaluations'], 'customer_id'))));
        $this->assertSame(['warning', 'not_calculable'], array_column($report['nutrition']['evaluations'], 'status'));
        $this->assertNotEmpty(app(NutritionReportService::class)->integrityIssues($report));
        $this->assertFalse($report['blocks_report']);
        $partial = app(NutritionReportService::class)->build($this->project(), $this->filters(['product_ids' => [50]]));
        $this->assertSame('not_calculable', $partial['nutrition']['evaluations'][0]['status']);
        $this->assertSame('partial_nutrition_scope', $partial['nutrition']['evaluations'][0]['reason']);
    }

    public function test_shared_public_is_not_counted_twice_across_products(): void
    {
        $group = $this->group(); $this->audience(91, $group->id); $this->composition();
        DB::table('products')->insert(['id' => 51, 'tenant_id' => 1, 'name' => 'Outro alimento', 'unit' => 'kg']);
        DB::table('production_deliveries')->insert([
            ['id' => 95, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 30, 'product_id' => 51, 'parent_delivery_id' => null,
                'customer_id' => null, 'delivery_date' => '2026-09-29', 'quantity' => 350, 'status' => 'approved'],
            ['id' => 96, 'tenant_id' => 1, 'sales_project_id' => 10, 'associate_id' => 30, 'product_id' => 51, 'parent_delivery_id' => 95,
                'customer_id' => 80, 'delivery_date' => '2026-09-29', 'quantity' => 350, 'status' => 'approved'],
        ]);
        app(NutritionCatalogService::class)->createFoodProfile($this->project(), ['product_id' => 51, 'name' => 'Outro',
            'reference_quantity_g' => '100', 'edible_fraction' => '0.8', 'source' => 'Teste', 'source_version' => '1',
            'effective_from' => '2026-01-01', 'values' => [['nutrient_id' => 1, 'amount' => '1.2']]]);
        $this->audience(96, $group->id);
        $report = app(NutritionReportService::class)->build($this->project(), $this->filters(['customer_ids' => [80]]));
        $groupResult = $report['nutrition']['groups'][0];
        $this->assertSame(0, bccomp('2500', $groupResult['person_days'], 16));
        $this->assertSame(0, bccomp('4800', $groupResult['nutrients']['protein_g']['known_total'], 16));
        $this->assertSame(0, bccomp('1.92', $groupResult['nutrients']['protein_g']['per_person_day'], 16));
        $this->audience(96, $group->id, 520);
        $ambiguous = app(NutritionReportService::class)->build($this->project(), $this->filters(['customer_ids' => [80]]));
        $this->assertNull($ambiguous['nutrition']['groups'][0]['person_days']);
        $this->assertNull($ambiguous['nutrition']['groups'][0]['nutrients']['protein_g']['per_person_day']);
    }

    public function test_nutrition_pdf_and_excel_include_sources_and_separate_clients(): void
    {
        $this->audience(); $this->composition();
        $project = $this->project();
        $report = app(NutritionReportService::class)->build($project, $this->filters(['customer_ids' => [80]]));
        $pdf = Pdf::loadView('pdf.consumption-report', compact('project', 'report'))->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $sheets = (new ConsumptionReportExport($report))->sheets();
        $this->assertContains('Nutrição', array_map(fn ($sheet) => $sheet->title(), $sheets));
        $this->assertContains('Fontes nutricionais', array_map(fn ($sheet) => $sheet->title(), $sheets));
        $bytes = Excel::raw(new ConsumptionReportExport($report), \Maatwebsite\Excel\Excel::XLSX);
        $this->assertStringStartsWith('PK', $bytes);
    }

    public function test_v2_migration_rolls_back_without_removing_financial_or_v1_tables(): void
    {
        (require database_path('migrations/2026_10_18_000002_create_project_nutrition_tables.php'))->down();
        $this->assertFalse(Schema::hasTable('food_profiles'));
        $this->assertTrue(Schema::hasTable('customer_billing_receipts'));
        $this->assertTrue(Schema::hasTable('distribution_consumption_profiles'));
    }

    public function test_nutrition_page_renders_new_forms_report_and_only_project_clients(): void
    {
        $this->audience(); $this->composition();
        Schema::table('tenants', fn (Blueprint $table) => $table->string('slug')->nullable());
        DB::table('tenants')->where('id', 1)->update(['slug' => 'tenant-a']);
        DB::table('customers')->insert(['id' => 88, 'tenant_id' => 1, 'name' => 'Cliente de outro projeto']);
        $request = \Illuminate\Http\Request::create('/tenant-a/delivery/projects/10/planning?analysis_mode=nutrition&date_from=2026-10-01&date_to=2026-10-31&customer_ids[]=80');
        $route = new \Illuminate\Routing\Route('GET', '/{tenant}/delivery/projects/{project}/planning', []);
        $route->bind($request);
        $route->setParameter('tenant', \App\Models\Tenant::findOrFail(1)); $route->setParameter('project', 10);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
        $layoutPath = storage_path('framework/testing/nutrition-layout');
        if (! is_dir($layoutPath.'/layouts')) { mkdir($layoutPath.'/layouts', 0777, true); }
        file_put_contents($layoutPath.'/layouts/bento.blade.php', "@yield('content')");
        app('view')->getFinder()->setPaths([$layoutPath, resource_path('views')]);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $response = app(\App\Http\Controllers\Delivery\ProjectPlanningController::class)->index($request);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Fração comestível', $response->getContent());
        $this->assertStringContainsString('Estimativa nutricional por cliente', $response->getContent());
        $this->assertStringNotContainsString('Cliente de outro projeto', $response->getContent());
    }
}
