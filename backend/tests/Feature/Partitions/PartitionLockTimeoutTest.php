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
                '-c', 'BEGIN; LOCK TABLE notification_logs_default IN ROW EXCLUSIVE MODE; SELECT pg_sleep(4); COMMIT;'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PGPASSWORD' => (string) $db['password'], 'PATH' => (string) getenv('PATH')],
        );
        $this->assertIsResource($holder);
        $deadline = microtime(true) + 10;
        while (!DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_locks WHERE relation = 'notification_logs_default'::regclass AND mode = 'RowExclusiveLock' AND granted AND pid <> pg_backend_pid()) AS e")->e && microtime(true) < $deadline) {
            usleep(20_000);
        }

        $started = microtime(true);
        $this->artisan('db:partitions:ensure')->expectsOutputToContain('notification_logs: bajarilmadi')->assertFailed();
        $waited = microtime(true) - $started;
        $this->assertLessThan(2.5, $waited, 'two attempts of 150 ms plus a 0.5 s pause, not the 4 s the lock is held');

        stream_get_contents($pipes[1]);
        proc_close($holder);

        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $this->assertContains(PartitionManager::monthName('notification_logs', PartitionManager::monthStart(now())->addMonths(3)), array_column(app(PartitionManager::class)->partitions('notification_logs'), 'name'));
    }
}
