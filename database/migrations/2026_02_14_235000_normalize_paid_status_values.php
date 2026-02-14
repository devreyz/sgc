<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure 'billed' is an accepted enum value before updating rows.
        if (Schema::hasTable('service_orders')) {
            DB::statement(<<<'SQL'
ALTER TABLE `service_orders`
    MODIFY `associate_payment_status` ENUM('pending','billed','paid','cancelled') NOT NULL DEFAULT 'pending',
    MODIFY `provider_payment_status` ENUM('pending','billed','paid','cancelled') NOT NULL DEFAULT 'pending';
SQL
            );

            // Now normalize legacy 'paid' values to 'billed'
            DB::table('service_orders')
                ->where('associate_payment_status', 'paid')
                ->update(['associate_payment_status' => 'billed']);

            DB::table('service_orders')
                ->where('provider_payment_status', 'paid')
                ->update(['provider_payment_status' => 'billed']);
        }

        // Normalize in service_order_payments (ensure column exists)
        if (Schema::hasTable('service_order_payments') && Schema::hasColumn('service_order_payments', 'status')) {
            DB::table('service_order_payments')
                ->where('status', 'paid')
                ->update(['status' => 'billed']);
        }
    }

    public function down(): void
    {
        // Revert changes if necessary (not strictly reversible safely)
        if (Schema::hasTable('service_orders')) {
            DB::table('service_orders')
                ->where('associate_payment_status', 'billed')
                ->update(['associate_payment_status' => 'paid']);

            DB::table('service_orders')
                ->where('provider_payment_status', 'billed')
                ->update(['provider_payment_status' => 'paid']);
        }

        if (Schema::hasTable('service_order_payments')) {
            DB::table('service_order_payments')
                ->where('status', 'billed')
                ->update(['status' => 'paid']);
        }
    }
};
