<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-services')]
class MySqlServiceConcurrencyTest extends TestCase
{
    private \PDO $second;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concorrência de serviços exige MySQL/InnoDB.');
        }$database = (string) DB::connection()->getDatabaseName();
        if (! str_contains(strtolower($database), 'test')) {
            $this->fail("Use banco dedicado contendo 'test' no nome, não {$database}.");
        }$config = config('database.connections.'.config('database.default'));
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $config['host'], $config['port'] ?? 3306, $database, $config['charset'] ?? 'utf8mb4');
        $this->second = new \PDO($dsn, $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $this->drop();
        DB::statement('CREATE TABLE svc_ops (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, operation_key CHAR(36) NOT NULL, UNIQUE KEY svc_ops_unique (tenant_id, operation_key)) ENGINE=InnoDB');
        DB::statement('CREATE TABLE svc_exec (id BIGINT UNSIGNED PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, status VARCHAR(24) NOT NULL) ENGINE=InnoDB');
        DB::statement('CREATE TABLE svc_obligations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, execution_id BIGINT UNSIGNED NOT NULL, direction VARCHAR(16) NOT NULL, amount DECIMAL(14,2) NOT NULL, paid DECIMAL(14,2) NOT NULL DEFAULT 0, UNIQUE KEY svc_obligation_once (execution_id,direction)) ENGINE=InnoDB');
        DB::statement('CREATE TABLE svc_sequences (tenant_id BIGINT UNSIGNED NOT NULL, year SMALLINT UNSIGNED NOT NULL, last_number BIGINT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY (tenant_id,year)) ENGINE=InnoDB');
        DB::table('svc_exec')->insert(['id' => 1, 'tenant_id' => 1, 'status' => 'submitted']);
        DB::table('svc_obligations')->insert(['execution_id' => 1, 'direction' => 'payable', 'amount' => 900, 'paid' => 0]);
        DB::table('svc_sequences')->insert(['tenant_id' => 1, 'year' => 2026, 'last_number' => 0]);
        DB::statement('SET SESSION innodb_lock_wait_timeout=1');
        $this->second->exec('SET SESSION innodb_lock_wait_timeout=1');
    }

    protected function tearDown(): void
    {
        if (isset($this->second) && $this->second->inTransaction()) {
            $this->second->rollBack();
        }if (DB::connection()->getDriverName() === 'mysql' && str_contains(strtolower((string) DB::connection()->getDatabaseName()), 'test')) {
            $this->drop();
        }parent::tearDown();
    }

    public function test_operation_key_prevents_concurrent_retry(): void
    {
        $key = '11111111-1111-4111-8111-111111111111';
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->prepare('INSERT INTO svc_ops (tenant_id,operation_key) VALUES (1,?)')->execute([$key]);
        $this->second->beginTransaction();
        $this->expectLock(fn () => $this->second->prepare('INSERT INTO svc_ops (tenant_id,operation_key) VALUES (1,?)')->execute([$key]));
        $this->second->rollBack();
        $first->commit();
        try {
            $this->second->prepare('INSERT INTO svc_ops (tenant_id,operation_key) VALUES (1,?)')->execute([$key]);
            $this->fail('Unique deveria rejeitar retry.');
        } catch (\PDOException $e) {
            $this->assertSame('23000', $e->getCode());
        }
    }

    public function test_obligation_lock_prevents_two_overpayments(): void
    {
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $row = $first->query('SELECT * FROM svc_obligations WHERE id=1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('900.00', $row['amount']);
        $this->second->beginTransaction();
        $this->expectLock(fn () => $this->second->query('SELECT * FROM svc_obligations WHERE id=1 FOR UPDATE')->fetch());
        $this->second->rollBack();
        $first->exec('UPDATE svc_obligations SET paid=600 WHERE id=1');
        $first->commit();
        $this->second->beginTransaction();
        $row = $this->second->query('SELECT * FROM svc_obligations WHERE id=1 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('300.00', number_format((float) $row['amount'] - (float) $row['paid'], 2, '.', ''));
        $this->second->rollBack();
    }

    public function test_execution_creates_each_direction_once(): void
    {
        DB::table('svc_obligations')->where('id', 1)->delete();
        DB::table('svc_obligations')->insert(['execution_id' => 1, 'direction' => 'payable', 'amount' => 900]);
        try {
            DB::table('svc_obligations')->insert(['execution_id' => 1, 'direction' => 'payable', 'amount' => 900]);
            $this->fail('Obrigação duplicada deveria ser rejeitada.');
        } catch (\Throwable $e) {
            $this->assertSame('23000', $e->getCode());
        }DB::table('svc_obligations')->insert(['execution_id' => 1, 'direction' => 'receivable', 'amount' => 1500]);
        $this->assertSame(2, DB::table('svc_obligations')->count());
    }

    public function test_sequence_lock_serializes_order_numbers(): void
    {
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $row = $first->query('SELECT * FROM svc_sequences WHERE tenant_id=1 AND year=2026 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        $a = (int) $row['last_number'] + 1;
        $first->exec("UPDATE svc_sequences SET last_number={$a} WHERE tenant_id=1 AND year=2026");
        $this->second->beginTransaction();
        $this->expectLock(fn () => $this->second->query('SELECT * FROM svc_sequences WHERE tenant_id=1 AND year=2026 FOR UPDATE')->fetch());
        $this->second->rollBack();
        $first->commit();
        $this->second->beginTransaction();
        $row = $this->second->query('SELECT * FROM svc_sequences WHERE tenant_id=1 AND year=2026 FOR UPDATE')->fetch(\PDO::FETCH_ASSOC);
        $b = (int) $row['last_number'] + 1;
        $this->second->exec("UPDATE svc_sequences SET last_number={$b} WHERE tenant_id=1 AND year=2026");
        $this->second->commit();
        $this->assertSame([1, 2], [$a, $b]);
    }

    private function expectLock(callable $callback): void
    {
        try {
            $callback();
            $this->fail('A segunda conexão deveria aguardar lock.');
        } catch (\PDOException $e) {
            $this->assertContains((int) ($e->errorInfo[1] ?? 0), [1205, 1213]);
        }
    }

    private function drop(): void
    {
        foreach (['svc_ops', 'svc_obligations', 'svc_exec', 'svc_sequences'] as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");
        }
    }
}
