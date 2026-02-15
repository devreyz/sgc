<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adiciona tenant_id a todas as tabelas de negócio para suportar multi-tenancy
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        
        // Tabelas principais de negócio
        $businessTables = [
            'associates',
            'associate_ledgers',
            'assets',
            'bank_accounts',
            'cash_movements',
            'chart_accounts',
            'collective_purchases',
            'customers',
            'direct_purchases',
            'direct_purchase_items',
            'documents',
            'document_templates',
            'equipment',
            'expenses',
            'loans',
            'loan_payments',
            'products',
            'product_categories',
            'production_deliveries',
            'project_demands',
            'project_payments',
            'provider_payment_requests',
            'purchase_items',
            'purchase_orders',
            'purchase_order_items',
            'revenues',
            'sales_projects',
            'services',
            'service_orders',
            'service_order_payments',
            'service_providers',
            'service_provider_ledgers',
            'service_provider_services',
            'stock_movements',
            'suppliers',
        ];

        // Adicionar tenant_id às tabelas de negócio
        foreach ($businessTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->foreignId('tenant_id')
                        ->after('id')
                        ->nullable()
                        ->constrained('tenants')
                        ->onDelete('cascade');
                    
                    // Adicionar índice composto para performance
                    $blueprint->index(['tenant_id', 'id']);
                });
            }
        }

        // Adicionar tenant_id às tabelas do Spatie Permission
        // roles
        if (Schema::hasTable($tableNames['roles'])) {
            Schema::table($tableNames['roles'], function (Blueprint $blueprint) use ($tableNames) {
                if (!Schema::hasColumn($tableNames['roles'], 'tenant_id')) {
                    $blueprint->foreignId('tenant_id')
                        ->after('id')
                        ->nullable()
                        ->constrained('tenants')
                        ->onDelete('cascade');
                    
                    $blueprint->index(['tenant_id', 'id']);
                }
            });
        }

        // permissions (geralmente globais, mas adicionamos para flexibilidade)
        if (Schema::hasTable($tableNames['permissions'])) {
            Schema::table($tableNames['permissions'], function (Blueprint $blueprint) use ($tableNames) {
                if (!Schema::hasColumn($tableNames['permissions'], 'tenant_id')) {
                    $blueprint->foreignId('tenant_id')
                        ->after('id')
                        ->nullable()
                        ->constrained('tenants')
                        ->onDelete('cascade');
                    
                    $blueprint->index(['tenant_id', 'id']);
                }
            });
        }

        // model_has_roles
        if (Schema::hasTable($tableNames['model_has_roles'])) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $blueprint) use ($tableNames) {
                if (!Schema::hasColumn($tableNames['model_has_roles'], 'tenant_id')) {
                    $blueprint->foreignId('tenant_id')
                        ->nullable()
                        ->constrained('tenants')
                        ->onDelete('cascade');
                    
                    $blueprint->index('tenant_id');
                }
            });
        }

        // model_has_permissions
        if (Schema::hasTable($tableNames['model_has_permissions'])) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $blueprint) use ($tableNames) {
                if (!Schema::hasColumn($tableNames['model_has_permissions'], 'tenant_id')) {
                    $blueprint->foreignId('tenant_id')
                        ->nullable()
                        ->constrained('tenants')
                        ->onDelete('cascade');
                    
                    $blueprint->index('tenant_id');
                }
            });
        }

        // Tabelas adicionais que podem existir
        $additionalTables = [
            'activity_log',
            'settings',
            'notifications',
            'equipment_readings',
            'maintenance_records',
            'maintenance_schedules',
            'maintenance_types',
            'generated_documents',
            'document_verifications',
            'service_provider_works',
        ];

        foreach ($additionalTables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    if (!Schema::hasColumn($table, 'tenant_id')) {
                        $blueprint->foreignId('tenant_id')
                            ->nullable()
                            ->constrained('tenants')
                            ->onDelete('cascade');
                        
                        $blueprint->index('tenant_id');
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
        $tableNames = config('permission.table_names');
        
        $allTables = [
            'associates',
            'associate_ledgers',
            'assets',
            'bank_accounts',
            'cash_movements',
            'chart_accounts',
            'collective_purchases',
            'customers',
            'direct_purchases',
            'direct_purchase_items',
            'documents',
            'document_templates',
            'equipment',
            'expenses',
            'loans',
            'loan_payments',
            'products',
            'product_categories',
            'production_deliveries',
            'project_demands',
            'project_payments',
            'provider_payment_requests',
            'purchase_items',
            'purchase_orders',
            'purchase_order_items',
            'revenues',
            'sales_projects',
            'services',
            'service_orders',
            'service_order_payments',
            'service_providers',
            'service_provider_ledgers',
            'service_provider_services',
            'stock_movements',
            'suppliers',
            'activity_log',
            'settings',
            'notifications',
            'equipment_readings',
            'maintenance_records',
            'maintenance_schedules',
            'maintenance_types',
            'generated_documents',
            'document_verifications',
            'service_provider_works',
            $tableNames['roles'],
            $tableNames['permissions'],
            $tableNames['model_has_roles'],
            $tableNames['model_has_permissions'],
        ];

        foreach ($allTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    // dropForeign requires index name on some DB drivers; use dropForeign if exists
                    try {
                        $blueprint->dropForeign(['tenant_id']);
                    } catch (\Throwable $e) {
                        // ignore if foreign key doesn't exist or driver requires explicit name
                    }

                    $blueprint->dropColumn('tenant_id');
                });
            }
        }
    }
};
