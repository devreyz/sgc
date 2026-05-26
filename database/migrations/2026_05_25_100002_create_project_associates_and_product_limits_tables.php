<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Associados participantes de um projeto (quando restrict_participants = true)
        if (! Schema::hasTable('project_associates')) {
            Schema::create('project_associates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
                $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['sales_project_id', 'associate_id'], 'pa_project_associate_unique');
                $table->index(['tenant_id', 'sales_project_id']);
            });
        }

        // Limites de quantidade por produto por associado dentro de um projeto
        if (! Schema::hasTable('project_associate_product_limits')) {
            Schema::create('project_associate_product_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
                $table->foreignId('associate_id')->constrained('associates')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('max_quantity', 12, 4)->comment('Quantidade máxima permitida para este associado/produto no projeto');
                $table->timestamps();

                $table->unique(
                    ['sales_project_id', 'associate_id', 'product_id'],
                    'papl_project_associate_product_unique'
                );
                $table->index(['tenant_id', 'sales_project_id', 'associate_id'], 'papl_tenant_project_assoc_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_associate_product_limits');
        Schema::dropIfExists('project_associates');
    }
};
