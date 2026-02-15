<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Conta Corrente para Prestadores de Serviço
     */
    public function up(): void
    {
        Schema::create('service_provider_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            // Prestador
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();
            
            // Tipo e Valor
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 14, 2)->comment('Saldo após a transação');
            
            // Descrição
            $table->string('description');
            $table->text('notes')->nullable();
            
            // Referência Polimórfica
            $table->string('reference_type', 191)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->index(['reference_type', 'reference_id'], 'service_provider_ledgers_ref_idx');
            
            // Categorização
            $table->enum('category', [
                'servico_prestado',  // Crédito por serviço prestado
                'pagamento_recebido', // Débito ao receber pagamento
                'ajuste',
                'outro'
            ])->default('servico_prestado');
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transaction_date');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'id'], 'service_provider_ledgers_tenant_id_idx');
            $table->index(['tenant_id', 'service_provider_id'], 'service_provider_ledgers_tenant_sp_idx');
            $table->index(['tenant_id', 'type'], 'service_provider_ledgers_tenant_type_idx');
            $table->index(['tenant_id', 'transaction_date'], 'service_provider_ledgers_tenant_txn_date_idx');
            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'service_provider_ledgers_tenant_ref_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_provider_ledgers');
    }
};
