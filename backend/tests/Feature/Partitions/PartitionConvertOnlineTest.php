<?php

namespace Tests\Feature\Partitions;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Partitions\PartitionConverter;
use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Wave M3: `db:partitions:convert` on tables with data. The command commits batch by batch and a second connection
 * writes during --finish, so these tests run WITHOUT the wrapping transaction: setUp turns the tables back into plain
 * ones (as on a pilot database before M3), tearDown converts them again and empties them.
 */
class PartitionConvertOnlineTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = [];

    private const TABLES = ['audit_logs', 'notification_logs'];

    private int $usersBefore = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usersBefore = (int) DB::table('users')->max('id');
        $this->restore();
        foreach (self::TABLES as $table) {
            app(PartitionConverter::class)->revertInline($table);
        }
    }

    protected function tearDown(): void
    {
        $this->restore();
        parent::tearDown();
    }

    public function test_a_copy_killed_half_way_resumes_and_finish_swaps_with_every_row_and_the_trigger(): void
    {
        DB::statement("INSERT INTO audit_logs (action, created_at) SELECT 'seed.' || g, timestamp '2026-03-01' + (g || ' minutes')::interval FROM generate_series(1, 5000) g");
        $converter = app(PartitionConverter::class);
        $converter->prepare('audit_logs');
        $this->assertSame('copying', $converter->state('audit_logs'));

        $batches = 0;
        try {
            $converter->copy('audit_logs', 400, function () use (&$batches) {
                if (++$batches === 3) {
                    throw new RuntimeException('killed');
                }
            });
            $this->fail('the copy should have been killed');
        } catch (RuntimeException $e) {
            $this->assertSame('killed', $e->getMessage());
        }
        $progress = $converter->progress('audit_logs');
        $this->assertSame(1200, (int) DB::table('audit_logs_m3_new')->count(), 'three committed batches survived the kill');
        $this->assertSame(5000, $progress['live_max_id'] - (int) DB::table('audit_logs')->min('id') + 1);

        // Writes while nobody copies: recorded by the trigger
        $user = User::factory()->create();
        $written = AuditLog::create(['action' => 'during.copy', 'user_id' => $user->id]);
        $this->assertGreaterThan(0, $converter->progress('audit_logs')['pending_changes']);

        // Resume: the command goes on from the last batch, builds the indexes, then --finish swaps
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--batch' => 1000])->expectsOutputToContain('--finish')->assertSuccessful();
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--finish' => true])->assertSuccessful();

        $manager = app(PartitionManager::class);
        $this->assertTrue($manager->isPartitioned('audit_logs'));
        $this->assertSame('swapped', $converter->state('audit_logs'));
        $this->assertSame(5001, DB::table('audit_logs')->count());
        $this->assertSame(0, (int) DB::selectOne('SELECT count(*) AS n FROM (SELECT row_to_json(o)::text FROM audit_logs_m3_old o EXCEPT SELECT row_to_json(n)::text FROM audit_logs n) d')->n, 'every row of the old table is in the new one, unchanged');
        $this->assertSame('audit_logs_2026_03', DB::selectOne('SELECT tableoid::regclass::text AS p FROM audit_logs ORDER BY id LIMIT 1')->p);
        $this->assertTrue((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'audit_logs_2026_03'::regclass AND tgname = 'audit_logs_append_only') AS e")->e);
        $this->assertSame(['audit_logs_pkey'], array_column(DB::select("SELECT conname FROM pg_constraint WHERE conrelid = 'audit_logs'::regclass AND contype = 'p'"), 'conname'));
        $this->assertTrue((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_class WHERE relname = 'audit_logs_model_index' AND relkind = 'I') AS e")->e, 'the parent index carries the old name');
        $this->assertSame('audit_logs', DB::selectOne("SELECT c.relname FROM pg_depend d JOIN pg_class c ON c.oid = d.refobjid WHERE d.objid = 'audit_logs_id_seq'::regclass AND d.deptype = 'a'")->relname);

        // The status says the old copy is kept; --drop-old waits the 7 days, then drops it
        $this->assertSame('swapped', collect($manager->status()['tables'])->firstWhere('table', 'audit_logs')['conversion']);
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--drop-old' => true])->expectsOutputToContain('kun saqlanadi')->assertFailed();
        $this->travel(8)->days();
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--drop-old' => true])->assertSuccessful();
        $this->assertFalse($manager->exists('audit_logs_m3_old'));
        $this->assertFalse($manager->exists('audit_logs_m3_changes'));
        $this->assertSame('partitioned', $converter->state('audit_logs'));
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM pg_trigger WHERE tgname = 'audit_logs_m3_capture'")->n);
        $this->assertSame('during.copy', AuditLog::find($written->id)?->action);
    }

    public function test_finish_under_a_concurrent_writer_on_a_second_connection_loses_no_row_and_no_update(): void
    {
        DB::statement("INSERT INTO notification_logs (type, status, sent_at) SELECT 'seed', 'sent', timestamp '2026-10-01' + (g || ' minutes')::interval FROM generate_series(1, 3000) g");
        $first = (int) DB::table('notification_logs')->min('id');
        $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--batch' => 500])->assertSuccessful();

        // A second connection (psql) writes until told to stop (a row it polls; hard cap 120 s): each turn one insert
        // and one update of a seed row (every 10th, cycling over 300), committed. It reports how many inserts it made.
        DB::statement('DROP TABLE IF EXISTS m3_writer_control');
        DB::statement('CREATE TABLE m3_writer_control (stop boolean NOT NULL, written int NOT NULL DEFAULT 0, landed_old int NOT NULL DEFAULT 0, last_relation text)');
        DB::table('m3_writer_control')->insert(['stop' => false]);
        // Each turn: one insert (recording the relation the row landed in: after the swap it must never be the renamed
        // old table) and one update of a seed row to a value that changes every turn, committed
        $sql = sprintf(
            "DO \$\$ DECLARE n int := 0; rel text; started timestamptz := clock_timestamp(); BEGIN LOOP "
            . "INSERT INTO notification_logs (type, status, error_message) VALUES ('writer', 'sent', 'w' || n) RETURNING tableoid::regclass::text INTO rel; "
            . "UPDATE notification_logs SET is_read = true, read_at = now(), error_message = 'u' || n WHERE id = %d + (n %% 300) * 10; "
            . "UPDATE m3_writer_control SET written = n + 1, last_relation = rel, landed_old = landed_old + (rel = 'notification_logs_m3_old')::int; COMMIT; n := n + 1; "
            . "EXIT WHEN n >= 300 AND (SELECT stop FROM m3_writer_control); "
            . "IF clock_timestamp() - started > interval '180 seconds' THEN RAISE EXCEPTION 'writer: not stopped within 180 s'; END IF; "
            . "PERFORM pg_sleep(0.002); END LOOP; RAISE NOTICE 'written=%%', n; END \$\$;",
            $first,
        );
        $writer = $this->psql($sql);
        $fate = fn (): string => json_encode(['process' => proc_get_status($writer['process']), 'control' => DB::table('m3_writer_control')->first()]) ?: '';

        try {
            // The switch-over starts once the writer is demonstrably busy...
            $this->waitFor(fn () => DB::table('notification_logs')->where('type', 'writer')->count() >= 100, 'the writer did not start', diagnostics: $fate);
            $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--finish' => true])->assertSuccessful();
            // ...and the writer goes on writing into the new table after the swap
            $oldMax = (int) DB::table('notification_logs_m3_old')->max('id');
            $this->waitFor(fn () => DB::table('notification_logs')->where('type', 'writer')->where('id', '>', $oldMax)->count() >= 100, 'the writer did not write after the swap', diagnostics: $fate);
        } finally {
            DB::table('m3_writer_control')->update(['stop' => true]);
            $exit = $this->wait($writer);
            $control = DB::table('m3_writer_control')->first();
            DB::statement('DROP TABLE IF EXISTS m3_writer_control');
        }
        $this->assertSame(0, $exit['code'], 'the writer never got an error: ' . $exit['stderr']);
        $this->assertSame(1, preg_match('/written=(\d+)/', $exit['stderr'], $m), $exit['stderr']);
        $writes = (int) $m[1];
        $this->assertSame($writes, (int) $control->written);
        $this->assertSame(0, (int) $control->landed_old, 'no writer row landed in the renamed old table');
        $this->assertStringStartsWith('notification_logs_20', (string) $control->last_relation, 'the last rows went to a month of the new table');
        $this->assertSame(0, (int) DB::table('notification_logs_m3_old')->where('type', 'writer')->where('id', '>', $oldMax)->count());

        $this->assertTrue(app(PartitionManager::class)->isPartitioned('notification_logs'));
        $inOld = (int) DB::table('notification_logs_m3_old')->where('type', 'writer')->count();
        $afterSwap = (int) DB::table('notification_logs')->where('type', 'writer')->where('id', '>', $oldMax)->count();
        $this->assertGreaterThan(0, $inOld, 'the writer wrote before the swap');
        $this->assertGreaterThan(0, $afterSwap, 'and after it: the swap happened while it was writing');
        $this->assertSame($writes, (int) DB::table('notification_logs')->where('type', 'writer')->count(), 'no insert lost');
        $this->assertSame($inOld, (int) DB::table('notification_logs')->where('type', 'writer')->where('id', '<=', $oldMax)->count(), 'every pre-swap insert is in the new table');
        $this->assertSame(3000 + $writes, DB::table('notification_logs')->count());
        $this->assertSame(300, (int) DB::table('notification_logs')->where('type', 'seed')->where('is_read', true)->count(), 'no update lost');
        // The last value each updated seed row got is the one the writer wrote last for it
        $last = DB::table('notification_logs')->where('type', 'seed')->where('is_read', true)->pluck('error_message', 'id');
        foreach ($last as $id => $value) {
            $turn = (int) substr((string) $value, 1);
            $this->assertSame($id, $first + ($turn % 300) * 10);
            $this->assertGreaterThan($writes - 301, $turn, "seed row {$id} kept its last update");
        }
        $this->assertSame(1, count(array_unique(DB::table('notification_logs')->pluck('id')->countBy()->values()->all())), 'no id twice');
    }

    public function test_an_update_left_uncommitted_across_the_catch_up_is_not_lost(): void
    {
        DB::statement("INSERT INTO notification_logs (type, status, sent_at) SELECT 'seed', 'sent', timestamp '2026-10-01' + (g || ' minutes')::interval FROM generate_series(1, 500) g");
        $id = (int) DB::table('notification_logs')->min('id') + 5;
        $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--batch' => 100])->assertSuccessful();

        // Committed: the change log knows the id
        DB::table('notification_logs')->where('id', $id)->update(['error_message' => 'v1']);
        // A second session changes the same row and keeps its transaction open while the catch-up runs
        DB::statement('DROP TABLE IF EXISTS m3_gate');
        DB::statement('CREATE TABLE m3_gate (go boolean NOT NULL)');
        DB::table('m3_gate')->insert(['go' => false]);
        $writer = $this->psql("BEGIN; UPDATE notification_logs SET error_message = 'v2', is_read = true WHERE id = {$id}; SELECT pg_advisory_lock(424242); "
            . "DO \$\$ BEGIN WHILE NOT (SELECT go FROM m3_gate) LOOP PERFORM pg_sleep(0.02); END LOOP; END \$\$; COMMIT;");
        try {
            $this->waitFor(fn () => (bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_locks WHERE locktype = 'advisory' AND objid = 424242 AND granted) AS e")->e, 'the second session did not update');
            $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--batch' => 100])->assertSuccessful();
            $this->assertSame('v1', DB::table('notification_logs_m3_new')->where('id', $id)->value('error_message'), 'the catch-up saw the committed version only');
        } finally {
            DB::table('m3_gate')->update(['go' => true]);
            $exit = $this->wait($writer);
            DB::statement('DROP TABLE IF EXISTS m3_gate');
        }
        $this->assertSame(0, $exit['code'], $exit['stderr']);

        $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--finish' => true])->assertSuccessful();
        $row = DB::table('notification_logs')->where('id', $id)->first();
        $this->assertSame('v2', $row->error_message, 'the update committed after the catch-up is in the new table');
        $this->assertTrue((bool) $row->is_read);
    }

    public function test_drop_old_compares_content_and_refuses_on_a_difference_naming_the_ids(): void
    {
        DB::statement("INSERT INTO notification_logs (type, status, sent_at) SELECT 'seed', 'sent', timestamp '2026-10-01' + (g || ' minutes')::interval FROM generate_series(1, 400) g");
        $this->artisan('db:partitions:convert', ['table' => 'notification_logs'])->assertSuccessful();
        $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--finish' => true])->assertSuccessful();
        $id = (int) DB::table('notification_logs')->min('id') + 7;
        // Same row count, different content (what a lost update looks like)
        DB::statement("UPDATE notification_logs_m3_old SET error_message = 'stale' WHERE id = {$id}");

        $this->travel(8)->days();
        $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--drop-old' => true])
            ->expectsOutputToContain((string) $id)
            ->assertFailed();
        $this->assertTrue(app(PartitionManager::class)->exists('notification_logs_m3_old'), 'never dropped on a content mismatch');

        DB::statement("UPDATE notification_logs_m3_old SET error_message = NULL WHERE id = {$id}");
        $this->artisan('db:partitions:convert', ['table' => 'notification_logs', '--drop-old' => true])->assertSuccessful();
        $this->assertFalse(app(PartitionManager::class)->exists('notification_logs_m3_old'));
    }

    public function test_rollback_copies_back_what_was_written_after_the_swap(): void
    {
        DB::statement("INSERT INTO audit_logs (action, created_at) SELECT 'seed.' || g, timestamp '2026-11-01' + (g || ' minutes')::interval FROM generate_series(1, 800) g");
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--batch' => 300])->assertSuccessful();
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--finish' => true])->assertSuccessful();

        $user = User::factory()->create();
        $after = AuditLog::create(['action' => 'after.swap', 'user_id' => $user->id]);
        $nulled = AuditLog::create(['action' => 'after.swap.nulled', 'user_id' => User::factory()->create()->id]);
        DB::table('users')->where('id', $nulled->user_id)->delete();

        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--rollback' => true])->assertSuccessful();

        $converter = app(PartitionConverter::class);
        $this->assertSame('plain', $converter->state('audit_logs'));
        $this->assertFalse(app(PartitionManager::class)->exists('audit_logs_m3_new'));
        $this->assertFalse(app(PartitionManager::class)->exists('audit_logs_2026_11'));
        $this->assertSame(802, DB::table('audit_logs')->count());
        $this->assertSame($user->id, (int) DB::table('audit_logs')->where('id', $after->id)->value('user_id'));
        $this->assertNull(DB::table('audit_logs')->where('id', $nulled->id)->value('user_id'));
        $this->assertSame('PRIMARY KEY (id)', DB::selectOne("SELECT pg_get_constraintdef(oid) AS d FROM pg_constraint WHERE conrelid = 'audit_logs'::regclass AND contype = 'p'")->d);
        $this->assertTrue((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'audit_logs'::regclass AND tgname = 'audit_logs_append_only' AND tgenabled = 'O') AS e")->e, 'the plain table is append-only again');
        $this->assertGreaterThan($nulled->id, AuditLog::create(['action' => 'after.rollback'])->id);

        // And it can be converted again from scratch
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs'])->assertSuccessful();
        $this->artisan('db:partitions:convert', ['table' => 'audit_logs', '--finish' => true])->assertSuccessful();
        $this->assertSame(803, DB::table('audit_logs')->count());
    }

    private function waitFor(callable $condition, string $message, int $seconds = 60, ?callable $diagnostics = null): void
    {
        $deadline = microtime(true) + $seconds;
        while (!$condition()) {
            if (microtime(true) > $deadline) {
                $this->fail($message . " within {$seconds} s" . ($diagnostics === null ? '' : ': ' . $diagnostics()));
            }
            usleep(20_000);
        }
    }

    /**
     * Back to the migrated state: leftovers of a conversion removed, the tables partitioned and empty.
     */
    private function restore(): void
    {
        $manager = app(PartitionManager::class);
        foreach (self::TABLES as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_m3_capture ON {$table}");
            foreach (['_m3_new', '_m3_old', '_m3_changes'] as $suffix) {
                DB::statement("DROP TABLE IF EXISTS {$table}{$suffix}");
            }
            if ($manager->relkind($table) === 'r') {
                DB::statement("TRUNCATE {$table}");
                app(PartitionConverter::class)->convertInline($table);
            }
            DB::statement("TRUNCATE {$table}");
        }
        // Users these tests created (committed: no transaction rolls them back)
        DB::table('users')->where('id', '>', $this->usersBefore)->delete();
    }

    /**
     * @return array{process: resource, pipes: array<int, resource>}
     */
    private function psql(string $sql): array
    {
        $db = config('database.connections.pgsql');
        $process = proc_open(
            ['psql', '-X', '-q', '-v', 'ON_ERROR_STOP=1', '-h', (string) $db['host'], '-p', (string) $db['port'], '-U', (string) $db['username'], '-d', (string) $db['database'], '-c', $sql],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PGPASSWORD' => (string) $db['password'], 'PATH' => (string) getenv('PATH')],
        );
        $this->assertIsResource($process);

        return ['process' => $process, 'pipes' => $pipes];
    }

    /**
     * @param  array{process: resource, pipes: array<int, resource>}  $writer
     * @return array{code: int, stderr: string}
     */
    private function wait(array $writer): array
    {
        $stderr = (string) stream_get_contents($writer['pipes'][2]);
        stream_get_contents($writer['pipes'][1]);

        return ['code' => proc_close($writer['process']), 'stderr' => $stderr];
    }
}
