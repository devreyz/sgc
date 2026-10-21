<?php

namespace App\Services\Planning;

final class PlanningRuleEvaluator
{
    public function evaluate(array $result, array $rule, array $source): array
    {
        $actual = match ($rule['metric']) {
            'daily_per_capita_g' => $result['daily_per_capita_g'],
            'coverage_days_vs_shelf_life' => $result['trace']['coverage_days'] ?? null,
            default => null,
        };
        $limit = $rule['metric'] === 'coverage_days_vs_shelf_life'
            ? ($result['trace']['shelf_life_days'] ?? null) : ($rule['value'] ?? null);
        $valid = null;
        if ($actual !== null && $limit !== null) {
            $comparison = bccomp((string) $actual, (string) $limit, ConsumptionCalculator::SCALE);
            $valid = match ($rule['operator']) {
                '<=' => $comparison <= 0, '>=' => $comparison >= 0,
                '<' => $comparison < 0, '>' => $comparison > 0, '=' => $comparison === 0,
                'between' => isset($rule['value_max']) && $comparison >= 0 && bccomp((string) $actual, (string) $rule['value_max'], ConsumptionCalculator::SCALE) <= 0,
                default => null,
            };
        }
        return ['rule' => $rule, 'source' => $source, 'actual' => $actual, 'limit' => $limit,
            'status' => $valid === null ? 'not_calculable' : ($valid ? 'compliant' : $rule['severity']),
            'blocks_report' => $valid === false && $rule['blocking_policy'] === 'report_block'];
    }
}
