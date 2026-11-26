<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\AnalyticsErasure;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Partitions\PartitionManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\AccessFixtures;
use Tests\TestCase;

/**
 * A privacy request (docs/OPERATIONS.md "Maxfiylik so'rovlari: statistikani o'chirish"): AnalyticsErasure::forget()
 * deletes every analytics_events row of one user, in every month and DEFAULT, with one DELETE of the parent table by
 * user_id (each partition read by its user_id index), and writes nothing else. The admin card action and the operator
 * command `analytics:forget-user` both use it. Events are never anonymous: every stored row carries the token's user.
 */
class AnalyticsForgetUserTest extends TestCase
{
    use AccessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        $this->customer->refresh();
        config(['analytics.enabled' => true]);
    }

    private function insertFor(int $userId, string $receivedAt): void
    {
        DB::table('analytics_events')->insert([
            'received_at' => $receivedAt, 'occurred_at' => $receivedAt, 'session_id' => (string) Str::uuid(), 'event_uid' => (string) Str::uuid(),
            'user_id' => $userId, 'event' => 1, 'source' => 1, 'platform' => 0,
        ]);
    }

    /** Rows of the user in this month, the month two back (the oldest attached) and a far month (DEFAULT) */
    private function seedAcrossPartitions(int $userId): void
    {
        $month = PartitionManager::monthStart(now());
        $this->insertFor($userId, now()->format('Y-m-d H:i:s'));
        $this->insertFor($userId, $month->subMonths(2)->addDays(3)->format('Y-m-d H:i:s'));
        $this->insertFor($userId, $month->addMonths(8)->addDays(1)->format('Y-m-d H:i:s'));
    }

    private function rowsOf(int $userId): int
    {
        return DB::table('analytics_events')->where('user_id', $userId)->count();
    }

    public function test_forget_deletes_every_month_of_one_user_by_the_user_index_and_writes_nothing_else(): void
    {
        $this->seedAcrossPartitions($this->customer->id);
        $other = $this->makeCustomer();
        $this->seedAcrossPartitions($other->id);
        $this->assertSame('analytics_events_default', DB::selectOne('SELECT tableoid::regclass::text AS p FROM analytics_events WHERE user_id = ? ORDER BY received_at DESC LIMIT 1', [$this->customer->id])->p);
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query;
        });

        $this->assertSame(3, app(AnalyticsErasure::class)->forget($this->customer->id));

        $writes = array_values(array_filter($queries, fn (QueryExecuted $q) => preg_match('/^\s*(insert|update|delete)\b/i', $q->sql) === 1));
        $this->assertCount(1, $writes);
        $this->assertMatchesRegularExpression('/^delete from "analytics_events" where "user_id" = \?$/i', $writes[0]->sql);
        $this->assertSame(0, $this->rowsOf($this->customer->id), 'every month, DEFAULT included');
        $this->assertSame(3, $this->rowsOf($other->id), 'nobody else\'s rows');
        $this->assertSame(0, app(AnalyticsErasure::class)->forget($this->customer->id), 'again: nothing left');

        // Not pruned by month (a user's rows are in every month): each partition, DEFAULT included, by its user_id index
        DB::statement('SET LOCAL enable_seqscan = off');
        $plan = collect(DB::select('EXPLAIN DELETE FROM analytics_events WHERE user_id = ?', [$this->customer->id]))->pluck('QUERY PLAN')->implode("\n");
        $this->assertDoesNotMatchRegularExpression('/Seq Scan/', $plan);
        $probes = preg_match_all('/(?:Bitmap Index Scan on|Index (?:Only )?Scan using) analytics_events_\w+?_user_id_(?:index|idx)/', $plan);
        $this->assertSame(count(app(PartitionManager::class)->partitions('analytics_events')), $probes, $plan);
        // No row-level security on it (not a tenant table: TenantRlsInventoryTest MIXED): no tenant role is needed
        $this->assertFalse((bool) DB::selectOne("SELECT relrowsecurity AS r FROM pg_class WHERE oid = 'analytics_events'::regclass")->r);
    }

    public function test_events_are_never_anonymous(): void
    {
        // The ingestion only ever runs for a token's user: a call without one is refused, so a user's rows are all of
        // their rows (no row with user_id NULL is written by it)
        $batch = ['session_id' => (string) Str::uuid(), 'platform' => 'android', 'events' => [['uid' => (string) Str::uuid(), 'name' => 'app_open', 'at' => (int) now()->getTimestampMs()]]];
        $this->postJson('/api/v1/analytics/events', $batch)->assertUnauthorized();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/analytics/events', $batch)->assertStatus(202)->assertJsonPath('data.accepted', 1);
        $this->assertSame(0, DB::table('analytics_events')->whereNull('user_id')->count());
        $this->assertSame(1, app(AnalyticsErasure::class)->forget($this->customer->id));
    }

    public function test_the_operator_command_forgets_a_user_prints_the_count_and_is_audited(): void
    {
        $this->seedAcrossPartitions($this->customer->id);
        $other = $this->makeCustomer();
        $this->insertFor($other->id, now()->format('Y-m-d H:i:s'));

        $this->artisan('analytics:forget-user', ['user' => (string) $this->customer->id, '--reason' => "Mijoz so'rovi #12"])
            ->expectsOutputToContain("3 ta statistika yozuvi o'chirildi")
            ->assertSuccessful();

        $this->assertSame(0, $this->rowsOf($this->customer->id));
        $this->assertSame(1, $this->rowsOf($other->id));
        $entry = AuditLog::where('action', 'cli_analytics_forgotten')->sole();
        $this->assertSame(User::class, $entry->model_type);
        $this->assertSame($this->customer->id, (int) $entry->model_id);
        $this->assertSame(3, $entry->payload_json['analytics_events_deleted']);
        $this->assertSame("Mijoz so'rovi #12", $entry->payload_json['reason']);

        $this->artisan('analytics:forget-user', ['user' => '999999999'])->assertFailed();
        $this->artisan('analytics:forget-user', ['user' => 'abc'])->assertFailed();
        $this->assertSame(1, AuditLog::where('action', 'cli_analytics_forgotten')->count());
    }
}
