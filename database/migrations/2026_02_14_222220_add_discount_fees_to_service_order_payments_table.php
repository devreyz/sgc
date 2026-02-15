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
        Schema::table('service_order_payments', function (Blueprint $table) {
            $table->decimal('discount', 10, 2)->default(0)->after('amount');
            $table->decimal('fees', 10, 2)->default(0)->after('discount');
            $table->decimal('final_amount', 10, 2)->nullable()->after('fees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_order_payments', function (Blueprint $table) {
            $table->dropColumn(['discount', 'fees', 'final_amount']);
        });
    }
};
