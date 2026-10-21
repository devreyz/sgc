<?php

namespace App\Services\Planning;

use App\Models\BeneficiaryPopulationPeriod;
use App\Models\PlanningRuleSet;
use App\Models\PlanningReportSnapshot;
use App\Models\ProductionDelivery;
use App\Models\SalesProject;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ConsumptionReportService
{
    public const METHODOLOGY = 'Quantidade bruta distribuída convertida em gramas e rateada uniformemente pela interseção inclusiva dos ciclos. Consumo = soma das quantidades calculáveis / soma dos pessoa-dias correspondentes. População de referência não é denominador. Pessoas únicas não disponíveis. Competência de distribuição considera ciclos completos pela data física. Valores financeiros e fiscais seguem seus documentos de origem.';

    public function __construct(private ConsumptionCalculator $calculator, private PlanningRuleEvaluator $rules, private ProjectPlanningService $planning) {}

    public function build(SalesProject $project, array $filters): array
    {
        $this->planning->assertContext($project);
        $filters = Validator::make($filters, [
            'date_from' => 'required|date_format:Y-m-d', 'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'competency' => ['required', Rule::in(['consumption', 'delivery'])],
            'analysis_mode' => ['required', Rule::in(['consumption', 'compliance'])],
            'type' => ['nullable', Rule::in(['associate', 'product', 'customer'])],
            'associate_ids' => 'nullable|array|max:1000', 'associate_ids.*' => ['integer', Rule::exists('associates', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'product_ids' => 'nullable|array|max:1000', 'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'customer_ids' => 'nullable|array|max:1000', 'customer_ids.*' => ['integer', Rule::exists('customers', 'id')->where('tenant_id', $project->tenant_id)->whereNull('deleted_at')],
            'beneficiary_group_ids' => 'nullable|array|max:1000', 'beneficiary_group_ids.*' => ['integer', Rule::exists('beneficiary_groups', 'id')->where('tenant_id', $project->tenant_id)->where('sales_project_id', $project->id)],
        ])->validate();
        $tenantId = $project->tenant_id;
        $query = ProductionDelivery::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)
            ->whereNotNull('parent_delivery_id')->where('status', 'approved')
            ->with([
                'product' => fn ($q) => $q->where('tenant_id', $tenantId),
                'customer' => fn ($q) => $q->where('tenant_id', $tenantId),
                'associate' => fn ($q) => $q->where('tenant_id', $tenantId),
                'parentDelivery' => fn ($q) => $q->where('tenant_id', $tenantId)->where('sales_project_id', $project->id),
                'consumptionProfile' => fn ($q) => $q->where('tenant_id', $tenantId)->where('sales_project_id', $project->id),
                'consumptionProfile.dailyAudiences' => fn ($q) => $q->where('tenant_id', $tenantId),
                'consumptionProfile.beneficiaryGroup' => fn ($q) => $q->where('tenant_id', $tenantId)->where('sales_project_id', $project->id),
            ]);
        foreach (['associate', 'product', 'customer'] as $key) {
            if (! empty($filters[$key.'_ids'])) {
                $query->whereIn($key.'_id', $filters[$key.'_ids']);
            }
        }
        if (! empty($filters['beneficiary_group_ids'])) {
            $query->whereHas('consumptionProfile', fn ($q) => $q->where('tenant_id', $tenantId)->where('sales_project_id', $project->id)->whereIn('beneficiary_group_id', $filters['beneficiary_group_ids']));
        }
        $physical = fn ($q) => $q->whereDate('delivery_date', '>=', $filters['date_from'])->whereDate('delivery_date', '<=', $filters['date_to']);
        if ($filters['competency'] === 'delivery') {
            $physical($query);
        } else {
            $query->where(function ($q) use ($filters, $tenantId, $project, $physical) {
                $q->whereHas('consumptionProfile', fn ($p) => $p->where('tenant_id', $tenantId)->where('sales_project_id', $project->id)
                    ->where('coverage_start_date', '<=', $filters['date_to'])->where('coverage_end_date', '>=', $filters['date_from']))
                    ->orWhere(function ($q) use ($tenantId, $project, $physical) {
                        // Surface legacy records with unknown windows if physically in period.
                        $physical($q);
                        $q->whereDoesntHave('consumptionProfile', fn ($p) => $p->where('tenant_id', $tenantId)->where('sales_project_id', $project->id)->whereNotNull('coverage_start_date')->whereNotNull('coverage_end_date'));
                    });
            });
        }
        $sets = PlanningRuleSet::where('tenant_id', $tenantId)->where('sales_project_id', $project->id)
            ->with(['rules' => fn ($q) => $q->where('tenant_id', $tenantId)])->orderByDesc('version')->get();
        $rows = [];
        $evaluations = [];
        $distributions = $query->orderBy('delivery_date')->orderBy('id')->get();
        app(\App\Services\TenantIdentityService::class)->namesForAssociates($distributions->pluck('associate')->filter());
        $populationPeriods = BeneficiaryPopulationPeriod::where('tenant_id', $tenantId)
            ->whereIn('beneficiary_group_id', $distributions->pluck('consumptionProfile.beneficiary_group_id')->filter()->unique())
            ->get()->groupBy('beneficiary_group_id');
        foreach ($distributions as $distribution) {
            $profile = $distribution->consumptionProfile;
            $cycle = $profile ? $profile->attributesToArray() : [];
            $cycle += ['quantity' => (string) $distribution->quantity, 'unit' => $distribution->product?->unit,
                'delivery_date' => $distribution->delivery_date->format('Y-m-d')];
            if ($profile) {
                foreach (['coverage_start_date', 'coverage_end_date', 'expires_at'] as $key) {
                    $cycle[$key] = $profile->$key?->format('Y-m-d');
                }
                $cycle['daily_audiences'] = $profile->dailyAudiences->map(fn ($row) => ['date' => $row->date->format('Y-m-d'), 'served_people' => $row->served_people])->all();
            }
            $result = $this->calculator->calculate($cycle, $filters['date_from'], $filters['date_to'], $filters['competency']);
            if (! $profile) {
                $result['reason'] = 'missing_profile';
            }
            $contextReason = app(DistributionPlanningContext::class)->reason($distribution, $profile);
            if ($contextReason) {
                $result = array_replace($result, ['status' => 'not_calculable', 'reason' => $contextReason,
                    'quantity_g' => null, 'person_days' => null, 'daily_per_capita_g' => null]);
            }
            $result['trace'] += ['distribution_id' => $distribution->id, 'profile' => $cycle,
                'beneficiary_group_id' => $profile?->beneficiary_group_id, 'served_people' => $profile?->served_people,
                'service_events' => $profile?->service_events, 'associate_receipt_id' => $distribution->associate_receipt_id,
                'billing_receipt_id' => $distribution->billing_receipt_id, 'distribution_billing_id' => $distribution->distribution_billing_id];
            $referenceDate = $result['trace']['overlap_start'] ?? $filters['date_from'];
            $result['trace']['reference_population_date'] = $referenceDate;
            $population = ($populationPeriods->get($profile?->beneficiary_group_id) ?? collect())->first(fn ($period) =>
                $period->effective_from->format('Y-m-d') <= $referenceDate && (! $period->effective_to || $period->effective_to->format('Y-m-d') >= $referenceDate));
            $result['trace']['reference_population'] = $population?->population;
            $result['trace']['population_period'] = $population?->attributesToArray();
            $row = $result + ['id' => $distribution->id, 'product_id' => $distribution->product_id, 'customer_id' => $distribution->customer_id,
                'associate_id' => $distribution->associate_id, 'product' => $distribution->product?->name ?? 'Produto indisponível',
                'associate' => $distribution->associate?->display_name ?? 'Produtor indisponível',
                'customer' => $distribution->customer?->name ?? 'Unidade indisponível', 'physical_quantity' => (string) $distribution->quantity,
                'unit' => $distribution->product?->unit, 'delivery_date' => $distribution->delivery_date->format('Y-m-d')];
            $rows[] = $row;
            // Evaluate each cycle against the version effective at its start, never today's rule.
            $ruleDate = $cycle['coverage_start_date'] ?? $cycle['delivery_date'];
            $set = $sets->first(fn ($set) => $set->effective_from->format('Y-m-d') <= $ruleDate && (! $set->effective_to || $set->effective_to->format('Y-m-d') >= $ruleDate));
            if ($set) {
                // Validity/per-capita cycle rules use the full cycle, not a truncated report slice.
                $whole = ! $contextReason && $profile && ! empty($cycle['coverage_end_date']) ? $this->calculator->calculate($cycle, $cycle['coverage_start_date'], $cycle['coverage_end_date']) : $result;
                foreach ($set->rules->where('evaluation_scope', 'consumption_cycle') as $rule) {
                    $evaluations[] = $this->rules->evaluate($whole, $rule->attributesToArray(), $set->attributesToArray()) + ['distribution_id' => $distribution->id];
                }
            }
        }
        $summary = $this->calculator->consolidate($rows);
        // A period rule must cover the entire requested interval; partial validity is not silently extended.
        $periodSet = $sets->first(fn ($set) => $set->effective_from->format('Y-m-d') <= $filters['date_from'] && (! $set->effective_to || $set->effective_to->format('Y-m-d') >= $filters['date_to']));
        if ($periodSet) {
            foreach ($periodSet->rules->where('evaluation_scope', 'report_period') as $rule) {
                $evaluationBase = $summary['status'] === 'partial' ? array_replace($summary, ['daily_per_capita_g' => null]) : $summary;
                $evaluations[] = $this->rules->evaluate($evaluationBase, $rule->attributesToArray(), $periodSet->attributesToArray()) + ['distribution_id' => null];
            }
        }
        $groupKey = ($filters['type'] ?? 'product').'_id';
        $groups = collect($rows)->groupBy($groupKey)->map(fn ($items, $id) => ['id' => $id, 'name' => $items->first()[$filters['type'] ?? 'product'], 'summary' => $this->calculator->consolidate($items->all())])->values()->all();
        return ['calculation_version' => ConsumptionCalculator::VERSION, 'filters' => $filters,
            'context' => ['tenant_id' => (int) $tenantId, 'sales_project_id' => (int) $project->id],
            'summary' => $summary, 'rows' => $rows, 'groups' => $groups, 'evaluations' => $evaluations,
            'methodology' => self::METHODOLOGY, 'generated_at' => now()->toIso8601String(),
            'compliance_counts' => collect($evaluations)->countBy('status')->all(),
            'blocks_report' => collect($evaluations)->contains('blocks_report', true)];
    }

    public function snapshot(SalesProject $project, array $report): PlanningReportSnapshot
    {
        $this->planning->assertContext($project);
        abort_unless(($report['context']['tenant_id'] ?? null) === (int) $project->tenant_id
            && ($report['context']['sales_project_id'] ?? null) === (int) $project->id, 403);
        return PlanningReportSnapshot::create(['tenant_id' => $project->tenant_id, 'sales_project_id' => $project->id,
            'created_by' => auth()->id(), 'calculation_version' => $report['calculation_version'],
            'parameters' => $report['filters'], 'result' => $report]);
    }

    public function integrityIssues(array $report): array
    {
        $issues = [];
        foreach ($report['rows'] as $row) {
            foreach (array_unique(array_filter([$row['reason'], ...$row['issues']])) as $reason) {
                if (in_array($reason, ['outside_period', 'not_applicable'], true)) {
                    continue;
                }
                $issues[] = ['severity' => 'warning', 'domain' => 'planning', 'title' => 'Atendimento e consumo',
                    'message' => self::reasonLabel($reason).' — distribuição #'.$row['id'], 'action' => 'Revise o atendimento no planejamento do projeto.',
                    'actionKey' => 'open_planning', 'distributionId' => $row['id'], 'entity_type' => 'distribution', 'entity_id' => $row['id']];
            }
        }
        foreach ($report['evaluations'] as $evaluation) {
            if ($evaluation['status'] === 'compliant') {
                continue;
            }
            $issues[] = ['severity' => $evaluation['status'] === 'critical' ? 'critical' : 'warning', 'domain' => 'planning',
                'title' => $evaluation['rule']['name'], 'message' => 'Regra '.$evaluation['source']['name'].' v'.$evaluation['source']['version'].': '.$evaluation['status'],
                'action' => 'Revise os critérios no planejamento.', 'actionKey' => 'open_planning', 'distributionId' => $evaluation['distribution_id']];
        }
        return $issues;
    }

    public static function reasonLabel(?string $reason): string
    {
        return match ($reason) {
            'missing_profile' => 'Atendimento e consumo não informados',
            'invalid_distribution_context' => 'Distribuição sem contexto físico aprovado e compatível',
            'changed_distribution_context' => 'Destino ou produto alterado; confirme novamente o atendimento',
            'invalid_beneficiary_context' => 'Grupo de atendimento incompatível com o cliente da distribuição',
            'missing_nutrition_profile' => 'Composição nutricional não informada para a data da distribuição',
            'invalid_nutrition_profile' => 'Composição nutricional inválida ou incompleta',
            'partial_nutrition_scope' => 'Meta não avaliável: filtro de produto ou produtor seleciona apenas parte do fornecimento',
            'nutrition_target_requires_consumption' => 'Meta por pessoa-dia requer competência de consumo',
            'missing_coverage' => 'Cobertura não informada', 'missing_served_people' => 'Público atendido não informado',
            'missing_mass_conversion' => 'Quantidade física disponível; consumo não calculável sem conversão para gramas',
            'missing_daily_attendance' => 'Faltam registros diários de atendimento',
            'coverage_exceeds_shelf_life' => 'Cobertura superior à durabilidade', 'expired_product' => 'Janela de consumo ultrapassa a validade',
            'invalid_consumption_window' => 'Janela de consumo inválida', 'zero_quantity' => 'Quantidade deve ser positiva',
            'not_applicable' => 'Não aplicável', default => $reason ?? '',
        };
    }
}
