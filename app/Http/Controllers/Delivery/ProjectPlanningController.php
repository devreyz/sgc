<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryGroup;
use App\Models\Customer;
use App\Models\PlanningReportSnapshot;
use App\Models\PlanningRuleSet;
use App\Models\Product;
use App\Models\ProductPlanningProfile;
use App\Models\ProductionDelivery;
use App\Models\ProjectPlanningSetting;
use App\Models\SalesProject;
use App\Models\Tenant;
use App\Services\Planning\ConsumptionReportService;
use App\Services\Planning\ProjectPlanningService;
use App\Services\Planning\NutritionCatalogService;
use App\Services\Planning\NutritionReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ProjectPlanningController extends Controller
{
    public function __construct(private ProjectPlanningService $planning)
    {
        $this->middleware(['auth', 'any.role:registrador_entregas']);
    }

    private function project(Request $request): SalesProject
    {
        $tenantId = (int) session('tenant_id');
        abort_unless($tenantId > 0, 403);
        $tenant = $request->route('tenant');
        if ($tenant instanceof Tenant) {
            abort_unless((int) $tenant->id === $tenantId, 403);
        }
        return SalesProject::where('tenant_id', $tenantId)->findOrFail((int) $request->route('project'));
    }

    public function index(Request $request)
    {
        $request->validate(['distribution' => 'nullable|integer|min:1']);
        $project = $this->project($request);
        $context = ['tenant_id' => $project->tenant_id, 'sales_project_id' => $project->id];
        $nutritionAvailable = Schema::hasTable('food_profiles');
        $menusAvailable = Schema::hasTable('project_menus');
        $request->validate(['analysis_mode' => ['nullable', \Illuminate\Validation\Rule::in(['consumption', 'compliance', 'nutrition'])]]);
        $customers = app(\App\Services\ProjectDistributionCustomerService::class)->customers($project);
        $historicalCustomers = Customer::where('tenant_id', $project->tenant_id)->whereIn('id',
            ProductionDelivery::where($context)->whereNotNull('parent_delivery_id')->select('customer_id'))->get();
        return response()->view('delivery.project-planning', [
            'currentTenant' => $project->tenant,
            'project' => $project, 'settings' => ProjectPlanningSetting::where($context)->first(),
            'groups' => BeneficiaryGroup::where($context)->with(['populationPeriods' => fn ($q) => $q->where('tenant_id', $project->tenant_id)->orderBy('effective_from')])->get(),
            'products' => Product::where('tenant_id', $project->tenant_id)->orderBy('name')->get(),
            'customers' => $customers->merge($historicalCustomers)->unique('id')->sortBy('name')->values(),
            'nutritionAvailable' => $nutritionAvailable,
            'menusAvailable' => $menusAvailable,
            'recipes' => $menusAvailable ? \App\Models\Recipe::where('tenant_id', $project->tenant_id)->with(['items' => fn ($q) => $q->where('tenant_id', $project->tenant_id)])->orderBy('code')->orderByDesc('version')->get() : collect(),
            'menus' => $menusAvailable ? \App\Models\ProjectMenu::where($context)->latest('id')->limit(50)->get() : collect(),
            'nutrients' => $nutritionAvailable ? \App\Models\Nutrient::where('tenant_id', $project->tenant_id)->orderBy('name')->get() : collect(),
            'foodProfiles' => $nutritionAvailable ? \App\Models\FoodProfile::where('tenant_id', $project->tenant_id)
                ->with(['nutrientValues' => fn ($q) => $q->where('tenant_id', $project->tenant_id), 'nutrientValues.nutrient' => fn ($q) => $q->where('tenant_id', $project->tenant_id)])->orderByDesc('version')->get() : collect(),
            'nutritionTargetSets' => $nutritionAvailable ? \App\Models\NutritionTargetSet::where($context)
                ->with(['targets' => fn ($q) => $q->where('tenant_id', $project->tenant_id)])->orderByDesc('version')->get() : collect(),
            'productProfiles' => ProductPlanningProfile::where($context)->orderByDesc('effective_from')->get(),
            'ruleSets' => PlanningRuleSet::where($context)->with(['rules' => fn ($q) => $q->where('tenant_id', $project->tenant_id)])->orderByDesc('version')->get(),
            'distributions' => ProductionDelivery::where($context)->whereNotNull('parent_delivery_id')->whereNotIn('status', ['rejected', 'cancelled'])
                ->when($request->filled('distribution'), fn ($q) => $q->whereKey((int) $request->input('distribution')))
                ->with(['product' => fn ($q) => $q->where('tenant_id', $project->tenant_id), 'customer' => fn ($q) => $q->where('tenant_id', $project->tenant_id),
                    'consumptionProfile' => fn ($q) => $q->where($context), 'consumptionProfile.dailyAudiences' => fn ($q) => $q->where('tenant_id', $project->tenant_id)])
                ->orderByDesc('delivery_date')->orderByDesc('id')->paginate(25),
            'report' => $request->filled('date_from') && $request->filled('date_to')
                ? app($request->input('analysis_mode') === 'nutrition' ? NutritionReportService::class : ConsumptionReportService::class)
                    ->build($project, $request->all() + ['analysis_mode' => 'consumption', 'competency' => 'consumption']) : null,
            'snapshots' => PlanningReportSnapshot::where($context)->latest()->limit(10)->get(['id', 'created_at', 'parameters']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        $project = $this->project($request);
        $action = $request->route('action');
        $input = $request->all();
        if (in_array($action, ['nutrients', 'food-profiles', 'nutrition-targets'], true)) {
            abort_unless(Schema::hasTable('food_profiles'), 422, 'Aplique a migração de nutrição.');
        }
        if (in_array($action, ['recipes', 'menus'], true)) { abort_unless(Schema::hasTable('project_menus'), 422, 'Aplique a migração de receitas e cardápios.'); }
        if ($action === 'consumption' && $request->filled('daily_audiences_text')) {
            $request->validate(['daily_audiences_text' => 'string|max:100000']);
            $input['daily_audiences'] = array_map(function ($line) {
                $parts = array_map('trim', explode(';', $line));
                return ['date' => $parts[0] ?? '', 'served_people' => $parts[1] ?? ''];
            }, preg_split('/\R/', trim($input['daily_audiences_text'])));
        }
        $result = match ($action) {
            'settings' => $this->planning->saveSettings($project, $input),
            'groups' => $this->planning->createGroup($project, $input),
            'population' => $this->planning->addPopulation($project, (int) $request->input('beneficiary_group_id'), $input),
            'products' => $this->planning->addProductProfile($project, $input),
            'rules' => $this->planning->createRuleSet($project, $input),
            'consumption' => $this->planning->saveConsumption($project, (int) $request->input('distribution_id'), $input),
            'nutrients' => app(NutritionCatalogService::class)->createNutrient($project, $input),
            'food-profiles' => app(NutritionCatalogService::class)->createFoodProfile($project, $input),
            'nutrition-targets' => app(NutritionCatalogService::class)->createTargetSet($project, $input),
            'recipes' => app(\App\Services\Planning\MenuPlanningService::class)->createRecipe($project, $input),
            'menus' => app(\App\Services\Planning\MenuPlanningService::class)->createMenu($project, $input),
            default => abort(404),
        };
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'id' => $result->id])->header('Cache-Control', 'no-store, private');
        }
        if ($action === 'menus') { return redirect()->route('delivery.projects.menus.show', ['tenant' => $project->tenant->slug, 'project' => $project->id, 'menu' => $result->id]); }
        return back()->with('status', 'Planejamento salvo.');
    }

    public function snapshot(Request $request)
    {
        $project = $this->project($request);
        $snapshot = PlanningReportSnapshot::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)
            ->findOrFail((int) $request->route('snapshot'));
        return response()->json($snapshot->result)->header('Cache-Control', 'no-store, private');
    }
}
