<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona parent_delivery_id em production_deliveries para suportar o
 * fluxo de 3 camadas: Recepção → Distribuição → Venda.
 *
 * - Um registro SEM parent_delivery_id e SEM customer_id = RECEPÇÃO
 *   (o associado entregou o produto à cooperativa; cliente ainda não definido)
 *
 * - Um registro COM parent_delivery_id = DISTRIBUIÇÃO
 *   (fração da recepção alocada a um cliente específico)
 *
 * NOTA: a coluna pode já existir no banco se foi adicionada manualmente.
 * A migration verifica isso antes de tentar adicionar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('production_deliveries', 'parent_delivery_id')) {
            Schema::table('production_deliveries', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_delivery_id')
                    ->nullable()
                    ->after('id')
                    ->comment('ID do registro de recepção pai; nulo = este registro é a recepção original');

                $table->foreign('parent_delivery_id')
                    ->references('id')
                    ->on('production_deliveries')
                    ->nullOnDelete();

                $table->index('parent_delivery_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('production_deliveries', 'parent_delivery_id')) {
            Schema::table('production_deliveries', function (Blueprint $table) {
                $table->dropForeign(['parent_delivery_id']);
                $table->dropIndex(['parent_delivery_id']);
                $table->dropColumn('parent_delivery_id');
            });
        }
    }
};
