<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_offline_operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('provider_id');
            $table->unsignedBigInteger('user_id');
            $table->uuid('operation_key');
            $table->json('result');
            $table->timestamp('created_at');
            $table->unique(['tenant_id', 'provider_id', 'user_id', 'operation_key'], 'service_offline_operation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_offline_operations');
    }
};
