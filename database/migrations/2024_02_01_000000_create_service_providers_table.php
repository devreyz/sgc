<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prestadores de Serviço / Trabalhadores Externos
     * Pessoas que prestam serviços para a cooperativa (tratoristas, motoristas, diaristas, etc.)
     */
    public function up(): void
    {
        Schema::create('service_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cpf', 14)->nullable()->unique();
            $table->string('rg', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email', 191)->nullable();
            $table->enum('type', ['tratorista', 'motorista', 'diarista', 'tecnico', 'consultor', 'outro'])->default('outro');
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 10)->nullable();
            
            // Dados bancários para pagamento
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_agency', 10)->nullable();
            $table->string('bank_account', 20)->nullable();
            $table->string('pix_key', 191)->nullable();
            
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('daily_rate', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Tabela de trabalhos realizados pelo prestador
        Schema::create('service_provider_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('associate_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->string('description');
            $table->decimal('hours_worked', 8, 2)->nullable();
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_value', 10, 2)->default(0);
            $table->string('location', 191)->nullable();
            $table->enum('payment_status', ['pendente', 'pago', 'cancelado'])->default('pendente');
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_provider_works');
        Schema::dropIfExists('service_providers');
    }
};
