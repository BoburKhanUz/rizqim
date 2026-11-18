<?php

namespace Tests\Feature\Partitions;

use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave M3 owner rule: partition maintenance never makes a till wait. With a second connection holding a lock on the
 * DEFAULT partition (a till transaction that wrote into it), ensure gives up after lock_timeout x retries instead of
 * queueing behind it (and so in front of every other writer), and succeeds once the lock is gone. Runs without the
 * wrapping transaction (the second connection must see the committed schema); the months it attaches are removed.
 */
class PartitionLockTimeoutTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    /** @var array<string, list<string>> */
    private array $before = [];

    /** @var list<resource> */
    private array $processes = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (array_keys(PartitionManager::tables()) as $table) {
            $this->before[$table] = array_column(app(PartitionManager::class)->partitions($table), 'name');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            proc_close($process);
        }
        // The holders log in as the application role (config pgsql): only that role may end them (M4: the test itself runs as the owner)
        DB::connection('pgsql')->statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE application_name LIKE 'm3_%' AND pid <> pg_backend_pid()");
        // Months a test detached come back empty (committed: no transaction rolls them back)
        foreach ($this->before as $names) {
            foreach ($names as $name) {
                if (app(PartitionManager::class)->exists($name) && !(bool) DB::selectOne('SELECT EXISTS (SELECT 1 FROM pg_inherits WHERE inhrelid = ?::regclass) AS e', [$name])->e) {
                    DB::statement("DROP TABLE {$name}");
                }
            }
        }
        DB::table('partition_archives')->delete();
        DB::statement('TRUNCATE audit_logs');
        DB::statement('TRUNCATE notification_logs');
        app(PartitionManager::class)->ensureAll();
        foreach ($this->before as $table => $names) {
            foreach (array_column(app(PartitionManager::class)->partitions($table), 'name') as $name) {
                if (!in_array($name, $names, true)) {
                    DB::statement("ALTER TABLE {$table} DETACH PARTITION {$name}");
                    DB::statement("DROP TABLE {$name}");
                }
            }
        }
        parent::tearDown();
    }

    public function test_ensure_gives_up_quickly_behind_a_held_lock_and_succeeds_after_it(): void
    {
        config(['partitions.lock_timeout' => '150ms', 'partitions.lock_retries' => 2]);
        $this->travelTo(now()->addMonths(2));

        $db = config('database.connections.pgsql');
        $holder = proc_open(
            ['psql', '-X', '-q', '-h', (string) $db['host'], '-p', (string) $db['port'], '-U', (string) $db['username'], '-d', (string) $db['database'],
                '-c', "BEGIN; SET LOCAL application_name = 'm3_lock_holder'; LOCK TABLE notification_logs_default IN ROW EXCLUSIVE MODE; SELECT pg_sleep(120); COMMIT;"],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PGPASSWORD' => (string) $db['password'], 'PATH' => (string) getenv('PATH')],
        );
        $this->assertIsResource($holder);
        // The holder keeps its lock until this test ends it (hard cap 120 s): no assumption about relative speeds
        $held = fn (): bool => (bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_locks l JOIN pg_stat_activity a ON a.pid = l.pid WHERE l.relation = 'notification_logs_default'::regclass AND l.mode = 'RowExclusiveLock' AND l.granted AND a.application_name = 'm3_lock_holder') AS e")->e;
        $deadline = microtime(true) + 60;
        while (!$held()) {
            if (microtime(true) > $deadline) {
                $this->fail('the lock holder did not start within 60 s');
            }
            usleep(20_000);
        }

        try {
            $this->artisan('db:partitions:ensure')->expectsOutputToContain('notification_logs: bajarilmadi')->assertFailed();
            // ensure gave up (lock_timeout x retries) while the lock was still held: it did not queue behind it
            $this->assertTrue($held(), 'ensure returned while the till-like transaction still held its lock');
        } finally {
            DB::connection('pgsql')->statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE application_name = 'm3_lock_holder'");
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            proc_close($holder);
        }

        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $this->assertContains(PartitionManager::monthName('notification_logs', PartitionManager::monthStart(now())->addMonths(3)), array_column(app(PartitionManager::class)->partitions('notification_logs'), 'name'));
    }

    /**
     * R1-2: the archive's detach needs the tables its foreign keys reference (branches, devices, stores, users). Sessions
     * hold them and let go in stages, longer than the lock timeout in total: a till's audit insert must never queue
     * behind the archive for more than about one lock timeout (the archive takes the referenced tables first and
     * the parent last, every lock wait and the whole statement bounded, and gives up rather than hold).
     */
    public function test_archive_never_keeps_a_till_audit_insert_waiting_while_referenced_tables_are_busy(): void
    {
        $this->holdLocks(['branches' => 2.5, 'devices' => 5, 'stores' => 7.5, 'users' => 10]);
        $archive = $this->artisanInBackground('db:partitions:archive', ['PARTITIONS_DETACH_LOCK_TIMEOUT' => '3s']);
        $this->waitUntilSomeoneWaitsForALock();

        $worst = $this->worstInsertWait(fn () => DB::table('audit_logs')->insert(['action' => 'till.insert', 'created_at' => now()]), 9);
        $this->assertLessThan(3.6, $worst, "a till insert waited {$worst} s behind the archive (the bound is the 3 s lock timeout)");
        $this->finishProcess($archive);
    }

    /**
     * R1-2 for ensure: attaching a month validates its foreign keys (the referenced tables) and takes the DEFAULT
     * partition; an insert that lands in DEFAULT waits at most about one lock timeout.
     */
    public function test_ensure_never_keeps_a_default_insert_waiting_while_referenced_tables_are_busy(): void
    {
        $last = PartitionManager::monthName('notification_logs', PartitionManager::monthStart(now())->addMonths(3));
        DB::statement("ALTER TABLE notification_logs DETACH PARTITION {$last}");
        DB::statement("DROP TABLE {$last}");

        $this->holdLocks(['orders' => 2.5, 'users' => 5]);
        $ensure = $this->artisanInBackground('db:partitions:ensure', ['PARTITIONS_LOCK_TIMEOUT' => '2s']);
        $this->waitUntilSomeoneWaitsForALock();

        $worst = $this->worstInsertWait(fn () => DB::table('notification_logs')->insert(['type' => 'till', 'status' => 'sent', 'sent_at' => '2035-01-01 00:00:00']), 5);
        $this->assertLessThan(2.6, $worst, "an insert into DEFAULT waited {$worst} s behind ensure (the bound is the 2 s lock timeout)");
        $this->finishProcess($ensure);
    }

    /**
     * @param  array<string, float>  $tables  table => seconds the lock is held
     */
    private function holdLocks(array $tables): void
    {
        $db = config('database.connections.pgsql');
        foreach ($tables as $table => $seconds) {
            $this->processes[] = proc_open(
                ['psql', '-X', '-q', '-h', (string) $db['host'], '-p', (string) $db['port'], '-U', (string) $db['username'], '-d', (string) $db['database'],
                    '-c', "BEGIN; LOCK TABLE {$table} IN ROW EXCLUSIVE MODE; SELECT pg_sleep({$seconds}); COMMIT;"],
                [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
                null,
                ['PGPASSWORD' => (string) $db['password'], 'PGAPPNAME' => 'm3_holder', 'PATH' => (string) getenv('PATH')],
            );
        }
        $deadline = microtime(true) + 30;
        while ((int) DB::selectOne("SELECT count(*) AS n FROM pg_locks l JOIN pg_stat_activity a ON a.pid = l.pid WHERE a.application_name = 'm3_holder' AND l.mode = 'RowExclusiveLock' AND l.granted AND l.locktype = 'relation'")->n < count($tables)) {
            if (microtime(true) > $deadline) {
                $this->fail('the lock holders did not start');
            }
            usleep(20_000);
        }
    }

    /**
     * @param  array<string, string>  $env
     * @return array{process: resource, out: string}
     */
    private function artisanInBackground(string $command, array $env): array
    {
        $db = config('database.connections.pgsql');
        $out = tempnam(sys_get_temp_dir(), 'm3');
        $process = proc_open(
            [PHP_BINARY, base_path('artisan'), $command],
            [1 => ['file', $out, 'w'], 2 => ['file', $out, 'a']],
            $pipes,
            base_path(),
            array_merge(getenv(), $env, [
                'DB_DATABASE' => (string) $db['database'], 'DB_PORT' => (string) $db['port'], 'DB_HOST' => (string) $db['host'],
                'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'APP_ENV' => 'testing',
            ]),
        );
        $this->assertIsResource($process);
        $this->processes[] = $process;

        return ['process' => $process, 'out' => (string) $out];
    }

    private function waitUntilSomeoneWaitsForALock(): void
    {
        $deadline = microtime(true) + 30;
        while (!DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_stat_activity WHERE wait_event_type = 'Lock' AND application_name NOT LIKE 'm3_%') AS e")->e) {
            if (microtime(true) > $deadline) {
                $this->fail('the maintenance command never waited for a lock');
            }
            usleep(10_000);
        }
    }

    /**
     * The longest of several inserts made over $seconds (each its own transaction, as a till's).
     */
    private function worstInsertWait(callable $insert, float $seconds): float
    {
        $worst = 0.0;
        $end = microtime(true) + $seconds;
        while (microtime(true) < $end) {
            $started = microtime(true);
            $insert();
            $worst = max($worst, microtime(true) - $started);
            usleep(100_000);
        }

        return $worst;
    }

    /**
     * @param  array{process: resource, out: string}  $command
     */
    private function finishProcess(array $command): void
    {
        $deadline = microtime(true) + 60;
        while (proc_get_status($command['process'])['running']) {
            if (microtime(true) > $deadline) {
                $this->fail('the maintenance command did not finish: ' . file_get_contents($command['out']));
            }
            usleep(50_000);
        }
        $this->assertStringNotContainsString('Exception', (string) file_get_contents($command['out']), (string) file_get_contents($command['out']));
    }
}
