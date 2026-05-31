<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_fees', function (Blueprint $table) {
            // 'discount' = reduz o valor líquido | 'accrual' = aumenta o valor líquido
            $table->string('nature', 20)->default('discount')->after('type');
            // Ordem de aplicação das taxas (menor = primeiro)
            $table->unsignedSmallInteger('sort_order')->default(0)->after('nature');
        });

        // Migrar taxa administrativa existente: taxa padrão do projeto → ProjectFee
        // Os projetos que já usam admin_fee_percentage mas não têm ProjectFees
        // são mantidos como estão — o ProjectFinancialCalculator faz fallback.
    }

    public function down(): void
    {
        Schema::table('project_fees', function (Blueprint $table) {
            $table->dropColumn(['nature', 'sort_order']);
        });
    }
};
