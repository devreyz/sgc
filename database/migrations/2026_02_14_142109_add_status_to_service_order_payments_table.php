<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('service_order_payments') && ! Schema::hasColumn('service_order_payments', 'status')) {
            Schema::table('service_order_payments', function (Blueprint $table) {
                $table->enum('status', ['pending', 'billed', 'cancelled'])
                    ->default('pending')
                    ->comment('Status: pending (aguardando faturamento), billed (faturado), cancelled (cancelado)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('service_order_payments') && Schema::hasColumn('service_order_payments', 'status')) {
            Schema::table('service_order_payments', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
