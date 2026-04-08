<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atualiza associate_receipts para:
 * 1. Permitir sales_project_id nulo (comprovante de entregas avulsas)
 * 2. Adicionar from_date / to_date para filtrar entregas avulsas por período
 * 3. Adicionar acknowledged_at para controle de consentimento / segunda via
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            // Torna opcional o vínculo com projeto
            $table->foreignId('sales_project_id')->nullable()->change();

            // Período para filtrar entregas avulsas (opcional)
            $table->date('from_date')->nullable()->after('sales_project_id');
            $table->date('to_date')->nullable()->after('from_date');

            // Confirmação de consentimento (assinatura)
            $table->timestamp('acknowledged_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('associate_receipts', function (Blueprint $table) {
            $table->dropColumn(['from_date', 'to_date', 'acknowledged_at']);
            $table->foreignId('sales_project_id')->nullable(false)->change();
        });
    }
};
