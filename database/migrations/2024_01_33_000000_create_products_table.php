<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Produtos da Cooperativa (Alimentos, Insumos)
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name');
            $table->string('sku')->nullable()->unique()->comment('Código interno');
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            
            // Classificação
            $table->enum('type', ['producao', 'insumo', 'revenda'])->default('producao');
            $table->string('unit', 10)->default('kg')->comment('Unidade de medida: kg, un, cx, lt, etc.');
            
            // Preços
            $table->decimal('cost_price', 10, 2)->nullable()->comment('Preço de custo');
            $table->decimal('sale_price', 10, 2)->nullable()->comment('Preço de venda');
            
            // Estoque
            $table->decimal('current_stock', 12, 3)->default(0)->comment('Estoque atual');
            $table->decimal('min_stock', 12, 3)->default(0)->comment('Estoque mínimo para alerta');
            $table->decimal('max_stock', 12, 3)->nullable()->comment('Estoque máximo');
            
            // Informações adicionais
            $table->text('description')->nullable();
            $table->string('ncm', 8)->nullable()->comment('Código NCM');
            $table->boolean('perishable')->default(false)->comment('Produto perecível');
            $table->integer('shelf_life_days')->nullable()->comment('Validade em dias');
            
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('type');
            $table->index('sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
