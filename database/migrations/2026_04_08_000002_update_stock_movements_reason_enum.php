<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        // Atualiza enum `reason` para conter todos os valores do enum PHP
        DB::statement(<<<SQL
ALTER TABLE `stock_movements` MODIFY `reason` ENUM(
    'compra', 'producao', 'devolucao', 'recebimento', 'inventario_mais',
    'venda', 'entrega', 'entrega_cliente', 'uso_interno', 'perda', 'quebra', 'vencimento', 'inventario_menos',
    'ajuste_inventario', 'correcao', 'transferencia', 'outro'
) NOT NULL DEFAULT 'outro';
SQL
        );
    }

    public function down(): void
    {
        // Reverte para o conjunto original (antes da alteração)
        DB::statement(<<<SQL
ALTER TABLE `stock_movements` MODIFY `reason` ENUM(
    'compra', 'producao', 'venda', 'transferencia', 'perda', 'ajuste_inventario', 'devolucao', 'outro'
) NOT NULL DEFAULT 'outro';
SQL
        );
    }
};
