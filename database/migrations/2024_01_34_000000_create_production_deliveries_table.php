<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Entregas de Produção (Produtor -> Cooperativa para o Projeto)
     */
    public function up(): void
    {
        Schema::create('production_deliveries', function (Blueprint $table) {
            $table->id();
            
            // Relacionamentos
            $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->foreignId('project_demand_id')->constrained('project_demands')->cascadeOnDelete();
            $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            
            // Entrega
            $table->date('delivery_date');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 10, 2)->comment('Preço unitário no momento da entrega');
            $table->decimal('gross_value', 12, 2)->storedAs('quantity * unit_price');
            
            // Valores calculados após aprovação
            $table->decimal('admin_fee_amount', 10, 2)->nullable()->comment('Valor da taxa de administração');
            $table->decimal('net_value', 12, 2)->nullable()->comment('Valor líquido para o associado');
            
            // Status
            $table->enum('status', [
                'pending',      // Pendente de aprovação
                'approved',     // Aprovada (gera crédito)
                'rejected',     // Rejeitada
                'cancelled'     // Cancelada
            ])->default('pending');
            
            // Qualidade
            $table->enum('quality_grade', ['A', 'B', 'C'])->nullable();
            $table->text('quality_notes')->nullable();
            
            // Auditoria
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('sales_project_id');
            $table->index('associate_id');
            $table->index('delivery_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_deliveries');
    }
};
