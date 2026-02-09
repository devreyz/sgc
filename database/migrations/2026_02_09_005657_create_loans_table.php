<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Empréstimos para Associados
     */
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            
            // Associado
            $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
            
            // Valores
            $table->decimal('amount', 12, 2)->comment('Valor total do empréstimo');
            $table->decimal('interest_rate', 5, 2)->default(0)->comment('Taxa de juros');
            $table->decimal('total_with_interest', 12, 2)->comment('Valor total com juros');
            $table->decimal('paid_amount', 12, 2)->default(0)->comment('Valor já pago');
            $table->decimal('balance', 12, 2)->comment('Saldo devedor');
            
            // Parcelamento
            $table->integer('installments')->default(1)->comment('Número de parcelas');
            $table->decimal('installment_value', 12, 2)->comment('Valor da parcela');
            $table->integer('paid_installments')->default(0);
            
            // Datas
            $table->date('loan_date')->comment('Data do empréstimo');
            $table->date('first_payment_date')->comment('Data do primeiro pagamento');
            $table->date('last_payment_date')->nullable()->comment('Data do último pagamento');
            
            // Status
            $table->enum('status', [
                'active',       // Ativo
                'paid',         // Quitado
                'overdue',      // Atrasado
                'cancelled'     // Cancelado
            ])->default('active');
            
            // Finalidade
            $table->string('purpose')->comment('Finalidade do empréstimo');
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('associate_id');
            $table->index('status');
            $table->index('loan_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
