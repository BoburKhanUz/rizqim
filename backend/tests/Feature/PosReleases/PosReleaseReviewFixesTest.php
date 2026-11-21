<?php

namespace Tests\Feature\PosReleases;

use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Releases\AppVersion;
use App\Domain\Pos\Releases\PosAppOutdatedNotice;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\Store;
use App\Services\Auth\DevicePairingService;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use RuntimeException;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\Feature\StaffOrderFixtures;
use Tests\TestCase;

/**
 * Wave M5 S5 review 3 fixes (F1 backend): M2 (GET /terminal judges the version the request reports), M3 (pairing is
 * refused only after `minimum_from`, never while the till holds queued operations), M4 (an over-long version header
 * never breaks a terminal request), and the LOWs (the owner notice is marked only after a send, one store's failure
 * does not stop the others).
 */
class PosReleaseReviewFixesTest extends TestCase
{
    use PosReleaseFixtures;
    use RefreshDatabase;
    use StaffOrderFixtures;

    private const HEADER = 'X-Rizqim-App-Version';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'UTC'));
        $this->setUpLifecycleStore();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{device: Device, token: string}
     */
    private function till(array $attributes = []): array
    {
        $till = $this->pairTerminal($this->owner, $this->branch);
        $till['device']->forceFill($attributes + ['app' => 'cashier', 'platform' => 'android', 'app_version' => null, 'last_seen_at' => now()->subHour()])->save();

        return $till;
    }

    private function tget(array $till, string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($till['token'])->withHeaders($headers)->getJson($uri);
    }

    private function code(): string
    {
        return app(DevicePairingService::class)->createCode($this->owner->store, $this->branch, DeviceType::POS, 'Kassa', $this->owner)['code'];
    }

    // M2

    public function test_the_terminal_state_follows_the_version_the_request_reports_inside_the_heartbeat_minute(): void
    {
        $this->release('1.2.0', ['build' => 3, 'is_minimum' => true, 'minimum_from' => now()->subDay()]);
        $till = $this->till(['app_version' => '1.0.0+1', 'last_seen_at' => now()->subSeconds(30)]);

        // The updated app starts within a minute of the old app's last request: no heartbeat save yet
        $release = $this->tget($till, '/api/v1/terminal', [self::HEADER => '1.2.0+3'])->assertOk()->json('data.app_release');
        $this->assertSame(['ok', '1.2.0+3'], [$release['state'], $release['current']]);
        $this->assertSame('1.0.0+1', $till['device']->refresh()->app_version);

        // No header, or one that does not parse: the stored version is used as before
        $this->assertSame('update_required', $this->tget($till, '/api/v1/terminal')->json('data.app_release.state'));
        $release = $this->tget($till, '/api/v1/terminal', [self::HEADER => 'v1.2'])->json('data.app_release');
        $this->assertSame(['update_required', '1.0.0+1'], [$release['state'], $release['current']]);
    }

    // M3

    public function test_pairing_inside_the_grace_period_is_not_refused(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->addDays(14)]);

        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'android', 'app' => 'cashier', 'app_version' => '1.1.0+1'])
            ->assertCreated();
    }

    public function test_pairing_is_never_refused_while_the_till_holds_queued_operations(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->subDay()]);
        $code = $this->code();
        $body = ['code' => $code, 'platform' => 'android', 'app' => 'cashier', 'app_version' => '1.1.0+1'];

        // Nothing queued (or not said): refused, the code kept
        $this->postJson('/api/v1/devices/pair', $body)->assertStatus(426)->assertJsonPath('data.reason', 'app_outdated');
        $this->postJson('/api/v1/devices/pair', $body + ['pending_operations' => 0])->assertStatus(426);
        $this->assertSame(0, DevicePairingCode::whereNotNull('used_at')->count());

        // A queue to send: pairs, and GET /terminal then shows the red notice
        $paired = $this->postJson('/api/v1/devices/pair', $body + ['pending_operations' => 3])->assertCreated();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withToken((string) $paired->json('data.token'))->getJson('/api/v1/terminal')
            ->assertOk()->assertJsonPath('data.app_release.state', 'update_required');

        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'pending_operations' => -1])->assertUnprocessable();
    }

    // M4

    public function test_a_version_header_longer_than_the_column_is_ignored_and_never_breaks_a_request(): void
    {
        $till = $this->till(['app_version' => '1.0.0+1']);
        $long = '123456.123456.123456.123456+123456789';
        $this->assertSame(37, strlen($long));
        $this->assertNull(AppVersion::parse($long));

        $this->tget($till, '/api/v1/terminal', [self::HEADER => $long])->assertOk();
        $this->assertSame('1.0.0+1', $till['device']->refresh()->app_version);
        $this->assertTrue($till['device']->last_seen_at->eq(now()));
        $this->tget($till, '/api/v1/pos/sync/status', [self::HEADER => $long])->assertOk();

        // Exactly the column's 32 characters is still a version
        $max = '123456.123456.123456.1234+123456';
        $this->assertSame(32, strlen($max));
        Carbon::setTestNow(now()->addMinutes(2));
        $this->tget($till, '/api/v1/terminal', [self::HEADER => $max])->assertOk();
        $this->assertSame($max, $till['device']->refresh()->app_version);
    }

    // LOW: the owner notice

    public function test_the_owner_notice_marks_the_tills_only_after_a_successful_send_and_one_store_does_not_stop_the_others(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->subDay()]);
        $first = $this->till(['app_version' => '1.0.0+1'])['device'];

        $this->setUpLifecycleStore();
        $second = $this->till(['app_version' => '1.0.0+1'])['device'];
        $this->assertNotSame($first->store_id, $second->store_id);

        $failing = (int) $first->store_id;
        $alerts = new class($failing) extends PosOwnerAlerts
        {
            /** @var list<int> */
            public array $sentTo = [];

            public function __construct(private int $failing)
            {
                parent::__construct(new FakeTelegramMessenger(), app(\App\Services\Notifications\NotificationRouter::class));
            }

            public function deliver(int $storeId, string $text): void
            {
                if ($storeId === $this->failing) {
                    throw new RuntimeException('Telegram down');
                }
                $this->sentTo[] = $storeId;
            }
        };
        $this->app->instance(PosOwnerAlerts::class, $alerts);

        $this->assertSame(1, app(PosAppOutdatedNotice::class)->run());
        $this->assertSame([(int) $second->store_id], $alerts->sentTo);
        // The failed store's till is not marked: the next run tells its owner
        $this->assertNull($first->refresh()->app_outdated_notified_release_id);
        $this->assertNotNull($second->refresh()->app_outdated_notified_release_id);

        $this->app->instance(TelegramMessenger::class, $telegram = new FakeTelegramMessenger());
        $this->app->forgetInstance(PosOwnerAlerts::class);
        Store::whereKey($failing)->update(['telegram_admin_chat_id' => '-100555']);
        $this->assertSame(1, app(PosAppOutdatedNotice::class)->run());
        $this->assertNotNull($first->refresh()->app_outdated_notified_release_id);
        $this->assertCount(1, array_filter($telegram->sent, fn (array $m) => $m['chat'] === '-100555'));
        $this->assertSame(0, app(PosAppOutdatedNotice::class)->run());
    }
}
