<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-financial')]
class MySqlFinancialConcurrencyTest extends TestCase
{
    private \PDO $second;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concorrencia financeira exige MySQL/MariaDB.');
        }

        $database = (string) DB::connection()->getDatabaseName();
        if (! str_contains(strtolower($database), 'test')) {
            $this->fail("A suite de concorrencia se recusa a alterar o banco '{$database}': use um banco dedicado contendo 'test' no nome.");
        }

        $config = config('database.connections.'.config('database.default'));
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $database,
            $config['charset'] ?? 'utf8mb4',
        );
        $this->second = new \PDO($dsn, $config['username'], $config['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        $this->dropProbeTables();
        DB::statement('CREATE TABLE f15_operations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, operation_key CHAR(36) NOT NULL, amount DECIMAL(14,2) NOT NULL, UNIQUE KEY f15_operation_unique (tenant_id, operation_key)) ENGINE=InnoDB');
        DB::statement('CREATE TABLE f15_receipts (id BIGINT UNSIGNED PRIMARY KEY, amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL) ENGINE=InnoDB');
        DB::statement('CREATE TABLE f15_distributions (id BIGINT UNSIGNED PRIMARY KEY, associate_receipt_id BIGINT UNSIGNED NULL, billing_receipt_id BIGINT UNSIGNED NULL) ENGINE=InnoDB');
        DB::statement('CREATE TABLE f15_sequences (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, scope_key VARCHAR(40) NOT NULL, receipt_type VARCHAR(30) NOT NULL, receipt_year SMALLINT UNSIGNED NOT NULL, last_number INT UNSIGNED NOT NULL DEFAULT 0, UNIQUE KEY f15_sequence_unique (tenant_id, scope_key, receipt_type, receipt_year)) ENGINE=InnoDB');
        DB::table('f15_receipts')->insert(['id' => 1, 'amount_paid' => 0, 'status' => 'pending_payment']);
        DB::table('f15_distributions')->insert(['id' => 1]);
        DB::table('f15_sequences')->insert(['tenant_id' => 1, 'scope_key' => 'tenant', 'receipt_type' => 'associate', 'receipt_year' => 2026]);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->second->exec('SET SESSION innodb_lock_wait_timeout = 1');
    }

    protected function tearDown(): void
    {
        if (isset($this->second) && $this->second->inTransaction()) {
            $this->second->rollBack();
        }
        if (DB::connection()->getDriverName() === 'mysql'
            && str_contains(strtolower((string) DB::connection()->getDatabaseName()), 'test')) {
            $this->dropProbeTables();
        }

        parent::tearDown();
    }

    public function test_unique_operation_key_is_the_last_barrier_during_concurrent_retry(): void
    {
        $key = '11111111-1111-4111-8111-111111111111';
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->prepare('INSERT INTO f15_operations (tenant_id, operation_key, amount) VALUES (1, ?, 500)')->execute([$key]);

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->prepare(
            'INSERT INTO f15_operations (tenant_id, operation_key, amount) VALUES (1, ?, 500)'
        )->execute([$key]));
        $this->second->rollBack();
        $first->commit();

        try {
            $this->second->prepare('INSERT INTO f15_operations (tenant_id, operation_key, amount) VALUES (1, ?, 500)')->execute([$key]);
            $this->fail('A UNIQUE tenant+operation_key deveria bloquear o retry duplicado.');
        } catch (\PDOException $exception) {
            $this->assertSame('23000', $exception->getCode());
        }

        $this->assertSame(1, (int) DB::table('f15_operations')->count());
    }

    public function test_receipt_and_distribution_locks_serialize_same_side_but_preserve_both_sides(): void
    {
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->query('SELECT id FROM f15_receipts WHERE id = 1 FOR UPDATE')->fetch();
        $first->query('SELECT id FROM f15_distributions WHERE id = 1 FOR UPDATE')->fetch();

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->query('SELECT id FROM f15_receipts WHERE id = 1 FOR UPDATE')->fetch());
        $this->second->rollBack();

        $first->exec("UPDATE f15_receipts SET amount_paid = 500, status = 'paid' WHERE id = 1");
        $first->exec('UPDATE f15_distributions SET associate_receipt_id = 10 WHERE id = 1');
        $first->commit();

        $this->second->beginTransaction();
        $row = $this->second->query('SELECT * FROM f15_distributions WHERE id = 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(10, (int) $row['associate_receipt_id']);
        $this->second->exec('UPDATE f15_distributions SET billing_receipt_id = 20 WHERE id = 1');
        $this->second->commit();

        $distribution = DB::table('f15_distributions')->find(1);
        $this->assertSame(10, (int) $distribution->associate_receipt_id);
        $this->assertSame(20, (int) $distribution->billing_receipt_id);
        $this->assertSame('500.00', DB::table('f15_receipts')->where('id', 1)->value('amount_paid'));
    }

    public function test_number_sequence_lock_produces_distinct_numbers(): void
    {
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $sequence = $first->query("SELECT * FROM f15_sequences WHERE tenant_id = 1 AND scope_key = 'tenant' AND receipt_type = 'associate' AND receipt_year = 2026 FOR UPDATE")->fetch(\PDO::FETCH_ASSOC);
        $numberA = (int) $sequence['last_number'] + 1;
        $first->exec("UPDATE f15_sequences SET last_number = {$numberA} WHERE id = {$sequence['id']}");

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->query('SELECT * FROM f15_sequences WHERE id = 1 FOR UPDATE')->fetch());
        $this->second->rollBack();
        $first->commit();

        $this->second->beginTransaction();
        $sequence = $this->second->query('SELECT * FROM f15_sequences WHERE id = 1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        $numberB = (int) $sequence['last_number'] + 1;
        $this->second->exec("UPDATE f15_sequences SET last_number = {$numberB} WHERE id = 1");
        $this->second->commit();

        $this->assertSame([1, 2], [$numberA, $numberB]);
    }

    private function expectLockTimeout(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A segunda conexao deveria aguardar o lock da primeira.');
        } catch (\PDOException $exception) {
            $this->assertContains((int) ($exception->errorInfo[1] ?? 0), [1205, 1213]);
        }
    }

    private function dropProbeTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS f15_operations');
        DB::statement('DROP TABLE IF EXISTS f15_receipts');
        DB::statement('DROP TABLE IF EXISTS f15_distributions');
        DB::statement('DROP TABLE IF EXISTS f15_sequences');
    }
}
