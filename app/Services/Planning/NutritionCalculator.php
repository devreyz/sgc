<?php

namespace App\Services\Planning;

final class NutritionCalculator
{
    public const VERSION = 2;

    public function calculate(array $consumption, ?array $foodProfile): array
    {
        $result = ['status' => 'not_calculable', 'reason' => null, 'edible_quantity_g' => null, 'nutrients' => [],
            'trace' => ['nutrition_calculation_version' => self::VERSION, 'quantity_basis' => 'estimated_edible_mass', 'food_profile' => $foodProfile]];
        if ($consumption['status'] !== 'calculated') {
            return array_replace($result, ['status' => $consumption['status'], 'reason' => $consumption['reason']]);
        }
        if (! $foodProfile) {
            return array_replace($result, ['reason' => 'missing_nutrition_profile']);
        }
        $reference = (string) ($foodProfile['reference_quantity_g'] ?? '0');
        $fraction = (string) ($foodProfile['edible_fraction'] ?? '0');
        if (empty($foodProfile['values']) || bccomp($reference, '0', 16) <= 0 || bccomp($fraction, '0', 16) <= 0 || bccomp($fraction, '1', 16) > 0) {
            return array_replace($result, ['reason' => 'invalid_nutrition_profile']);
        }
        $result['edible_quantity_g'] = bcmul($consumption['quantity_g'], $fraction, 16);
        foreach ($foodProfile['values'] as $value) {
            $total = bcdiv(bcmul($result['edible_quantity_g'], (string) $value['amount'], 16), $reference, 16);
            $result['nutrients'][$value['code']] = [
                'nutrient_id' => $value['nutrient_id'], 'name' => $value['name'], 'unit' => $value['unit'],
                'total' => $total, 'per_person_day' => bcdiv($total, $consumption['person_days'], 16),
            ];
        }
        return array_replace($result, ['status' => 'calculated']);
    }
}
