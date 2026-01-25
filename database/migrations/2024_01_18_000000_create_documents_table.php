<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Documentos gerais (upload para Google Drive)
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name');
            $table->string('original_name')->comment('Nome original do arquivo');
            $table->string('path')->comment('Caminho no Google Drive');
            $table->string('disk')->default('google');
            
            // Metadados
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable()->comment('Tamanho em bytes');
            $table->string('extension', 10)->nullable();
            
            // Categoria
            $table->enum('category', [
                'contrato',
                'nota_fiscal',
                'comprovante',
                'dap_caf',
                'documento_pessoal',
                'licenca',
                'relatorio',
                'foto',
                'outro'
            ])->default('outro');
            
            // Polimorfismo
            $table->morphs('documentable');
            
            // Datas
            $table->date('document_date')->nullable()->comment('Data do documento');
            $table->date('expiry_date')->nullable()->comment('Data de validade');
            
            // Auditoria
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
