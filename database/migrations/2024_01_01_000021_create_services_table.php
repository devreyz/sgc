<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Catálogo de Serviços (Hora Máquina, Frete, Consultoria)
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name');
            $table->string('code', 191)->nullable()->unique();
            $table->text('description')->nullable();
            
            // Tipo e Unidade
            $table->enum('type', [
                'hora_maquina',     // Hora de trator/máquina
                'frete',            // Transporte
                'consultoria',      // Consultoria técnica
                'beneficiamento',   // Beneficiamento de produtos
                'outro'
            ])->default('outro');
            
            $table->string('unit', 20)->default('hora')->comment('hora, km, kg, un');
            
            // Preços
            $table->decimal('base_price', 10, 2)->comment('Preço base por unidade');
            $table->decimal('min_charge', 10, 2)->nullable()->comment('Valor mínimo cobrado');
            
            // Asset vinculado (opcional)
            $table->foreignId('default_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            
            // Status
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
        Schema::dropIfExists('services');
    }
};
