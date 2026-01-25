<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Movimentações de Estoque
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            
            // Produto
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            // Movimento
            $table->enum('type', ['entrada', 'saida', 'ajuste']);
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_before', 12, 3);
            $table->decimal('stock_after', 12, 3);
            
            // Custo
            $table->decimal('unit_cost', 10, 2)->nullable();
            $table->decimal('total_cost', 12, 2)->nullable();
            
            // Origem do movimento
            $table->enum('reason', [
                'compra',           // Compra de fornecedor
                'producao',         // Entrada de produção
                'venda',            // Saída por venda
                'transferencia',    // Transferência entre locais
                'perda',            // Perda/Vencimento
                'ajuste_inventario',// Ajuste de inventário
                'devolucao',        // Devolução
                'outro'
            ])->default('outro');
            
            // Polimorfismo (Venda, Compra, OS, etc.)
            $table->string('moveable_type', 191)->nullable();
            $table->unsignedBigInteger('moveable_id')->nullable();
            $table->index(['moveable_type', 'moveable_id']);
            
            // Lote e Validade
            $table->string('batch')->nullable()->comment('Lote');
            $table->date('expiry_date')->nullable();
            
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('movement_date');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('product_id');
            $table->index('type');
            $table->index('movement_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
