<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIfMissing('beneficiary_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'sales_project_id']);
        });
        $this->createIfMissing('beneficiary_population_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('beneficiary_group_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('population');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['beneficiary_group_id', 'effective_from'], 'bpp_group_from_idx');
        });
        // MySQL preserva o DDL já executado quando uma migration falha. Na
        // retomada, completa o índice da tabela criada antes do erro 1059.
        if (! Schema::hasIndex('beneficiary_population_periods', ['beneficiary_group_id', 'effective_from'])) {
            Schema::table('beneficiary_population_periods', fn (Blueprint $table) =>
                $table->index(['beneficiary_group_id', 'effective_from'], 'bpp_group_from_idx'));
        }
        $this->createIfMissing('project_planning_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('default_coverage_days')->nullable();
            $table->timestamps();
        });
        $this->createIfMissing('product_planning_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('default_coverage_days')->nullable();
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->decimal('mass_conversion_factor', 20, 8)->nullable();
            $table->string('storage_condition')->nullable();
            $table->string('source');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sales_project_id', 'product_id'], 'product_planning_context');
        });
        $this->createIfMissing('planning_rule_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('source_type');
            $table->string('source_reference');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['sales_project_id', 'version']);
        });
        $this->createIfMissing('planning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('planning_rule_set_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('metric');
            $table->string('operator', 8);
            $table->decimal('value', 24, 8)->nullable();
            $table->decimal('value_max', 24, 8)->nullable();
            $table->string('evaluation_scope');
            $table->string('severity');
            $table->string('blocking_policy')->default('warning_only');
            $table->timestamps();
        });
        $this->createIfMissing('distribution_consumption_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_id')->unique()->constrained('production_deliveries')->restrictOnDelete();
            $table->foreignId('beneficiary_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('count_method');
            $table->unsignedInteger('served_people')->nullable();
            $table->unsignedInteger('service_events')->nullable();
            $table->decimal('person_days_direct', 20, 8)->nullable();
            $table->date('coverage_start_date')->nullable();
            $table->date('coverage_end_date')->nullable();
            $table->unsignedInteger('shelf_life_days_snapshot')->nullable();
            $table->date('expires_at')->nullable();
            $table->string('expiry_source')->nullable();
            $table->decimal('mass_conversion_factor_snapshot', 20, 8)->nullable();
            $table->string('coverage_source')->nullable();
            $table->boolean('calculation_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'sales_project_id', 'coverage_start_date'], 'consumption_window_start');
            $table->index('coverage_end_date');
        });
        $this->createIfMissing('distribution_daily_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('distribution_consumption_profile_id');
            $table->foreign('distribution_consumption_profile_id', 'daily_audience_profile_fk')->references('id')->on('distribution_consumption_profiles')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('served_people');
            $table->timestamps();
            $table->unique(['distribution_consumption_profile_id', 'date'], 'consumption_daily_date');
        });
        $this->createIfMissing('planning_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_project_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedInteger('calculation_version');
            $table->json('parameters');
            $table->json('result');
            $table->timestamps();
        });
    }

    private function createIfMissing(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    public function down(): void
    {
        foreach (['planning_report_snapshots', 'distribution_daily_audiences', 'distribution_consumption_profiles', 'planning_rules', 'planning_rule_sets', 'product_planning_profiles', 'project_planning_settings', 'beneficiary_population_periods', 'beneficiary_groups'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
