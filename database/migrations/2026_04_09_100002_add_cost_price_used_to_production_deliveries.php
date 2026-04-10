<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona campo cost_price_used à tabela production_deliveries
 * para persistência histórica do valor de compra/repasse unitário
 * utilizado no momento da entrega.
 *
 * Garante que registros antigos nunca sejam recalculados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->decimal('cost_price_used', 10, 2)->nullable()
                ->after('unit_price')
                ->comment('Preço de compra/repasse unitário no momento da entrega');
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropColumn('cost_price_used');
        });
    }
};
