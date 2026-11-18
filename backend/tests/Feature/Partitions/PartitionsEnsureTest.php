<?php

namespace Tests\Feature\Partitions;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Partitions\PartitionManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wave M3: the monthly partitions of the journal tables (migration 2026_11_25_100000, db:partitions:ensure/status).
 * DDL is transactional in Postgres: every partition created here goes away with the test's transaction.
 */
class PartitionsEnsureTest extends TestCase
{
    // M4: does DDL itself (tests/Concerns/UsesOwnerConnection.php): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;

    public function test_the_migration_partitions_the_three_journals_with_months_ahead_and_a_default(): void
    {
        $manager = app(PartitionManager::class);
        foreach (['audit_logs', 'fiscal_receipts', 'notification_logs'] as $table) {
            $this->assertTrue($manager->isPartitioned($table), "{$table} is partitioned");
            $names = array_column($manager->partitions($table), 'name');
            $this->assertContains("{$table}_default", $names);
            $this->assertContains("{$table}_2026_01", $names);
            $this->assertContains(PartitionManager::monthName($table, PartitionManager::monthStart(now())->addMonths(3)), $names);
        }

        $pk = DB::selectOne("SELECT pg_get_constraintdef(oid) AS d FROM pg_constraint WHERE conrelid = 'audit_logs'::regclass AND contype = 'p'")->d;
        $this->assertSame('PRIMARY KEY (id, created_at)', $pk);
        $this->assertFalse((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fiscal_receipts_original_receipt_id_foreign') AS e")->e, 'the fiscal self foreign key is gone');
        $this->assertSame('NO', DB::selectOne("SELECT is_nullable FROM information_schema.columns WHERE table_name = 'fiscal_receipts' AND column_name = 'created_at'")->is_nullable);
    }

    public function test_rollover_with_time_travel_and_the_default_partition_catching_a_row_that_ensure_moves_out(): void
    {
        $this->travelTo('2027-02-27 12:00:00');
        $this->artisan('db:partitions:ensure')->assertSuccessful();

        foreach (['2027_02', '2027_03', '2027_04', '2027_05'] as $month) {
            $this->assertTrue($this->inherits('audit_logs', "audit_logs_{$month}"), "audit_logs_{$month} attached");
        }
        $this->assertFalse($this->inherits('audit_logs', 'audit_logs_2027_06'));

        $inFebruary = AuditLog::create(['action' => 'partitions.test']);
        $this->assertSame('audit_logs_2027_02', $this->partitionOf('audit_logs', $inFebruary->id));

        // Past the horizon: the insert does not fail, the row waits in DEFAULT and the status says so
        $this->travelTo('2027-07-10 08:00:00');
        $late = AuditLog::create(['action' => 'partitions.test']);
        $this->assertSame('audit_logs_default', $this->partitionOf('audit_logs', $late->id));
        $status = app(PartitionManager::class)->status();
        $this->assertFalse($status['ok']);
        $codes = array_map(fn (array $p) => $p['table'] . ':' . $p['code'], $status['problems']);
        $this->assertContains('audit_logs:default_rows', $codes);
        $this->assertContains('audit_logs:future_months_low', $codes);

        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $this->assertSame('audit_logs_2027_07', $this->partitionOf('audit_logs', $late->id));
        $this->assertSame(0, (int) DB::selectOne('SELECT count(*) AS n FROM audit_logs_default')->n);
        // The append-only trigger is cloned to the months ensure attaches
        $this->assertTrue((bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = 'audit_logs_2027_10'::regclass AND tgname = 'audit_logs_append_only') AS e")->e);

        $after = app(PartitionManager::class)->status();
        $this->assertSame([], array_values(array_filter($after['problems'], fn (array $p) => in_array($p['code'], ['default_rows', 'future_months_low'], true))));
        $audit = collect($after['tables'])->firstWhere('table', 'audit_logs');
        $this->assertSame(3, $audit['future_months']);
        $this->assertSame(0, $audit['default_rows']);
    }

    public function test_ensure_is_idempotent_and_never_recreates_an_archived_month(): void
    {
        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $before = array_column(app(PartitionManager::class)->partitions('notification_logs'), 'name');
        $report = app(PartitionManager::class)->ensure('notification_logs');
        $this->assertSame([], $report['created']);

        // A month the archive took: gone from the table and catalogued: a late row of that month stays in DEFAULT
        DB::statement('ALTER TABLE notification_logs DETACH PARTITION notification_logs_2026_02');
        DB::statement('DROP TABLE notification_logs_2026_02');
        DB::table('partition_archives')->insert(['table_name' => 'notification_logs', 'partition_name' => 'notification_logs_2026_02', 'range_from' => '2026-02-01', 'range_to' => '2026-03-01', 'status' => 'dropped', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('notification_logs')->insert(['type' => 't', 'status' => 'sent', 'sent_at' => '2026-02-10 10:00:00']);

        $report = app(PartitionManager::class)->ensure('notification_logs');
        $this->assertSame(['notification_logs_2026_02'], $report['skipped']);
        $this->assertNotContains('notification_logs_2026_02', array_column(app(PartitionManager::class)->partitions('notification_logs'), 'name'));
        $this->assertCount(count($before) - 1, app(PartitionManager::class)->partitions('notification_logs'));
    }

    public function test_audit_partitions_stay_append_only_and_a_user_delete_still_sets_null(): void
    {
        $user = User::factory()->create();
        $entry = AuditLog::create(['action' => 'partitions.append_only', 'user_id' => $user->id]);

        try {
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['action' => 'changed']));
            $this->fail('an update of an audit entry must be refused');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        try {
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $entry->id)->delete());
            $this->fail('a delete of an audit entry must be refused');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        DB::table('users')->where('id', $user->id)->delete();
        $this->assertNull(DB::table('audit_logs')->where('id', $entry->id)->value('user_id'));
        $this->assertSame('partitions.append_only', DB::table('audit_logs')->where('id', $entry->id)->value('action'));
    }

    public function test_status_json_and_the_readable_table(): void
    {
        Artisan::call('db:partitions:status', ['--json' => true]);
        $json = json_decode(Artisan::output(), true);
        $this->assertIsArray($json);
        $this->assertSame(['generated_at', 'ok', 'problems', 'tables', 'archives'], array_keys($json));
        $this->assertTrue($json['ok'], json_encode($json['problems']));
        $this->assertSame(['audit_logs', 'fiscal_receipts', 'notification_logs'], array_column($json['tables'], 'table'));
        $this->assertSame([
            'table', 'column', 'partitioned', 'online_months', 'archive', 'partitions', 'oldest_month', 'newest_month',
            'current_month_attached', 'future_months', 'default_rows', 'due_for_archive', 'bytes', 'missing_triggers',
        ], array_keys($json['tables'][0]));
        $this->assertSame(3, $json['tables'][0]['future_months']);
        $this->assertSame(['detached', 'uploaded', 'verified', 'dropped', 'failed', 'held', 'stale_detached', 'oldest_detached_at', 'local_only_allowed'], array_keys($json['archives']));

        $this->artisan('db:partitions:status')->expectsOutputToContain('Hammasi joyida.')->assertSuccessful();
    }

    private function inherits(string $parent, string $child): bool
    {
        return (bool) DB::selectOne('SELECT EXISTS (SELECT 1 FROM pg_inherits WHERE inhparent = ?::regclass AND inhrelid = to_regclass(?)) AS e', [$parent, $child])->e;
    }

    private function partitionOf(string $table, int $id): string
    {
        return (string) DB::selectOne("SELECT tableoid::regclass::text AS p FROM {$table} WHERE id = ?", [$id])->p;
    }
}
