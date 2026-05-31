<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();

            $table->string('name');                         // Ex: Município de Itacarambi, CONAB
            $table->string('short_name')->nullable();       // Ex: Itacarambi, CONAB
            $table->string('cnpj', 18)->nullable();
            $table->enum('type', [
                'municipio', 'estado', 'federal', 'conab', 'hospital', 'cooperativa', 'outro'
            ])->default('outro');

            $table->string('responsible_name')->nullable();
            $table->string('responsible_role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();

            // Endereço
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();

            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->index(['tenant_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
