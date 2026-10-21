<?php

namespace Tests\Unit;

use App\Services\Planning\MenuDeliveryScheduler;
use App\Services\Planning\MenuNutrientAggregator;
use App\Services\Planning\RecipeDemandCalculator;
use PHPUnit\Framework\TestCase;

class MenuCalculatorsTest extends TestCase
{
    public function test_recipe_scales_gross_ingredients_by_declared_yield(): void
    {
        $demand = (new RecipeDemandCalculator)->expand([
            ['product_id' => 1, 'gross_quantity_g' => '1000'], ['product_id' => 2, 'gross_quantity_g' => '500'],
        ], '10', '25');
        $this->assertSame(0, bccomp('2500', $demand[1], 16));
        $this->assertSame(0, bccomp('1250', $demand[2], 16));
    }

    public function test_daily_and_weekly_rates_are_weighted_and_missing_values_are_unknown(): void
    {
        $calculator = new MenuNutrientAggregator;
        $nutrients = [['id' => 1, 'code' => 'protein', 'name' => 'Proteína', 'unit' => 'g']];
        $result = $calculator->summarize([
            ['nutrients' => ['protein' => ['total' => '100']]], ['nutrients' => ['protein' => ['total' => '300']]],
        ], $nutrients, '100');
        $this->assertSame(0, bccomp('4', $result['nutrients']['protein']['per_person_day'], 16));
        $incomplete = $calculator->summarize([['nutrients' => ['protein' => ['total' => '100']]], ['nutrients' => []]], $nutrients, '100');
        $this->assertSame('partial', $incomplete['nutrients']['protein']['status']);
        $this->assertNull($incomplete['nutrients']['protein']['per_person_day']);
        $this->assertSame(0, bccomp('100', $incomplete['nutrients']['protein']['known_total'], 16));
    }

    public function test_delivery_suggestions_respect_inclusive_shelf_life_without_inventing_missing_validity(): void
    {
        $demand = [];
        foreach (['2026-10-01', '2026-10-04', '2026-10-05'] as $date) {
            $demand[] = ['product_id' => 1, 'product' => 'Alface', 'date' => $date, 'gross_quantity_g' => '1000', 'shelf_life_days' => 4, 'shelf_life_source' => 'product:1'];
        }
        $demand[] = ['product_id' => 2, 'product' => 'Sem validade', 'date' => '2026-10-01', 'gross_quantity_g' => '1000', 'shelf_life_days' => null, 'shelf_life_source' => null];
        $suggestions = (new MenuDeliveryScheduler)->suggest($demand);
        $this->assertCount(2, $suggestions);
        $this->assertSame('2026-10-01', $suggestions[0]['delivery_date']);
        $this->assertSame('2026-10-04', $suggestions[0]['estimated_expiry']);
        $this->assertSame(0, bccomp('2000', $suggestions[0]['gross_quantity_g'], 16));
        $this->assertSame('2026-10-05', $suggestions[1]['delivery_date']);
    }
}
