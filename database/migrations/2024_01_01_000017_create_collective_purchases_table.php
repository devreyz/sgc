<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Campanhas de Compras Coletivas
     * Ex: "Compra de Adubo Jan/26"
     */
    public function up(): void
    {
        Schema::create('collective_purchases', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('title');
            $table->string('code', 191)->nullable()->unique();
            $table->text('description')->nullable();
            
            // Período
            $table->date('order_start_date')->comment('Início do período de pedidos');
            $table->date('order_end_date')->comment('Fim do período de pedidos');
            $table->date('expected_delivery_date')->nullable()->comment('Previsão de entrega');
            $table->date('actual_delivery_date')->nullable();
            
            // Fornecedor
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            
            // Valores
            $table->decimal('total_value', 14, 2)->default(0)->comment('Valor total da compra');
            $table->decimal('discount_percentage', 5, 2)->default(0)->comment('Desconto negociado');
            
            // Status
            $table->enum('status', [
                'open',             // Aberta para pedidos
                'closed',           // Fechada para pedidos
                'ordered',          // Pedido feito ao fornecedor
                'in_transit',       // Em trânsito
                'received',         // Recebida na cooperativa
                'distributing',     // Em distribuição
                'completed',        // Concluída
                'cancelled'         // Cancelada
            ])->default('open');
            
            // Documentação
            $table->string('invoice_number')->nullable();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index('order_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collective_purchases');
    }
};
