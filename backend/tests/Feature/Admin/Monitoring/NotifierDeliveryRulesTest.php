<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Jobs\EvaluateAlertsJob;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\AlertNotifier;
use App\Services\Admin\Monitoring\Checks\SchedulerStaleCheck;
use App\Services\Admin\Monitoring\Checks\WalArchiveCheck;
use App\Services\Admin\Monitoring\FailedJobs;
use App\Services\Admin\Monitoring\PostgresStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * M3 review round 3 (R3-1..R3-10): an alert whose announcement has not gone out is offered again on every evaluation
 * until it is sent or resolved; criticals first and outside the warnings' hourly limit; a digest instead of silence; a
 * reopen after a sent resolve is announced; a broken cache never loses a message; the database being down still
 * reaches Telegram; WAL failures are timed from when they started failing; the Telegram budget starts at the first
 * message; and the smaller fixes.
 */
class NotifierDeliveryRulesTest extends TestCase
{
    use MonitoringFixtures;
    use RefreshDatabase;

    /** @var list<AlertFinding> */
    public static array $findings = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '-100777', 'monitoring.pitr.enabled' => false]);
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        self::$findings = [];
        $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $this->app->bind(SchedulerStaleCheck::class, fn () => new class extends SchedulerStaleCheck
        {
            public function findings(AlertRule $rule): ?array
            {
                return NotifierDeliveryRulesTest::$findings;
            }
        });
        File::delete(storage_path('framework/monitoring-db-down.json'));
    }

    protected function tearDown(): void
    {
        File::delete(storage_path('framework/monitoring-db-down.json'));

        parent::tearDown();
    }

    private function run_(): void
    {
        app(AlertEvaluator::class)->run();
    }

    /** @return list<string> */
    private function texts(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.telegram.org'))
            ->map(fn ($p) => (string) ($p[0]->data()['text'] ?? ''))->values()->all();
    }

    private function sentContaining(string $needle): int
    {
        return count(array_filter($this->texts(), fn ($text) => str_contains($text, $needle)));
    }

    // --- R3-1 -------------------------------------------------------------------------------------------------------

    public function test_a_critical_is_sent_even_after_the_warnings_used_the_hourly_limit_and_the_rest_get_a_digest(): void
    {
        config(['monitoring.telegram.hourly_limit' => 20]);
        $warnings = array_map(fn ($i) => new AlertFinding("till{$i}", "Kassa {$i} oflayn", [], null, 'warning'), range(1, 25));
        self::$findings = [...$warnings, new AlertFinding('db', 'DISK 95% CRITICAL', [], null, 'critical')];

        $this->run_();

        $this->assertSame(1, $this->sentContaining('DISK 95%'), 'the critical is sent, and first');
        $this->assertStringContainsString('DISK 95%', $this->texts()[0]);
        $this->assertSame(20, $this->sentContaining('[DIQQAT] Kassa'));
        $this->assertSame(1, $this->sentContaining('Yana 5 ta ogohlantirish ochiq'), 'one digest instead of silence');
        $this->assertNotNull(Alert::query()->where('subject_key', 'db')->sole()->telegram_opened_at);

        // The digest is not repeated every minute
        $this->travel(1)->minutes();
        $this->run_();
        $this->assertSame(1, $this->sentContaining('Yana 5 ta ogohlantirish ochiq'));

        // An hour later the limit has room again: what is still open goes out
        $this->travel(61)->minutes();
        $this->run_();
        $this->assertSame(25, $this->sentContaining('[DIQQAT] Kassa'));
        $this->assertSame(0, Alert::query()->unresolved()->whereNull('telegram_opened_at')->count());
    }

    public function test_criticals_have_their_own_cap_per_rule(): void
    {
        config(['monitoring.telegram.critical_hourly_limit_per_rule' => 3]);
        self::$findings = array_map(fn ($i) => new AlertFinding("c{$i}", "Kritik {$i}", [], null, 'critical'), range(1, 5));

        $this->run_();

        $this->assertSame(3, $this->sentContaining('[KRITIK] Kritik'));
        $this->assertSame(1, $this->sentContaining('Yana 2 ta ogohlantirish ochiq'));
    }

    // --- R3-2 -------------------------------------------------------------------------------------------------------

    public function test_a_problem_that_comes_back_after_its_resolve_notice_is_announced_again(): void
    {
        self::$findings = [new AlertFinding('global', 'Scheduler stopped')];
        $this->run_();
        self::$findings = [];
        $this->travel(2)->minutes();
        $this->run_();
        self::$findings = [new AlertFinding('global', 'Scheduler stopped')];
        $this->travel(2)->minutes();
        $this->run_();

        $this->assertSame(['[KRITIK] Scheduler stopped', '[HAL QILINDI] Scheduler stopped', '[KRITIK] Scheduler stopped'], array_map(fn ($t) => strtok($t, "\n"), $this->texts()));
        $this->assertNotNull(Alert::query()->unresolved()->sole()->telegram_opened_at);
    }

    // --- R3-3 -------------------------------------------------------------------------------------------------------

    public function test_a_broken_cache_never_loses_the_announcement(): void
    {
        self::$findings = [new AlertFinding('global', 'Queue worker dead')];
        $broken = new class extends \Illuminate\Cache\Repository
        {
            public function __construct() {}

            public function __call($method, $parameters)
            {
                throw new \RuntimeException('redis down');
            }

            public function has($key): bool
            {
                throw new \RuntimeException('redis down');
            }

            public function get($key, $default = null): mixed
            {
                throw new \RuntimeException('redis down');
            }

            public function add($key, $value, $ttl = null)
            {
                throw new \RuntimeException('redis down');
            }

            public function put($key, $value, $ttl = null)
            {
                throw new \RuntimeException('redis down');
            }
        };
        $this->app->instance(\Illuminate\Cache\RateLimiter::class, new \Illuminate\Cache\RateLimiter($broken));
        \Illuminate\Support\Facades\RateLimiter::clearResolvedInstance(\Illuminate\Cache\RateLimiter::class);
        Cache::shouldReceive('store')->andReturn($broken);
        Cache::shouldReceive('has', 'get', 'add', 'put', 'forget', 'remember')->andThrow(new \RuntimeException('redis down'));

        $this->run_();

        $this->assertSame(1, $this->sentContaining('Queue worker dead'), 'a failing cache means: send');
        $this->assertNotNull(Alert::query()->sole()->telegram_opened_at);
    }

    // --- R3-4 -------------------------------------------------------------------------------------------------------

    public function test_the_database_being_down_still_reaches_telegram_once_per_quarter_hour_and_its_recovery_too(): void
    {
        $this->assertNull(DB::selectOne('SELECT 1 AS n') === null ? 'x' : null);
        $port = config('database.connections.pgsql.port');
        $down = function () {
            config(['database.connections.pgsql.port' => 1]);
            DB::purge('pgsql');
        };
        $job = fn () => (new EvaluateAlertsJob)->handle(app(AlertEvaluator::class));

        try {
            $down();
            $job();
            $this->assertSame(1, $this->sentContaining("Ma'lumotlar bazasi javob bermayapti"));

            $this->travel(5)->minutes();
            $job();
            $this->assertSame(1, $this->sentContaining("Ma'lumotlar bazasi javob bermayapti"), 'at most one per 15 minutes');

            $this->travel(11)->minutes();
            $job();
            $this->assertSame(2, $this->sentContaining("Ma'lumotlar bazasi javob bermayapti"));
        } finally {
            config(['database.connections.pgsql.port' => $port]);
            DB::purge('pgsql');
        }
        $this->assertFileExists(storage_path('framework/monitoring-db-down.json'));
    }

    public function test_the_recovery_of_the_database_is_announced_once(): void
    {
        File::put(storage_path('framework/monitoring-db-down.json'), json_encode(['since' => now()->subMinutes(20)->getTimestamp(), 'last_sent' => now()->subMinutes(5)->getTimestamp()]));

        (new EvaluateAlertsJob)->handle(app(AlertEvaluator::class));
        (new EvaluateAlertsJob)->handle(app(AlertEvaluator::class));

        $this->assertSame(1, $this->sentContaining("Ma'lumotlar bazasi yana javob bermoqda"));
        $this->assertFileDoesNotExist(storage_path('framework/monitoring-db-down.json'));
    }

    public function test_the_page_and_the_production_check_say_when_nobody_would_be_told(): void
    {
        config(['services.telegram.ops_chat_id' => '', 'services.telegram.superadmin_chat_id' => '', 'monitoring.healthchecks_ping_url' => '']);

        $snapshot = app(\App\Services\Admin\Monitoring\MonitoringStatus::class)->snapshot();
        $this->assertSame(['telegram_configured' => false, 'dead_man_configured' => false], $snapshot['notifications']);

        config(['app.env' => 'production']);
        $this->artisan('app:check-production')
            ->expectsOutputToContain('TELEGRAM_OPS_CHAT_ID')
            ->expectsOutputToContain('HEALTHCHECKS_PING_URL');
    }

    // --- R3-5 -------------------------------------------------------------------------------------------------------

    private function archiver(Carbon $lastArchived, ?Carbon $lastFailed, bool $failing): void
    {
        $this->app->instance(PostgresStats::class, new class($lastArchived, $lastFailed, $failing) extends PostgresStats
        {
            public function __construct(private Carbon $ok, private ?Carbon $failed, private bool $failing) {}

            public function archiver(): ?array
            {
                return ['archive_mode' => 'on', 'last_archived_wal' => 'X', 'last_archived_at' => $this->ok->toIso8601ZuluString(),
                    'last_failed_wal' => $this->failed ? 'Y' : null, 'last_failed_at' => $this->failed?->toIso8601ZuluString(), 'failed_count' => $this->failed ? 1 : 0,
                    'failing' => $this->failing, 'failing_seconds' => $this->failing ? (int) $this->ok->diffInSeconds(now(), true) : null, 'ready_wal' => 1];
            }
        });
    }

    public function test_one_failed_wal_attempt_after_a_quiet_night_is_not_paged_but_a_lasting_failure_is(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        $rule = $this->rule(AlertKind::WAL_ARCHIVE);
        $quiet = now()->subHours(5);

        $this->archiver($quiet, now()->subSeconds(10), true);
        $this->assertSame([], app(WalArchiveCheck::class)->findings($rule), 'the first failure after 5 quiet hours');

        $this->travel(3)->minutes();
        $this->archiver($quiet, now()->subSeconds(10), true);
        $this->assertSame([], app(WalArchiveCheck::class)->findings($rule));

        $this->travel(3)->minutes();
        $this->archiver($quiet, now()->subSeconds(10), true);
        $findings = app(WalArchiveCheck::class)->findings($rule);
        $this->assertCount(1, $findings);
        $this->assertStringStartsWith('WAL kamida 6 daqiqadan beri arxivga yuborilmayapti', $findings[0]->title);

        // It works again: the timer starts over
        $this->archiver(now(), now()->subMinute(), false);
        $this->assertSame([], app(WalArchiveCheck::class)->findings($rule));
        $this->archiver(now()->subHours(2), now()->subSeconds(5), true);
        $this->assertSame([], app(WalArchiveCheck::class)->findings($rule));
    }

    public function test_the_page_does_not_call_a_single_wal_failure_a_broken_pitr(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        MonitoringMarker::query()->create(['key' => 'pitr', 'recorded_at' => now(), 'meta' => ['enabled' => true, 'status' => 'ok', 'repo2' => 'on', 'offsite' => true,
            'last_full_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'last_backup_at' => now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')]]);
        $this->archiver(now()->subHours(5), now()->subSeconds(10), true);

        $pitr = app(\App\Services\Admin\Monitoring\MonitoringStatus::class)->snapshot()['pitr'];

        $this->assertSame('ok', $pitr['status']);
        $this->assertTrue($pitr['archive_failing']);
        $this->assertSame(0, intdiv((int) $pitr['archive_failing_seconds'], 60));
    }

    // --- R3-6 -------------------------------------------------------------------------------------------------------

    public function test_the_telegram_budget_starts_at_the_first_message_not_before_the_checks(): void
    {
        $notifier = app(AlertNotifier::class);
        $notifier->startRun();
        $this->travel(11)->seconds(); // slow checks
        $alert = $this->makeAlert(AlertKind::QUEUE_WORKER_STALE, ['subject_key' => 'global']);

        $notifier->opened($alert, $this->rule(AlertKind::QUEUE_WORKER_STALE));

        $this->assertCount(1, $this->texts());
        Queue::assertNothingPushed();
    }

    // --- LOW --------------------------------------------------------------------------------------------------------

    public function test_failed_jobs_dates_are_tashkent_days(): void
    {
        // 2026-11-10 21:30 UTC is 2026-11-11 02:30 in Tashkent
        $this->failedJob(['failed_at' => Carbon::parse('2026-11-10 21:30:00', 'UTC')]);
        $jobs = app(FailedJobs::class);

        $this->assertSame(1, $jobs->listQuery(['from' => '2026-11-11', 'to' => '2026-11-11'])->count());
        $this->assertSame(0, $jobs->listQuery(['from' => '2026-11-10', 'to' => '2026-11-10'])->count());
    }

    public function test_the_raw_pgbackrest_message_stays_out_of_the_title(): void
    {
        config(['monitoring.pitr.enabled' => true]);
        $this->onlyRule(AlertKind::PITR_BACKUP_STALE, ['enabled' => true]);
        MonitoringMarker::query()->create(['key' => 'pitr', 'recorded_at' => now(), 'meta' => ['enabled' => true, 'status' => 'error', 'message' => 'unable to connect to s3.secret-bucket.internal.example:443', 'repo2' => 'on', 'offsite' => true,
            'last_full_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'last_backup_at' => now()->subHour()->utc()->format('Y-m-d\TH:i:s\Z')]]);

        $this->run_();

        $alert = Alert::query()->where('kind', 'pitr_backup_stale')->where('subject_key', 'status')->sole();
        $this->assertStringNotContainsString('secret-bucket', $alert->title);
        $this->assertSame('unable to connect to s3.secret-bucket.internal.example:443', $alert->details['message']);
        $this->assertSame(0, $this->sentContaining('secret-bucket'));
    }

    public function test_a_stale_marker_that_said_pitr_is_on_keeps_it_on(): void
    {
        $this->onlyRule(AlertKind::PITR_BACKUP_STALE, ['enabled' => true]);
        DB::table('alert_rules')->where('kind', 'pitr_backup_stale')->update(['created_at' => now()->subDays(10)]);
        MonitoringMarker::query()->create(['key' => 'pitr', 'recorded_at' => now()->subHour(), 'meta' => ['enabled' => true, 'status' => 'ok', 'repo2' => 'on', 'offsite' => true,
            'last_full_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'last_backup_at' => now()->subHours(2)->utc()->format('Y-m-d\TH:i:s\Z')]]);

        $this->run_();
        $this->run_();

        $this->assertSame(['marker'], Alert::query()->where('kind', 'pitr_backup_stale')->unresolved()->pluck('subject_key')->all());

        // The sidecar says it is off now (fresh marker): off
        MonitoringMarker::query()->whereKey('pitr')->update(['recorded_at' => now(), 'meta' => json_encode(['enabled' => false])]);
        $this->run_();
        $this->assertSame([], Alert::query()->where('kind', 'pitr_backup_stale')->unresolved()->pluck('subject_key')->all());
    }
}
