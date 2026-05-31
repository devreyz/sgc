<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_billings', function (Blueprint $table) {
            // Snapshot das taxas aplicadas no momento do faturamento (JSON)
            // Formato: [{id, name, type, nature, rate, amount, label}, ...]
            $table->json('fee_snapshot')->nullable()->after('total_net');
        });
    }

    public function down(): void
    {
        Schema::table('distribution_billings', function (Blueprint $table) {
            $table->dropColumn('fee_snapshot');
        });
    }
};
