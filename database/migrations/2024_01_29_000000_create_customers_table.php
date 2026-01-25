<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Clientes Externos (Prefeituras, Escolas, Mercados)
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name');
            $table->string('trade_name')->nullable()->comment('Nome fantasia');
            $table->string('cnpj', 18)->unique();
            $table->enum('type', ['prefeitura', 'escola', 'mercado', 'restaurante', 'hospital', 'outro'])->default('outro');
            
            // Contato
            $table->string('responsible_name')->nullable()->comment('Nome do responsável');
            $table->string('responsible_role')->nullable()->comment('Cargo do responsável');
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            
            // Endereço
            $table->string('address')->nullable();
            $table->string('address_number', 10)->nullable();
            $table->string('complement')->nullable();
            $table->string('district')->nullable();
            $table->string('city');
            $table->string('state', 2);
            $table->string('zip_code', 9)->nullable();
            
            // Informações adicionais
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('cnpj');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
