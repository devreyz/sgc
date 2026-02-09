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
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            
            // Empréstimo
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            
            // Parcela
            $table->integer('installment_number')->comment('Número da parcela');
            
            // Valores
            $table->decimal('amount', 12, 2)->comment('Valor da parcela');
            $table->decimal('interest', 10, 2)->default(0)->comment('Juros');
            $table->decimal('fine', 10, 2)->default(0)->comment('Multa por atraso');
            $table->decimal('total_paid', 12, 2)->comment('Total pago');
            
            // Datas
            $table->date('due_date')->comment('Data de vencimento');
            $table->date('payment_date')->nullable()->comment('Data do pagamento');
            
            // Status
            $table->enum('status', [
                'pending',      // Pendente
                'paid',         // Pago
                'overdue',      // Atrasado
                'cancelled'     // Cancelado
            ])->default('pending');
            
            // Forma de pagamento
            $table->enum('payment_method', [
                'desconto_saldo',  // Desconto do saldo do associado
                'dinheiro',
                'pix',
                'transferencia',
                'outro'
            ])->nullable();
            
            // Referência ao ledger entry
            $table->foreignId('ledger_entry_id')->nullable()->constrained('associate_ledgers')->nullOnDelete();
            
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            $table->index('loan_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_payments');
    }
};
