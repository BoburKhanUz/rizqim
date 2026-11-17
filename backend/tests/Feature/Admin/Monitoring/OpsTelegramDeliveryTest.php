<?php

namespace Tests\Feature\Admin\Monitoring;

use App\Jobs\SendTelegramRequest;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Services\Admin\Monitoring\AlertEvaluator;
use App\Services\Admin\Monitoring\AlertFinding;
use App\Services\Admin\Monitoring\AlertKind;
use App\Services\Admin\Monitoring\Checks\SchedulerStaleCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\MonitoringFixtures;
use Tests\TestCase;

/**
 * M3: ops alerts reach Telegram while the queue worker is dead (they used to wait in the queue behind it), and an
 * unreachable Telegram never holds the scheduler for long: per request timeout, per run budget, a breaker; what is
 * not sent at once goes to the queue. Cooldown, the hourly limit and resolve notices keep working.
 */
class OpsTelegramDeliveryTest extends TestCase
{
    use MonitoringFixtures;
    use RefreshDatabase;

    /** @var list<AlertFinding> */
    private array $findings = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        AlertRule::ensureAll();
        config(['services.telegram.bot_token' => '1:token', 'services.telegram.ops_chat_id' => '-100777']);
        // No queue worker: whatever is queued is never run
        Queue::fake();
        $this->onlyRule(AlertKind::SCHEDULER_STALE);
        $this->app->bind(SchedulerStaleCheck::class, fn () => new class($this) extends SchedulerStaleCheck
        {
            public function __construct(private readonly OpsTelegramDeliveryTest $test) {}

            public function findings(AlertRule $rule): ?array
            {
                return $this->test->currentFindings();
            }
        });
    }

    /** @return list<AlertFinding> */
    public function currentFindings(): array
    {
        return $this->findings;
    }

    private function problems(int $count): void
    {
        $this->findings = $count === 0 ? [] : array_map(fn ($i) => new AlertFinding("s{$i}", "Muammo {$i}"), range(1, $count));
    }

    private function run_(): array
    {
        return app(AlertEvaluator::class)->run();
    }

    /** @return list<string> */
    private function telegramTexts(): array
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'api.telegram.org'))
            ->map(fn ($pair) => (string) ($pair[0]->data()['text'] ?? ''))->values()->all();
    }

    public function test_an_alert_reaches_telegram_while_no_queue_worker_runs(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->problems(1);

        $this->run_();

        $this->assertCount(1, $this->telegramTexts());
        $this->assertStringStartsWith('[KRITIK] Muammo 1', $this->telegramTexts()[0]);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/bot1:token/sendMessage' && $request['chat_id'] === '-100777');
        Queue::assertNotPushed(SendTelegramRequest::class);
        $this->assertNotNull(Alert::query()->sole()->telegram_opened_at);

        // The resolve notice goes the same way
        $this->problems(0);
        $this->run_();
        $this->assertCount(2, $this->telegramTexts());
        $this->assertStringStartsWith('[HAL QILINDI] Muammo 1', $this->telegramTexts()[1]);
        Queue::assertNotPushed(SendTelegramRequest::class);
    }

    public function test_an_unreachable_telegram_is_tried_once_per_breaker_window_and_the_rest_is_queued(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;
            throw new ConnectionException('cURL error 28: timed out');
        });
        $this->problems(4);

        $this->run_();

        $this->assertSame(1, $attempts, 'after the first failure the breaker skips Telegram');
        // Nothing is lost: every announcement is handed to the queue (sent once a worker runs again)
        Queue::assertPushed(SendTelegramRequest::class, 4);
        $this->assertSame(4, Alert::query()->whereNotNull('telegram_opened_at')->count());

        // Within the breaker window Telegram is not tried again
        $this->problems(5);
        $this->run_();
        $this->assertSame(1, $attempts);
        Queue::assertPushed(SendTelegramRequest::class, 5);

        // After it, the next message is tried at once again
        $this->travel((int) config('monitoring.telegram.breaker_seconds') + 1)->seconds();
        $this->problems(6);
        $this->run_();
        $this->assertSame(2, $attempts);
    }

    public function test_a_slow_telegram_is_bounded_by_the_run_budget(): void
    {
        config(['monitoring.telegram.run_budget_seconds' => 10, 'monitoring.telegram.timeout' => 5]);
        $timeouts = [];
        Http::fake(function (Request $request, array $options) use (&$timeouts) {
            $timeouts[] = $options['timeout'] ?? null;
            // Each answer takes 4 seconds
            $this->travel(4)->seconds();

            return Http::response(['ok' => true]);
        });
        $this->problems(5);

        $this->run_();

        // 4 s + 4 s + 2 s left: the third request may wait only what is left, then the budget is spent
        $this->assertSame([5, 5, 2], array_map(fn ($t) => (int) ceil((float) $t), $timeouts));
        Queue::assertPushed(SendTelegramRequest::class, 2);
        $this->assertSame(5, Alert::query()->whereNotNull('telegram_opened_at')->count());
    }

    public function test_a_telegram_refusal_is_not_retried_through_the_queue(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);
        $this->problems(2);

        $this->run_();

        // A refusal is final (the queued job would not retry it either) and does not trip the breaker
        $this->assertCount(2, $this->telegramTexts());
        Queue::assertNotPushed(SendTelegramRequest::class);
    }

    public function test_cooldown_and_hourly_limit_still_apply(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        config(['monitoring.telegram.hourly_limit' => 2]);
        $this->problems(4);

        $this->run_();

        $texts = $this->telegramTexts();
        $this->assertCount(3, $texts); // 2 alerts + the "held back" notice
        $this->assertStringStartsWith("Ogohlantirishlar ko'p", $texts[2]);
    }
}
