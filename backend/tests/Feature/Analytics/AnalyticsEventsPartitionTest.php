<?php

namespace Tests\Feature\Analytics;

use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave P, step 2 (docs/ANALYTICS_PLAN.md A.3, 7-bo'lim 1-savol): analytics_events is created partitioned by month of
 * received_at (never converted), with a DEFAULT partition; db:partitions:ensure keeps the next months attached and
 * db:partitions:archive drops the months past the retention (ANALYTICS_EVENTS_MONTHS, 3) without an archive copy.
 * Months older than the retention are never created. DDL is transactional: whatever a test creates goes with it.
 */
class AnalyticsEventsPartitionTest extends TestCase
{
    // M4: does DDL itself (attach, detach, drop, the migration's down/up): the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use RefreshDatabase;

    private function months(): array
    {
        return array_column(array_filter(app(PartitionManager::class)->partitions('analytics_events'), fn (array $p) => !$p['default']), 'name');
    }

    private function insertAt(string $receivedAt): int
    {
        return (int) DB::table('analytics_events')->insertGetId([
            'received_at' => $receivedAt, 'occurred_at' => $receivedAt, 'session_id' => (string) Str::uuid(), 'event_uid' => (string) Str::uuid(),
            'user_id' => null, 'event' => 1, 'source' => 1, 'platform' => 0,
        ]);
    }

    public function test_the_table_is_partitioned_by_received_at_with_the_online_months_ahead_and_a_default(): void
    {
        $manager = app(PartitionManager::class);
        $this->assertTrue($manager->isPartitioned('analytics_events'));
        $names = array_column($manager->partitions('analytics_events'), 'name');
        $this->assertContains('analytics_events_default', $names);

        $current = PartitionManager::monthStart(now());
        $expected = [];
        for ($month = $current->subMonths(2); $month <= $current->addMonths(3); $month = $month->addMonth()) {
            $expected[] = PartitionManager::monthName('analytics_events', $month);
        }
        $this->assertSame($expected, $this->months(), 'the retention window (3 months) back, 3 ahead; nothing from partitions.first_month');

        $this->assertSame('PRIMARY KEY (id, received_at)', DB::selectOne("SELECT pg_get_constraintdef(oid) AS d FROM pg_constraint WHERE conrelid = 'analytics_events'::regclass AND contype = 'p'")->d);
        $this->assertSame('RANGE (received_at)', DB::selectOne("SELECT pg_get_partkeydef('analytics_events'::regclass) AS d")->d);
        $indexes = collect(DB::select("SELECT indexdef FROM pg_indexes WHERE tablename = 'analytics_events'"))->pluck('indexdef')->implode("\n");
        $this->assertStringContainsString('USING brin (received_at)', $indexes);
        $this->assertStringContainsString('USING btree (session_id)', $indexes);
        // No foreign key on a partitioned journal (M3 rule) and no row-level security (NOT_UNDER_RLS: MIXED)
        $this->assertSame(0, (int) DB::selectOne("SELECT count(*) AS n FROM pg_constraint WHERE conrelid = 'analytics_events'::regclass AND contype = 'f'")->n);
        $this->assertFalse((bool) DB::selectOne("SELECT relrowsecurity AS r FROM pg_class WHERE oid = 'analytics_events'::regclass")->r);
        // A month ensure attaches gets the parent's indexes
        $month = PartitionManager::monthName('analytics_events', $current);
        $this->assertSame(2, (int) DB::selectOne('SELECT count(*) AS n FROM pg_indexes WHERE tablename = ? AND indexdef NOT LIKE ?', [$month, '%_pkey%'])->n);
    }

    public function test_ensure_attaches_new_months_and_the_archive_drops_the_expired_ones_without_a_copy(): void
    {
        $kept = $this->insertAt(now()->format('Y-m-d H:i:s'));
        $oldMonth = PartitionManager::monthStart(now())->subMonths(2);
        $old = $this->insertAt($oldMonth->addDays(3)->format('Y-m-d H:i:s'));

        // Two months on: ensure attaches the new months, the archive takes the months past the retention (3 months,
        // counting the current one): the month two back and older go, this month stays
        $this->travelTo(PartitionManager::monthStart(now())->addMonths(2)->addDays(1)->setTime(22, 40));
        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $this->assertContains(PartitionManager::monthName('analytics_events', PartitionManager::monthStart(now())->addMonths(3)), $this->months());

        $this->artisan('db:partitions:archive')->assertSuccessful();

        $cutoff = PartitionManager::monthStart(now())->subMonths(2);
        foreach ($this->months() as $name) {
            $this->assertGreaterThanOrEqual($cutoff->format('Y_m'), substr($name, -7), "{$name} is within the retention");
        }
        $this->assertFalse(app(PartitionManager::class)->exists(PartitionManager::monthName('analytics_events', $oldMonth)), 'dropped, not kept detached');
        $this->assertSame('dropped', DB::table('partition_archives')->where('partition_name', PartitionManager::monthName('analytics_events', $oldMonth))->value('status'));
        $this->assertFalse(DB::table('analytics_events')->where('id', $old)->exists());
        $this->assertSame(0, DB::table('partition_archives')->where('table_name', 'analytics_events')->whereNotIn('status', ['dropped'])->count(), 'no archive copy is made of analytics');
        $this->assertTrue(DB::table('analytics_events')->where('id', $kept)->exists(), 'the month inside the retention keeps its rows');

        $status = app(PartitionManager::class)->status();
        $row = collect($status['tables'])->firstWhere('table', 'analytics_events');
        $this->assertEquals(['partitioned' => true, 'online_months' => 3, 'archive' => false, 'due_for_archive' => 0, 'default_rows' => 0], array_intersect_key($row, array_flip(['partitioned', 'online_months', 'archive', 'due_for_archive', 'default_rows'])));
        $this->assertSame([], array_values(array_filter($status['problems'], fn (array $p) => $p['table'] === 'analytics_events')));
    }

    public function test_ensure_moves_a_row_out_of_default_into_its_month(): void
    {
        $this->travelTo(PartitionManager::monthStart(now())->addMonths(5)->addDays(2));
        $id = $this->insertAt(now()->format('Y-m-d H:i:s'));
        $this->assertSame('analytics_events_default', DB::selectOne('SELECT tableoid::regclass::text AS p FROM analytics_events WHERE id = ?', [$id])->p);

        $this->artisan('db:partitions:ensure')->assertSuccessful();
        $this->assertSame(PartitionManager::monthName('analytics_events', PartitionManager::monthStart(now())), DB::selectOne('SELECT tableoid::regclass::text AS p FROM analytics_events WHERE id = ?', [$id])->p);
    }

    #[\Tests\Concerns\AsSchemaOwner]
    public function test_the_migration_goes_down_and_up_again(): void
    {
        $migration = require database_path('migrations/2026_12_08_100000_create_analytics_events_table.php');
        $manager = app(PartitionManager::class);

        $migration->down();
        $this->assertFalse($manager->exists('analytics_events'));
        $this->assertFalse($manager->exists('analytics_events_default'));
        $this->assertFalse($manager->exists(PartitionManager::monthName('analytics_events', PartitionManager::monthStart(now()))));

        $migration->up();
        $this->assertTrue($manager->isPartitioned('analytics_events'));
        $this->assertCount(6, $this->months());
        $this->insertAt(now()->format('Y-m-d H:i:s'));
        $this->assertSame(1, DB::table('analytics_events')->count());
    }
}
