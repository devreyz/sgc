<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Compras Diretas da Cooperativa (não coletivas)
     */
    public function up(): void
    {
        Schema::create('direct_purchases', function (Blueprint $table) {
            $table->id();
            
            // Fornecedor
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            
            // Valores
            $table->decimal('total_value', 12, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('final_value', 12, 2);
            
            // Status
            $table->enum('status', [
                'draft',            // Rascunho
                'requested',        // Solicitada
                'approved',         // Aprovada
                'ordered',          // Pedido feito ao fornecedor
                'partial_received', // Parcialmente recebida
                'received',         // Recebida
                'cancelled'         // Cancelada
            ])->default('draft');
            
            // Pagamento
            $table->enum('payment_status', [
                'pending',      // Pendente
                'partial',      // Parcial
                'paid'          // Pago
            ])->default('pending');
            
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->enum('payment_method', [
                'dinheiro',
                'pix',
                'transferencia',
                'boleto',
                'cartao_credito',
                'cartao_debito',
                'cheque',
                'parcelado',
                'outro'
            ])->nullable();
            
            // Datas
            $table->date('purchase_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Notas Fiscais
            $table->string('invoice_number')->nullable();
            $table->string('invoice_path')->nullable();
            
            // Documentação
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('supplier_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_purchases');
    }
};
