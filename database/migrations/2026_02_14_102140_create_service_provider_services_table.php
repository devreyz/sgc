<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tabela pivot: Serviços oferecidos por cada prestador com valores específicos
     */
    public function up(): void
    {
        Schema::create('service_provider_services', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('service_provider_id')->constrained('service_providers')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            
            // Valores que o PRESTADOR recebe (não o cliente final)
            $table->decimal('provider_hourly_rate', 10, 2)->nullable()->comment('Quanto o prestador recebe por hora');
            $table->decimal('provider_daily_rate', 10, 2)->nullable()->comment('Quanto o prestador recebe por dia');
            $table->decimal('provider_unit_rate', 10, 2)->nullable()->comment('Quanto o prestador recebe por unidade (km, kg, etc)');
            
            // Status
            $table->boolean('status')->default(true)->comment('Prestador ainda oferece este serviço?');
            
            // Observações
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->unique(['service_provider_id', 'service_id'], 'provider_service_unique');
            $table->index('status');
        });
        
        // Criar tabela de pagamentos de ordens de serviço
        Schema::create('service_order_payments', function (Blueprint $table) {
            $table->id();
            
            // Relacionamento
            $table->foreignId('service_order_id')->constrained('service_orders')->cascadeOnDelete();
            
            // Dados do pagamento
            $table->date('payment_date')->comment('Data do pagamento');
            $table->decimal('amount', 12, 2)->comment('Valor pago');
            
            // Forma de pagamento
            $table->enum('payment_method', ['dinheiro', 'pix', 'transferencia', 'cheque', 'cartao', 'boleto'])->default('dinheiro');
            
            // Conta bancária (se houver)
            $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            
            // Observações
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            // Índices
            $table->index('service_order_id');
            $table->index('payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_order_payments');
        Schema::dropIfExists('service_provider_services');
    }
};
