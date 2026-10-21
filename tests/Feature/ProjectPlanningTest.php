<?php

namespace Tests\Feature;

use App\Exports\ConsumptionReportExport;
use App\Models\DistributionConsumptionProfile;
use App\Models\PlanningRuleSet;
use App\Models\SalesProject;
use App\Services\Planning\ConsumptionReportService;
use App\Services\Planning\ProjectPlanningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProjectPlanningTest extends \Tests\TestCase
{
    use \Tests\Concerns\BuildsDeliveryReportSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildDeliveryReportSchema();
        activity()->disableLogging();
        session(['tenant_id' => 1]);
        $migration = require database_path('migrations/2026_10_18_000001_create_project_planning_tables.php');
        Schema::disableForeignKeyConstraints();
        $migration->down();
        Schema::enableForeignKeyConstraints();
        $migration->up();
        Schema::table('products', fn (Blueprint $t) => $t->unsignedInteger('shelf_life_days')->nullable());
        Schema::table('production_deliveries', fn (Blueprint $t) => $t->unsignedBigInteger('stock_movement_id')->nullable());
        DB::table('production_deliveries')->where('id', 91)->update(['delivery_date' => '2026-09-29', 'quantity' => '350']);
        DB::table('production_deliveries')->where('id', 92)->update(['delivery_date' => '2026-10-06', 'quantity' => '360']);
    }

    private function project(): SalesProject { return SalesProject::findOrFail(10); }

    public function test_planning_migration_resumes_after_partial_creation_without_losing_existing_data(): void
    {
        foreach (['planning_report_snapshots', 'distribution_daily_audiences', 'distribution_consumption_profiles', 'planning_rules', 'planning_rule_sets', 'product_planning_profiles', 'project_planning_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('beneficiary_population_periods', fn (Blueprint $table) => $table->dropIndex('bpp_group_from_idx'));
        $groupId = DB::table('beneficiary_groups')->insertGetId([
            'tenant_id' => 1, 'sales_project_id' => 10, 'name' => 'Grupo preservado',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('beneficiary_population_periods')->insert([
            'tenant_id' => 1, 'beneficiary_group_id' => $groupId, 'population' => 50,
            'effective_from' => '2026-10-01', 'source' => 'Cadastro existente',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_18_000001_create_project_planning_tables.php');
        $migration->up();
        $migration->up();

        self::assertTrue(Schema::hasIndex('beneficiary_population_periods', 'bpp_group_from_idx'));
        self::assertTrue(Schema::hasTable('planning_report_snapshots'));
        self::assertTrue(Schema::hasTable('distribution_consumption_profiles'));
        self::assertSame(1, DB::table('beneficiary_groups')->where('name', 'Grupo preservado')->count());
        self::assertSame(50, (int) DB::table('beneficiary_population_periods')->where('beneficiary_group_id', $groupId)->value('population'));
        foreach (['beneficiary_groups', 'beneficiary_population_periods', 'project_planning_settings', 'product_planning_profiles', 'planning_rule_sets', 'planning_rules', 'distribution_consumption_profiles', 'distribution_daily_audiences', 'planning_report_snapshots'] as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                self::assertLessThanOrEqual(64, strlen($index['name']), $index['name']);
            }
        }
    }
    private function filters(array $overrides = []): array
    {
        return array_replace(['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'analysis_mode' => 'consumption', 'competency' => 'consumption', 'type' => 'product'], $overrides);
    }
    private function profiles(): void
    {
        $service = app(ProjectPlanningService::class);
        $service->saveConsumption($this->project(), 91, ['count_method' => 'served_people', 'served_people' => 500, 'coverage_days' => 7]);
        $service->saveConsumption($this->project(), 92, ['count_method' => 'served_people', 'served_people' => 520, 'coverage_days' => 7]);
    }

    public function test_planning_report_selects_overlap_and_does_not_mutate_financial_columns(): void
    {
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toArray();
        $this->profiles();
        $reports = app(ConsumptionReportService::class);
        $report = $reports->build($this->project(), $this->filters());
        $this->assertCount(2, $report['rows']);
        $this->assertSame(0, bccomp('610000', $report['summary']['quantity_g'], 8));
        $this->assertSame(0, bccomp('6140', $report['summary']['person_days'], 8));
        $physical = $reports->build($this->project(), $this->filters(['competency' => 'delivery']));
        $this->assertSame([92], array_column($physical['rows'], 'id'));
        $this->assertEquals($before, DB::table('production_deliveries')->orderBy('id')->get()->toArray());
        $this->assertStringNotContainsString('Produto externo', json_encode($report));
    }

    public function test_legacy_distributions_remain_non_calculable_and_standard_report_works(): void
    {
        $report = app(ConsumptionReportService::class)->build($this->project(), $this->filters());
        $this->assertSame('not_calculable', $report['summary']['status']);
        $this->assertNull($report['summary']['daily_per_capita_g']);
        $this->assertSame('missing_profile', $report['rows'][0]['reason']);
        $legacy = app(\App\Services\DeliveryReportService::class)->build($this->project(), ['type' => 'product']);
        $this->assertSame(1, $legacy['totals']['receptions_count']);
    }

    public function test_cross_tenant_distribution_is_rejected_even_without_global_scope(): void
    {
        DB::table('production_deliveries')->insert(['id' => 100, 'tenant_id' => 2, 'sales_project_id' => 20,
            'parent_delivery_id' => 99, 'associate_id' => 40, 'customer_id' => 80, 'product_id' => 60,
            'delivery_date' => '2026-10-01', 'quantity' => 999, 'status' => 'approved']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(ProjectPlanningService::class)->saveConsumption($this->project(), 100, ['count_method' => 'served_people', 'served_people' => 50, 'coverage_days' => 7]);
    }

    public function test_project_context_requires_active_correct_tenant(): void
    {
        $project = $this->project();
        session(['tenant_id' => 2]);
        $this->expectException(HttpException::class);
        app(ConsumptionReportService::class)->build($project, $this->filters());
    }

    public function test_cross_project_and_cross_tenant_groups_and_rules_are_rejected(): void
    {
        $service = app(ProjectPlanningService::class);
        $other = SalesProject::withoutGlobalScopes()->findOrFail(20);
        session(['tenant_id' => 2]);
        $group = $service->createGroup($other, ['name' => 'Grupo externo']);
        session(['tenant_id' => 1]);
        $this->expectException(ValidationException::class);
        $service->saveConsumption($this->project(), 91, ['count_method' => 'served_people', 'served_people' => 500,
            'coverage_days' => 7, 'beneficiary_group_id' => $group->id]);
    }

    public function test_population_history_is_not_consumption_denominator_and_overlaps_are_rejected(): void
    {
        $service = app(ProjectPlanningService::class);
        $project = $this->project();
        $group = $service->createGroup($project, ['name' => 'CRAS']);
        $first = $service->addPopulation($project, $group->id, ['population' => 3000, 'effective_from' => '2026-01-01', 'source' => 'Cadastro']);
        $service->addPopulation($project, $group->id, ['population' => 3120, 'effective_from' => '2026-11-01', 'source' => 'Atualização']);
        $this->assertSame('2026-10-31', $first->fresh()->effective_to->format('Y-m-d'));
        $service->saveConsumption($project, 92, ['count_method' => 'served_people', 'served_people' => 520, 'coverage_days' => 7, 'beneficiary_group_id' => $group->id]);
        $report = app(ConsumptionReportService::class)->build($project, $this->filters());
        $row = collect($report['rows'])->firstWhere('id', 92);
        $this->assertSame(3000, $row['trace']['reference_population']);
        $this->assertSame(0, bccomp('3640', $row['person_days'], 8));
        $this->expectException(ValidationException::class);
        $service->addPopulation($project, $group->id, ['population' => 1, 'effective_from' => '2026-10-15', 'source' => 'Sobreposição']);
    }

    public function test_versioned_rules_and_report_snapshot_remain_reproducible(): void
    {
        $this->profiles();
        $project = $this->project();
        $planning = app(ProjectPlanningService::class);
        $input = ['name' => 'Critério interno', 'source_type' => 'internal', 'source_reference' => 'Acordo teste',
            'effective_from' => '2026-01-01', 'rules' => [['name' => 'Máximo', 'metric' => 'daily_per_capita_g',
                'operator' => '<=', 'value' => '90', 'evaluation_scope' => 'consumption_cycle', 'severity' => 'warning', 'blocking_policy' => 'warning_only']]];
        $set = $planning->createRuleSet($project, $input);
        $reports = app(ConsumptionReportService::class);
        $original = $reports->build($project, $this->filters());
        $snapshot = $reports->snapshot($project, $original);
        $input['rules'][0]['value'] = '500';
        $newSet = $planning->createRuleSet($project, $input);
        $this->assertSame(2, (int) $newSet->version);
        $this->assertSame($original, $snapshot->fresh()->result);
        $this->assertSame('90.00000000', $set->rules()->first()->value);
        $this->assertSame('warning', $snapshot->result['evaluations'][0]['status']);
        $this->assertSame('compliant', $reports->build($project, $this->filters())['evaluations'][0]['status']);
    }

    public function test_product_defaults_snapshot_conversion_and_validity(): void
    {
        $project = $this->project();
        $planning = app(ProjectPlanningService::class);
        DB::table('products')->where('id', 50)->update(['unit' => 'pé']);
        $planning->saveSettings($project, ['default_coverage_days' => 9]);
        $planning->addProductProfile($project, ['product_id' => 50, 'default_coverage_days' => 7, 'shelf_life_days' => 4,
            'mass_conversion_factor' => '250', 'source' => 'Pesagem', 'effective_from' => '2026-01-01']);
        $profile = $planning->saveConsumption($project, 92, ['count_method' => 'served_people', 'served_people' => 200]);
        $this->assertSame('2026-10-12', $profile->coverage_end_date->format('Y-m-d'));
        $this->assertSame('2026-10-09', $profile->expires_at->format('Y-m-d'));
        $this->assertSame('estimated_shelf_life', $profile->expiry_source);
        $planning->addProductProfile($project, ['product_id' => 50, 'default_coverage_days' => 2,
            'mass_conversion_factor' => '999', 'source' => 'Outra pesagem', 'effective_from' => '2026-11-01']);
        $this->assertSame('250.00000000', $profile->fresh()->mass_conversion_factor_snapshot);
        $report = app(ConsumptionReportService::class)->build($project, $this->filters());
        $row = collect($report['rows'])->firstWhere('id', 92);
        $this->assertContains('coverage_exceeds_shelf_life', $row['issues']);
        $this->assertSame('calculated', $row['status']);
    }

    public function test_pdf_and_xlsx_render_and_xlsx_treats_user_names_as_text(): void
    {
        $this->profiles();
        DB::table('products')->where('id', 50)->update(['name' => '=1+1']);
        $project = $this->project();
        $report = app(ConsumptionReportService::class)->build($project, $this->filters());
        $pdf = Pdf::loadView('pdf.consumption-report', compact('project', 'report'))->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $bytes = Excel::raw(new ConsumptionReportExport($report), \Maatwebsite\Excel\Excel::XLSX);
        $this->assertStringStartsWith('PK', $bytes);
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $cell = $book->getActiveSheet()->getCell('C2');
        (new ConsumptionReportExport($report))->sheets()[1]->bindValue($cell, '=1+1');
        $this->assertSame('s', $cell->getDataType());
    }

    public function test_daily_attendance_is_validated_and_saved_with_tenant(): void
    {
        $profile = app(ProjectPlanningService::class)->saveConsumption($this->project(), 92, [
            'count_method' => 'daily_attendance', 'coverage_days' => 2, 'daily_audiences' => [
                ['date' => '2026-10-06', 'served_people' => 80], ['date' => '2026-10-07', 'served_people' => 0],
            ],
        ]);
        $this->assertSame([1, 1], $profile->dailyAudiences()->pluck('tenant_id')->all());
        $report = app(ConsumptionReportService::class)->build($this->project(), $this->filters());
        $this->assertSame(0, bccomp('80', collect($report['rows'])->firstWhere('id', 92)['person_days'], 8));
        $this->expectException(ValidationException::class);
        app(ProjectPlanningService::class)->saveConsumption($this->project(), 92, [
            'count_method' => 'daily_attendance', 'coverage_days' => 2,
            'daily_audiences' => [['date' => '2026-11-01', 'served_people' => 80]],
        ]);
    }

    public function test_snapshot_and_rules_cannot_be_overwritten(): void
    {
        $this->profiles();
        $reports = app(ConsumptionReportService::class);
        $snapshot = $reports->snapshot($this->project(), $reports->build($this->project(), $this->filters()));
        $this->expectException(\LogicException::class);
        $snapshot->update(['result' => ['tampered' => true]]);
    }

    public function test_historical_cycle_uses_rule_effective_before_report_start_and_block_is_report_only(): void
    {
        $this->profiles();
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toJson();
        app(ProjectPlanningService::class)->createRuleSet($this->project(), [
            'name' => 'Regra setembro', 'source_type' => 'internal', 'source_reference' => 'Teste',
            'effective_from' => '2026-09-01', 'effective_to' => '2026-09-30',
            'rules' => [['name' => 'Máximo', 'metric' => 'daily_per_capita_g', 'operator' => '<=', 'value' => '90',
                'evaluation_scope' => 'consumption_cycle', 'severity' => 'critical', 'blocking_policy' => 'report_block']],
        ]);
        $report = app(ConsumptionReportService::class)->build($this->project(), $this->filters());
        $this->assertCount(1, $report['evaluations']);
        $this->assertTrue($report['blocks_report']);
        $this->assertSame(91, $report['evaluations'][0]['distribution_id']);
        $this->assertSame($before, DB::table('production_deliveries')->orderBy('id')->get()->toJson());
    }

    public function test_group_linked_to_other_customer_cannot_be_used(): void
    {
        $groupId = DB::table('beneficiary_groups')->insertGetId(['tenant_id' => 1, 'sales_project_id' => 10, 'customer_id' => 81, 'name' => 'Outra escola']);
        $this->expectException(ValidationException::class);
        app(ProjectPlanningService::class)->saveConsumption($this->project(), 91, [
            'count_method' => 'served_people', 'served_people' => 500, 'coverage_days' => 7, 'beneficiary_group_id' => $groupId,
        ]);
    }

    public function test_stock_expiry_has_priority_over_manual_date_and_delayed_consumption(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('tenant_id'); $table->unsignedBigInteger('product_id');
            $table->date('expiry_date'); $table->timestamps(); $table->softDeletes();
        });
        DB::table('stock_movements')->insert(['id' => 1, 'tenant_id' => 1, 'product_id' => 50, 'expiry_date' => '2026-10-08']);
        DB::table('production_deliveries')->where('id', 90)->update(['stock_movement_id' => 1]);
        $profile = app(ProjectPlanningService::class)->saveConsumption($this->project(), 92, [
            'count_method' => 'served_people', 'served_people' => 500, 'coverage_start_date' => '2026-10-10',
            'coverage_end_date' => '2026-10-11', 'expires_at' => '2026-12-31',
        ]);
        $this->assertSame('2026-10-08', $profile->expires_at->format('Y-m-d'));
        $this->assertSame('stock', $profile->expiry_source);
        $report = app(ConsumptionReportService::class)->build($this->project(), $this->filters());
        $this->assertContains('expired_product', collect($report['rows'])->firstWhere('id', 92)['issues']);
    }

    public function test_planning_page_renders_forms_and_controller_returns_private_response(): void
    {
        $request = \Illuminate\Http\Request::create('/tenant-a/delivery/projects/10/planning');
        $tenant = \App\Models\Tenant::findOrFail(1);
        $tenant->slug = 'tenant-a';
        Schema::table('tenants', fn (Blueprint $t) => $t->string('slug')->nullable());
        DB::table('tenants')->where('id', 1)->update(['slug' => 'tenant-a']);
        $route = new \Illuminate\Routing\Route('GET', '/{tenant}/delivery/projects/{project}/planning', []);
        $route->bind($request);
        $route->setParameter('tenant', $tenant);
        $route->setParameter('project', 10);
        $request->setRouteResolver(fn () => $route);
        $layoutPath = storage_path('framework/testing/planning-layout');
        if (! is_dir($layoutPath.'/layouts')) { mkdir($layoutPath.'/layouts', 0777, true); }
        file_put_contents($layoutPath.'/layouts/bento.blade.php', "@yield('content')");
        app('view')->getFinder()->setPaths([$layoutPath, resource_path('views')]);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $response = app(\App\Http\Controllers\Delivery\ProjectPlanningController::class)->index($request);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $html = $response->getContent();
        $this->assertStringContainsString('Público e população histórica', $html);
        $this->assertStringContainsString('rules[0][metric]', $html);
        $this->assertStringContainsString('distribution-91', $html);
    }

    public function test_migration_is_reversible(): void
    {
        $migration = require database_path('migrations/2026_10_18_000001_create_project_planning_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('distribution_consumption_profiles'));
        $this->assertTrue(Schema::hasTable('production_deliveries'));
        $migration->up();
        $this->assertTrue(Schema::hasTable('planning_report_snapshots'));
    }

}

