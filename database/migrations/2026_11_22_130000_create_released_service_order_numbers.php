<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('released_service_order_numbers', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('number');
            $table->primary(['tenant_id', 'year', 'number'], 'released_service_order_number_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('released_service_order_numbers');
    }
};
