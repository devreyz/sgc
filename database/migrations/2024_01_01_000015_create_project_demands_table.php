<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Metas/Demandas do Projeto (Produtos e quantidades esperadas)
     */
    public function up(): void
    {
        Schema::create('project_demands', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            // Metas
            $table->decimal('target_quantity', 12, 3)->comment('Quantidade meta total');
            $table->decimal('delivered_quantity', 12, 3)->default(0)->comment('Quantidade já entregue');
            
            // Preço
            $table->decimal('unit_price', 10, 2)->comment('Preço unitário pago pelo órgão');
            $table->decimal('total_value', 14, 2)->storedAs('target_quantity * unit_price');
            
            // Período de entrega
            $table->date('delivery_start')->nullable();
            $table->date('delivery_end')->nullable();
            
            // Frequência
            $table->enum('frequency', ['unica', 'semanal', 'quinzenal', 'mensal'])->default('mensal');
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['sales_project_id', 'product_id']);
            $table->index('sales_project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_demands');
    }
};
