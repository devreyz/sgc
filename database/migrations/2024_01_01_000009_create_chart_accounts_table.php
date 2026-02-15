<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Plano de Contas (Chart of Accounts)
     * Ex: Manutenção, Combustível, Adiantamentos, Impostos
     */
    public function up(): void
    {
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            // Estrutura hierárquica
            $table->string('code', 20)->comment('Código contábil: 1.1.01');
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->nullOnDelete();
            
            // Classificação
            $table->enum('type', ['receita', 'despesa', 'ativo', 'passivo', 'patrimonio'])->default('despesa');
            $table->enum('nature', ['debit', 'credit'])->default('debit');
            
            // Configurações
            $table->boolean('allows_entries')->default(true)->comment('Permite lançamentos diretos');
            $table->boolean('status')->default(true);
            $table->text('description')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'id']);
            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'type']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_accounts');
    }
};
