<?php

namespace Tests\Unit;

use App\Services\Planning\NutritionAudienceCalculator;
use App\Services\Planning\NutritionCalculator;
use PHPUnit\Framework\TestCase;

class NutritionCalculatorTest extends TestCase
{
    public function test_edible_mass_and_nutrients_use_declared_reference_without_money_rounding(): void
    {
        $result = (new NutritionCalculator)->calculate(['status' => 'calculated', 'reason' => null,
            'quantity_g' => '250000', 'person_days' => '2500'], [
            'reference_quantity_g' => '100', 'edible_fraction' => '0.8',
            'values' => [['nutrient_id' => 1, 'code' => 'protein_g', 'name' => 'Proteína', 'unit' => 'g', 'amount' => '1.2']],
        ]);
        $this->assertSame(0, bccomp('200000', $result['edible_quantity_g'], 16));
        $this->assertSame(0, bccomp('2400', $result['nutrients']['protein_g']['total'], 16));
        $this->assertSame(0, bccomp('0.96', $result['nutrients']['protein_g']['per_person_day'], 16));
        $this->assertArrayNotHasKey('iron_mg', $result['nutrients']);
    }

    public function test_nonstandard_reference_and_missing_profile_are_explicit(): void
    {
        $calculator = new NutritionCalculator;
        $cycle = ['status' => 'calculated', 'reason' => null, 'quantity_g' => '1000', 'person_days' => '10'];
        $result = $calculator->calculate($cycle, ['reference_quantity_g' => '50', 'edible_fraction' => '1',
            'values' => [['nutrient_id' => 1, 'code' => 'iron', 'name' => 'Ferro', 'unit' => 'mg', 'amount' => '2']]]);
        $this->assertSame(0, bccomp('40', $result['nutrients']['iron']['total'], 16));
        $this->assertSame('missing_nutrition_profile', $calculator->calculate($cycle, null)['reason']);
        $this->assertNull($calculator->calculate($cycle, null)['edible_quantity_g']);
    }

    private function row(int $people = 500, ?int $group = 1): array
    {
        return ['status' => 'calculated', 'trace' => ['beneficiary_group_id' => $group,
            'overlap_start' => '2026-10-01', 'overlap_end' => '2026-10-07', 'coverage_days' => 7,
            'profile' => ['count_method' => 'served_people', 'served_people' => $people]]];
    }

    public function test_same_audience_is_counted_once_across_foods_and_conflicts_are_not_guessed(): void
    {
        $calculator = new NutritionAudienceCalculator;
        $this->assertSame(0, bccomp('3500', $calculator->personDays([$this->row(), $this->row()]), 16));
        $this->assertNull($calculator->personDays([$this->row(), $this->row(520)]));
        $this->assertNull($calculator->personDays([$this->row(500, null), $this->row(500, null)]));
        $this->assertSame(0, bccomp('3500', $calculator->personDays([$this->row(500, null)]), 16));
    }
}
