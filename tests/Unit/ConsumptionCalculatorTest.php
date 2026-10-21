<?php

namespace Tests\Unit;

use App\Services\Planning\ConsumptionCalculator;
use App\Services\Planning\PlanningRuleEvaluator;
use PHPUnit\Framework\TestCase;

class ConsumptionCalculatorTest extends TestCase
{
    private function cycle(array $overrides = []): array
    {
        return array_replace([
            'quantity' => '350', 'unit' => 'kg', 'count_method' => 'served_people',
            'served_people' => 500, 'coverage_start_date' => '2026-09-29',
            'coverage_end_date' => '2026-10-05', 'delivery_date' => '2026-09-29',
        ], $overrides);
    }

    public function test_month_overlap_and_weighted_consolidation(): void
    {
        $calculator = new ConsumptionCalculator;
        $a = $calculator->calculate($this->cycle(), '2026-10-01', '2026-10-31');
        $b = $calculator->calculate($this->cycle(['quantity' => '360', 'served_people' => 520,
            'coverage_start_date' => '2026-10-06', 'coverage_end_date' => '2026-10-12']), '2026-10-01', '2026-10-31');
        $this->assertSame(5, $a['trace']['overlap_days']);
        $this->assertSame(0, bccomp('250000', $a['quantity_g'], 8));
        $this->assertSame(0, bccomp('2500', $a['person_days'], 8));
        $total = $calculator->consolidate([$a, $b]);
        $this->assertSame(0, bccomp('610000', $total['quantity_g'], 8));
        $this->assertSame(0, bccomp('6140', $total['person_days'], 8));
        $this->assertSame(0, bccomp('99.348534201954', $total['daily_per_capita_g'], 12));
        $this->assertNull($total['unique_people']);
        $physical = $calculator->calculate($this->cycle(), '2026-10-01', '2026-10-31', 'delivery');
        $this->assertSame('outside_period', $physical['reason']);
    }

    public function test_people_times_days_and_per_capita(): void
    {
        $result = (new ConsumptionCalculator)->calculate($this->cycle(['quantity' => '1000']), '2026-09-01', '2026-10-31');
        $this->assertSame(0, bccomp('3500', $result['person_days'], 8));
        $this->assertSame(0, bccomp('285.714285714285', $result['daily_per_capita_g'], 12));
    }

    public function test_daily_attendance_is_summed_and_missing_days_are_not_zero(): void
    {
        $daily = [];
        foreach ([80, 70, 90, 60, 85, 65, 50] as $index => $people) {
            $daily[] = ['date' => '2026-10-0'.($index + 1), 'served_people' => $people];
        }
        $cycle = $this->cycle(['count_method' => 'daily_attendance', 'coverage_start_date' => '2026-10-01',
            'coverage_end_date' => '2026-10-07', 'daily_audiences' => $daily]);
        $calculator = new ConsumptionCalculator;
        $result = $calculator->calculate($cycle, '2026-10-01', '2026-10-31');
        $this->assertSame(0, bccomp('500', $result['person_days'], 8));
        $partial = $calculator->calculate($cycle, '2026-10-02', '2026-10-02');
        $this->assertSame(0, bccomp('70', $partial['person_days'], 8));
        $this->assertSame(1, $partial['trace']['overlap_days']);
        array_pop($cycle['daily_audiences']);
        $this->assertSame('not_calculable', $calculator->calculate($cycle, '2026-10-01', '2026-10-31')['status']);
    }

    public function test_missing_conversion_and_legacy_data_never_become_zero(): void
    {
        $calculator = new ConsumptionCalculator;
        $missing = $calculator->calculate($this->cycle(['unit' => 'pé']), '2026-10-01', '2026-10-31');
        $this->assertSame('missing_mass_conversion', $missing['reason']);
        $this->assertNull($missing['daily_per_capita_g']);
        $legacy = $calculator->calculate([], '2026-10-01', '2026-10-31');
        $this->assertSame('missing_coverage', $legacy['reason']);
        $this->assertNull($calculator->consolidate([$missing, $legacy])['daily_per_capita_g']);
    }

    public function test_shelf_life_is_an_issue_and_does_not_change_denominator(): void
    {
        $result = (new ConsumptionCalculator)->calculate($this->cycle(['shelf_life_days_snapshot' => 4,
            'expires_at' => '2026-10-03']), '2026-09-01', '2026-10-31');
        $this->assertSame('calculated', $result['status']);
        $this->assertContains('coverage_exceeds_shelf_life', $result['issues']);
        $this->assertContains('expired_product', $result['issues']);
        $this->assertSame(0, bccomp('3500', $result['person_days'], 8));
    }

    public function test_direct_person_days_are_allocated_and_non_intersecting_cycles_excluded(): void
    {
        $calculator = new ConsumptionCalculator;
        $result = $calculator->calculate($this->cycle(['count_method' => 'person_days_direct', 'person_days_direct' => '3420']), '2026-10-01', '2026-10-31');
        $this->assertSame(0, bccomp(bcdiv('17100', '7', 16), $result['person_days'], 16));
        $this->assertSame('outside_period', $calculator->calculate($this->cycle(), '2026-11-01', '2026-11-30')['reason']);
        $this->assertSame('invalid_consumption_window', $calculator->calculate($this->cycle(['coverage_end_date' => '2026-09-28']), '2026-10-01', '2026-10-31')['reason']);
    }

    public function test_rules_are_configured_and_missing_metrics_not_compliant(): void
    {
        $evaluator = new PlanningRuleEvaluator;
        $rule = ['metric' => 'daily_per_capita_g', 'operator' => '<=', 'value' => '90', 'severity' => 'critical', 'blocking_policy' => 'report_block'];
        $result = ['daily_per_capita_g' => '100'];
        $evaluation = $evaluator->evaluate($result, $rule, ['version' => 1, 'source_type' => 'internal']);
        $this->assertSame('critical', $evaluation['status']);
        $this->assertTrue($evaluation['blocks_report']);
        $this->assertSame('not_calculable', $evaluator->evaluate(['daily_per_capita_g' => null], $rule, [])['status']);
    }
}
