<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->boolean('restrict_participants')
                ->default(false)
                ->after('allow_any_product')
                ->comment('Se true, apenas associados selecionados podem registrar entregas');

            $table->decimal('max_total_value_per_associate', 14, 2)
                ->nullable()
                ->after('restrict_participants')
                ->comment('Limite máximo de faturamento (R$) por associado neste projeto');
        });
    }

    public function down(): void
    {
        Schema::table('sales_projects', function (Blueprint $table) {
            $table->dropColumn([
                'restrict_participants',
                'max_total_value_per_associate',
            ]);
        });
    }
};
