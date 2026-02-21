<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna stock_movement_id em production_deliveries
 * para rastrear o movimento de estoque gerado pela aprovação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->foreignId('stock_movement_id')
                ->nullable()
                ->after('project_payment_id')
                ->constrained('stock_movements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropForeign(['stock_movement_id']);
            $table->dropColumn('stock_movement_id');
        });
    }
};
