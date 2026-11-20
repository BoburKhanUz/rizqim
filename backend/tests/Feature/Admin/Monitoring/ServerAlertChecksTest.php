<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Domain\Realtime\Health\RealtimeHealth;
use App\Jobs\QueueHeartbeatJob;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\PostgresStats;
use App\Services\Admin\Monitoring\RedisStats;
use App\Services\Admin\Monitoring\TlsProbe;
use App\Support\HealthReport;
use App\Support\Partitions\PartitionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * M3 (docs/scale/M3_DESIGN.md section 7): the 13 server-level alert kinds. Each is driven into firing and back to
 * resolved; a check that cannot measure leaves its alerts alone ("unknown", never "ok"); with PITR off the PITR
 * kinds stay quiet.
 */
class ServerAlertChecksTest extends TestCase
{
    use MonitoringFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
        config(['monitoring.pitr.enabled' => false]);
    }

    protected function tearDown(): void
    {
        DB::purge('probe');

        parent::tearDown();
    }

    private function run_(): array
    {
        return app(AlertEvaluator::class)->run();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Alert>
     */
    private function unresolved(AlertKind $kind)
    {
        return Alert::query()->where('kind', $kind->value)->unresolved()->orderBy('id')->get();
    }

    private function subjects(AlertKind $kind): array
    {
        return $this->unresolved($kind)->pluck('subject_key')->sort()->values()->all();
    }

    /** A rule that has existed long enough for its grace period to be over. */
    private function oldRule(AlertKind $kind, array $attributes = []): AlertRule
    {
        $rule = $this->onlyRule($kind, $attributes);
        DB::table('alert_rules')->where('id', $rule->id)->update(['created_at' => now()->subDays(400)]);

        return $rule->refresh();
    }

    private function marker(string $key, array $meta, ?Carbon $at = null): void
    {
        MonitoringMarker::query()->updateOrCreate(['key' => $key], ['recorded_at' => $at ?? now(), 'meta' => $meta]);
    }

    private function pitrMeta(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true, 'stanza' => 'rizqim', 'status' => 'ok', 'message' => null, 'repo2' => 'on', 'repo2_note' => null,
            'backups' => 3,
            'last_full_at' => now()->subDays(2)->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_backup_at' => now()->subHours(2)->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_backup_type' => 'diff', 'last_backup_label' => '20261117-213000F_20261118-213000D',
            'repos' => [['key' => 1, 'status' => 'ok', 'message' => null], ['key' => 2, 'status' => 'ok', 'message' => null]],
            'archived_wal' => '000000010000000000000042', 'archived_at' => now()->subMinute()->utc()->format('Y-m-d\TH:i:s\Z'),
            'archive_failing' => false, 'ready_wal' => 0, 'outage_since' => null,
        ], $overrides);
    }

    /** A second connection to the test database (outside the test's transaction). */
    private function probe(): \Illuminate\Database\Connection
    {
        config(['database.connections.probe' => config('database.connections.' . config('database.default'))]);

        return DB::connection('probe');
    }

    /** @param  array<string, mixed>  $methods  PostgresStats method => answer */
    private function fakeStats(array $methods): void
    {
        $this->app->instance(PostgresStats::class, new class($methods) extends PostgresStats
        {
            public function __construct(public array $answers) {}

            public function archiver(): ?array
            {
                return array_key_exists('archiver', $this->answers) ? $this->answers['archiver'] : parent::archiver();
            }

            public function tableStats(array $tables): ?array
            {
                return array_key_exists('tableStats', $this->answers) ? $this->answers['tableStats'] : parent::tableStats($tables);
            }
        });
    }

    // --- db_connections ---------------------------------------------------------------------------------------------

    public function test_db_connections_fires_at_its_share_of_max_connections_and_resolves(): void
    {
        $rule = $this->onlyRule(AlertKind::DB_CONNECTIONS);
        $rule->forceFill(['threshold' => 1])->save(); // this very session is >= 1 % of max_connections

        $this->run_();
        $alert = $this->unresolved(AlertKind::DB_CONNECTIONS)->firstWhere('subject_key', 'server');
        $this->assertNotNull($alert);
        $this->assertSame('critical', $alert->severity);
        $this->assertMatchesRegularExpression('/^Bazaga \d+ ta ulanish ochiq/', $alert->title);
        $this->assertGreaterThan(0, $alert->details['max']);

        $rule->forceFill(['threshold' => 100])->save();
        config(['monitoring.postgres.pgbouncer_max_connections' => 100000]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::DB_CONNECTIONS));
    }

    // --- db_long_transaction ----------------------------------------------------------------------------------------

    public function test_db_long_transaction_sees_an_idle_open_transaction_and_ignores_the_backup_tools(): void
    {
        $rule = $this->onlyRule(AlertKind::DB_LONG_TRANSACTION);
        $rule->forceFill(['threshold' => 3600, 'threshold_secondary' => 0])->save();
        $probe = $this->probe();
        $probe->statement("SET application_name = 'pg_dump'");
        $probe->beginTransaction();
        $probe->select('SELECT 1');

        // The nightly pg_dump is one long transaction by design
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::DB_LONG_TRANSACTION));

        $probe->rollBack();
        $probe->statement("SET application_name = 'forgotten-begin'");
        $probe->beginTransaction();
        $probe->select('SELECT 1');
        $this->run_();
        $alert = $this->unresolved(AlertKind::DB_LONG_TRANSACTION)->sole();
        $this->assertSame('idle', $alert->subject_key);
        $this->assertSame('warning', $alert->severity);
        $this->assertStringContainsString('ochiq qolib, hech narsa qilmayapti', $alert->title);
        $this->assertSame('forgotten-begin', $alert->details['sessions'][0]['application']);

        $probe->rollBack();
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::DB_LONG_TRANSACTION));
    }

    // --- db_lock_waits ----------------------------------------------------------------------------------------------

    public function test_db_lock_waits_counts_sessions_waiting_on_a_lock(): void
    {
        $rule = $this->onlyRule(AlertKind::DB_LOCK_WAITS);
        $rule->forceFill(['threshold' => 1, 'threshold_secondary' => 0])->save();

        $holder = $this->probe();
        $holder->beginTransaction();
        $holder->statement('LOCK TABLE partition_archives IN ACCESS EXCLUSIVE MODE');

        $c = config('database.connections.' . config('database.default'));
        $waiter = pg_connect(sprintf("host=%s port=%s dbname=%s user=%s password=%s connect_timeout=5", $c['host'], $c['port'], $c['database'], $c['username'], $c['password']), PGSQL_CONNECT_FORCE_NEW);
        $this->assertNotFalse($waiter);
        pg_send_query($waiter, "SET lock_timeout = '20s'; LOCK TABLE partition_archives IN ACCESS SHARE MODE");

        try {
            $waited = false;
            for ($i = 0; $i < 50 && !$waited; $i++) {
                usleep(100_000);
                $waited = (int) $holder->selectOne("SELECT count(*) AS n FROM pg_stat_activity WHERE datname = current_database() AND wait_event_type = 'Lock'")->n > 0;
            }
            $this->assertTrue($waited, 'the second session waits on the lock');

            $this->run_();
            $alert = $this->unresolved(AlertKind::DB_LOCK_WAITS)->sole();
            $this->assertStringContainsString("qulf kutmoqda", $alert->title);
            $this->assertGreaterThanOrEqual(1, $alert->details['count']);
        } finally {
            $holder->rollBack();
            while (pg_get_result($waiter) !== false) {
                // drain the waiter's results once the lock is released
            }
            pg_close($waiter);
        }

        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::DB_LOCK_WAITS));
    }

    // --- wal_archive ------------------------------------------------------------------------------------------------

    public function test_wal_archive_is_quiet_with_pitr_off_and_fires_on_failure_backlog_and_archive_mode_off(): void
    {
        $this->onlyRule(AlertKind::WAL_ARCHIVE);
        $healthy = ['archive_mode' => 'on', 'last_archived_wal' => 'A', 'last_archived_at' => now()->toIso8601ZuluString(), 'last_failed_wal' => null, 'last_failed_at' => null, 'failed_count' => 0, 'failing' => false, 'failing_seconds' => null, 'ready_wal' => 0];
        $this->fakeStats(['archiver' => ['failing' => true, 'failing_seconds' => 3600, 'ready_wal' => 500] + $healthy]);

        // PITR off (fresh install): nothing, whatever the archiver says
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::WAL_ARCHIVE));

        // Failing seen for the first time: only the backlog pages; the failure itself once it lasted the rule's 5 minutes
        config(['monitoring.pitr.enabled' => true]);
        $this->run_();
        $this->assertSame(['backlog'], $this->subjects(AlertKind::WAL_ARCHIVE));
        $this->travel(6)->minutes();
        $this->run_();
        $this->assertSame(['backlog', 'failing'], $this->subjects(AlertKind::WAL_ARCHIVE));
        $failing = $this->unresolved(AlertKind::WAL_ARCHIVE)->firstWhere('subject_key', 'failing');
        $this->assertSame("WAL kamida 6 daqiqadan beri arxivga yuborilmayapti: davom etsa PITR zanjiri uziladi va to'liq zaxira kerak bo'ladi", $failing->title);
        $this->assertSame('critical', $failing->severity);

        // Cannot read the archiver: unknown, the alerts are left as they are
        $this->fakeStats(['archiver' => null]);
        $this->run_();
        $this->assertSame(['backlog', 'failing'], $this->subjects(AlertKind::WAL_ARCHIVE));

        // Archiving works again (the last success moved): the failure clock starts over
        $this->fakeStats(['archiver' => ['failing' => true, 'last_archived_at' => now()->toIso8601ZuluString(), 'ready_wal' => 0] + $healthy]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::WAL_ARCHIVE));

        $this->fakeStats(['archiver' => ['archive_mode' => 'off'] + $healthy]);
        $this->run_();
        $this->assertSame(['archive_mode'], $this->subjects(AlertKind::WAL_ARCHIVE));

        $this->fakeStats(['archiver' => $healthy]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::WAL_ARCHIVE));
    }

    public function test_the_archiver_is_read_from_postgres_under_the_statement_timeout(): void
    {
        $state = app(PostgresStats::class)->archiver();
        $this->assertNotNull($state);
        $this->assertContains($state['archive_mode'], ['on', 'off', 'always']);
        $this->assertIsBool($state['failing']);

        // A measurement slower than the timeout is "unknown" and the setting does not leak to the caller
        $before = DB::selectOne("SELECT current_setting('statement_timeout') AS v")->v;
        config(['monitoring.sql_statement_timeout' => '100ms']);
        $this->assertNull(PostgresStats::bounded(fn () => DB::select('SELECT pg_sleep(1)')));
        $this->assertSame($before, DB::selectOne("SELECT current_setting('statement_timeout') AS v")->v);
        $this->assertSame(1, PostgresStats::bounded(fn () => (int) DB::selectOne('SELECT 1 AS n')->n));
        $this->assertSame($before, DB::selectOne("SELECT current_setting('statement_timeout') AS v")->v);
    }

    // --- pitr_backup_stale ------------------------------------------------------------------------------------------

    public function test_pitr_backup_stale_watches_the_marker_the_backups_and_the_offsite_copy(): void
    {
        $this->oldRule(AlertKind::PITR_BACKUP_STALE);

        // Off and nothing reported: quiet
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PITR_BACKUP_STALE));

        // On, the backup service never reported
        config(['monitoring.pitr.enabled' => true]);
        $this->run_();
        $this->assertSame(['marker'], $this->subjects(AlertKind::PITR_BACKUP_STALE));

        $this->marker('pitr', $this->pitrMeta());
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PITR_BACKUP_STALE));

        // The sidecar stopped: the marker is 20 minutes old
        $this->marker('pitr', $this->pitrMeta(), now()->subMinutes(20));
        $this->run_();
        $alert = $this->unresolved(AlertKind::PITR_BACKUP_STALE)->sole();
        $this->assertSame('marker', $alert->subject_key);
        $this->assertStringStartsWith('Zaxira xizmati 20 daqiqadan beri', $alert->title);

        // Old backups, a broken off-site setup and a repository error
        $this->marker('pitr', $this->pitrMeta([
            'last_backup_at' => now()->subHours(30)->utc()->format('Y-m-d\TH:i:s\Z'),
            'last_full_at' => now()->subDays(9)->utc()->format('Y-m-d\TH:i:s\Z'),
            'repo2' => 'no_cipher',
            'repos' => [['key' => 1, 'status' => 'ok'], ['key' => 2, 'status' => 'error', 'message' => 'unable to connect']],
        ]));
        $this->run_();
        $this->assertSame(['any', 'full', 'offsite', 'repo2'], $this->subjects(AlertKind::PITR_BACKUP_STALE));
        $this->assertSame("Oxirgi to'liq zaxira 9 kun oldin olingan", $this->unresolved(AlertKind::PITR_BACKUP_STALE)->firstWhere('subject_key', 'full')->title);

        // An off-site copy that is simply not set up is not paged (the page shows it)
        $this->marker('pitr', $this->pitrMeta(['repo2' => 'off']));
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PITR_BACKUP_STALE));
    }

    public function test_pitr_counts_as_on_when_a_fresh_marker_says_so_even_without_the_env_key(): void
    {
        $this->oldRule(AlertKind::PITR_BACKUP_STALE);
        $this->marker('pitr', $this->pitrMeta(['status' => 'error', 'message' => 'stanza missing']));

        $this->run_();

        $alert = $this->unresolved(AlertKind::PITR_BACKUP_STALE)->sole();
        $this->assertStringStartsWith('Uzluksiz zaxira xato holatda', $alert->title);
        $this->assertSame('stanza missing', $alert->details['message']);

        // The sidecar writes enabled=false: off
        $this->marker('pitr', ['enabled' => false]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PITR_BACKUP_STALE));
    }

    // --- restore_drill_stale ----------------------------------------------------------------------------------------

    private function drill(string $status, Carbon $at, array $extra = []): void
    {
        DB::table('restore_drills')->insert(array_merge([
            'started_at' => $at, 'finished_at' => $at->copy()->addMinutes(4), 'status' => $status, 'repo' => '1',
            'target_time' => $at, 'reached_time' => $at, 'restore_seconds' => 252, 'data_loss_seconds' => 38,
            'backup_label' => '20261101-213000F', 'checks' => json_encode(['probe' => 'ok', 'app_smoke' => 'ok', 'trigger' => 'manual']), 'error' => $status === 'failed' ? 'manifest: orders 10 != 9' : null,
            'created_at' => $at,
        ], $extra));
    }

    public function test_restore_drill_stale_warns_when_overdue_and_is_critical_when_the_last_drill_failed(): void
    {
        $this->oldRule(AlertKind::RESTORE_DRILL_STALE);

        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::RESTORE_DRILL_STALE), 'PITR off: quiet');

        config(['monitoring.pitr.enabled' => true]);
        $this->run_();
        $this->assertSame(['app_check', 'overdue'], $this->subjects(AlertKind::RESTORE_DRILL_STALE));
        $overdue = $this->unresolved(AlertKind::RESTORE_DRILL_STALE)->firstWhere('subject_key', 'overdue');
        $this->assertSame('overdue', $overdue->subject_key);
        $this->assertSame('warning', $overdue->severity);

        $this->drill('ok', now()->subDay());
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::RESTORE_DRILL_STALE));

        $this->drill('failed', now());
        $this->run_();
        $failed = $this->unresolved(AlertKind::RESTORE_DRILL_STALE)->sole();
        $this->assertSame('failed', $failed->subject_key);
        $this->assertSame('critical', $failed->severity);
        $this->assertSame('manifest: orders 10 != 9', $failed->details['error']);

        $this->travel(1)->minutes();
        $this->drill('ok', now());
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::RESTORE_DRILL_STALE));

        $this->travel(36)->days();
        $this->run_();
        $this->assertSame(['overdue'], $this->subjects(AlertKind::RESTORE_DRILL_STALE));
    }

    public function test_a_new_install_gets_the_rules_days_for_its_first_drill(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        $this->onlyRule(AlertKind::RESTORE_DRILL_STALE);

        $this->run_();

        $this->assertSame([], $this->subjects(AlertKind::RESTORE_DRILL_STALE));
    }

    // --- disk_usage -------------------------------------------------------------------------------------------------

    public function test_disk_usage_warns_escalates_to_critical_resolves_and_says_when_the_sidecar_is_silent(): void
    {
        $this->onlyRule(AlertKind::DISK_USAGE);
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '-100777']);
        \Illuminate\Support\Facades\Http::fake(['api.telegram.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true])]);
        $disk = fn (float $percent) => ['data_path' => '/var/lib/postgresql/data', 'fs_total_bytes' => 100 * 1024 ** 3, 'fs_used_bytes' => (int) ($percent * 1024 ** 3),
            'fs_avail_bytes' => (int) ((100 - $percent) * 1024 ** 3), 'used_percent' => $percent, 'repo_used_percent' => 10.0, 'repo_avail_bytes' => 90 * 1024 ** 3,
            'pg_wal_bytes' => 512 * 1024 ** 2, 'spool_bytes' => 0, 'db_bytes' => 20 * 1024 ** 3];

        // No marker yet (a backup service not rebuilt for M3): unknown, nothing opens
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::DISK_USAGE));

        $this->marker('disk', $disk(85.0));
        $this->run_();
        $alert = $this->unresolved(AlertKind::DISK_USAGE)->sole();
        $this->assertSame(['data', 'warning'], [$alert->subject_key, $alert->severity]);
        $this->assertSame("Baza turgan disk 85 % to'lgan, bo'sh joy 15.0 GB", $alert->title);

        // Worse: the same alert is raised to critical and announced again
        $this->marker('disk', $disk(95.0));
        $this->run_();
        $raised = $this->unresolved(AlertKind::DISK_USAGE)->sole();
        $this->assertSame($alert->id, $raised->id);
        $this->assertSame('critical', $raised->severity);
        $texts = \Illuminate\Support\Facades\Http::recorded()->map(fn ($pair) => $pair[0]['text'])->values()->all();
        $this->assertCount(2, $texts);
        $this->assertStringStartsWith('[DIQQAT] Baza turgan disk 85 %', $texts[0]);
        $this->assertStringStartsWith('[KRITIK] Baza turgan disk 95 %', $texts[1]);

        $this->marker('disk', $disk(50.0));
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::DISK_USAGE));

        // The sidecar went silent: the disk cannot be watched any more
        $this->marker('disk', $disk(50.0), now()->subMinutes(16));
        $this->run_();
        $silent = $this->unresolved(AlertKind::DISK_USAGE)->sole();
        $this->assertSame(['marker', 'critical'], [$silent->subject_key, $silent->severity]);
    }

    // --- redis_memory -----------------------------------------------------------------------------------------------

    public function test_redis_memory_warns_then_is_critical_and_unknown_leaves_alerts_alone(): void
    {
        $this->onlyRule(AlertKind::REDIS_MEMORY);
        $answer = null;
        $this->app->instance(RedisStats::class, new class($answer) extends RedisStats
        {
            public function __construct(public ?array &$answer) {}

            public function memory(): ?array
            {
                return $this->answer;
            }
        });
        $memory = fn (?float $percent) => ['used_bytes' => 850 * 1024 ** 2, 'max_bytes' => $percent === null ? null : 1024 ** 3, 'percent' => $percent, 'policy' => 'noeviction'];

        $answer = $memory(85.0);
        $this->run_();
        $this->assertSame('warning', $this->unresolved(AlertKind::REDIS_MEMORY)->sole()->severity);
        $this->assertStringStartsWith('Redis xotirasi 85 % band', $this->unresolved(AlertKind::REDIS_MEMORY)->sole()->title);

        $answer = null;
        $this->run_();
        $this->assertSame(['global'], $this->subjects(AlertKind::REDIS_MEMORY));

        $answer = $memory(93.0);
        $this->run_();
        $this->assertSame('critical', $this->unresolved(AlertKind::REDIS_MEMORY)->sole()->severity);

        // No maxmemory: no cap to reach
        $answer = $memory(null);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::REDIS_MEMORY));
    }

    // --- queue_worker_stale -----------------------------------------------------------------------------------------

    public function test_queue_worker_stale_fires_without_a_heartbeat_and_resolves_when_a_worker_runs_the_job(): void
    {
        $this->oldRule(AlertKind::QUEUE_WORKER_STALE);

        // Scheduler stale too: nothing is dispatched, so this rule cannot tell (scheduler_stale alerts)
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::QUEUE_WORKER_STALE));

        HealthReport::beat();
        $this->run_();
        $this->assertSame("Navbat ishchisi tekshiruv vazifasini bajarmadi: navbat ishlamayapti", $this->unresolved(AlertKind::QUEUE_WORKER_STALE)->sole()->title);

        (new QueueHeartbeatJob(now()->getTimestamp() - 5))->handle();
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::QUEUE_WORKER_STALE));
        $this->assertSame(5, QueueHeartbeatJob::last()['waited_seconds']);

        $this->travel(4)->minutes();
        HealthReport::beat();
        $this->run_();
        $this->assertStringStartsWith('Navbat ishchisi 4 daqiqadan beri', $this->unresolved(AlertKind::QUEUE_WORKER_STALE)->sole()->title);
    }

    public function test_the_heartbeat_job_goes_to_the_default_queue_every_minute(): void
    {
        $this->assertSame('default', (new QueueHeartbeatJob)->queue);
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events());
        $event = $events->first(fn ($e) => $e->description === 'monitoring-queue-heartbeat');
        $this->assertNotNull($event, 'the heartbeat is scheduled');
        $this->assertSame('* * * * *', $event->expression);

        \Illuminate\Support\Facades\Queue::fake();
        $event->run($this->app);
        \Illuminate\Support\Facades\Queue::assertPushedOn('default', QueueHeartbeatJob::class);
    }

    // --- realtime_down ----------------------------------------------------------------------------------------------

    public function test_realtime_down_waits_its_minutes_and_resolves_once_the_heartbeat_is_back(): void
    {
        $this->onlyRule(AlertKind::REALTIME_DOWN);
        config(['realtime.revision_store' => 'array']);

        // Real-time off: nothing to watch
        config(['broadcasting.default' => 'null']);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::REALTIME_DOWN));

        config(['broadcasting.default' => 'reverb']);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::REALTIME_DOWN), 'not live for less than the rule\'s minutes');

        $this->travel(4)->minutes();
        $this->run_();
        $alert = $this->unresolved(AlertKind::REALTIME_DOWN)->sole();
        $this->assertStringStartsWith('Real vaqt yangilanishlari 4 daqiqadan beri ishonchsiz', $alert->title);
        $this->assertSame('warning', $alert->severity);

        RealtimeHealth::recordHeartbeat(3);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::REALTIME_DOWN));
    }

    // --- table_bloat ------------------------------------------------------------------------------------------------

    public function test_table_bloat_reports_dead_rows_and_tables_not_vacuumed(): void
    {
        $this->onlyRule(AlertKind::TABLE_BLOAT);
        $row = fn (string $table, int $live, int $dead, ?int $vacAge) => ['table' => $table, 'live' => $live, 'dead' => $dead, 'last_vacuum_at' => null, 'vacuum_age_seconds' => $vacAge];

        $this->fakeStats(['tableStats' => [$row('orders', 400000, 150000, 600), $row('payments', 10, 5, null), $row('stores', 1000, 20000, 30 * 3600)]]);
        $this->run_();
        $this->assertSame(['orders', 'stores'], $this->subjects(AlertKind::TABLE_BLOAT));
        $this->assertSame("orders jadvalida 150 000 ta o'lik qator (37.5 %)", $this->unresolved(AlertKind::TABLE_BLOAT)->firstWhere('subject_key', 'orders')->title);
        $this->assertSame("stores jadvali 24 soatdan beri tozalanmagan (20 000 ta o'lik qator)", $this->unresolved(AlertKind::TABLE_BLOAT)->firstWhere('subject_key', 'stores')->title);

        $this->fakeStats(['tableStats' => [$row('orders', 400000, 2000, 60), $row('stores', 1000, 0, 30 * 3600)]]);
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::TABLE_BLOAT));
    }

    public function test_table_stats_are_read_from_postgres(): void
    {
        $stats = app(PostgresStats::class)->tableStats(['orders', 'stores']);

        $this->assertNotNull($stats);
        $this->assertSame(['orders', 'stores'], array_column($stats, 'table'));
    }

    // --- partitions -------------------------------------------------------------------------------------------------

    // M4: runs the migration's DDL itself: as the schema owner when the roles are split
    #[\Tests\Concerns\AsSchemaOwner]
    public function test_partitions_fire_when_the_months_ahead_run_out_and_resolve_after_ensure(): void
    {
        $this->onlyRule(AlertKind::PARTITIONS);

        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PARTITIONS));

        // Past the horizon the migration attached
        $this->travel(4)->months();
        $this->run_();
        $subjects = $this->subjects(AlertKind::PARTITIONS);
        $this->assertContains('future_months:audit_logs', $subjects);
        $alert = $this->unresolved(AlertKind::PARTITIONS)->firstWhere('subject_key', 'future_months:audit_logs');
        $this->assertSame('critical', $alert->severity);
        $this->assertStringContainsString('php artisan db:partitions:ensure', $alert->title);

        app(PartitionManager::class)->ensureAll();
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::PARTITIONS));
    }

    // --- tls_expiry -------------------------------------------------------------------------------------------------

    public function test_tls_expiry_warns_then_is_critical_and_is_quiet_without_an_https_host(): void
    {
        $this->onlyRule(AlertKind::TLS_EXPIRY);
        $state = null;
        $this->app->instance(TlsProbe::class, new class($state) extends TlsProbe
        {
            public function __construct(public ?array &$state) {}

            public function check(): ?array
            {
                return $this->state;
            }
        });

        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::TLS_EXPIRY));

        $state = ['host' => 'rizqim.example', 'expires_at' => now()->addDays(10)->addHour()->toIso8601ZuluString(), 'reachable' => true];
        $this->run_();
        $alert = $this->unresolved(AlertKind::TLS_EXPIRY)->sole();
        $this->assertSame(['rizqim.example', 'warning'], [$alert->subject_key, $alert->severity]);
        $this->assertStringStartsWith('rizqim.example sertifikati 10 kundan keyin tugaydi', $alert->title);

        // Not reachable from here: unknown, the alert stays
        $state = ['host' => 'rizqim.example', 'expires_at' => null, 'reachable' => false];
        $this->run_();
        $this->assertSame(['rizqim.example'], $this->subjects(AlertKind::TLS_EXPIRY));

        $state = ['host' => 'rizqim.example', 'expires_at' => now()->addDays(2)->toIso8601ZuluString(), 'reachable' => true];
        $this->run_();
        $this->assertSame('critical', $this->unresolved(AlertKind::TLS_EXPIRY)->sole()->severity);

        $state = ['host' => 'rizqim.example', 'expires_at' => now()->addDays(80)->toIso8601ZuluString(), 'reachable' => true];
        $this->run_();
        $this->assertSame([], $this->subjects(AlertKind::TLS_EXPIRY));
    }

    public function test_the_tls_host_is_app_urls_https_host_only(): void
    {
        $probe = new TlsProbe;
        config(['app.url' => 'http://localhost', 'monitoring.tls.host' => '']);
        $this->assertNull($probe->host());
        config(['app.url' => 'https://127.0.0.1']);
        $this->assertNull($probe->host());
        config(['app.url' => 'https://bot.rizqim.uz']);
        $this->assertSame('bot.rizqim.uz', $probe->host());
        config(['monitoring.tls.host' => 'admin.rizqim.uz']);
        $this->assertSame('admin.rizqim.uz', $probe->host());
    }

    // --- a fresh install and a silent sidecar -----------------------------------------------------------------------

    public function test_a_fresh_install_with_everything_off_pages_nothing_about_backups_disk_or_drills(): void
    {
        HealthReport::beat();
        (new QueueHeartbeatJob)->handle();

        $this->run_();

        foreach ([AlertKind::WAL_ARCHIVE, AlertKind::PITR_BACKUP_STALE, AlertKind::RESTORE_DRILL_STALE, AlertKind::DISK_USAGE, AlertKind::QUEUE_WORKER_STALE, AlertKind::PARTITIONS, AlertKind::TLS_EXPIRY, AlertKind::REDIS_MEMORY, AlertKind::TABLE_BLOAT] as $kind) {
            $this->assertSame([], $this->subjects($kind), $kind->value);
        }
    }

    public function test_every_kind_has_a_check_texts_and_fields_within_bounds(): void
    {
        $this->assertCount(21, AlertKind::cases());
        foreach (AlertKind::cases() as $kind) {
            $this->assertNotSame('', $kind->label());
            $this->assertNotSame('', $kind->action());
            $this->assertContains($kind->severity(), ['critical', 'warning']);
            $this->assertInstanceOf(\App\Services\Admin\Monitoring\Checks\AlertCheck::class, app($kind->check()));
            $fields = $kind->fields();
            $defaults = $kind->defaults();
            $this->assertGreaterThanOrEqual($fields['threshold']['min'], $defaults['threshold'], $kind->value);
            $this->assertLessThanOrEqual($fields['threshold']['max'], $defaults['threshold'], $kind->value);
            $this->assertSame($fields['threshold_secondary'] === null, $defaults['threshold_secondary'] === null, $kind->value);
            $this->assertSame($fields['window'] === null, $defaults['window_minutes'] === null, $kind->value);
        }
    }
}
