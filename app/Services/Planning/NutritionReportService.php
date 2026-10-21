<?php

namespace App\Services\Planning;

use App\Models\Nutrient;
use App\Models\NutritionTargetSet;
use App\Models\SalesProject;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class NutritionReportService
{
    public const METHODOLOGY = 'Estimativa nutricional a partir da massa bruta atribuída ao período × fração comestível informada. Nutriente = massa comestível × valor da composição / quantidade de referência. A composição vigente na data física e sua fonte são preservadas na emissão. O público é consolidado por cliente, grupo e dia, uma vez por dia entre produtos; divergências de público ou ausência de grupo impedem per capita consolidado. Nutriente ausente é desconhecido, não zero. Metas são por pessoa-dia, configuradas por grupo e cliente. Esta estimativa não constitui aprovação técnica de cardápio.';

    public function __construct(private ConsumptionReportService $consumption, private NutritionCalculator $calculator, private NutritionAudienceCalculator $audience) {}

    public function build(SalesProject $project, array $filters): array
    {
        app(ProjectPlanningService::class)->assertContext($project);
        if (! Schema::hasTable('food_profiles')) {
            throw ValidationException::withMessages(['analysis_mode' => 'Aplique a migração de nutrição antes de usar esta análise.']);
        }
        $report = $this->consumption->build($project, array_replace($filters, ['analysis_mode' => 'consumption']));
        $report['filters']['analysis_mode'] = 'nutrition';
        $report['calculation_version'] = NutritionCalculator::VERSION;
        $report['methodology'] .= ' '.self::METHODOLOGY;
        $tenantId = $project->tenant_id;
        $nutrients = Nutrient::where('tenant_id', $tenantId)->orderBy('code')->get();
        $resolver = app(FoodCompositionResolver::class);
        $profiles = $resolver->load((int) $tenantId, array_column($report['rows'], 'product_id'));
        foreach ($report['rows'] as &$row) {
            $date = $row['delivery_date'];
            $input = $resolver->resolve($profiles, (int) $row['product_id'], $date);
            $row['nutrition'] = $this->calculator->calculate($row, $input);
        }
        unset($row);
        $targetSets = NutritionTargetSet::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)
            ->with(['targets' => fn ($q) => $q->where('tenant_id', $tenantId)])->orderByDesc('version')->get();
        $groups = [];
        $evaluations = [];
        $eligibleRows = collect($report['rows'])->reject(fn ($row) => $row['status'] === 'not_applicable');
        foreach ($eligibleRows->groupBy(fn ($row) => $row['customer_id'].':'.($row['trace']['beneficiary_group_id'] ?? 'unknown')) as $key => $items) {
            $first = $items->first();
            $personDays = $this->audience->personDays($items->all());
            $group = ['customer_id' => $first['customer_id'], 'customer' => $first['customer'], 'beneficiary_group_id' => $first['trace']['beneficiary_group_id'],
                'person_days' => $personDays, 'audience_reason' => $personDays === null ? 'ambiguous_nutrition_audience' : null,
                'distribution_ids' => $items->pluck('id')->all(), 'nutrients' => []];
            foreach ($nutrients as $nutrient) {
                $total = '0';
                $known = 0;
                foreach ($items as $row) {
                    $value = $row['nutrition']['nutrients'][$nutrient->code] ?? null;
                    if ($value) {
                        $total = bcadd($total, $value['total'], 16);
                        $known++;
                    }
                }
                $complete = $known === $items->count();
                $group['nutrients'][$nutrient->code] = ['nutrient_id' => $nutrient->id, 'name' => $nutrient->name, 'unit' => $nutrient->unit,
                    'status' => $known === 0 ? 'not_calculable' : ($complete && $personDays !== null ? 'calculated' : 'partial'),
                    'known_total' => $known ? $total : null,
                    'per_person_day' => $complete && $personDays !== null ? bcdiv($total, $personDays, 16) : null,
                    'missing_cycles' => $items->count() - $known];
            }
            // Targets always retain customer+group context, regardless of report display grouping.
            $set = $targetSets->first(fn ($set) => (int) $set->customer_id === (int) $group['customer_id']
                && (int) $set->beneficiary_group_id === (int) $group['beneficiary_group_id']
                && $set->effective_from->format('Y-m-d') <= $report['filters']['date_from']
                && (! $set->effective_to || $set->effective_to->format('Y-m-d') >= $report['filters']['date_to']));
            if ($set) {
                foreach ($set->targets as $target) {
                    $nutrient = $nutrients->firstWhere('id', $target->nutrient_id);
                    if (! $nutrient) { continue; }
                    $scopeReason = ! empty($report['filters']['product_ids']) || ! empty($report['filters']['associate_ids'])
                        ? 'partial_nutrition_scope' : ($report['filters']['competency'] !== 'consumption' ? 'nutrition_target_requires_consumption' : null);
                    $value = $scopeReason ? null : $group['nutrients'][$nutrient->code]['per_person_day'];
                    $valid = $value === null ? null
                        : ((! isset($target->min_value) || bccomp($value, $target->min_value, 16) >= 0)
                            && (! isset($target->max_value) || bccomp($value, $target->max_value, 16) <= 0));
                    $evaluations[] = ['customer_id' => $group['customer_id'], 'beneficiary_group_id' => $group['beneficiary_group_id'],
                        'source' => $set->attributesToArray(), 'target' => $target->attributesToArray(),
                        'nutrient' => $nutrient->attributesToArray(), 'actual' => $value, 'reason' => $scopeReason,
                        'status' => $valid === null ? 'not_calculable' : ($valid ? 'compliant' : $target->severity)];
                }
            }
            $groups[] = $group;
        }
        $missing = collect($report['rows'])->where('nutrition.status', 'not_calculable')->count();
        $report['nutrition'] = ['groups' => $groups, 'evaluations' => $evaluations,
            'counts' => collect($evaluations)->countBy('status')->all(), 'not_calculable_cycles' => $missing,
            'missing_profile_cycles' => collect($report['rows'])->where('nutrition.reason', 'missing_nutrition_profile')->count(),
            'methodology' => self::METHODOLOGY];
        return $report;
    }

    public function integrityIssues(array $report): array
    {
        $issues = [];
        $add = function (string $message, ?int $id, string $severity = 'warning') use (&$issues) {
            $issues[] = ['domain' => 'nutrition', 'severity' => $severity, 'title' => 'Nutrição', 'message' => $message,
                'action' => 'Revise composição e público no planejamento.', 'actionKey' => 'open_planning', 'distributionId' => $id];
        };
        foreach ($report['rows'] as $row) {
            if ($row['nutrition']['status'] === 'not_calculable') {
                $add(ConsumptionReportService::reasonLabel($row['nutrition']['reason']).' — distribuição #'.$row['id'], $row['id']);
            }
        }
        foreach ($report['nutrition']['groups'] as $group) {
            if ($group['audience_reason']) {
                $add('Público nutricional ambíguo para '.$group['customer'].'. Informe grupo e atendimentos compatíveis.', $group['distribution_ids'][0] ?? null);
            }
            foreach ($group['nutrients'] as $nutrient) {
                if ($nutrient['missing_cycles'] > 0) {
                    $add('Composição incompleta de '.$nutrient['name'].' para '.$group['customer'].'.', $group['distribution_ids'][0] ?? null);
                }
            }
        }
        foreach ($report['nutrition']['evaluations'] as $evaluation) {
            if ($evaluation['status'] !== 'compliant') {
                $add('Meta '.$evaluation['source']['name'].' v'.$evaluation['source']['version'].': '.$evaluation['status'], null,
                    $evaluation['status'] === 'critical' ? 'critical' : 'warning');
            }
        }
        return $issues;
    }
}
