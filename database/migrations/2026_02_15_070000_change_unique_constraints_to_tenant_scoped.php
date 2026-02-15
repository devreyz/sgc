<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Altera constraints UNIQUE simples para UNIQUE compostas (campo + tenant_id)
     * permitindo que organizações diferentes possam ter registros com os mesmos
     * identificadores (CNPJ, CPF, códigos, etc).
     */
    public function up(): void
    {
        $tables = [
            'suppliers' => 'cpf_cnpj',
            'service_providers' => 'cpf',
            'customers' => 'cpf_cnpj',
            'associates' => 'cpf_cnpj',
        ];

        foreach ($tables as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            // Tentar remover constraint unique existente
            try {
                // No MySQL, precisamos descobrir o nome da constraint
                $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Column_name = '{$column}' AND Non_unique = 0");

                foreach ($indexes as $index) {
                    if ($index->Key_name !== 'PRIMARY') {
                        DB::statement("ALTER TABLE {$table} DROP INDEX {$index->Key_name}");
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Não foi possível remover constraint unique de {$table}.{$column}: ".$e->getMessage());
            }

            // Adicionar constraint unique composta
            try {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->unique([$column, 'tenant_id'], "{$table->getTable()}_{$column}_tenant_unique");
                });
            } catch (\Exception $e) {
                Log::warning("Não foi possível criar constraint unique composta em {$table}.{$column}: ".$e->getMessage());
            }
        }

        // Tabelas com códigos/SKUs
        $codeTables = [
            'products' => 'code',
            'equipments' => 'code',
            'chart_accounts' => 'code',
            'assets' => 'code',
            'services' => 'code',
        ];

        foreach ($codeTables as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            try {
                $indexes = DB::select("SHOW INDEXES FROM {$table} WHERE Column_name = '{$column}' AND Non_unique = 0");

                foreach ($indexes as $index) {
                    if ($index->Key_name !== 'PRIMARY') {
                        DB::statement("ALTER TABLE {$table} DROP INDEX {$index->Key_name}");
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Não foi possível remover constraint unique de {$table}.{$column}: ".$e->getMessage());
            }

            try {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->unique([$column, 'tenant_id'], "{$table->getTable()}_{$column}_tenant_unique");
                });
            } catch (\Exception $e) {
                Log::warning("Não foi possível criar constraint unique composta em {$table}.{$column}: ".$e->getMessage());
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'suppliers' => 'cpf_cnpj',
            'service_providers' => 'cpf',
            'customers' => 'cpf_cnpj',
            'associates' => 'cpf_cnpj',
            'products' => 'code',
            'equipments' => 'code',
            'chart_accounts' => 'code',
            'assets' => 'code',
            'services' => 'code',
        ];

        foreach ($tables as $table => $column) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            try {
                DB::statement("ALTER TABLE {$table} DROP INDEX {$table}_{$column}_tenant_unique");
            } catch (\Exception $e) {
                // Ignore se não existir
            }

            // Recriar constraint unique simples (opcional - pode causar erros se houver duplicatas)
            // Deixar comentado para evitar problemas
            // try {
            //     Schema::table($table, function (Blueprint $table) use ($column) {
            //         $table->unique($column);
            //     });
            // } catch (\Exception $e) {
            //     Log::warning("Não foi possível recriar constraint unique em {$table}.{$column}");
            // }
        }
    }
};
