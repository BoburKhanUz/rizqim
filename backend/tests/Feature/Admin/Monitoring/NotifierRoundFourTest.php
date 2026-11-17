<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\MonitoringMarker;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\AlertNotifier;
use App\Services\Admin\Monitoring\Checks\QueueWorkerStaleCheck;
use App\Services\Admin\Monitoring\Checks\RedisMemoryCheck;
use App\Services\Admin\Monitoring\Checks\SchedulerStaleCheck;
use App\Services\Admin\Monitoring\DatabaseDownNotice;
use App\Services\Admin\Monitoring\QueuedOpsMessage;
use App\Services\Admin\Monitoring\RedisStats;
use App\Services\Admin\Monitoring\WalFailureClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * M3 re-review (RR-3, RR-6, RR-7, RR-8): the database-down notice records before it sends and survives a full disk;
 * a flapping database is announced once; many alerts of one rule go out as one grouped message (also when they
 * resolve) and never starve the other rules; the evaluator's overlap lock and the Redis-down alert need no Redis; a
 * message handed to the queue counts only once pushed and is sent directly if it is still unsent; an escalated
 * alert keeps its resolve notice; the WAL failure clock lives in the database.
 */
class NotifierRoundFourTest extends TestCase
{
    use MonitoringFixtures;
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '-100777', 'monitoring.pitr.enabled' => false]);
        Queue::fake();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->dir = sys_get_temp_dir() . '/rizqim-dbdown-' . getmypid() . '-' . uniqid();
        @mkdir($this->dir, 0777, true);
        config(['monitoring.db_down_state_paths' => [$this->dir . '/state.json', $this->dir . '/fallback.json']]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    /** @return list<string> */
    private function texts(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'api.telegram.org'))
            ->map(fn ($p) => (string) ($p[0]->data()['text'] ?? ''))->values()->all();
    }

    // --- RR-3 -------------------------------------------------------------------------------------------------------

    public function test_a_full_disk_never_turns_the_down_notice_into_a_message_every_minute(): void
    {
        // The state file is on the full disk; the fallback (/dev/shm in production) still works
        config(['monitoring.db_down_state_paths' => ['/dev/full', $this->dir . '/fallback.json']]);
        $notice = app(DatabaseDownNotice::class);
        for ($minute = 0; $minute < 5; $minute++) {
            $notice->down(new \RuntimeException('db down'));
            $this->travel(1)->minutes();
        }

        $this->assertCount(1, $this->texts());
    }

    public function test_with_nowhere_to_record_it_sends_at_most_once_an_hour_and_says_so(): void
    {
        config(['monitoring.db_down_state_paths' => ['/dev/full', '/dev/full']]);
        $this->travelTo(now()->startOfHour()->addMinutes(55));
        $notice = app(DatabaseDownNotice::class);
        for ($minute = 0; $minute < 70; $minute++) {
            $notice->down(new \RuntimeException('db down'));
            $this->travel(1)->minutes();
        }

        $this->assertCount(2, $this->texts(), 'one per hour (here: two clock hours touched)');
        $this->assertStringContainsString('soatda bir marta', $this->texts()[0]);
    }

    // --- RR-6 -------------------------------------------------------------------------------------------------------

    public function test_a_flapping_database_is_announced_once_and_its_recovery_only_after_it_stayed_up(): void
    {
        $notice = app(DatabaseDownNotice::class);
        for ($minute = 0; $minute < 10; $minute++) {
            $minute % 2 === 0 ? $notice->down(new \RuntimeException('too many clients')) : $notice->up();
            $this->travel(1)->minutes();
        }
        $this->assertCount(1, $this->texts());

        // Up for good: announced once it has stayed up 3 minutes, with the number of flaps
        for ($minute = 0; $minute < 5; $minute++) {
            $notice->up();
            $this->travel(1)->minutes();
        }
        $this->assertCount(2, $this->texts());
        $this->assertStringContainsString('yana javob bermoqda', $this->texts()[1]);
        $this->assertStringContainsString('4 marta uzilib-ulandi', $this->texts()[1]);
    }

    // --- RR-7 -------------------------------------------------------------------------------------------------------

    private function alertOf(AlertKind $kind, string $subject, string $title, string $severity = 'warning'): Alert
    {
        $rule = AlertRule::query()->where('kind', $kind->value)->firstOrFail();

        return Alert::query()->create([
            'alert_rule_id' => $rule->id, 'kind' => $kind->value, 'subject_key' => $subject, 'severity' => $severity, 'state' => 'open',
            'title' => $title, 'details' => [], 'first_seen_at' => now(), 'last_seen_at' => now(), 'count' => 1,
        ]);
    }

    public function test_many_alerts_of_one_rule_are_one_message_and_never_starve_another_rule(): void
    {
        AlertRule::query()->update(['telegram' => true, 'enabled' => true]);
        for ($i = 0; $i < 100; $i++) {
            $this->alertOf(AlertKind::POS_DEVICE_OFFLINE, "dev{$i}", "Kassa {$i} oflayn");
        }
        $this->travel(5)->minutes();
        $this->alertOf(AlertKind::DB_LOCK_WAITS, 'x', 'LOCK-WAITS on fiscal_receipts');

        app(AlertNotifier::class)->announcePending();

        $texts = $this->texts();
        $this->assertCount(2, $texts);
        $this->assertStringContainsString('100 ta', $texts[0]);
        $this->assertStringContainsString('Kassa 0 oflayn', $texts[0]);
        $this->assertStringContainsString('va yana 97 ta', $texts[0]);
        $this->assertStringContainsString('LOCK-WAITS', $texts[1]);
        $this->assertSame(0, Alert::query()->whereNull('telegram_opened_at')->count());
    }

    public function test_many_alerts_of_one_rule_resolving_at_once_are_one_message(): void
    {
        $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $this->app->bind(SchedulerStaleCheck::class, fn () => new class extends SchedulerStaleCheck
        {
            public static array $f = [];

            public function findings(AlertRule $rule): ?array
            {
                return self::$f;
            }
        });
        $check = app(SchedulerStaleCheck::class);
        $check::$f = array_map(fn ($i) => new AlertFinding("t{$i}", "Kassa {$i}", [], null, 'warning'), range(1, 30));
        app(AlertEvaluator::class)->run();
        $check::$f = [];
        app(AlertEvaluator::class)->run();

        $texts = $this->texts();
        $this->assertCount(2, $texts);
        $this->assertStringContainsString('[HAL QILINDI]', $texts[1]);
        $this->assertStringContainsString('30 tasi', $texts[1]);
    }

    // --- RR-8a ------------------------------------------------------------------------------------------------------

    public function test_the_evaluator_overlap_lock_needs_no_redis(): void
    {
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => $e->description === 'monitoring-evaluate-alerts');
        $this->assertNotNull($event);
        $this->assertFalse($event->withoutOverlapping, 'no cache mutex: the job takes a file lock itself');
    }

    public function test_redis_that_does_not_answer_is_a_critical_alert_and_the_queue_and_scheduler_say_unknown(): void
    {
        $this->app->instance(RedisStats::class, new class extends RedisStats
        {
            public function used(): bool
            {
                return true;
            }

            public function memory(): ?array
            {
                return null;
            }

            public function reachable(): bool
            {
                return false;
            }
        });
        $findings = app(RedisMemoryCheck::class)->findings($this->rule(AlertKind::REDIS_MEMORY));
        $this->assertSame('down', $findings[0]->subjectKey);
        $this->assertSame('critical', $findings[0]->severity);

        Cache::shouldReceive('get', 'has', 'put', 'add', 'forget')->andThrow(new \RuntimeException('redis down'));
        $this->assertNull(app(QueueWorkerStaleCheck::class)->findings($this->rule(AlertKind::QUEUE_WORKER_STALE)));
        $this->assertNull(app(SchedulerStaleCheck::class)->findings($this->rule(AlertKind::SCHEDULER_STALE)));
    }

    // --- RR-8b ------------------------------------------------------------------------------------------------------

    public function test_a_queued_message_is_sent_directly_if_it_is_still_unsent_after_ten_minutes(): void
    {
        $this->onlyRule(AlertKind::QUEUE_WORKER_STALE);
        $alert = $this->alertOf(AlertKind::QUEUE_WORKER_STALE, 'global', 'Navbat ishchisi to\'xtagan', 'critical');
        $down = true;
        Http::fake(['api.telegram.org/*' => function () use (&$down) {
            if ($down) {
                throw new ConnectionException('down');
            }

            return Http::response(['ok' => true]);
        }]);

        app(AlertNotifier::class)->announcePending();
        Queue::assertPushed(QueuedOpsMessage::class, 1);
        $this->assertNotNull($alert->refresh()->telegram_opened_at);

        // The worker is dead: nobody sends it. Telegram is back; ten minutes later it goes out directly
        $down = false;
        Cache::flush();
        $this->travel(11)->minutes();
        $notifier = app(AlertNotifier::class);
        $notifier->announcePending();
        $this->assertCount(1, $this->texts());
        $notifier->announcePending();
        $this->assertCount(1, $this->texts(), 'once');
    }

    public function test_a_failed_queue_push_does_not_count_as_announced(): void
    {
        $alert = $this->alertOf(AlertKind::QUEUE_WORKER_STALE, 'global', 'Navbat', 'critical');
        Http::fake(['api.telegram.org/*' => fn () => throw new ConnectionException('down')]);
        Queue::shouldReceive('connection', 'push', 'pushOn', 'later', 'laterOn')->andThrow(new \RuntimeException('redis down'));

        app(AlertNotifier::class)->announcePending();

        $this->assertNull($alert->refresh()->telegram_opened_at);
    }

    // --- RR-8c ------------------------------------------------------------------------------------------------------

    public function test_an_escalated_alert_held_by_the_cap_still_gets_its_resolve_notice(): void
    {
        config(['monitoring.telegram.critical_hourly_limit_per_rule' => 1]);
        $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $this->app->bind(SchedulerStaleCheck::class, fn () => new class extends SchedulerStaleCheck
        {
            public static array $f = [];

            public function findings(AlertRule $rule): ?array
            {
                return self::$f;
            }
        });
        $check = app(SchedulerStaleCheck::class);
        $check::$f = [new AlertFinding('a', 'A', [], null, 'critical'), new AlertFinding('b', 'B', [], null, 'warning')];
        app(AlertEvaluator::class)->run(); // A (critical) and B (warning) announced
        $check::$f = [new AlertFinding('a', 'A', [], null, 'critical'), new AlertFinding('b', 'B kritik', [], null, 'critical')];
        app(AlertEvaluator::class)->run(); // B escalates, held by the cap of 1
        $check::$f = [new AlertFinding('a', 'A', [], null, 'critical')];
        app(AlertEvaluator::class)->run(); // B resolves

        $this->assertSame(1, count(array_filter($this->texts(), fn ($t) => str_starts_with($t, '[HAL QILINDI] B'))));
    }

    // --- RR-8d ------------------------------------------------------------------------------------------------------

    public function test_the_wal_failure_clock_lives_in_the_database(): void
    {
        $state = ['failing' => true, 'last_archived_at' => '2026-11-17T01:00:00Z'];
        $this->assertSame(0, WalFailureClock::failingSeconds($state));
        $this->travel(7)->minutes();
        Cache::shouldReceive('get', 'put', 'forget', 'has')->andThrow(new \RuntimeException('redis down'));

        $this->assertSame(420, WalFailureClock::failingSeconds($state));
        $this->assertNotNull(MonitoringMarker::query()->find('wal_failing'));
    }
}
