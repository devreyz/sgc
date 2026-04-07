<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('associate_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
            $table->unsignedSmallInteger('receipt_year');
            $table->unsignedInteger('receipt_number');
            $table->date('issued_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Número único por tenant + ano
            $table->unique(['tenant_id', 'receipt_year', 'receipt_number'], 'unique_receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('associate_receipts');
    }
};
