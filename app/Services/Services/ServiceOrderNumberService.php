<?php

namespace App\Services\Services;

use Illuminate\Support\Facades\DB;

class ServiceOrderNumberService
{
    public function next(int $tenantId, int $year): string
    {
        DB::table('service_order_sequences')->insertOrIgnore(['tenant_id' => $tenantId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $counter = DB::table('service_order_sequences')->where('tenant_id', $tenantId)->where('year', $year)->lockForUpdate()->first();
        $number = ((int) $counter->last_number) + 1;
        DB::table('service_order_sequences')->where('tenant_id', $tenantId)->where('year', $year)->update(['last_number' => $number, 'updated_at' => now()]);

        return sprintf('OS-%d-%06d', $year, $number);
    }
}
