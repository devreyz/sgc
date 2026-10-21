<?php

namespace App\Services\Planning;

final class MenuNutrientAggregator
{
    public function summarize(array $parts, array $nutrients, ?string $personDays): array
    {
        $result = [];
        foreach ($nutrients as $nutrient) {
            $total = '0'; $missing = 0;
            foreach ($parts as $part) {
                $value = $part['nutrients'][$nutrient['code']]['total'] ?? $part['nutrients'][$nutrient['code']]['known_total'] ?? null;
                if ($value === null) { $missing++; continue; }
                $total = bcadd($total, $value, 16);
                if (($part['nutrients'][$nutrient['code']]['missing_parts'] ?? 0) > 0) { $missing++; }
            }
            $hasValue = count($parts) > $missing || collect($parts)->contains(fn ($part) => isset($part['nutrients'][$nutrient['code']]['known_total']));
            $rate = $hasValue && ! $missing && $personDays !== null && bccomp($personDays, '0', 16) > 0 ? bcdiv($total, $personDays, 16) : null;
            $result[$nutrient['code']] = ['nutrient_id' => $nutrient['id'], 'name' => $nutrient['name'], 'unit' => $nutrient['unit'],
                'known_total' => $hasValue ? $total : null, 'per_person_day' => $rate, 'missing_parts' => $missing,
                'status' => ! $hasValue ? 'not_calculable' : ($rate !== null ? 'calculated' : 'partial')];
        }
        return ['nutrients' => $result, 'person_days' => $personDays];
    }
}
