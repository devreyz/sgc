<?php

namespace App\Services\Planning;

use Carbon\CarbonImmutable;

final class NutritionAudienceCalculator
{
    /** Shared group/day attendance is counted once across products, never once per food. */
    public function personDays(array $rows): ?string
    {
        $context = null;
        foreach ($rows as $row) {
            $rowContext = [$row['customer_id'] ?? null, $row['trace']['beneficiary_group_id'] ?? null];
            if ($context !== null && $context !== $rowContext) { return null; }
            $context = $rowContext;
        }
        if (count($rows) > 1 && empty($rows[0]['trace']['beneficiary_group_id'])) {
            return null;
        }
        $days = [];
        foreach ($rows as $row) {
            if ($row['status'] !== 'calculated') {
                return null;
            }
            $profile = $row['trace']['profile'];
            $daily = collect($profile['daily_audiences'] ?? [])->keyBy('date');
            $start = CarbonImmutable::parse($row['trace']['overlap_start']);
            $end = CarbonImmutable::parse($row['trace']['overlap_end']);
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $date = $day->toDateString();
                $value = match ($profile['count_method']) {
                    'served_people' => (string) $profile['served_people'],
                    'daily_attendance' => isset($daily[$date]) ? (string) $daily[$date]['served_people'] : null,
                    'person_days_direct' => bcdiv((string) $profile['person_days_direct'], (string) $row['trace']['coverage_days'], 16),
                    default => null,
                };
                if ($value === null || (isset($days[$date]) && bccomp($days[$date], $value, 16) !== 0)) {
                    return null;
                }
                $days[$date] = $value;
            }
        }
        $total = '0';
        foreach ($days as $value) {
            $total = bcadd($total, $value, 16);
        }
        return bccomp($total, '0', 16) > 0 ? $total : null;
    }
}
