<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Taxas de faturamento ao CLIENTE por projeto ──────────────────────
        // Independente de project_fees (que são taxas do associado).
        // Se vazia, CustomerBillingReceiptService cai de volta para project_fees.
        Schema::create('customer_project_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('sales_project_id')->index();

            $table->string('name');                          // "Taxa de Intermediação", "Frete Cliente", etc.
            $table->string('type', 20)->default('percentage'); // 'percentage' | 'fixed'
            $table->string('nature', 20)->default('discount'); // 'discount' | 'accrual'
            $table->decimal('value', 10, 4);                 // 5.0000 (%) ou 150.0000 (R$)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sales_project_id')->references('id')->on('sales_projects')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_project_fees');
    }
};
