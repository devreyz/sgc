<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Pedidos individuais dos associados na compra coletiva
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('collective_purchase_id')->constrained('collective_purchases')->cascadeOnDelete();
            $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
            
            // Valores
            $table->decimal('total_value', 12, 2)->default(0);
            
            // Status
            $table->enum('status', [
                'requested',             // Solicitado
                'confirmed',             // Confirmado
                'ordered_from_supplier', // Pedido ao fornecedor
                'in_transit',            // Em trânsito
                'arrived',               // Chegou na cooperativa
                'delivered',             // Entregue ao associado
                'cancelled'              // Cancelado
            ])->default('requested');
            
            // Forma de pagamento
            $table->enum('payment_method', [
                'saldo',        // Débito no saldo
                'dinheiro',     // Pagamento em dinheiro
                'parcelado',    // Parcelado
                'outro'
            ])->default('saldo');
            
            // Datas
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            
            // Documentação
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('collective_purchase_id');
            $table->index('associate_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
