<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Perfil do Associado (Produtor Rural)
     * Relacionamento 1:1 com User
     */
    public function up(): void
    {
        Schema::create('associates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // Documentos
            $table->string('cpf_cnpj', 18)->unique();
            $table->string('rg', 20)->nullable();
            $table->string('dap_caf', 50)->nullable()->comment('DAP ou CAF do produtor rural');
            $table->date('dap_caf_expiry')->nullable()->comment('Validade do DAP/CAF');
            
            // Endereço Rural
            $table->string('property_name')->nullable()->comment('Nome da propriedade');
            $table->string('address')->nullable();
            $table->string('district')->nullable()->comment('Bairro/Comunidade');
            $table->string('city');
            $table->string('state', 2);
            $table->string('zip_code', 9)->nullable();
            $table->decimal('property_area', 10, 2)->nullable()->comment('Área em hectares');
            
            // Contato
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            
            // Dados Bancários
            $table->string('bank_name')->nullable();
            $table->string('bank_agency', 10)->nullable();
            $table->string('bank_account', 20)->nullable();
            $table->enum('bank_account_type', ['corrente', 'poupanca'])->nullable();
            $table->string('pix_key')->nullable();
            $table->enum('pix_key_type', ['cpf', 'cnpj', 'email', 'phone', 'random'])->nullable();
            
            // Informações adicionais
            $table->date('admission_date')->nullable()->comment('Data de admissão na cooperativa');
            $table->string('registration_number')->nullable()->comment('Número de matrícula na coop');
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('cpf_cnpj');
            $table->index('dap_caf');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('associates');
    }
};
