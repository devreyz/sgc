<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aumenta precisão dos campos financeiros e de quantidade em production_deliveries
 * para 4 casas decimais internamente, evitando erros de arredondamento acumulado.
 *
 * - quantity: 12,3 → 12,4
 * - unit_price: 10,2 → 12,4
 * - cost_price_used: 10,2 → 12,4
 * - admin_fee_amount: 10,2 → 14,4
 * - net_value: 12,2 → 14,4
 * - gross_value (STORED): 12,2 → 14,4  (precisa drop + recreate)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop a stored column antes de alterar as colunas que ela referencia
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropColumn('gross_value');
        });

        // 2. Alterar precisão das colunas
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->decimal('quantity', 12, 4)->change();
            $table->decimal('unit_price', 12, 4)->comment('Preço unitário de venda no momento da entrega')->change();
            $table->decimal('cost_price_used', 12, 4)->nullable()->comment('Preço de compra/repasse unitário no momento da entrega')->change();
            $table->decimal('admin_fee_amount', 14, 4)->nullable()->comment('Valor da taxa de administração')->change();
            $table->decimal('net_value', 14, 4)->nullable()->comment('Valor líquido para o associado')->change();
        });

        // 3. Recriar a stored column com precisão aumentada
        DB::statement('ALTER TABLE `production_deliveries` ADD `gross_value` DECIMAL(14,4) AS (`quantity` * `unit_price`) STORED AFTER `unit_price`');
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropColumn('gross_value');
        });

        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
            $table->decimal('unit_price', 10, 2)->comment('Preço unitário no momento da entrega')->change();
            $table->decimal('cost_price_used', 10, 2)->nullable()->comment('Preço de compra/repasse unitário no momento da entrega')->change();
            $table->decimal('admin_fee_amount', 10, 2)->nullable()->comment('Valor da taxa de administração')->change();
            $table->decimal('net_value', 12, 2)->nullable()->comment('Valor líquido para o associado')->change();
        });

        DB::statement('ALTER TABLE `production_deliveries` ADD `gross_value` DECIMAL(12,2) AS (`quantity` * `unit_price`) STORED AFTER `unit_price`');
    }
};
