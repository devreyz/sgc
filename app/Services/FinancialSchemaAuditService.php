<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class FinancialSchemaAuditService
{
    /** @var array<string, array{target: string|null, delete: string|null, risk: string}> */
    private const EXPECTED = [
        'production_deliveries.parent_delivery_id' => ['target' => 'production_deliveries.id', 'delete' => 'SET NULL', 'risk' => 'critical'],
        'production_deliveries.associate_receipt_id' => ['target' => null, 'delete' => null, 'risk' => 'critical: operational pointer without migration FK'],
        'production_deliveries.billing_receipt_id' => ['target' => 'customer_billing_receipts.id', 'delete' => 'SET NULL', 'risk' => 'critical'],
        'production_deliveries.distribution_billing_id' => ['target' => null, 'delete' => null, 'risk' => 'legacy pointer without migration FK'],
        'production_deliveries.project_payment_id' => ['target' => 'project_payments.id', 'delete' => 'SET NULL', 'risk' => 'legacy pointer'],
        'associate_receipts.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_receipts.sales_project_id' => ['target' => 'sales_projects.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_receipts.associate_id' => ['target' => 'associates.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_receipts.paid_by' => ['target' => 'users.id', 'delete' => 'SET NULL', 'risk' => 'audit'],
        'associate_receipts.bank_account_id' => ['target' => 'bank_accounts.id', 'delete' => 'SET NULL', 'risk' => 'financial'],
        'associate_receipt_payments.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_receipt_payments.associate_receipt_id' => ['target' => 'associate_receipts.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_receipt_payments.bank_account_id' => ['target' => 'bank_accounts.id', 'delete' => 'SET NULL', 'risk' => 'financial'],
        'customer_billing_receipts.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'customer_billing_receipts.sales_project_id' => ['target' => 'sales_projects.id', 'delete' => 'SET NULL', 'risk' => 'historical'],
        'customer_billing_receipts.customer_id' => ['target' => 'customers.id', 'delete' => 'SET NULL', 'risk' => 'historical'],
        'customer_billing_receipts.organization_id' => ['target' => 'organizations.id', 'delete' => 'SET NULL', 'risk' => 'historical'],
        'customer_receipt_payments.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'customer_receipt_payments.customer_billing_receipt_id' => ['target' => 'customer_billing_receipts.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'customer_receipt_payments.bank_account_id' => ['target' => 'bank_accounts.id', 'delete' => 'SET NULL', 'risk' => 'financial'],
        'associate_ledgers.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_ledgers.associate_id' => ['target' => 'associates.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'associate_ledgers.created_by' => ['target' => 'users.id', 'delete' => 'SET NULL', 'risk' => 'audit'],
        'bank_accounts.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'cash_movements.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'cash_movements.bank_account_id' => ['target' => 'bank_accounts.id', 'delete' => 'SET NULL', 'risk' => 'financial'],
        'cash_movements.transfer_to_account_id' => ['target' => 'bank_accounts.id', 'delete' => 'SET NULL', 'risk' => 'financial'],
        'cash_movements.created_by' => ['target' => 'users.id', 'delete' => 'SET NULL', 'risk' => 'audit'],
        'project_fees.tenant_id' => ['target' => 'tenants.id', 'delete' => 'RESTRICT', 'risk' => 'critical'],
        'project_fees.sales_project_id' => ['target' => 'sales_projects.id', 'delete' => 'RESTRICT', 'risk' => 'critical'],
        'customer_project_fees.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'customer_project_fees.sales_project_id' => ['target' => 'sales_projects.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'receipt_number_sequences.tenant_id' => ['target' => 'tenants.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
        'receipt_number_sequences.sales_project_id' => ['target' => 'sales_projects.id', 'delete' => 'CASCADE', 'risk' => 'critical'],
    ];

    /** @return array{driver: string, database: string, engines: array<string, string|null>, primary_keys: list<array<string, mixed>>, constraints: list<array<string, mixed>>, required_uniques: list<array<string, mixed>>} */
    public function audit(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $database = (string) $connection->getDatabaseName();

        if ($driver !== 'mysql') {
            return [
                'driver' => $driver,
                'database' => $database,
                'engines' => [],
                'primary_keys' => [],
                'constraints' => [],
                'required_uniques' => [],
            ];
        }

        $tables = collect(array_keys(self::EXPECTED))
            ->map(fn (string $key): string => explode('.', $key, 2)[0])
            ->push('bank_accounts')
            ->unique()->values();
        $placeholders = $tables->map(fn (): string => '?')->implode(',');
        $bindings = [$database, ...$tables->all()];
        $engines = collect(DB::select(
            "select TABLE_NAME, ENGINE from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_NAME in ({$placeholders})",
            $bindings,
        ))->mapWithKeys(fn (object $row): array => [$row->TABLE_NAME => $row->ENGINE])->all();

        $foreignKeys = collect(DB::select(
            'select k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE '
            .'from information_schema.KEY_COLUMN_USAGE k '
            .'left join information_schema.REFERENTIAL_CONSTRAINTS r on r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA and r.CONSTRAINT_NAME = k.CONSTRAINT_NAME '
            .'where k.CONSTRAINT_SCHEMA = ? and k.REFERENCED_TABLE_NAME is not null',
            [$database],
        ))->keyBy(fn (object $row): string => "{$row->TABLE_NAME}.{$row->COLUMN_NAME}");
        $indexes = collect(DB::select(
            'select TABLE_NAME, COLUMN_NAME, INDEX_NAME, NON_UNIQUE from information_schema.STATISTICS where TABLE_SCHEMA = ? order by SEQ_IN_INDEX',
            [$database],
        ))->groupBy(fn (object $row): string => "{$row->TABLE_NAME}.{$row->COLUMN_NAME}");
        $columns = collect(DB::select(
            'select TABLE_NAME, COLUMN_NAME, IS_NULLABLE from information_schema.COLUMNS where TABLE_SCHEMA = ?',
            [$database],
        ))->keyBy(fn (object $row): string => "{$row->TABLE_NAME}.{$row->COLUMN_NAME}");
        $primaryKeys = collect(DB::select(
            'select TABLE_NAME, COLUMN_NAME, SEQ_IN_INDEX from information_schema.STATISTICS '
            .'where TABLE_SCHEMA = ? and INDEX_NAME = \'PRIMARY\' order by TABLE_NAME, SEQ_IN_INDEX',
            [$database],
        ))->whereIn('TABLE_NAME', $tables)->groupBy('TABLE_NAME')->map(fn ($rows, string $table): array => [
            'table' => $table,
            'columns' => $rows->pluck('COLUMN_NAME')->values()->all(),
        ])->values()->all();

        $constraints = collect(self::EXPECTED)->map(function (array $expected, string $key) use ($foreignKeys, $indexes, $columns): array {
            [$table, $column] = explode('.', $key, 2);
            $actual = $foreignKeys->get($key);
            $columnIndexes = $indexes->get($key, collect());
            $actualTarget = $actual ? "{$actual->REFERENCED_TABLE_NAME}.{$actual->REFERENCED_COLUMN_NAME}" : null;
            $matches = $expected['target'] === null
                ? $actualTarget === null
                : $actualTarget === $expected['target'] && $actual->DELETE_RULE === $expected['delete'];

            return [
                'table' => $table,
                'column' => $column,
                'expected_fk' => $expected['target'],
                'actual_fk' => $actualTarget,
                'index' => $columnIndexes->pluck('INDEX_NAME')->unique()->implode(', ') ?: null,
                'unique' => $columnIndexes->contains(fn (object $row): bool => (int) $row->NON_UNIQUE === 0),
                'nullable' => ($columns->get($key)?->IS_NULLABLE ?? null) === 'YES',
                'expected_on_delete' => $expected['delete'],
                'actual_on_delete' => $actual->DELETE_RULE ?? null,
                'matches_migration' => $matches,
                'risk' => $expected['target'] === null ? $expected['risk'] : ($matches ? 'ok' : $expected['risk']),
            ];
        })->values()->all();

        return [
            'driver' => $driver,
            'database' => $database,
            'engines' => $engines,
            'primary_keys' => $primaryKeys,
            'constraints' => $constraints,
            'required_uniques' => [
                $this->uniqueStatus($database, 'associate_receipt_payments', ['tenant_id', 'operation_key'], 'arp_tenant_operation_unique'),
                $this->uniqueStatus($database, 'customer_receipt_payments', ['tenant_id', 'operation_key'], 'crp_tenant_operation_unique'),
                $this->uniqueStatus($database, 'receipt_number_sequences', ['tenant_id', 'scope_key', 'receipt_type', 'receipt_year']),
            ],
        ];
    }

    /** @param list<string> $columns */
    private function uniqueStatus(string $database, string $table, array $columns, ?string $preferredName = null): array
    {
        $rows = collect(DB::select(
            'select INDEX_NAME, COLUMN_NAME, SEQ_IN_INDEX from information_schema.STATISTICS '
            .'where TABLE_SCHEMA = ? and TABLE_NAME = ? and NON_UNIQUE = 0 order by INDEX_NAME, SEQ_IN_INDEX',
            [$database, $table],
        ));
        $matching = $rows->groupBy('INDEX_NAME')->first(
            fn ($group, string $name): bool => ($preferredName === null || $name === $preferredName)
                && $group->pluck('COLUMN_NAME')->values()->all() === $columns,
        );

        return [
            'table' => $table,
            'columns' => $columns,
            'index' => $matching?->first()?->INDEX_NAME,
            'exists' => $matching !== null,
        ];
    }
}
