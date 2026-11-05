<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = [
            'pre_distribution_sheets' => [
                ['tenant_id', 'tenants'], ['assigned_to', 'users'], ['created_by', 'users'], ['completed_by', 'users'],
            ],
            'pre_distribution_sheet_projects' => [
                ['sales_project_id', 'sales_projects'],
                [['tenant_id', 'sheet_id'], 'pre_distribution_sheets', 'pdsp_sheet_fk', ['tenant_id', 'id']],
            ],
            'pre_distribution_items' => [
                ['sales_project_id', 'sales_projects'], ['customer_id', 'customers'], ['product_id', 'products'],
                [['tenant_id', 'sheet_id', 'sales_project_id'], 'pre_distribution_sheet_projects', 'pdi_project_fk', ['tenant_id', 'sheet_id', 'sales_project_id']],
            ],
            'pre_distribution_operations' => [
                ['tenant_id', 'tenants'], ['actor_id', 'users'],
            ],
            'pre_distribution_events' => [
                ['actor_id', 'users'],
                [['tenant_id', 'operation_id'], 'pre_distribution_operations', 'pde_operation_fk', ['tenant_id', 'id']],
                [['tenant_id', 'sheet_id'], 'pre_distribution_sheets', 'pde_sheet_fk', ['tenant_id', 'id']],
            ],
            'pre_distribution_links' => [
                ['actor_id', 'users'], ['reversed_by', 'users'],
                [['tenant_id', 'item_id'], 'pre_distribution_items', 'pdl_item_fk', ['tenant_id', 'id']],
                [['tenant_id', 'operation_id'], 'pre_distribution_operations', 'pdl_op_fk', ['tenant_id', 'id']],
                [['tenant_id', 'reversal_operation_id'], 'pre_distribution_operations', 'pdl_reverse_op_fk', ['tenant_id', 'id']],
            ],
            'pre_distribution_reports' => [
                ['tenant_id', 'tenants'], ['actor_id', 'users'],
                [['tenant_id', 'operation_id'], 'pre_distribution_operations', 'pdr_operation_fk', ['tenant_id', 'id']],
            ],
            'pre_distribution_security_events' => [
                ['tenant_id', 'tenants'], ['actor_id', 'users'],
            ],
        ];

        $engines = array_column(Schema::getTables(DB::connection()->getDatabaseName()), 'engine', 'name');
        foreach (['tenants', 'users', 'sales_projects', 'customers', 'products', 'production_deliveries'] as $table) {
            if (strcasecmp($engines[$table] ?? '', 'InnoDB') !== 0) {
                throw new RuntimeException("A tabela {$table} precisa usar InnoDB antes de habilitar pré-distribuições.");
            }
        }

        // MyISAM ignores foreign keys. Restore them after conversion, with validation enabled.
        foreach ($tables as $name => $definitions) {
            if (strcasecmp($engines[$name] ?? '', 'InnoDB') !== 0) {
                $tableName = DB::connection()->getQueryGrammar()->wrapTable($name);
                DB::statement("ALTER TABLE {$tableName} ENGINE=InnoDB");
            }

            $existing = array_column(Schema::getForeignKeys($name), 'name');
            Schema::table($name, function (Blueprint $table) use ($definitions, $existing): void {
                foreach ($definitions as $definition) {
                    [$columns, $parent, $key, $references] = array_pad($definition, 4, null);
                    $key ??= $table->getTable().'_'.$columns.'_foreign';
                    if (! in_array($key, $existing, true)) {
                        $table->foreign($columns, $key)->references($references ?? 'id')->on($parent)->restrictOnDelete();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Retain transactional storage and restored constraints on rollback.
    }
};
