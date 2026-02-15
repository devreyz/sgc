<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Configurações do sistema usando Spatie Settings
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            
            $table->string('group', 100);
            $table->string('name', 100);
            $table->boolean('locked')->default(false);
            $table->json('payload');
            
            $table->timestamps();
            
            $table->unique(['tenant_id', 'group', 'name']);
            $table->index(['tenant_id', 'group']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
