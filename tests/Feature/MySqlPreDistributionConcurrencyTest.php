<?php

namespace Tests\Feature;

use App\Models\PreDistributionItem;
use App\Models\User;
use App\Services\PreDistributions\PreDistributionReconciliationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Runs the foundation scenarios too, against actual InnoDB constraints and decimal columns. */
#[Group('mysql-pre-distributions')]
class MySqlPreDistributionConcurrencyTest extends PreDistributionFoundationTest
{
    protected function assertSafeDatabase(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Requer MySQL e banco descartável dedicado.');
        }
        $database = (string) DB::connection()->getDatabaseName();
        self::assertSame('1', getenv('PRE_DISTRIBUTIONS_MYSQL_DESTRUCTIVE_TESTS'), 'Autorize explicitamente a recriação do banco descartável de testes.');
        self::assertMatchesRegularExpression('/^sgc_pre_distributions_test_[a-z0-9_]+$/D', $database, 'Nunca execute esta suíte em banco real: ela recria todas as tabelas do banco dedicado.');
    }

    public function test_engine_migration_repairs_existing_myisam_tables_and_is_safe_to_retry(): void
    {
        $this->officialSchema();
        $table = 'pre_distribution_security_events';
        $keys = array_column(Schema::getForeignKeys($table), 'name');
        foreach ($keys as $key) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$key}");
        }
        DB::statement("ALTER TABLE {$table} ENGINE=MyISAM");

        $migration = require database_path('migrations/2026_11_05_000007_enforce_transactional_pre_distribution_tables.php');
        $migration->up();
        $migration->up();

        $engines = array_column(Schema::getTables(DB::connection()->getDatabaseName()), 'engine', 'name');
        self::assertSame('InnoDB', $engines[$table]);
        self::assertEqualsCanonicalizing($keys, array_column(Schema::getForeignKeys($table), 'name'));
    }

    public function test_two_real_connections_cannot_consume_the_same_preliminary_balance_or_repeat_an_operation(): void
    {
        $this->officialSchema();
        $this->completedSheet();
        $this->official(101);
        $this->official(102, '100');
        DB::table('tenant_user')->where('user_id', 3)->update(['roles' => json_encode(['registrador_entregas'])]);
        $secondActor = User::findOrFail(3);
        $itemId = PreDistributionItem::firstOrFail()->id;
        $service = app(PreDistributionReconciliationService::class);
        $key = (string) Str::uuid();
        $data = ['rows' => [['pre_distribution_item_id' => $itemId, 'pre_distribution_version' => 3, 'distribution_id' => 101, 'quantity' => '40']]];
        $original = DB::getDefaultConnection();
        $first = DB::connection();
        config(['database.connections.pd_second' => config('database.connections.'.$original)]);
        $second = DB::connection('pd_second');
        $pdo = $second->getPdo();
        self::assertNotSame($first->getPdo(), $pdo);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $first->beginTransaction();
        try {
            $result = $service->linkExisting(1, $this->manager, $key, $data);
            $pdo->beginTransaction();
            try {
                $pdo->query('SELECT id FROM tenants WHERE id = 1 FOR UPDATE');
                self::fail('A segunda conexão ultrapassou o bloqueio da confirmação em andamento.');
            } catch (\PDOException $e) {
                self::assertSame(1205, (int) ($e->errorInfo[1] ?? 0));
            } finally {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
            $first->commit();
            DB::setDefaultConnection('pd_second');
            self::assertEquals($result, $service->linkExisting(1, $this->manager, $key, $data));
            $next = ['rows' => [['pre_distribution_item_id' => $itemId, 'pre_distribution_version' => 3, 'distribution_id' => 102, 'quantity' => '60']]];
            try {
                $service->linkExisting(1, $secondActor, (string) Str::uuid(), $next);
                self::fail('Versão lida antes do commit ainda foi aceita.');
            } catch (HttpException $e) {
                self::assertSame(409, $e->getStatusCode());
            }
            $next['rows'][0]['pre_distribution_version'] = 4;
            $next['rows'][0]['quantity'] = '61';
            try {
                $service->linkExisting(1, $secondActor, (string) Str::uuid(), $next);
                self::fail('Saldo preliminar ultrapassado.');
            } catch (ValidationException $e) {
                self::assertStringContainsString('saldo preliminar', $e->getMessage());
            }
            $next['rows'][0]['quantity'] = '60';
            $service->linkExisting(1, $secondActor, (string) Str::uuid(), $next);
            self::assertSame('0.0000', $service->progress(1, $itemId)['remaining']);
            self::assertSame('100.0000', (string) DB::table('pre_distribution_links')->sum('quantity'));
            self::assertSame(2, DB::table('pre_distribution_links')->count());
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($first->transactionLevel() > 0) {
                $first->rollBack();
            }
            DB::setDefaultConnection($original);
            DB::purge('pd_second');
        }
    }
}
