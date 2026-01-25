<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Receitas da Cooperativa
     */
    public function up(): void
    {
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('description');
            $table->string('document_number')->nullable();
            
            // Valores
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('received_amount', 12, 2)->nullable();
            
            // Datas
            $table->date('date')->comment('Data do documento');
            $table->date('due_date')->nullable()->comment('Data de vencimento');
            $table->date('received_date')->nullable();
            
            // Classificação
            $table->foreignId('chart_account_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            
            // Polimorfismo
            $table->string('revenueable_type', 191)->nullable();
            $table->unsignedBigInteger('revenueable_id')->nullable();
            $table->index(['revenueable_type', 'revenueable_id']);
            
            // Status
            $table->enum('status', ['pending', 'received', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['dinheiro', 'pix', 'transferencia', 'boleto', 'cartao', 'cheque', 'outro'])->nullable();
            
            // Documentação
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
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
        Schema::dropIfExists('revenues');
    }
};
