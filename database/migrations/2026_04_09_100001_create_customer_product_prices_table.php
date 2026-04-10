<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela de preços específicos por cliente/produto.
 * Funciona como sobrescrita opcional do valor padrão do produto.
 *
 * Prioridade de resolução:
 * 1. Preço do produto para aquele cliente dentro do projeto
 * 2. Preço do produto para aquele cliente (sem projeto)
 * 3. Valor padrão do produto (sale_price / cost_price)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('sales_projects')->nullOnDelete()
                  ->comment('Se preenchido, preço vale apenas para este projeto');

            $table->decimal('sale_price', 10, 2)->comment('Preço de venda para este cliente');
            $table->decimal('cost_price', 10, 2)->nullable()
                  ->comment('Preço de compra/repasse ao associado (opcional, senão calcula via taxa)');

            $table->date('start_date')->nullable()->comment('Início da vigência (null = sempre válido)');
            $table->date('end_date')->nullable()->comment('Fim da vigência (null = sem expiração)');

            $table->timestamps();
            $table->softDeletes();

            // Índices para busca rápida
            $table->index(['tenant_id', 'customer_id', 'product_id'], 'cpp_tenant_customer_product');
            $table->index(['customer_id', 'product_id', 'project_id'], 'cpp_customer_product_project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_prices');
    }
};
