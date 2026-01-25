<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Contas a Pagar/Pagas (Despesas)
     * Com polimorfismo para vincular a Asset, SalesProject, User, etc.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('description');
            $table->string('document_number')->nullable()->comment('Número do documento/NF');
            
            // Valores
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('interest', 10, 2)->default(0);
            $table->decimal('fine', 10, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->nullable();
            
            // Datas
            $table->date('date')->comment('Data do documento');
            $table->date('due_date')->comment('Data de vencimento');
            $table->date('paid_date')->nullable()->comment('Data do pagamento');
            
            // Classificação
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            
            // Polimorfismo: Vincula despesa a Asset, SalesProject, User, etc.
            $table->nullableMorphs('expenseable');
            
            // Status
            $table->enum('status', ['pending', 'paid', 'cancelled', 'overdue'])->default('pending');
            $table->enum('payment_method', ['dinheiro', 'pix', 'transferencia', 'boleto', 'cartao', 'cheque', 'outro'])->nullable();
            
            // Recorrência
            $table->boolean('is_recurring')->default(false);
            $table->integer('installment_number')->nullable()->comment('Número da parcela');
            $table->integer('total_installments')->nullable()->comment('Total de parcelas');
            $table->foreignId('parent_expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            
            // Documentação
            $table->string('document_path')->nullable()->comment('Comprovante no Google Drive');
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
