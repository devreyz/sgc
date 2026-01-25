<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Conta Corrente Interna do Associado (Ledger)
     * Tabela CRÍTICA para o fluxo financeiro do sócio
     */
    public function up(): void
    {
        Schema::create('associate_ledgers', function (Blueprint $table) {
            $table->id();
            
            // Associado
            $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
            
            // Tipo e Valor
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 14, 2)->comment('Saldo após a transação');
            
            // Descrição
            $table->string('description');
            $table->text('notes')->nullable();
            
            // Referência Polimórfica (Venda, CompraColetiva, Servico, etc.)
            $table->string('reference_type', 191)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id']);
            
            // Categorização
            $table->enum('category', [
                'producao',      // Crédito por entrega de produção
                'taxa_admin',    // Débito de taxa administrativa
                'compra_insumo', // Débito por compra de insumo
                'servico',       // Débito por serviço (trator, frete)
                'adiantamento',  // Crédito por adiantamento
                'devolucao',     // Crédito por devolução
                'ajuste',        // Ajuste manual
                'transferencia', // Transferência para conta bancária
                'outro'
            ])->default('outro');
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transaction_date');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('associate_id');
            $table->index('type');
            $table->index('category');
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('associate_ledgers');
    }
};
