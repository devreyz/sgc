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
        // Lista de tabelas que podem estar sem tenant_id
        $tables = [
            'service_order_payments',
            'loan_payments',
            'direct_purchase_items',
            'purchase_order_items',
            'purchase_items',
            'service_provider_services',
            'service_provider_works',
            'equipment_readings',
            'maintenance_schedules',
            'maintenance_records',
            'maintenance_types',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
                    
                    try {
                        $table->foreign('tenant_id')
                            ->references('id')
                            ->on('tenants')
                            ->onDelete('set null');
                    } catch (\Throwable $e) {
                        // Foreign key já existe ou erro na criação
                        \Log::warning("Não foi possível criar foreign key tenant_id para {$table}: " . $e->getMessage());
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'service_order_payments',
            'loan_payments',
            'direct_purchase_items',
            'purchase_order_items',
            'purchase_items',
            'service_provider_services',
            'service_provider_works',
            'equipment_readings',
            'maintenance_schedules',
            'maintenance_records',
            'maintenance_types',
        ];

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    try {
                        $table->dropForeign(['tenant_id']);
                    } catch (\Throwable $e) {
                        // Ignora se não existir
                    }

                    try {
                        $table->dropColumn('tenant_id');
                    } catch (\Throwable $e) {
                        // Ignora se não existir
                    }
                });
            }
        }
    }
};
