<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Ordens de Serviço executadas
     */
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            
            // Número da OS
            $table->string('number', 191)->unique();
            
            // Relacionamentos
            $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            
            // Datas
            $table->date('scheduled_date')->nullable();
            $table->date('execution_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            
            // Quantidades
            $table->decimal('quantity', 10, 2)->nullable()->comment('Horas, Km, etc.');
            $table->string('unit', 20)->default('hora');
            
            // Valores
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('final_price', 12, 2);
            
            // Localização
            $table->string('location')->nullable()->comment('Local do serviço');
            $table->decimal('distance_km', 8, 2)->nullable();
            
            // Status
            $table->enum('status', [
                'scheduled',    // Agendada
                'in_progress',  // Em execução
                'completed',    // Concluída
                'cancelled',    // Cancelada
                'billed'        // Faturada (debitada)
            ])->default('scheduled');
            
            // Operador
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Medidores (para máquinas)
            $table->integer('horimeter_start')->nullable();
            $table->integer('horimeter_end')->nullable();
            $table->integer('odometer_start')->nullable();
            $table->integer('odometer_end')->nullable();
            
            // Combustível
            $table->decimal('fuel_used', 8, 2)->nullable();
            
            // Documentação
            $table->text('work_description')->nullable();
            $table->text('notes')->nullable();
            
            // Auditoria
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('associate_id');
            $table->index('service_id');
            $table->index('status');
            $table->index('execution_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
