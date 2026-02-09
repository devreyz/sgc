<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('direct_purchase_items', function (Blueprint $table) {
            $table->id();
            
            // Compra
            $table->foreignId('direct_purchase_id')->constrained('direct_purchases')->cascadeOnDelete();
            
            // Produto
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name')->comment('Nome gravado no momento da compra');
            
            // Quantidade e Valores
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 20);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 12, 2);
            
            // Recebimento
            $table->decimal('received_quantity', 10, 2)->default(0);
            $table->boolean('fully_received')->default(false);
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('direct_purchase_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_purchase_items');
    }
};
