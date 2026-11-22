<?php

namespace App\Services\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceOrderNumberService
{
    public function next(int $tenantId, int $year): string
    {
        $counter = $this->lockSequence($tenantId, $year);
        if (Schema::hasTable('released_service_order_numbers')) {
            $released = DB::table('released_service_order_numbers')->where('tenant_id', $tenantId)->where('year', $year);
            while ($number = (clone $released)->orderBy('number')->lockForUpdate()->value('number')) {
                (clone $released)->where('number', $number)->delete();
                $formatted = sprintf('OS-%d-%06d', $year, $number);
                if (! $this->occupied($tenantId, $formatted)) {
                    return $formatted;
                }
            }
        }
        $number = (int) $counter->last_number;
        do {
            $formatted = sprintf('OS-%d-%06d', $year, ++$number);
        } while ($this->occupied($tenantId, $formatted));
        DB::table('service_order_sequences')->where('tenant_id', $tenantId)->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);

        return $formatted;
    }

    public function release(int $tenantId, string $number): void
    {
        if (! preg_match('/^OS-([0-9]{4})-([0-9]{6,})$/D', $number, $parts) || (int) $parts[2] < 1) {
            return;
        }
        $this->lockSequence($tenantId, (int) $parts[1]);
        DB::table('released_service_order_numbers')->insertOrIgnore([
            'tenant_id' => $tenantId, 'year' => (int) $parts[1], 'number' => (int) $parts[2],
        ]);
    }

    private function lockSequence(int $tenantId, int $year): object
    {
        DB::table('service_order_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('service_order_sequences')->where('tenant_id', $tenantId)->where('year', $year)->lockForUpdate()->first();
    }

    private function occupied(int $tenantId, string $number): bool
    {
        return DB::table('service_orders')->where('tenant_id', $tenantId)->where('number', $number)->lockForUpdate()->first(['id']) !== null;
    }
}
