<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remover tabela legada de preços por cliente
        Schema::dropIfExists('customer_product_prices');

        // Remover colunas de preço do produto
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'cost_price']);
        });
    }

    public function down(): void
    {
        // Recriar colunas de preço no produto
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 4)->nullable()->after('unit');
            $table->decimal('cost_price', 10, 4)->nullable()->after('sale_price');
        });

        // Recriar tabela legada
        Schema::create('customer_product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('project_id')->nullable()->constrained('sales_projects')->nullOnDelete();
            $table->decimal('sale_price', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
