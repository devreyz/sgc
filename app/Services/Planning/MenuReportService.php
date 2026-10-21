<?php

namespace App\Services\Planning;

use App\Models\BeneficiaryGroup;
use App\Models\Customer;
use App\Models\Nutrient;
use App\Models\NutritionTargetSet;
use App\Models\PlanningReportSnapshot;
use App\Models\Product;
use App\Models\ProductPlanningProfile;
use App\Models\ProductionDelivery;
use App\Models\Recipe;
use App\Models\SalesProject;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class MenuReportService
{
    public const VERSION = 3;
    public const METHODOLOGY = 'Demanda planejada em gramas brutas por ingrediente: receita escalada pelo rendimento declarado e pelas porções por pessoa, ou produto em gramas por pessoa, multiplicados pelo público da refeição. Composição e fração comestível usam o motor nutricional da V2 na data de cada dia planejado; não são presumidos fatores de cocção/retenção. O público diário é contado uma vez entre refeições compatíveis; semana = soma dos nutrientes / soma dos pessoa-dias diários. Realizado é distribuição física aprovada do mesmo projeto, cliente, grupo e período, não consumo efetivo nem preparo confirmado. Saldo comparativo não constitui reserva de estoque, pedido de compra ou autorização financeira. Cronograma é sugestão baseada em durabilidade estimada, sem substituir a validade real de lote.';

    public function __construct(private MenuPlanningService $menus, private FoodCompositionResolver $food,
        private NutritionCalculator $nutrition, private RecipeDemandCalculator $recipes, private MenuNutrientAggregator $aggregate) {}

    public function build(SalesProject $project, int $menuId, bool $useSnapshot = true): array
    {
        $menu = $this->menus->menu($project, $menuId);
        $tenantId = (int) $project->tenant_id;
        $customer = Customer::where('tenant_id', $tenantId)->findOrFail($menu->customer_id);
        $group = BeneficiaryGroup::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)->findOrFail($menu->beneficiary_group_id);
        if ($group->customer_id && (int) $group->customer_id !== (int) $menu->customer_id) {
            throw ValidationException::withMessages(['menu' => 'O público não corresponde ao cliente deste cardápio.']);
        }
        if ($useSnapshot && $menu->calculation_snapshot_id && $menu->status !== 'planned') {
            $snapshot = PlanningReportSnapshot::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)->findOrFail($menu->calculation_snapshot_id);
            $report = $snapshot->result;
            if ((int) ($report['menu']['id'] ?? 0) !== (int) $menu->id
                || (int) ($report['menu']['customer_id'] ?? 0) !== (int) $menu->customer_id
                || (int) ($report['menu']['beneficiary_group_id'] ?? 0) !== (int) $menu->beneficiary_group_id) {
                throw ValidationException::withMessages(['menu' => 'Cálculo preservado incompatível com o cardápio.']);
            }
            $report['menu'] = $menu->attributesToArray();
            $report['calculation_snapshot_id'] = $snapshot->id;
            $report['generated_at'] = now()->toIso8601String();
            return $this->withActual($project, $menu, $report);
        }
        $menu->load(['days' => fn ($q) => $q->where('tenant_id', $tenantId)->orderBy('date'),
            'days.meals' => fn ($q) => $q->where('tenant_id', $tenantId)->orderBy('id'),
            'days.meals.items' => fn ($q) => $q->where('tenant_id', $tenantId)->orderBy('id')]);
        $items = $menu->days->flatMap->meals->flatMap->items;
        $recipes = Recipe::where('tenant_id', $tenantId)->whereIn('id', $items->pluck('recipe_id')->filter()->unique())
            ->with(['items' => fn ($q) => $q->where('tenant_id', $tenantId)])->get()->keyBy('id');
        $productIds = $items->pluck('product_id')->merge($recipes->flatMap->items->pluck('product_id'))->filter()->unique()->values()->all();
        $products = Product::where('tenant_id', $tenantId)->whereIn('id', $productIds)->get()->keyBy('id');
        $profiles = $this->food->load($tenantId, $productIds);
        $nutrients = Nutrient::where('tenant_id', $tenantId)->orderBy('code')->get()->map->attributesToArray()->all();
        $planningProfiles = ProductPlanningProfile::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)
            ->whereIn('product_id', $productIds)->orderByDesc('effective_from')->get()->groupBy('product_id');
        $targetSets = NutritionTargetSet::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)
            ->where('customer_id', $menu->customer_id)->where('beneficiary_group_id', $menu->beneficiary_group_id)
            ->with(['targets' => fn ($q) => $q->where('tenant_id', $tenantId)])->orderByDesc('version')->get();
        $rows = []; $days = []; $dailyDemand = []; $issues = []; $evaluations = [];
        $issue = function (string $message, bool $blocking = true) use (&$issues) {
            $issues[] = ['severity' => 'warning', 'domain' => 'menu', 'title' => 'Cardápio', 'message' => $message, 'blocks_review' => $blocking];
        };
        foreach ($menu->days as $day) {
            $date = $day->date->format('Y-m-d');
            if ($date < $menu->date_from->format('Y-m-d') || $date > $menu->date_to->format('Y-m-d')) {
                $issue('Dia fora do período do cardápio.'); continue;
            }
            $dayMeals = []; $dayMass = [];
            foreach ($day->meals as $meal) {
                $people = (string) $meal->planned_people;
                $mass = []; $sources = [];
                if (bccomp($people, '0', 16) <= 0) { $issue('Refeição sem público positivo em '.$date); continue; }
                foreach ($meal->items as $item) {
                    $amount = (string) $item->quantity_per_person;
                    if (bccomp($amount, '0', 16) <= 0 || (empty($item->product_id) === empty($item->recipe_id))) {
                        $issue('Item de refeição inválido em '.$date); continue;
                    }
                    $quantity = bcmul($amount, $people, 16);
                    if ($item->recipe_id) {
                        $recipe = $recipes->get($item->recipe_id);
                        if (! $recipe || $recipe->items->isEmpty() || $item->quantity_unit !== 'portion' || bccomp($recipe->yield_portions, '0', 16) <= 0) {
                            $issue('Receita indisponível ou incompatível em '.$date); continue;
                        }
                        $expanded = $this->recipes->expand($recipe->items->map->attributesToArray()->all(), $recipe->yield_portions, $quantity);
                        $sources[] = ['recipe' => $recipe->attributesToArray(), 'ingredients' => $recipe->items->map->attributesToArray()->all(), 'planned_portions' => $quantity];
                    } else {
                        if ($item->quantity_unit !== 'g') { $issue('Unidade de ingrediente incompatível em '.$date); continue; }
                        $expanded = [(int) $item->product_id => $quantity];
                    }
                    foreach ($expanded as $id => $grams) { $mass[$id] = bcadd($mass[$id] ?? '0', $grams, 16); }
                }
                $ingredients = [];
                foreach ($mass as $id => $grams) {
                    $product = $products->get($id);
                    $composition = $product ? $this->food->resolve($profiles, (int) $id, $date) : null;
                    $nutrition = $this->nutrition->calculate(['status' => 'calculated', 'reason' => null, 'quantity_g' => $grams, 'person_days' => $people], $composition);
                    if ($nutrition['status'] !== 'calculated') { $issue('Composição indisponível para '.($product?->name ?? 'produto inválido').' em '.$date); }
                    $ingredients[] = ['product_id' => $id, 'product' => $product?->name ?? 'Produto indisponível', 'gross_quantity_g' => $grams,
                        'nutrients' => $nutrition['nutrients'], 'nutrition' => $nutrition];
                    $dayMass[$id] = bcadd($dayMass[$id] ?? '0', $grams, 16);
                }
                if (! $ingredients) { $issue('Refeição sem ingredientes calculáveis em '.$date); }
                $summary = $this->aggregate->summarize($ingredients, $nutrients, $people);
                $row = ['id' => $meal->id, 'date' => $date, 'name' => $meal->name, 'planned_people' => (int) $meal->planned_people,
                    'ingredients' => $ingredients, 'recipes' => $sources, 'nutrients' => $summary['nutrients']];
                $rows[] = $row; $dayMeals[] = $row;
            }
            if (! $dayMeals) { continue; }
            $public = collect($dayMeals)->pluck('planned_people')->unique()->values();
            $personDays = $public->count() === 1 ? (string) $public[0] : null;
            if ($personDays === null) { $issue('Público divergente entre refeições em '.$date.'. Não há denominador diário confiável.'); }
            $summary = $this->aggregate->summarize($dayMeals, $nutrients, $personDays);
            foreach ($summary['nutrients'] as $nutrient) {
                if ($nutrient['status'] !== 'calculated') { $issue('Composição diária incompleta de '.$nutrient['name'].' em '.$date); }
            }
            $days[] = ['date' => $date, 'week' => CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY)->toDateString(), 'meal_ids' => array_column($dayMeals, 'id')] + $summary;
            $set = $targetSets->first(fn ($set) => $set->effective_from->format('Y-m-d') <= $date && (! $set->effective_to || $set->effective_to->format('Y-m-d') >= $date));
            if ($set) {
                foreach ($set->targets as $target) {
                    $nutrient = collect($nutrients)->firstWhere('id', $target->nutrient_id);
                    if (! $nutrient) { $issue('Meta com nutriente incompatível em '.$date); continue; }
                    $value = $summary['nutrients'][$nutrient['code']]['per_person_day'];
                    $valid = $value === null ? null : (($target->min_value === null || bccomp($value, $target->min_value, 16) >= 0)
                        && ($target->max_value === null || bccomp($value, $target->max_value, 16) <= 0));
                    $evaluations[] = ['date' => $date, 'source' => $set->attributesToArray(), 'target' => $target->attributesToArray(),
                        'nutrient' => $nutrient, 'actual' => $value, 'status' => $valid === null ? 'not_calculable' : ($valid ? 'compliant' : $target->severity)];
                }
            }
            foreach ($dayMass as $id => $grams) {
                $p = ($planningProfiles->get($id) ?? collect())->first(fn ($p) => $p->effective_from->format('Y-m-d') <= $date && (! $p->effective_to || $p->effective_to->format('Y-m-d') >= $date));
                $life = $p?->shelf_life_days ?? $products->get($id)?->shelf_life_days;
                if (! $life) { $issue('Sem durabilidade para sugerir entregas de '.($products->get($id)?->name ?? '#'.$id), false); }
                $dailyDemand[] = ['date' => $date, 'product_id' => $id, 'product' => $products->get($id)?->name ?? 'Produto indisponível',
                    'gross_quantity_g' => $grams, 'shelf_life_days' => $life ? (int) $life : null,
                    'shelf_life_source' => $life ? ($p?->shelf_life_days ? 'project_product:'.$p->id : 'product:'.$id) : null];
            }
        }
        if (! $nutrients) { $issue('Catálogo nutricional não informado.'); }
        $weeks = [];
        foreach (collect($days)->groupBy('week') as $week => $weekDays) {
            $personDays = $weekDays->contains(fn ($day) => $day['person_days'] === null) ? null : $weekDays->reduce(fn ($sum, $day) => bcadd($sum, $day['person_days'], 16), '0');
            $weeks[] = ['week' => $week] + $this->aggregate->summarize($weekDays->all(), $nutrients, $personDays);
        }
        $report = ['calculation_version' => self::VERSION, 'context' => ['tenant_id' => $tenantId, 'sales_project_id' => (int) $project->id],
            'filters' => ['analysis_mode' => 'menu', 'menu_id' => $menu->id, 'date_from' => $menu->date_from->format('Y-m-d'),
                'date_to' => $menu->date_to->format('Y-m-d'), 'competency' => 'planning', 'customer_ids' => [$menu->customer_id], 'beneficiary_group_ids' => [$menu->beneficiary_group_id]],
            'menu' => $menu->attributesToArray(), 'customer' => $customer->name, 'beneficiary_group' => $group->name,
            'rows' => $rows, 'days' => $days, 'weeks' => $weeks, 'daily_demand' => $dailyDemand,
            'suggested_deliveries' => (new MenuDeliveryScheduler)->suggest($dailyDemand), 'evaluations' => $evaluations,
            'issues' => $issues, 'ready_for_review' => count($rows) > 0 && ! collect($issues)->contains('blocks_review', true),
            'methodology' => self::METHODOLOGY, 'generated_at' => now()->toIso8601String(), 'calculated_at' => now()->toIso8601String(), 'blocks_report' => false];
        return $this->withActual($project, $menu, $report);
    }

    private function withActual(SalesProject $project, \App\Models\ProjectMenu $menu, array $report): array
    {
        $actual = app(ConsumptionReportService::class)->build($project, ['analysis_mode' => 'consumption', 'competency' => 'delivery',
            'date_from' => $menu->date_from->format('Y-m-d'), 'date_to' => $menu->date_to->format('Y-m-d'),
            'customer_ids' => [$menu->customer_id], 'beneficiary_group_ids' => [$menu->beneficiary_group_id], 'type' => 'product']);
        $products = []; $actualIssues = [];
        foreach ($report['daily_demand'] as $demand) {
            $id = $demand['product_id'];
            $products[$id] ??= ['product_id' => $id, 'product' => $demand['product'], 'planned_g' => '0', 'distributed_g' => '0', 'distribution_ids' => [], 'complete' => true];
            $products[$id]['planned_g'] = bcadd($products[$id]['planned_g'], $demand['gross_quantity_g'], 16);
        }
        foreach ($actual['rows'] as $row) {
            $id = $row['product_id'];
            $products[$id] ??= ['product_id' => $id, 'product' => $row['product'], 'planned_g' => '0', 'distributed_g' => '0', 'distribution_ids' => [], 'complete' => true];
            $factor = (new QuantityMassConverter)->factor($row['unit'], $row['trace']['profile']['mass_conversion_factor_snapshot'] ?? null);
            $valid = ! in_array($row['reason'], ['invalid_distribution_context', 'changed_distribution_context', 'invalid_beneficiary_context'], true)
                && $factor !== null && bccomp($row['physical_quantity'], '0', 16) > 0;
            if (! $valid) {
                $products[$id]['complete'] = false;
                $actualIssues[] = ['distribution_id' => $row['id'], 'reason' => $factor === null ? 'missing_mass_conversion' : $row['reason']];
                continue;
            }
            $products[$id]['distributed_g'] = bcadd($products[$id]['distributed_g'], bcmul($row['physical_quantity'], $factor, 16), 16);
            $products[$id]['distribution_ids'][] = $row['id'];
        }
        foreach ($products as &$product) {
            $product['difference_g'] = $product['complete'] ? bcsub($product['distributed_g'], $product['planned_g'], 16) : null;
            $product['remaining_to_distribute_g'] = ! $product['complete'] ? null : (bccomp($product['difference_g'], '0', 16) < 0 ? bcsub('0', $product['difference_g'], 16) : '0');
        }
        unset($product);
        $report['comparison'] = array_values($products);
        $report['actual_issues'] = $actualIssues;
        $report['unassigned_distributions_count'] = ProductionDelivery::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)
            ->where('customer_id', $menu->customer_id)->whereNotNull('parent_delivery_id')->where('status', 'approved')
            ->whereDate('delivery_date', '>=', $menu->date_from)->whereDate('delivery_date', '<=', $menu->date_to)
            ->whereDoesntHave('consumptionProfile', fn ($q) => $q->where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->whereNotNull('beneficiary_group_id'))->count();
        return $report;
    }
}
