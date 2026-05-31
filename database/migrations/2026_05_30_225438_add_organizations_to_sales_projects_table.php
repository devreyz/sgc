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
        // Pivot: projeto pode envolver múltiplas organizações
        Schema::create('sales_project_organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_project_id')->constrained('sales_projects')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['sales_project_id', 'organization_id'], 'spo_project_org_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_project_organizations');
    }
};
