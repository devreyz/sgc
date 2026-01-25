<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Itens disponíveis para compra na campanha
     */
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('collective_purchase_id')->constrained('collective_purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            // Produto
            $table->string('product_description')->nullable()->comment('Descrição específica do item');
            $table->string('brand')->nullable();
            $table->string('unit', 10)->default('un');
            
            // Preços
            $table->decimal('unit_price', 10, 2)->comment('Preço unitário negociado');
            $table->decimal('min_quantity', 10, 2)->nullable()->comment('Quantidade mínima por pedido');
            $table->decimal('max_quantity', 10, 2)->nullable()->comment('Quantidade máxima disponível');
            
            // Totais
            $table->decimal('total_ordered', 12, 2)->default(0)->comment('Total pedido pelos associados');
            $table->decimal('total_received', 12, 2)->default(0)->comment('Total recebido');
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('collective_purchase_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
