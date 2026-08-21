<?php

namespace App\Console\Commands;

use App\Services\FinancialSchemaAuditService;
use Illuminate\Console\Command;

class AuditFinancialSchema extends Command
{
    protected $signature = 'finance:audit-schema {--json : Retorna JSON para automacao}';

    protected $description = 'Audita engines, FKs e indices financeiros sem alterar o schema';

    public function handle(FinancialSchemaAuditService $auditor): int
    {
        $result = $auditor->audit();
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->components->info("Schema financeiro: {$result['driver']} / {$result['database']}");
        if ($result['driver'] !== 'mysql') {
            $this->components->warn('A matriz real de constraints exige MySQL/MariaDB information_schema.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tabela', 'Primary key'],
            collect($result['primary_keys'])->map(fn (array $row): array => [
                $row['table'], implode(', ', $row['columns']),
            ])->all(),
        );

        $this->table(
            ['Tabela', 'Coluna', 'Nullable', 'FK esperada', 'FK real', 'Indice', 'Unique', 'ON DELETE', 'Risco'],
            collect($result['constraints'])->map(fn (array $row): array => [
                $row['table'], $row['column'], $row['nullable'] ? 'sim' : 'nao', $row['expected_fk'] ?? '-', $row['actual_fk'] ?? '-',
                $row['index'] ?? '-', $row['unique'] ? 'sim' : 'nao', $row['actual_on_delete'] ?? '-', $row['risk'],
            ])->all(),
        );
        $this->table(
            ['Tabela', 'Colunas unicas', 'Indice', 'Existe'],
            collect($result['required_uniques'])->map(fn (array $row): array => [
                $row['table'], implode(', ', $row['columns']), $row['index'] ?? '-', $row['exists'] ? 'sim' : 'nao',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
