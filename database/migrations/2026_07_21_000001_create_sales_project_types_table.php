<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_project_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 80);
            $table->string('color', 30)->default('gray');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales_projects MODIFY type VARCHAR(80) NOT NULL DEFAULT 'pnae'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE sales_projects SET type = 'outro' WHERE type NOT IN ('pnae', 'paa', 'contrato', 'licitacao', 'outro')");
            DB::statement("ALTER TABLE sales_projects MODIFY type ENUM('pnae', 'paa', 'contrato', 'licitacao', 'outro') NOT NULL DEFAULT 'pnae'");
        }

        Schema::dropIfExists('sales_project_types');
    }
};
