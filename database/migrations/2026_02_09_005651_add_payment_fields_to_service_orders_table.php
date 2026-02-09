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
        Schema::table('service_orders', function (Blueprint $table) {
            $table->boolean('paid')->default(false)->after('status');
            $table->date('paid_date')->nullable()->after('paid');
            $table->foreignId('payment_id')->nullable()->constrained('cash_movements')->nullOnDelete()->after('paid_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
            $table->dropColumn(['paid', 'paid_date', 'payment_id']);
        });
    }
};
