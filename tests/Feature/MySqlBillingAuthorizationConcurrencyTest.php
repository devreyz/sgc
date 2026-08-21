<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('mysql-billing-authorization')]
class MySqlBillingAuthorizationConcurrencyTest extends TestCase
{
    private \PDO $second;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concorrência de autorização exige MySQL/MariaDB.');
        }
        $database = (string) DB::connection()->getDatabaseName();
        if (! str_contains(strtolower($database), 'test')) {
            $this->fail("A suíte se recusa a alterar '{$database}': use um banco dedicado contendo 'test' no nome.");
        }

        $config = config('database.connections.'.config('database.default'));
        $this->second = new \PDO(sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'], $config['port'] ?? 3306, $database, $config['charset'] ?? 'utf8mb4',
        ), $config['username'], $config['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);

        $this->dropProbeTables();
        DB::statement('CREATE TABLE f2b_receipts (id BIGINT UNSIGNED PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL) ENGINE=InnoDB');
        DB::statement('CREATE TABLE f2b_authorizations (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, tenant_id BIGINT UNSIGNED NOT NULL, receipt_id BIGINT UNSIGNED NOT NULL, organization_id BIGINT UNSIGNED NOT NULL, sequence_no INT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL, active_marker TINYINT(1) NULL, operation_key CHAR(36) NOT NULL, UNIQUE KEY f2b_operation_unique (tenant_id, operation_key), UNIQUE KEY f2b_round_unique (tenant_id, receipt_id, organization_id, sequence_no), UNIQUE KEY f2b_active_unique (tenant_id, receipt_id, organization_id, active_marker)) ENGINE=InnoDB');
        DB::statement('CREATE TABLE f2b_distributions (id BIGINT UNSIGNED PRIMARY KEY, receipt_id BIGINT UNSIGNED NOT NULL, quantity DECIMAL(14,4) NOT NULL) ENGINE=InnoDB');
        DB::table('f2b_receipts')->insert(['id' => 1, 'tenant_id' => 1, 'status' => 'pending_payment']);
        DB::table('f2b_distributions')->insert(['id' => 1, 'receipt_id' => 1, 'quantity' => 10]);
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

    public function test_two_sends_are_serialized_by_receipt_lock(): void
    {
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch();
        $first->exec("INSERT INTO f2b_authorizations (tenant_id, receipt_id, organization_id, sequence_no, status, active_marker, operation_key) VALUES (1,1,1,1,'sent',1,'11111111-1111-4111-8111-111111111111')");

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch());
        $this->second->rollBack();
        $first->commit();

        $this->assertSame(1, (int) DB::table('f2b_authorizations')->where('active_marker', 1)->count());
    }

    public function test_two_authorizations_produce_one_state_transition(): void
    {
        $this->seedSentRound();
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch();
        $first->query('SELECT id FROM f2b_authorizations WHERE id = 1 FOR UPDATE')->fetch();
        $first->exec("UPDATE f2b_authorizations SET status = 'authorized' WHERE id = 1 AND status = 'sent'");

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch());
        $this->second->rollBack();
        $first->commit();

        $this->assertSame('authorized', DB::table('f2b_authorizations')->where('id', 1)->value('status'));
    }

    public function test_authorize_and_request_correction_cannot_win_together(): void
    {
        $this->seedSentRound();
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch();
        $first->query('SELECT id FROM f2b_authorizations WHERE id = 1 FOR UPDATE')->fetch();
        $first->exec("UPDATE f2b_authorizations SET status = 'authorized' WHERE id = 1 AND status = 'sent'");

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch());
        $this->second->rollBack();
        $first->commit();

        $affected = $this->second->exec("UPDATE f2b_authorizations SET status = 'correction_requested', active_marker = NULL WHERE id = 1 AND status = 'sent'");
        $this->assertSame(0, $affected);
    }

    public function test_material_edit_waits_for_authorization_lock_order(): void
    {
        $this->seedSentRound();
        $first = DB::connection()->getPdo();
        $first->beginTransaction();
        $first->query('SELECT id FROM f2b_receipts WHERE id = 1 FOR UPDATE')->fetch();
        $first->query('SELECT id FROM f2b_authorizations WHERE id = 1 FOR UPDATE')->fetch();
        $first->query('SELECT id FROM f2b_distributions WHERE receipt_id = 1 ORDER BY id FOR UPDATE')->fetchAll();

        $this->second->beginTransaction();
        $this->expectLockTimeout(fn () => $this->second->exec('UPDATE f2b_distributions SET quantity = 20 WHERE id = 1'));
        $this->second->rollBack();
        $first->commit();

        $this->second->exec('UPDATE f2b_distributions SET quantity = 20 WHERE id = 1');
        $this->assertSame('20.0000', DB::table('f2b_distributions')->where('id', 1)->value('quantity'));
    }

    public function test_unique_active_marker_allows_history_but_only_one_current_round(): void
    {
        $this->seedSentRound();
        DB::table('f2b_authorizations')->where('id', 1)->update(['status' => 'correction_requested', 'active_marker' => null]);
        DB::table('f2b_authorizations')->insert(['tenant_id' => 1, 'receipt_id' => 1, 'organization_id' => 1, 'sequence_no' => 2, 'status' => 'sent', 'active_marker' => 1, 'operation_key' => '22222222-2222-4222-8222-222222222222']);

        try {
            DB::table('f2b_authorizations')->insert(['tenant_id' => 1, 'receipt_id' => 1, 'organization_id' => 1, 'sequence_no' => 3, 'status' => 'sent', 'active_marker' => 1, 'operation_key' => '33333333-3333-4333-8333-333333333333']);
            $this->fail('O índice deveria impedir duas rodadas ativas.');
        } catch (\Throwable $exception) {
            $this->assertSame('23000', $exception->getCode());
        }
        $this->assertSame(2, (int) DB::table('f2b_authorizations')->count());
    }

    private function seedSentRound(): void
    {
        DB::table('f2b_authorizations')->insert([
            'id' => 1, 'tenant_id' => 1, 'receipt_id' => 1, 'organization_id' => 1,
            'sequence_no' => 1, 'status' => 'sent', 'active_marker' => 1,
            'operation_key' => '11111111-1111-4111-8111-111111111111',
        ]);
    }

    private function expectLockTimeout(callable $operation): void
    {
        try {
            $operation();
            $this->fail('A segunda conexão deveria aguardar o lock da primeira.');
        } catch (\PDOException $exception) {
            $this->assertContains((int) ($exception->errorInfo[1] ?? 0), [1205, 1213]);
        }
    }

    private function dropProbeTables(): void
    {
        DB::statement('DROP TABLE IF EXISTS f2b_distributions');
        DB::statement('DROP TABLE IF EXISTS f2b_authorizations');
        DB::statement('DROP TABLE IF EXISTS f2b_receipts');
    }
}
