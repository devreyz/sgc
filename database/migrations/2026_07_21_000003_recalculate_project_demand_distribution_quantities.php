<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_demands') || ! Schema::hasTable('production_deliveries')) {
            return;
        }

        DB::table('project_demands')
            ->select(['id', 'tenant_id', 'sales_project_id', 'customer_id'])
            ->when(
                Schema::hasColumn('project_demands', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->orderBy('id')
            ->chunkById(100, function ($demands): void {
                foreach ($demands as $demand) {
                    $distributions = DB::table('production_deliveries')
                        ->where('tenant_id', $demand->tenant_id)
                        ->where('sales_project_id', $demand->sales_project_id)
                        ->where('project_demand_id', $demand->id)
                        ->whereNotNull('parent_delivery_id')
                        ->where('status', 'approved')
                        ->when(
                            Schema::hasColumn('production_deliveries', 'deleted_at'),
                            fn ($query) => $query->whereNull('deleted_at')
                        )
                        ->when(
                            $demand->customer_id,
                            fn ($query) => $query->where('customer_id', $demand->customer_id)
                        );

                    DB::table('project_demands')
                        ->where('id', $demand->id)
                        ->where('tenant_id', $demand->tenant_id)
                        ->update([
                            'delivered_quantity' => $distributions->sum('quantity'),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // The previous values were derived from reception records and cannot be restored safely.
    }
};
