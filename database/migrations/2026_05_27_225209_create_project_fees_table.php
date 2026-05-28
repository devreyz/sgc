<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('sales_project_id')->index();
            $table->string('name');              // "Taxa administrativa", "Frete", "Embalagem", etc.
            $table->string('type', 20)          // 'percentage' ou 'fixed'
                  ->default('percentage');
            $table->decimal('value', 10, 4);    // 5.0000 (%) ou 150.0000 (R$)
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('sales_project_id')->references('id')->on('sales_projects');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_fees');
    }
};

