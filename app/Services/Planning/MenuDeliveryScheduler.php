<?php

namespace App\Services\Planning;

use Carbon\CarbonImmutable;

final class MenuDeliveryScheduler
{
    public function suggest(array $dailyDemand): array
    {
        usort($dailyDemand, fn ($a, $b) => [$a['product_id'], $a['date']] <=> [$b['product_id'], $b['date']]);
        $batches = [];
        foreach ($dailyDemand as $demand) {
            $life = $demand['shelf_life_days'] ?? null;
            if (! $life || $life < 1) { continue; }
            $last = array_key_last($batches);
            // Different validity sources are kept separate even if their dates overlap.
            if ($last !== null && $batches[$last]['product_id'] === $demand['product_id']
                && $batches[$last]['shelf_life_days'] === (int) $life
                && $batches[$last]['shelf_life_source'] === $demand['shelf_life_source']
                && $demand['date'] <= $batches[$last]['estimated_expiry']) {
                $batches[$last]['gross_quantity_g'] = bcadd($batches[$last]['gross_quantity_g'], $demand['gross_quantity_g'], 16);
                $batches[$last]['consumption_end'] = $demand['date'];
                continue;
            }
            $batches[] = ['product_id' => $demand['product_id'], 'product' => $demand['product'], 'delivery_date' => $demand['date'],
                'consumption_end' => $demand['date'], 'gross_quantity_g' => $demand['gross_quantity_g'], 'shelf_life_days' => (int) $life,
                'shelf_life_source' => $demand['shelf_life_source'], 'estimated_expiry' => CarbonImmutable::parse($demand['date'])->addDays($life - 1)->toDateString()];
        }
        return $batches;
    }
}
