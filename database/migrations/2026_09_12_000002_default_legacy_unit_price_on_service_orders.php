<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_orders') || ! Schema::hasColumn('service_orders', 'unit_price')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `service_orders` MODIFY `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0');

            return;
        }

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->decimal('unit_price', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Mantém o padrão seguro para não quebrar ordens criadas pela fundação versionada.
    }
};
