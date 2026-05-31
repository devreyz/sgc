<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            // Rastreia qual tabela de preços foi usada no momento da distribuição
            $table->unsignedBigInteger('price_table_id')->nullable()->after('customer_id')
                ->comment('Tabela de preços usada no momento da distribuição (snapshottada via unit_price)');

            // Fonte do preço para auditoria (customer_project | customer | price_table | product_default)
            $table->string('price_source', 30)->nullable()->after('price_table_id')
                ->comment('Hierarquia de resolução do preço: customer_project, customer, price_table, product_default');

            $table->foreign('price_table_id')->references('id')->on('price_tables')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropForeign(['price_table_id']);
            $table->dropColumn(['price_table_id', 'price_source']);
        });
    }
};

