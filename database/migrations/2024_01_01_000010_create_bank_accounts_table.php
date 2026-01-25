<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Contas Bancárias da Cooperativa
     */
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name')->comment('Nome identificador: Caixa, Banco do Brasil, etc.');
            $table->enum('type', ['caixa', 'corrente', 'poupanca', 'investimento', 'aplicacao'])->default('corrente');
            
            // Dados bancários
            $table->string('bank_code', 3)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('agency', 10)->nullable();
            $table->string('agency_digit', 1)->nullable();
            $table->string('account_number', 20)->nullable();
            $table->string('account_digit', 2)->nullable();
            
            // Saldos
            $table->decimal('initial_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->date('balance_date')->nullable()->comment('Data de referência do saldo');
            
            // Configurações
            $table->boolean('is_default')->default(false)->comment('Conta padrão para operações');
            $table->boolean('status')->default(true);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
