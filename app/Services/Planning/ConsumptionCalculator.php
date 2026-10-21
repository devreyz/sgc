<?php

namespace App\Services\Planning;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ConsumptionCalculator
{
    public const VERSION = 1;
    public const SCALE = 16;

    public function calculate(array $cycle, string $from, string $to, string $competency = 'consumption'): array
    {
        $reportStart = $this->date($from);
        $reportEnd = $this->date($to);
        if ($reportEnd->lt($reportStart) || ! in_array($competency, ['consumption', 'delivery'], true)) {
            throw new InvalidArgumentException('Período ou competência inválidos.');
        }
        $result = [
            'status' => 'not_calculable', 'reason' => null, 'quantity_g' => null,
            'person_days' => null, 'daily_per_capita_g' => null, 'issues' => [],
            'trace' => ['calculation_version' => self::VERSION, 'allocation_method' => 'uniform', 'quantity_basis' => 'distributed_gross', 'competency' => $competency],
        ];
        if (! ($cycle['calculation_enabled'] ?? true) || ($cycle['count_method'] ?? '') === 'not_applicable') {
            return array_replace($result, ['status' => 'not_applicable', 'reason' => 'not_applicable']);
        }
        if (empty($cycle['coverage_start_date']) || empty($cycle['coverage_end_date'])) {
            return array_replace($result, ['reason' => 'missing_coverage']);
        }
        $start = $this->date($cycle['coverage_start_date']);
        $end = $this->date($cycle['coverage_end_date']);
        if ($end->lt($start)) {
            return array_replace($result, ['reason' => 'invalid_consumption_window']);
        }
        $cycleDays = (int) $start->diffInDays($end) + 1;
        $overlapStart = $start->max($reportStart);
        $overlapEnd = $end->min($reportEnd);
        if ($competency === 'delivery') {
            $deliveryDate = $this->date($cycle['delivery_date']);
            if ($deliveryDate->lt($reportStart) || $deliveryDate->gt($reportEnd)) {
                return array_replace($result, ['status' => 'not_applicable', 'reason' => 'outside_period']);
            }
            $overlapStart = $start;
            $overlapEnd = $end;
        } elseif ($overlapEnd->lt($overlapStart)) {
            return array_replace($result, ['status' => 'not_applicable', 'reason' => 'outside_period']);
        }
        $days = (int) $overlapStart->diffInDays($overlapEnd) + 1;
        $result['trace'] += ['cycle_start' => $start->toDateString(), 'cycle_end' => $end->toDateString(),
            'coverage_days' => $cycleDays, 'overlap_start' => $overlapStart->toDateString(), 'overlap_end' => $overlapEnd->toDateString(), 'overlap_days' => $days];
        $shelfLife = $cycle['shelf_life_days_snapshot'] ?? null;
        $result['trace']['shelf_life_days'] = $shelfLife;
        $result['trace']['expires_at'] = $cycle['expires_at'] ?? null;
        if ($shelfLife !== null && $cycleDays > $shelfLife) {
            $result['issues'][] = 'coverage_exceeds_shelf_life';
        }
        if (! empty($cycle['expires_at']) && $end->gt($this->date($cycle['expires_at']))) {
            $result['issues'][] = 'expired_product';
        }
        $factor = (new QuantityMassConverter)->factor($cycle['unit'] ?? null, $cycle['mass_conversion_factor_snapshot'] ?? null);
        if ($factor === null || bccomp((string) $factor, '0', self::SCALE) <= 0) {
            return array_replace($result, ['reason' => 'missing_mass_conversion']);
        }
        if (bccomp((string) ($cycle['quantity'] ?? '0'), '0', self::SCALE) <= 0) {
            return array_replace($result, ['reason' => 'zero_quantity']);
        }
        $result['trace']['mass_conversion_factor'] = (string) $factor;
        $quantity = bcmul((string) $cycle['quantity'], (string) $factor, self::SCALE);
        $result['quantity_g'] = bcdiv(bcmul($quantity, (string) $days, self::SCALE), (string) $cycleDays, self::SCALE);
        $personDays = match ($cycle['count_method'] ?? '') {
            'served_people' => bcmul((string) ($cycle['served_people'] ?? 0), (string) $days, self::SCALE),
            'person_days_direct' => bcdiv(bcmul((string) ($cycle['person_days_direct'] ?? 0), (string) $days, self::SCALE), (string) $cycleDays, self::SCALE),
            'daily_attendance' => $this->attendance($cycle['daily_audiences'] ?? [], $overlapStart, $overlapEnd, $result),
            default => '0',
        };
        if (bccomp($personDays, '0', self::SCALE) <= 0) {
            return array_replace($result, ['reason' => 'missing_served_people']);
        }
        // Missing daily records cannot be silently interpreted as zero attendance.
        if (in_array('missing_daily_attendance', $result['issues'], true)) {
            return array_replace($result, ['reason' => 'missing_daily_attendance']);
        }
        return array_replace($result, [
            'status' => 'calculated', 'person_days' => $personDays,
            'daily_per_capita_g' => bcdiv($result['quantity_g'], $personDays, self::SCALE),
        ]);
    }

    public function consolidate(array $results): array
    {
        $quantity = '0';
        $personDays = '0';
        $calculated = 0;
        $missing = 0;
        foreach ($results as $result) {
            if ($result['status'] === 'calculated') {
                $quantity = bcadd($quantity, $result['quantity_g'], self::SCALE);
                $personDays = bcadd($personDays, $result['person_days'], self::SCALE);
                $calculated++;
            } elseif ($result['status'] === 'not_calculable') {
                $missing++;
            }
        }
        return [
            'status' => $calculated ? ($missing ? 'partial' : 'calculated') : ($missing ? 'not_calculable' : 'not_applicable'),
            'quantity_g' => $calculated ? $quantity : null, 'person_days' => $calculated ? $personDays : null,
            'daily_per_capita_g' => $calculated ? bcdiv($quantity, $personDays, self::SCALE) : null,
            'calculated_cycles' => $calculated, 'not_calculable_cycles' => $missing,
            'unique_people' => null,
        ];
    }

    private function attendance(array $records, CarbonImmutable $from, CarbonImmutable $to, array &$result): string
    {
        $byDate = [];
        foreach ($records as $record) {
            $byDate[$this->date($record['date'])->toDateString()] = (string) $record['served_people'];
        }
        $total = '0';
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            if (! array_key_exists($day->toDateString(), $byDate)) {
                $result['issues'][] = 'missing_daily_attendance';
                break;
            }
            $total = bcadd($total, $byDate[$day->toDateString()], self::SCALE);
        }
        return $total;
    }

    private function date(string $date): CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'America/Sao_Paulo');
        if (! $parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Data inválida.');
        }
        return $parsed;
    }
}
