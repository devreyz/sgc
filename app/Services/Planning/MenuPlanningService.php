<?php

namespace App\Services\Planning;

use App\Models\BeneficiaryGroup;
use App\Models\MenuDay;
use App\Models\MenuMeal;
use App\Models\ProjectMenu;
use App\Models\Recipe;
use App\Models\SalesProject;
use App\Services\ProjectDistributionCustomerService;
use App\Services\TenantSecurityAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MenuPlanningService
{
    public const STATUSES = ['planned', 'calculated', 'awaiting_validation', 'approved', 'in_execution', 'finalized'];

    public function __construct(private ProjectPlanningService $planning) {}

    public function menu(SalesProject $project, int $id): ProjectMenu
    {
        $this->planning->assertContext($project);
        return ProjectMenu::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->findOrFail($id);
    }

    public function createRecipe(SalesProject $project, array $input): Recipe
    {
        $this->planning->assertContext($project);
        $data = Validator::make($input, [
            'code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{0,79}$/'], 'name' => 'required|string|max:255',
            'yield_portions' => ['required', 'regex:'.NutritionCatalogService::DECIMAL, 'numeric', 'gt:0'],
            'portion_weight_g' => ['required', 'regex:'.NutritionCatalogService::DECIMAL, 'numeric', 'gt:0'],
            'source_reference' => 'required|string|max:255', 'instructions' => 'nullable|string|max:20000',
            'items' => 'required|array|min:1|max:100', 'items.*' => 'array:product_id,gross_quantity_g',
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'items.*.gross_quantity_g' => ['required', 'regex:'.NutritionCatalogService::DECIMAL, 'numeric', 'gt:0'],
        ])->validate();
        return DB::transaction(function () use ($project, $data) {
            \App\Models\Tenant::whereKey($project->tenant_id)->lockForUpdate()->firstOrFail();
            $version = (int) Recipe::where('tenant_id', $project->tenant_id)->where('code', $data['code'])->max('version') + 1;
            $items = $data['items']; unset($data['items']);
            $recipe = Recipe::create(['tenant_id' => $project->tenant_id, 'version' => $version] + $data);
            foreach ($items as $item) { $recipe->items()->create(['tenant_id' => $project->tenant_id] + $item); }
            return $recipe;
        });
    }

    public function createMenu(SalesProject $project, array $input): ProjectMenu
    {
        $this->planning->assertContext($project);
        $data = Validator::make($input, [
            'code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{0,79}$/'], 'name' => 'required|string|max:255',
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'beneficiary_group_id' => ['required', 'integer', Rule::exists('beneficiary_groups', 'id')->where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)],
            'date_from' => 'required|date_format:Y-m-d', 'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
        ])->validate();
        if (CarbonImmutable::parse($data['date_from'])->diffInDays(CarbonImmutable::parse($data['date_to'])) > 365) {
            throw ValidationException::withMessages(['date_to' => 'Cada versão de cardápio pode cobrir até 366 dias.']);
        }
        app(ProjectDistributionCustomerService::class)->assertAllowed($project, [$data['customer_id']]);
        return DB::transaction(function () use ($project, $data) {
            SalesProject::where('tenant_id', $project->tenant_id)->lockForUpdate()->findOrFail($project->id);
            if (($project->start_date && $data['date_from'] < $project->start_date->format('Y-m-d'))
                || ($project->end_date && $data['date_to'] > $project->end_date->format('Y-m-d'))) {
                throw ValidationException::withMessages(['date_from' => 'O período do cardápio deve estar dentro do projeto.']);
            }
            $group = BeneficiaryGroup::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->findOrFail($data['beneficiary_group_id']);
            if ($group->customer_id && (int) $group->customer_id !== (int) $data['customer_id']) {
                throw ValidationException::withMessages(['beneficiary_group_id' => 'O grupo pertence a outro cliente.']);
            }
            $context = ['tenant_id' => $project->tenant_id, 'sales_project_id' => $project->id, 'customer_id' => $data['customer_id'],
                'beneficiary_group_id' => $data['beneficiary_group_id'], 'code' => $data['code']];
            return ProjectMenu::create($context + $data + ['version' => (int) ProjectMenu::where($context)->max('version') + 1, 'revision' => 1, 'status' => 'planned']);
        });
    }

    public function saveMeal(SalesProject $project, int $menuId, array $input): ProjectMenu
    {
        $this->planning->assertContext($project);
        $data = Validator::make($input, [
            'revision' => 'required|integer|min:1', 'meal_id' => 'nullable|integer|min:1',
            'date' => 'required|date_format:Y-m-d', 'name' => 'required|string|max:100',
            'planned_people' => 'required|integer|min:1|max:100000000',
            'items' => 'required|array|min:1|max:100', 'items.*' => 'array:product_id,recipe_id,quantity_per_person,quantity_unit',
            'items.*.product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'items.*.recipe_id' => ['nullable', 'integer', Rule::exists('recipes', 'id')->where('tenant_id', $project->tenant_id)],
            'items.*.quantity_per_person' => ['required', 'regex:'.NutritionCatalogService::DECIMAL, 'numeric', 'gt:0'],
            'items.*.quantity_unit' => ['required', Rule::in(['g', 'portion'])],
        ])->validate();
        foreach ($data['items'] as $item) {
            if ((empty($item['product_id']) === empty($item['recipe_id']))
                || $item['quantity_unit'] !== (! empty($item['recipe_id']) ? 'portion' : 'g')) {
                throw ValidationException::withMessages(['items' => 'Escolha produto em gramas brutas ou receita em porções, exclusivamente.']);
            }
        }
        return DB::transaction(function () use ($project, $menuId, $data) {
            $menu = $this->lockMenu($project, $menuId, $data['revision']);
            $this->assertPlanned($menu);
            if ($data['date'] < $menu->date_from->format('Y-m-d') || $data['date'] > $menu->date_to->format('Y-m-d')) {
                throw ValidationException::withMessages(['date' => 'A refeição deve estar dentro do período do cardápio.']);
            }
            $day = $menu->days()->where('tenant_id', $project->tenant_id)->whereDate('date', $data['date'])->first()
                ?? $menu->days()->create(['tenant_id' => $project->tenant_id, 'date' => $data['date']]);
            $meal = ! empty($data['meal_id'])
                ? MenuMeal::where('tenant_id', $project->tenant_id)->whereIn('menu_day_id', $menu->days()->select('id'))->findOrFail($data['meal_id'])
                : new MenuMeal(['tenant_id' => $project->tenant_id]);
            if ($day->meals()->where('tenant_id', $project->tenant_id)->where('name', $data['name'])->when($meal->exists, fn ($q) => $q->whereKeyNot($meal->id))->exists()) {
                throw ValidationException::withMessages(['name' => 'Já existe refeição com esse nome no dia.']);
            }
            $oldDayId = $meal->menu_day_id;
            $meal->fill(['menu_day_id' => $day->id, 'name' => $data['name'], 'planned_people' => $data['planned_people']])->save();
            // Delete through models so ingredient replacement remains in the activity log.
            $meal->items()->where('tenant_id', $project->tenant_id)->get()->each->delete();
            foreach ($data['items'] as $item) { $meal->items()->create(['tenant_id' => $project->tenant_id] + $item); }
            if ($oldDayId && (int) $oldDayId !== (int) $day->id) {
                $old = MenuDay::where('tenant_id', $project->tenant_id)->find($oldDayId);
                if ($old && ! $old->meals()->exists()) { $old->delete(); }
            }
            $menu->increment('revision');
            return $menu;
        });
    }

    public function removeMeal(SalesProject $project, int $menuId, array $input): ProjectMenu
    {
        $data = Validator::make($input, ['revision' => 'required|integer|min:1', 'meal_id' => 'required|integer|min:1'])->validate();
        return DB::transaction(function () use ($project, $menuId, $data) {
            $menu = $this->lockMenu($project, $menuId, $data['revision']); $this->assertPlanned($menu);
            $meal = MenuMeal::where('tenant_id', $project->tenant_id)->whereIn('menu_day_id', $menu->days()->select('id'))->findOrFail($data['meal_id']);
            $dayId = $meal->menu_day_id;
            $meal->items()->where('tenant_id', $project->tenant_id)->get()->each->delete();
            $meal->delete();
            $day = $menu->days()->where('tenant_id', $project->tenant_id)->findOrFail($dayId);
            if (! $day->meals()->exists()) { $day->delete(); }
            $menu->increment('revision');
            return $menu;
        });
    }

    public function cloneMenu(SalesProject $project, int $id): ProjectMenu
    {
        return DB::transaction(function () use ($project, $id) {
            $source = $this->lockMenu($project, $id, $this->menu($project, $id)->revision)->load(['days' => fn ($q) => $q->where('tenant_id', $project->tenant_id),
                'days.meals' => fn ($q) => $q->where('tenant_id', $project->tenant_id), 'days.meals.items' => fn ($q) => $q->where('tenant_id', $project->tenant_id)]);
            $copy = $this->createMenu($project, ['name' => $source->name, 'code' => $source->code, 'customer_id' => $source->customer_id,
                'beneficiary_group_id' => $source->beneficiary_group_id, 'date_from' => $source->date_from->format('Y-m-d'), 'date_to' => $source->date_to->format('Y-m-d')]);
            foreach ($source->days as $day) {
                foreach ($day->meals as $meal) {
                    $copy = $this->saveMeal($project, $copy->id, ['revision' => $copy->revision, 'date' => $day->date->format('Y-m-d'),
                        'name' => $meal->name, 'planned_people' => $meal->planned_people, 'items' => $meal->items->map(fn ($item) => $item->only(['product_id', 'recipe_id', 'quantity_per_person', 'quantity_unit']))->all()]);
                }
            }
            return $copy;
        });
    }

    public function transition(SalesProject $project, int $id, array $input): ProjectMenu
    {
        $data = Validator::make($input, ['revision' => 'required|integer|min:1',
            'action' => ['required', Rule::in(['calculate', 'revise', 'submit', 'approve', 'start', 'finalize'])],
            'reviewer_name' => 'nullable|required_if:action,approve|string|max:255',
            'professional_reference' => 'nullable|required_if:action,approve|string|max:255',
            'approval_reference' => 'nullable|required_if:action,approve|string|max:255',
        ])->validate();
        return DB::transaction(function () use ($project, $id, $data) {
            $menu = $this->lockMenu($project, $id, $data['revision']);
            $next = match ($data['action']) {
                'calculate' => $menu->status === 'planned' ? 'calculated' : null,
                'revise' => in_array($menu->status, ['calculated', 'awaiting_validation'], true) ? 'planned' : null,
                'submit' => $menu->status === 'calculated' ? 'awaiting_validation' : null,
                'approve' => $menu->status === 'awaiting_validation' ? 'approved' : null,
                'start' => $menu->status === 'approved' ? 'in_execution' : null,
                'finalize' => $menu->status === 'in_execution' ? 'finalized' : null,
            };
            if (! $next) { throw ValidationException::withMessages(['action' => 'Transição incompatível com o estado atual.']); }
            if ($data['action'] === 'calculate') {
                $report = app(MenuReportService::class)->build($project, $menu->id, false);
                if (empty($report['rows'])) { throw ValidationException::withMessages(['menu' => 'Cadastre pelo menos uma refeição.']); }
                $snapshot = app(ConsumptionReportService::class)->snapshot($project, $report);
                $menu->calculation_snapshot_id = $snapshot->id;
            }
            if (in_array($data['action'], ['submit', 'approve'], true)) {
                $snapshot = \App\Models\PlanningReportSnapshot::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->findOrFail($menu->calculation_snapshot_id);
                if ((int) ($snapshot->result['menu']['id'] ?? 0) !== (int) $menu->id
                    || (int) ($snapshot->result['menu']['customer_id'] ?? 0) !== (int) $menu->customer_id
                    || (int) ($snapshot->result['menu']['beneficiary_group_id'] ?? 0) !== (int) $menu->beneficiary_group_id) {
                    throw ValidationException::withMessages(['menu' => 'Cálculo preservado incompatível com cliente e público do cardápio.']);
                }
                if (! ($snapshot->result['ready_for_review'] ?? false)) {
                    throw ValidationException::withMessages(['menu' => 'Complete a composição e resolva o público ambíguo antes da validação.']);
                }
            }
            if ($data['action'] === 'approve') {
                abort_unless(auth()->user(), 403);
                app(TenantSecurityAuthorization::class)->authorize(auth()->user(), (int) $project->tenant_id, 'approve_menus');
                $menu->fill(['reviewer_name' => $data['reviewer_name'], 'professional_reference' => $data['professional_reference'],
                    'approval_reference' => $data['approval_reference'], 'approved_by' => auth()->id(), 'approved_at' => now()]);
            }
            if ($data['action'] === 'revise') { $menu->calculation_snapshot_id = null; }
            $menu->status = $next; $menu->revision++; $menu->save();
            return $menu;
        });
    }

    private function lockMenu(SalesProject $project, int $id, int $revision): ProjectMenu
    {
        $this->planning->assertContext($project);
        $menu = ProjectMenu::where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)->lockForUpdate()->findOrFail($id);
        if ($menu->revision !== $revision) { throw ValidationException::withMessages(['revision' => 'O cardápio mudou. Atualize a página antes de salvar.']); }
        return $menu;
    }

    private function assertPlanned(ProjectMenu $menu): void
    {
        if ($menu->status !== 'planned') { throw ValidationException::withMessages(['menu' => 'Revise o cálculo ou crie uma nova versão para alterar o cardápio.']); }
    }
}
