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
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    /** @var array<string, list<string>> */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (array_keys(PartitionManager::tables()) as $table) {
            $this->before[$table] = array_column(app(PartitionManager::class)->partitions($table), 'name');
        }
    }

    protected function tearDown(): void
    {
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
            DB::statement("SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE application_name = 'm3_lock_holder'");
            stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            proc_close($holder);
        }

        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $this->assertContains(PartitionManager::monthName('notification_logs', PartitionManager::monthStart(now())->addMonths(3)), array_column(app(PartitionManager::class)->partitions('notification_logs'), 'name'));
    }
}
