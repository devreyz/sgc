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
        Schema::table('production_deliveries', function (Blueprint $table) {
            // Taxa administrativa personalizada para entregas avulsas (standalone)
            $table->decimal('admin_fee_percentage', 5, 2)->nullable()->default(0)
                  ->after('unit_price')
                  ->comment('Taxa admin % para entregas avulsas; nulo = usa taxa do projeto');

            // Indicador se a entrega consome do estoque interno (gera SAÍDA) em vez de receber do produtor (gera ENTRADA)
            $table->boolean('from_stock')->default(false)
                  ->after('admin_fee_percentage')
                  ->comment('Se verdadeiro, aprovação gera SAÍDA do estoque ao invés de ENTRADA');

            // Associate pode ser nulo para entregas "do estoque" (consumo interno)
            $table->foreignId('associate_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropColumn(['admin_fee_percentage', 'from_stock']);
            $table->foreignId('associate_id')->nullable(false)->change();
        });
    }
};
