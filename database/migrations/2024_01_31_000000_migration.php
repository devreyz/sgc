<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Melhora o fluxo de trabalho dos projetos de venda:
     * - Campos de retenção renomeados para admin_fee (mais claro)
     * - Remove campos computed redundantes (gross_value já é calculado)
     * - Adiciona índices para melhor performance
     */
    public function up(): void
    {
        // Ajustar production_deliveries
        Schema::table('production_deliveries', function (Blueprint $table) {
            // Remover colunas computed desnecessárias se existirem
            // MySQL não permite alterar directly, então vamos dropar e recriar
            $table->dropColumn('gross_value');
        });
        
        Schema::table('production_deliveries', function (Blueprint $table) {
            // Recriar gross_value como stored computed column
            $table->decimal('gross_value', 12, 2)
                ->storedAs('quantity * unit_price')
                ->after('unit_price');
        });
        
        // Adicionar índices úteis
        Schema::table('project_demands', function (Blueprint $table) {
            $table->index('product_id');
        });
        
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->index('product_id');
            $table->index('project_demand_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropColumn('gross_value');
        });
        
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->decimal('gross_value', 12, 2)
                ->storedAs('quantity * unit_price')
                ->after('unit_price');
        });
        
        Schema::table('project_demands', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
        });
        
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropIndex(['product_id']);
            $table->dropIndex(['project_demand_id']);
        });
    }
};
