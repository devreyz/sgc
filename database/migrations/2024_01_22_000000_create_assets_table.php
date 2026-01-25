<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Bens da Cooperativa (Tratores, Caminhões, Prédios, Equipamentos)
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name');
            $table->string('identifier')->nullable()->comment('Placa, número de série, patrimônio');
            $table->enum('type', ['trator', 'caminhao', 'veiculo', 'implemento', 'equipamento', 'imovel', 'outro'])->default('outro');
            $table->string('brand')->nullable()->comment('Marca');
            $table->string('model')->nullable()->comment('Modelo');
            $table->year('year_manufacture')->nullable()->comment('Ano de fabricação');
            $table->year('year_model')->nullable()->comment('Ano do modelo');
            
            // Aquisição
            $table->date('acquisition_date')->nullable();
            $table->decimal('acquisition_value', 12, 2)->nullable();
            $table->decimal('current_value', 12, 2)->nullable()->comment('Valor atual estimado');
            $table->string('invoice_number')->nullable()->comment('Número da NF de compra');
            
            // Status e localização
            $table->enum('status', ['disponivel', 'em_uso', 'manutencao', 'inativo', 'vendido'])->default('disponivel');
            $table->string('location')->nullable()->comment('Localização atual');
            
            // Para veículos
            $table->string('renavam', 11)->nullable();
            $table->string('chassis', 17)->nullable();
            $table->date('licensing_expiry')->nullable()->comment('Vencimento do licenciamento');
            
            // Manutenção
            $table->integer('horimeter')->nullable()->comment('Horímetro atual');
            $table->integer('odometer')->nullable()->comment('Hodômetro atual');
            $table->date('last_maintenance')->nullable();
            $table->date('next_maintenance')->nullable();
            
            // Documentação
            $table->string('document_path')->nullable()->comment('Caminho dos documentos no Google Drive');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('identifier');
            $table->index('type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
