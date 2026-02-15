<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Projetos de Vendas Institucionais (PNAE, PAA, Contratos)
     */
    public function up(): void
    {
        Schema::create('sales_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            
            // Identificação
            $table->string('title');
            $table->string('code', 191)->nullable()->comment('Código do projeto/contrato');
            $table->enum('type', ['pnae', 'paa', 'contrato', 'licitacao', 'outro'])->default('pnae');
            
            // Cliente (Prefeitura, Escola, etc.)
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            
            // Período
            $table->date('start_date');
            $table->date('end_date');
            $table->year('reference_year')->comment('Ano de referência');
            
            // Valores
            $table->decimal('total_value', 14, 2)->default(0)->comment('Valor total do contrato');
            $table->decimal('admin_fee_percentage', 5, 2)->default(10.00)->comment('Taxa de administração da coop (%)');
            
            // Status
            $table->enum('status', [
                'draft',        // Rascunho
                'active',       // Em execução
                'suspended',    // Suspenso
                'completed',    // Concluído
                'cancelled'     // Cancelado
            ])->default('draft');
            
            // Documentação
            $table->string('contract_number')->nullable();
            $table->string('process_number')->nullable()->comment('Número do processo licitatório');
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['tenant_id', 'id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'reference_year']);
            $table->unique(['tenant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_projects');
    }
};
