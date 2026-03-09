<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Reestrutura as tabelas PDV para suportar particionamento RANGE trimestral.
 *
 * MySQL não suporta particionamento em tabelas com foreign keys.
 * Estratégia:
 *  1. Remover FKs das tabelas filhas → pdv_sales
 *  2. Remover FKs de pdv_sales → outras tabelas
 *  3. Alterar PK de pdv_sales para (id, created_at) composta
 *  4. Converter tabelas para InnoDB
 *  5. Aplicar PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) trimestral
 *
 * A integridade referencial é mantida pela aplicação (PdvService).
 */
return new class extends Migration
{
    public function up(): void
    {
        // === 1. Remover FKs das tabelas filhas que apontam para pdv_sales ===
        $this->dropForeignKeysTo('pdv_sale_items', 'pdv_sales');
        $this->dropForeignKeysTo('pdv_sale_payments', 'pdv_sales');
        $this->dropForeignKeysTo('pdv_fiado_payments', 'pdv_sales');

        // === 2. Remover FKs de pdv_sales que apontam para outras tabelas ===
        $this->dropForeignKeysFrom('pdv_sales');

        // === 3. Alterar PK de pdv_sales para incluir created_at ===
        DB::statement('ALTER TABLE pdv_sales DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');
        DB::statement('ALTER TABLE pdv_sales MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // === 4. Ajustar index do code ===
        try { DB::statement('ALTER TABLE pdv_sales DROP INDEX pdv_sales_code_unique'); } catch (\Exception $e) {}
        try { DB::statement('ALTER TABLE pdv_sales DROP INDEX pdv_sales_code_index'); } catch (\Exception $e) {}
        DB::statement('ALTER TABLE pdv_sales ADD INDEX pdv_sales_code_idx (code, created_at)');

        // === 5. Converter para InnoDB (necessário para particionamento no MySQL 8) ===
        foreach (['pdv_customers', 'pdv_sales', 'pdv_sale_items', 'pdv_sale_payments', 'pdv_fiado_payments'] as $table) {
            DB::statement("ALTER TABLE {$table} ENGINE = InnoDB");
        }

        // === 6. Aplicar particionamento RANGE por trimestre usando UNIX_TIMESTAMP ===
        $partitions = $this->buildPartitionsSql();
        DB::statement("ALTER TABLE pdv_sales PARTITION BY RANGE (UNIX_TIMESTAMP(created_at)) ({$partitions})");
    }

    public function down(): void
    {
        // Remover particionamento
        DB::statement('ALTER TABLE pdv_sales REMOVE PARTITIONING');

        // Restaurar PK original
        DB::statement('ALTER TABLE pdv_sales DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        DB::statement('ALTER TABLE pdv_sales MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');

        // Restaurar index do code
        try { DB::statement('ALTER TABLE pdv_sales DROP INDEX pdv_sales_code_idx'); } catch (\Exception $e) {}
        DB::statement('ALTER TABLE pdv_sales ADD UNIQUE INDEX pdv_sales_code_unique (code)');

        // Restaurar FKs de pdv_sales
        DB::statement('ALTER TABLE pdv_sales ADD CONSTRAINT pdv_sales_tenant_id_foreign FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE pdv_sales ADD CONSTRAINT pdv_sales_pdv_customer_id_foreign FOREIGN KEY (pdv_customer_id) REFERENCES pdv_customers(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE pdv_sales ADD CONSTRAINT pdv_sales_created_by_foreign FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        DB::statement('ALTER TABLE pdv_sales ADD CONSTRAINT pdv_sales_cancelled_by_foreign FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL');

        // Restaurar FKs das tabelas filhas
        DB::statement('ALTER TABLE pdv_sale_items ADD CONSTRAINT pdv_sale_items_pdv_sale_id_foreign FOREIGN KEY (pdv_sale_id) REFERENCES pdv_sales(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE pdv_sale_payments ADD CONSTRAINT pdv_sale_payments_pdv_sale_id_foreign FOREIGN KEY (pdv_sale_id) REFERENCES pdv_sales(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE pdv_fiado_payments ADD CONSTRAINT pdv_fiado_payments_pdv_sale_id_foreign FOREIGN KEY (pdv_sale_id) REFERENCES pdv_sales(id) ON DELETE CASCADE');
    }

    private function buildPartitionsSql(): string
    {
        $now = Carbon::now();
        $startYear = $now->year - 1;
        $endYear = $now->year + 2;

        $parts = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $parts[] = "PARTITION p{$year}_q1 VALUES LESS THAN (" . Carbon::create($year, 4, 1)->timestamp . ")";
            $parts[] = "PARTITION p{$year}_q2 VALUES LESS THAN (" . Carbon::create($year, 7, 1)->timestamp . ")";
            $parts[] = "PARTITION p{$year}_q3 VALUES LESS THAN (" . Carbon::create($year, 10, 1)->timestamp . ")";
            $parts[] = "PARTITION p{$year}_q4 VALUES LESS THAN (" . Carbon::create($year + 1, 1, 1)->timestamp . ")";
        }

        $parts[] = "PARTITION p_future VALUES LESS THAN (MAXVALUE)";
        return implode(",\n", $parts);
    }

    private function dropForeignKeysTo(string $table, string $referencedTable): void
    {
        $dbName = DB::getDatabaseName();
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME = ?
        ", [$dbName, $table, $referencedTable]);

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
        }
    }

    private function dropForeignKeysFrom(string $table): void
    {
        $dbName = DB::getDatabaseName();
        $fks = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
        ", [$dbName, $table]);

        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
        }
    }
};
