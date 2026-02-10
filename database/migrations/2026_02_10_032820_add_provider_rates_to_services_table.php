<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona campos de valores pagos ao prestador de serviço.
     * 
     * Lógica financeira:
     * - base_price: valor cobrado do associado (já existe)
     * - provider_hourly_rate: valor pago ao prestador por hora
     * - provider_daily_rate: valor pago ao prestador por diária
     * - Diferença vai para o caixa da cooperativa
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Valores pagos ao prestador
            $table->decimal('provider_hourly_rate', 10, 2)->nullable()->after('base_price')
                ->comment('Valor pago ao prestador por hora de trabalho');
            
            $table->decimal('provider_daily_rate', 10, 2)->nullable()->after('provider_hourly_rate')
                ->comment('Valor pago ao prestador por diária de trabalho');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['provider_hourly_rate', 'provider_daily_rate']);
        });
    }
};
