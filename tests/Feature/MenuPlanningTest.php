<?php

namespace Tests\Feature;

use App\Models\BeneficiaryGroup;
use App\Models\PlanningReportSnapshot;
use App\Models\ProjectMenu;
use App\Models\Recipe;
use App\Models\SalesProject;
use App\Services\Planning\ConsumptionReportService;
use App\Services\Planning\MenuPlanningService;
use App\Services\Planning\MenuReportService;
use App\Services\Planning\NutritionCatalogService;
use App\Services\Planning\ProjectPlanningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MenuPlanningTest extends TestCase
{
    use \Tests\Concerns\BuildsDeliveryReportSchema;

    protected function setUp(): void
    {
        parent::setUp(); $this->buildDeliveryReportSchema(); activity()->disableLogging(); session(['tenant_id' => 1]);
        foreach (['2026_10_18_000001_create_project_planning_tables.php', '2026_10_18_000002_create_project_nutrition_tables.php', '2026_10_21_000001_create_recipes_and_project_menus.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::table('products', fn (Blueprint $table) => $table->unsignedInteger('shelf_life_days')->nullable());
        Schema::table('production_deliveries', fn (Blueprint $table) => $table->unsignedBigInteger('stock_movement_id')->nullable());
        DB::table('products')->where('id', 50)->update(['shelf_life_days' => 4]);
        DB::table('production_deliveries')->where('id', 91)->update(['delivery_date' => '2026-10-01']);
        DB::table('production_deliveries')->where('id', 92)->update(['delivery_date' => '2026-10-01']);
    }

    private function project(): SalesProject { return SalesProject::findOrFail(10); }
    private function group(int $client = 80): BeneficiaryGroup
    {
        return app(ProjectPlanningService::class)->createGroup($this->project(), ['name' => 'Grupo '.$client, 'customer_id' => $client]);
    }
    private function composition(string $value = '2'): void
    {
        $catalog = app(NutritionCatalogService::class);
        $nutrient = \App\Models\Nutrient::where('tenant_id', 1)->first() ?? $catalog->createNutrient($this->project(), ['code' => 'protein_g', 'name' => 'Proteína', 'unit' => 'g']);
        $catalog->createFoodProfile($this->project(), ['product_id' => 50, 'name' => 'Teste', 'reference_quantity_g' => '100',
            'edible_fraction' => '1', 'source' => 'Fixture', 'source_version' => '1', 'effective_from' => '2026-01-01',
            'values' => [['nutrient_id' => $nutrient->id, 'amount' => $value]]]);
    }
    private function recipe(): Recipe
    {
        return app(MenuPlanningService::class)->createRecipe($this->project(), ['code' => 'banana', 'name' => 'Receita teste',
            'yield_portions' => '10', 'portion_weight_g' => '80', 'source_reference' => 'Ficha técnica teste',
            'items' => [['product_id' => 50, 'gross_quantity_g' => '1000']]]);
    }
    private function menu(?int $group = null, int $client = 80): ProjectMenu
    {
        return app(MenuPlanningService::class)->createMenu($this->project(), ['code' => 'semana-1', 'name' => 'Semana teste',
            'customer_id' => $client, 'beneficiary_group_id' => $group ?? $this->group($client)->id,
            'date_from' => '2026-10-01', 'date_to' => '2026-10-07']);
    }
    private function meal(ProjectMenu $menu, string $date = '2026-10-01', string $name = 'Almoço', int $people = 100, ?int $recipe = null): ProjectMenu
    {
        $item = $recipe ? ['recipe_id' => $recipe, 'quantity_per_person' => '1', 'quantity_unit' => 'portion']
            : ['product_id' => 50, 'quantity_per_person' => '100', 'quantity_unit' => 'g'];
        return app(MenuPlanningService::class)->saveMeal($this->project(), $menu->id, ['revision' => $menu->revision, 'name' => $name,
            'date' => $date, 'planned_people' => $people, 'items' => [$item]]);
    }

    public function test_recipe_menu_demands_and_daily_weekly_public_are_correct(): void
    {
        $this->composition(); $recipe = $this->recipe(); $menu = $this->meal($this->menu(), recipe: $recipe->id);
        $menu = $this->meal($menu, name: 'Jantar', recipe: $recipe->id);
        $menu = $this->meal($menu, '2026-10-05', people: 200);
        $report = app(MenuReportService::class)->build($this->project(), $menu->id);
        $this->assertCount(3, $report['rows']);
        $this->assertTrue($report['ready_for_review']);
        $this->assertSame(0, bccomp('100', $report['days'][0]['person_days'], 16));
        $this->assertSame(0, bccomp('400', $report['days'][0]['nutrients']['protein_g']['known_total'], 16));
        $this->assertSame(0, bccomp('4', $report['days'][0]['nutrients']['protein_g']['per_person_day'], 16));
        $this->assertSame(0, bccomp('40000', $report['comparison'][0]['planned_g'], 16));
        $this->assertCount(2, $report['suggested_deliveries']);
        $this->assertSame('2026-10-04', $report['suggested_deliveries'][0]['estimated_expiry']);
    }

    public function test_actual_comparison_uses_only_linked_customer_group_and_does_not_write_finance(): void
    {
        $group = $this->group(); $other = $this->group(81);
        $before = DB::table('production_deliveries')->orderBy('id')->get()->toJson();
        app(ProjectPlanningService::class)->saveConsumption($this->project(), 91, ['count_method' => 'served_people', 'served_people' => 100, 'coverage_days' => 1, 'beneficiary_group_id' => $group->id]);
        app(ProjectPlanningService::class)->saveConsumption($this->project(), 92, ['count_method' => 'served_people', 'served_people' => 100, 'coverage_days' => 1, 'beneficiary_group_id' => $other->id]);
        $this->composition(); $menu = $this->meal($this->menu($group->id));
        $report = app(MenuReportService::class)->build($this->project(), $menu->id);
        $this->assertSame([91], $report['comparison'][0]['distribution_ids']);
        $this->assertSame(0, bccomp('10000', $report['comparison'][0]['planned_g'], 16));
        $this->assertSame(0, bccomp('40000', $report['comparison'][0]['distributed_g'], 16));
        $this->assertSame($before, DB::table('production_deliveries')->orderBy('id')->get()->toJson());
    }

    public function test_recipe_versions_are_immutable_and_existing_menu_keeps_original_recipe(): void
    {
        $recipe = $this->recipe(); $menu = $this->meal($this->menu(), recipe: $recipe->id);
        $new = $this->recipe();
        $this->assertSame(2, (int) $new->version);
        $this->assertSame($recipe->id, (int) $menu->days()->first()->meals()->first()->items()->first()->recipe_id);
        $this->expectException(\LogicException::class);
        $recipe->update(['yield_portions' => '1']);
    }

    public function test_missing_composition_or_conflicting_public_prevents_submission(): void
    {
        $menus = app(MenuPlanningService::class); $menu = $this->meal($this->menu());
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'calculate']);
        $this->assertSame('calculated', $menu->status);
        $this->assertFalse(PlanningReportSnapshot::findOrFail($menu->calculation_snapshot_id)->result['ready_for_review']);
        $this->expectException(ValidationException::class);
        $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'submit']);
    }

    public function test_review_snapshot_preserves_food_profile_and_cloning_does_not_change_approved_plan(): void
    {
        $menus = app(MenuPlanningService::class); $this->composition(); $menu = $this->meal($this->menu());
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'calculate']);
        $this->composition('50');
        $preserved = app(MenuReportService::class)->build($this->project(), $menu->id);
        $this->assertSame(0, bccomp('2', $preserved['days'][0]['nutrients']['protein_g']['per_person_day'], 16));
        $copy = $menus->cloneMenu($this->project(), $menu->id);
        $this->assertSame(2, $copy->version); $this->assertSame('planned', $copy->status);
        $fresh = app(MenuReportService::class)->build($this->project(), $copy->id);
        $this->assertSame(0, bccomp('50', $fresh['days'][0]['nutrients']['protein_g']['per_person_day'], 16));
        $this->assertSame(3, (int) PlanningReportSnapshot::findOrFail($menu->calculation_snapshot_id)->calculation_version);
    }

    public function test_approval_requires_authorized_actor_and_reviewer_evidence(): void
    {
        $menus = app(MenuPlanningService::class); $this->composition(); $menu = $this->meal($this->menu());
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'calculate']);
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'submit']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'approve',
            'reviewer_name' => 'Responsável', 'professional_reference' => 'Registro informado', 'approval_reference' => 'Parecer 1']);
    }

    public function test_cross_customer_group_and_cross_tenant_recipe_are_rejected(): void
    {
        $group = $this->group(81);
        $this->expectException(ValidationException::class);
        $this->menu($group->id, 80);
    }

    public function test_stale_revision_cannot_overwrite_plan(): void
    {
        $menu = $this->menu(); $this->meal($menu);
        $this->expectException(ValidationException::class);
        $this->meal($menu, name: 'Jantar');
    }

    public function test_meal_outside_menu_and_mixed_product_recipe_are_rejected(): void
    {
        $menu = $this->menu();
        $this->expectException(ValidationException::class);
        $this->meal($menu, '2026-10-08');
    }

    public function test_migration_rolls_back_only_v3(): void
    {
        (require database_path('migrations/2026_10_21_000001_create_recipes_and_project_menus.php'))->down();
        $this->assertFalse(Schema::hasTable('project_menus'));
        $this->assertTrue(Schema::hasTable('production_deliveries'));
        $this->assertTrue(Schema::hasTable('food_profiles'));
    }

    public function test_full_lifecycle_records_explicit_technical_approval_and_blocks_later_edits(): void
    {
        $menus = app(MenuPlanningService::class); $this->composition(); $menu = $this->meal($this->menu());
        foreach (['calculate', 'submit'] as $action) { $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => $action]); }
        $actor = new \App\Models\User; $actor->setRawAttributes(['id' => 101, 'name' => 'Operador de teste'], true); auth()->setUser($actor);
        $authorization = \Mockery::mock(\App\Services\TenantSecurityAuthorization::class);
        $authorization->shouldReceive('authorize')->once()->with($actor, 1, 'approve_menus')->andReturn(new \App\Models\TenantUser);
        $this->app->instance(\App\Services\TenantSecurityAuthorization::class, $authorization);
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'approve',
            'reviewer_name' => 'Responsável técnico informado', 'professional_reference' => 'Registro teste', 'approval_reference' => 'Parecer teste']);
        $this->assertSame('approved', $menu->status); $this->assertSame(101, (int) $menu->approved_by); $this->assertNotNull($menu->approved_at);
        foreach (['start', 'finalize'] as $action) { $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => $action]); }
        $this->assertSame('finalized', $menu->status);
        $this->expectException(ValidationException::class); $this->meal($menu, name: 'Outra refeição');
    }

    public function test_foreign_tenant_recipe_and_meal_of_another_menu_are_rejected(): void
    {
        $foreignRecipe = DB::table('recipes')->insertGetId(['tenant_id' => 2, 'code' => 'privado', 'name' => 'Receita privada', 'version' => 1,
            'yield_portions' => 10, 'portion_weight_g' => 100, 'source_reference' => 'Outro tenant']);
        $menu = $this->menu();
        $this->expectException(ValidationException::class); $this->meal($menu, recipe: $foreignRecipe);
    }

    public function test_meal_identifier_cannot_cross_menu_or_customer(): void
    {
        $first = $this->meal($this->menu()); $second = $this->menu(client: 81);
        $meal = $first->days()->first()->meals()->first();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(MenuPlanningService::class)->saveMeal($this->project(), $second->id, ['revision' => $second->revision, 'meal_id' => $meal->id,
            'date' => '2026-10-01', 'name' => 'Almoço', 'planned_people' => 100,
            'items' => [['product_id' => 50, 'quantity_per_person' => '100', 'quantity_unit' => 'g']]]);
    }

    public function test_unassigned_or_invalid_distribution_never_becomes_menu_execution(): void
    {
        $this->composition(); $menu = $this->meal($this->menu());
        $service = app(MenuReportService::class); $report = $service->build($this->project(), $menu->id);
        $this->assertSame(0, bccomp('0', $report['comparison'][0]['distributed_g'], 16));
        $this->assertSame(1, $report['unassigned_distributions_count']);
        app(ProjectPlanningService::class)->saveConsumption($this->project(), 91, ['count_method' => 'served_people', 'served_people' => 100,
            'coverage_days' => 1, 'beneficiary_group_id' => $menu->beneficiary_group_id]);
        DB::table('production_deliveries')->where('id', 91)->update(['product_id' => 60]);
        $report = $service->build($this->project(), $menu->id);
        $this->assertCount(1, $report['actual_issues']);
        $this->assertNull(collect($report['comparison'])->firstWhere('product_id', 60)['difference_g']);
    }

    public function test_pdf_xlsx_and_menu_screen_render_with_source_versions(): void
    {
        $this->composition(); $menu = $this->meal($this->menu(), recipe: $this->recipe()->id); $project = $this->project();
        $report = app(MenuReportService::class)->build($project, $menu->id);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.menu-report', compact('project', 'report'))->setPaper('a4', 'landscape')->output();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $bytes = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\MenuReportExport($report), \Maatwebsite\Excel\Excel::XLSX);
        $this->assertStringStartsWith('PK', $bytes);
        Schema::table('tenants', fn (Blueprint $table) => $table->string('slug')->nullable()); DB::table('tenants')->where('id', 1)->update(['slug' => 'tenant-a']);
        $request = \Illuminate\Http\Request::create('/tenant-a/delivery/projects/10/planning/menus/'.$menu->id);
        $route = new \Illuminate\Routing\Route('GET', '/{tenant}/delivery/projects/{project}/planning/menus/{menu}', []); $route->bind($request);
        $route->setParameter('tenant', \App\Models\Tenant::findOrFail(1)); $route->setParameter('project', 10); $route->setParameter('menu', $menu->id);
        $request->setRouteResolver(fn () => $route); $this->app->instance('request', $request);
        $layout = storage_path('framework/testing/menu-layout'); if (! is_dir($layout.'/layouts')) { mkdir($layout.'/layouts', 0777, true); }
        file_put_contents($layout.'/layouts/bento.blade.php', "@yield('content')"); app('view')->getFinder()->setPaths([$layout, resource_path('views')]);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $response = app(\App\Http\Controllers\Delivery\ProjectMenuController::class)->show($request);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Adicionar refeição', $response->getContent());
        $this->assertStringContainsString('Sugestões de entrega', $response->getContent());
    }

    public function test_weekly_nutrition_is_weighted_and_not_a_mean_of_daily_rates(): void
    {
        $this->composition(); $menu = $this->meal($this->menu()); $menu = $this->meal($menu, name: 'Jantar');
        $menu = $this->meal($menu, '2026-10-02', people: 200);
        $report = app(MenuReportService::class)->build($this->project(), $menu->id);
        $this->assertCount(1, $report['weeks']);
        $this->assertSame('2026-09-28', $report['weeks'][0]['week']);
        $this->assertSame(0, bccomp('300', $report['weeks'][0]['person_days'], 16));
        $this->assertSame(0, bccomp('800', $report['weeks'][0]['nutrients']['protein_g']['known_total'], 16));
        $this->assertSame(0, bccomp(bcdiv('800', '300', 16), $report['weeks'][0]['nutrients']['protein_g']['per_person_day'], 16));
    }

    public function test_conflicting_meal_public_is_unknown_and_blocks_review(): void
    {
        $this->composition(); $menu = $this->meal($this->menu()); $menu = $this->meal($menu, name: 'Jantar', people: 200);
        $report = app(MenuReportService::class)->build($this->project(), $menu->id);
        $this->assertFalse($report['ready_for_review']); $this->assertNull($report['days'][0]['person_days']);
        $this->assertNull($report['days'][0]['nutrients']['protein_g']['per_person_day']);
    }

    public function test_revising_a_calculation_allows_editing_and_clears_review_snapshot(): void
    {
        $menus = app(MenuPlanningService::class); $this->composition(); $menu = $this->meal($this->menu());
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'calculate']);
        $snapshot = $menu->calculation_snapshot_id;
        $menu = $menus->transition($this->project(), $menu->id, ['revision' => $menu->revision, 'action' => 'revise']);
        $this->assertNull($menu->calculation_snapshot_id); $this->assertTrue(PlanningReportSnapshot::whereKey($snapshot)->exists());
        $menu = $this->meal($menu, name: 'Jantar');
        $meal = $menu->days()->first()->meals()->where('name', 'Jantar')->firstOrFail();
        $menu = $menus->removeMeal($this->project(), $menu->id, ['revision' => $menu->revision, 'meal_id' => $meal->id]);
        $this->assertSame(1, $menu->days()->first()->meals()->count());
    }

    public function test_product_and_recipe_cannot_both_be_selected_in_the_same_item(): void
    {
        $menu = $this->menu(); $recipe = $this->recipe();
        $this->expectException(ValidationException::class);
        app(MenuPlanningService::class)->saveMeal($this->project(), $menu->id, ['revision' => $menu->revision, 'name' => 'Almoço',
            'date' => '2026-10-01', 'planned_people' => 100, 'items' => [['product_id' => 50, 'recipe_id' => $recipe->id,
                'quantity_per_person' => '1', 'quantity_unit' => 'portion']]]);
    }

    public function test_menu_of_another_project_cannot_be_read_under_current_project(): void
    {
        $menu = $this->menu();
        $project = SalesProject::withoutGlobalScopes()->findOrFail(20); session(['tenant_id' => 2]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(MenuReportService::class)->build($project, $menu->id);
    }

    public function test_menu_export_endpoint_preserves_version_three_result(): void
    {
        $this->composition(); $menu = $this->meal($this->menu());
        // File readability is restricted by the Windows sandbox; workbook generation is covered separately.
        \Maatwebsite\Excel\Facades\Excel::shouldReceive('download')->once()
            ->withArgs(fn ($export, $filename) => $export instanceof \App\Exports\MenuReportExport && str_ends_with($filename, '.xlsx'))
            ->andReturn(response()->streamDownload(fn () => print '', 'menu.xlsx'));
        $request = \Illuminate\Http\Request::create('/tenant-a/delivery/projects/10/reports/export?format=xlsx&type=customer&analysis_mode=menu&competency=planning&menu_id='.$menu->id);
        $route = new \Illuminate\Routing\Route('GET', '/{tenant}/delivery/projects/{project}/reports/export', []); $route->bind($request);
        $route->setParameter('tenant', \App\Models\Tenant::findOrFail(1)); $route->setParameter('project', 10); $request->setRouteResolver(fn () => $route);
        $response = app(\App\Http\Controllers\Delivery\DeliveryReportController::class)->export($request, app(\App\Services\TemplatedPdfService::class));
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $snapshot = PlanningReportSnapshot::latest('id')->firstOrFail();
        $this->assertSame(3, (int) $snapshot->calculation_version); $this->assertSame($menu->id, $snapshot->result['menu']['id']);
        $this->assertSame('menu', $snapshot->parameters['analysis_mode']);
    }

    public function test_menu_issues_are_integrated_without_operational_blocking(): void
    {
        $menu = $this->menu();
        $base = ['critical' => [], 'warning' => [], 'info' => [], 'counts' => ['critical' => 0, 'warning' => 0, 'info' => 0]];
        $result = app(\App\Services\Planning\PlanningIntegrityService::class)->append($this->project(), $base);
        $issue = collect($result['warning'])->firstWhere('menuId', $menu->id);
        $this->assertSame('menu', $issue['domain']); $this->assertSame('open_menu', $issue['actionKey']);
        $this->assertSame([], $result['critical']);
    }
}
