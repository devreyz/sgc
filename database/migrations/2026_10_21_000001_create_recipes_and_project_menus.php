<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->unsignedInteger('version');
            $table->decimal('yield_portions', 20, 8);
            $table->decimal('portion_weight_g', 20, 8);
            $table->string('source_reference');
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code', 'version']);
        });
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('recipe_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_quantity_g', 24, 8);
            $table->timestamps();
            $table->unique(['recipe_id', 'product_id']);
        });
        Schema::create('project_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_group_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->unsignedInteger('version');
            $table->unsignedInteger('revision')->default(1);
            $table->date('date_from');
            $table->date('date_to');
            $table->string('status')->default('planned');
            $table->foreignId('calculation_snapshot_id')->nullable()->constrained('planning_report_snapshots')->restrictOnDelete();
            $table->string('reviewer_name')->nullable();
            $table->string('professional_reference')->nullable();
            $table->string('approval_reference')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['sales_project_id', 'customer_id', 'beneficiary_group_id', 'code', 'version'], 'project_menu_version');
            $table->index(['tenant_id', 'sales_project_id', 'customer_id']);
        });
        Schema::create('menu_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_menu_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->timestamps();
            $table->unique(['project_menu_id', 'date']);
        });
        Schema::create('menu_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_day_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('planned_people');
            $table->timestamps();
            $table->unique(['menu_day_id', 'name']);
        });
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_meal_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('recipe_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity_per_person', 20, 8);
            $table->string('quantity_unit', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['menu_items', 'menu_meals', 'menu_days', 'project_menus', 'recipe_items', 'recipes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
