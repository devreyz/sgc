<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suporte a múltiplos clientes por projeto de venda.
 *
 * - Cria tabela pivot sales_project_customers
 * - Adiciona customer_id opcional em production_deliveries
 * - Adiciona customer_id opcional em project_demands
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabela pivot: um projeto pode ter múltiplos clientes
        Schema::create('sales_project_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('notes')->nullable()->comment('Observações específicas deste cliente no projeto');
            $table->timestamps();

            $table->unique(['sales_project_id', 'customer_id'], 'spc_project_customer_unique');
            $table->index('customer_id');
        });

        // Entrega pode ser destinada a um cliente específico do projeto
        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('sales_project_id')
                ->constrained('customers')
                ->nullOnDelete()
                ->comment('Cliente destino desta entrega (opcional, quando projeto tem múltiplos clientes)');
        });

        // Demanda pode ser específica de um cliente do projeto
        Schema::table('project_demands', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('product_id')
                ->constrained('customers')
                ->nullOnDelete()
                ->comment('Cliente desta demanda (opcional, quando projeto tem múltiplos clientes)');
        });
    }

    public function down(): void
    {
        Schema::table('project_demands', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('production_deliveries', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::dropIfExists('sales_project_customers');
    }
};
