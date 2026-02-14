<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        // Add 'billed' to the enum options for associate_payment_status and provider_payment_status
        DB::statement(<<<SQL
ALTER TABLE `service_orders`
    MODIFY `associate_payment_status` ENUM('pending','billed','paid','cancelled') NOT NULL DEFAULT 'pending',
    MODIFY `provider_payment_status` ENUM('pending','billed','paid','cancelled') NOT NULL DEFAULT 'pending';
SQL
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('service_orders')) {
            return;
        }

        // Revert to previous enum set (keeps values as-is if some rows already 'billed')
        DB::statement(<<<SQL
ALTER TABLE `service_orders`
    MODIFY `associate_payment_status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    MODIFY `provider_payment_status` ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending';
SQL
        );
    }
};
