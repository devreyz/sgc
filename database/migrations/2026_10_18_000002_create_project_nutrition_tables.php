<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('distribution_consumption_profiles', function (Blueprint $table) {
            // No backfill: the destination at the time an old audience was entered is unknown.
            $table->unsignedBigInteger('customer_id_snapshot')->nullable();
            $table->unsignedBigInteger('product_id_snapshot')->nullable();
        });
        Schema::create('nutrients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->string('unit', 20);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });
        Schema::create('food_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->decimal('reference_quantity_g', 20, 8);
            $table->decimal('edible_fraction', 12, 8);
            $table->string('source');
            $table->string('source_version');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'version']);
            $table->index(['tenant_id', 'product_id', 'effective_from']);
        });
        Schema::create('food_nutrient_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('food_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('nutrient_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 24, 8);
            $table->timestamps();
            $table->unique(['food_profile_id', 'nutrient_id']);
        });
        Schema::create('nutrition_target_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_group_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('source_type');
            $table->string('source_reference');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['sales_project_id', 'beneficiary_group_id', 'customer_id', 'version'], 'nutrition_target_version');
        });
        Schema::create('nutrition_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('nutrition_target_set_id')->constrained()->restrictOnDelete();
            $table->foreignId('nutrient_id')->constrained()->restrictOnDelete();
            $table->decimal('min_value', 24, 8)->nullable();
            $table->decimal('max_value', 24, 8)->nullable();
            $table->string('severity')->default('warning');
            $table->timestamps();
            $table->unique(['nutrition_target_set_id', 'nutrient_id']);
        });
    }

    public function down(): void
    {
        foreach (['nutrition_targets', 'nutrition_target_sets', 'food_nutrient_values', 'food_profiles', 'nutrients'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('distribution_consumption_profiles', function (Blueprint $table) {
            $table->dropColumn(['customer_id_snapshot', 'product_id_snapshot']);
        });
    }
};
