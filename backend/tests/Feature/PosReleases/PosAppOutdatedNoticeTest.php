<?php

namespace Tests\Feature\PosReleases;

use App\Domain\Pos\Releases\PosAppOutdatedNotice;
use App\Enums\DeviceType;
use App\Models\Device;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\Feature\StaffOrderFixtures;
use Tests\TestCase;

/**
 * Wave M5 S5-a (owner answer 8): 14 days below the minimum, then ONE owner message naming the tills, once per till
 * and per minimum declaration. The till is never touched.
 */
class PosAppOutdatedNoticeTest extends TestCase
{
    use PosReleaseFixtures;
    use RefreshDatabase;
    use StaffOrderFixtures;

    private FakeTelegramMessenger $telegram;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-21 10:00:00', 'UTC'));
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);
        $this->setUpLifecycleStore();
        $this->store->forceFill(['telegram_admin_chat_id' => '-100777'])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function till(string $name, string $version, array $attributes = []): Device
    {
        $device = $this->pairTerminal($this->owner, $this->branch, DeviceType::POS)['device'];
        $device->forceFill($attributes + ['name' => $name, 'app' => 'cashier', 'platform' => 'android', 'app_version' => $version, 'last_seen_at' => now()->subHour()])->save();

        return $device;
    }

    /**
     * What reached the store's admin chat (every owner also gets the same text by their notification preferences).
     *
     * @return list<array{chat: string, text: string}>
     */
    private function sent(): array
    {
        return array_values(array_filter($this->telegram->sent, fn (array $message) => $message['chat'] === '-100777'));
    }

    private function notice(): int
    {
        return app(PosAppOutdatedNotice::class)->run();
    }

    public function test_nothing_is_sent_inside_the_grace_period_or_without_a_minimum(): void
    {
        $this->till('Kassa 1', '1.0.0+1');
        $this->assertSame(0, $this->notice());

        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->addDays(14)]);
        $this->assertSame(0, $this->notice());
        $this->assertSame([], $this->sent());

        // Day 13: still quiet. Day 14: the message
        Carbon::setTestNow(now()->addDays(13));
        $this->assertSame(0, $this->notice());
        Carbon::setTestNow(now()->addDay()->addMinute());
        $this->assertSame(1, $this->notice());
    }

    public function test_the_owner_is_told_once_per_till_per_minimum_naming_the_tills(): void
    {
        $minimum = $this->release('1.2.0', ['build' => 3, 'is_minimum' => true, 'minimum_from' => now()->subMinute()]);
        $latest = $this->release('1.4.0');
        $old = $this->till('Kassa 1', '1.0.0+1');
        $this->till('Kassa 2', '1.1.0');
        $current = $this->till('Kassa 3', '1.2.0+3');
        $newer = $this->till('Kassa 4', '1.4.0+1');

        $this->assertSame(1, $this->notice());
        // One message to the store's admin chat naming the two old tills, the link of the latest build
        $this->assertCount(1, $this->sent());
        $text = $this->sent()[0]['text'];
        $this->assertSame('-100777', $this->sent()[0]['chat']);
        $this->assertStringContainsString('Kassa 1', $text);
        $this->assertStringContainsString('Kassa 2', $text);
        $this->assertStringNotContainsString('Kassa 3', $text);
        $this->assertStringNotContainsString('Kassa 4', $text);
        $this->assertStringContainsString('1.0.0+1 → 1.2.0+3', $text);
        $this->assertStringContainsString($latest->file_url, $text);
        $this->assertStringContainsString("Ish to'xtatilmaydi", $text);
        // The owner is told too (their own chat), with the same text
        $this->assertContains($text, array_column($this->telegram->sent, 'text'));
        $this->assertGreaterThanOrEqual(1, count(array_filter($this->telegram->sent, fn ($m) => $m['chat'] !== '-100777' && $m['text'] === $text)));

        $this->assertSame($minimum->id, (int) $old->refresh()->app_outdated_notified_release_id);
        $this->assertNull($current->refresh()->app_outdated_notified_release_id);
        $this->assertNull($newer->refresh()->app_outdated_notified_release_id);

        // Run again (and the next day): not again for the same tills
        $this->assertSame(0, $this->notice());
        Carbon::setTestNow(now()->addDay());
        $this->assertSame(0, $this->notice());
        $this->assertCount(1, $this->sent());

        // A till that joins later below the minimum is told about alone
        $this->till('Kassa 5', '1.0.0+9');
        $this->assertSame(1, $this->notice());
        $this->assertCount(2, $this->sent());
        $this->assertStringContainsString('Kassa 5', $this->sent()[1]['text']);
        $this->assertStringNotContainsString('Kassa 1', $this->sent()[1]['text']);

        // A new, higher minimum is a new period: every till still below it is told once more
        $this->release('1.3.0', ['is_minimum' => true, 'minimum_from' => now()->subMinute()]);
        $this->assertSame(1, $this->notice());
        $this->assertCount(3, $this->sent());
        $this->assertStringContainsString('Kassa 1', $this->sent()[2]['text']);
        $this->assertStringContainsString('Kassa 3', $this->sent()[2]['text']); // 1.2.0 is below 1.3.0 now
    }

    public function test_revoked_silent_other_app_and_other_platform_tills_are_not_mentioned(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->subMinute()]);
        $this->till('Bekor', '1.0.0', ['revoked_at' => now()]);
        $this->till('Uxlab qolgan', '1.0.0', ['last_seen_at' => now()->subDays(40)]);
        $this->till('Windows kassa', '1.0.0', ['platform' => 'windows']);
        $this->till('Ofitsiant', '1.0.0', ['app' => 'waiter']);
        $this->till('Versiyasiz', '1.0.0', ['app_version' => null]);

        $this->assertSame(0, $this->notice());
        $this->assertSame([], $this->sent());

        // A till with no recorded app runs the default cashier app
        $this->till('Eski yozuv', '1.0.0', ['app' => null]);
        $this->assertSame(1, $this->notice());
        $this->assertStringContainsString('Eski yozuv', $this->sent()[0]['text']);
    }

    public function test_a_withdrawn_minimum_sends_nothing_and_the_command_is_scheduled(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->subDay(), 'withdrawn_at' => now()]);
        $this->till('Kassa 1', '1.0.0');

        $this->assertSame(0, $this->notice());

        $this->release('1.3.0', ['is_minimum' => true, 'minimum_from' => now()->subDay()]);
        $this->assertSame(0, Artisan::call('pos:notify-outdated-apps'));
        $this->assertStringContainsString('1 outdated-app owner message(s) sent', Artisan::output());
        $this->assertCount(1, $this->sent());

        $scheduled = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->contains(fn ($event) => str_contains((string) $event->command, 'pos:notify-outdated-apps'));
        $this->assertTrue($scheduled);
    }

    public function test_the_notice_never_touches_the_till_itself(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->subDay()]);
        $device = $this->till('Kassa 1', '1.0.0');
        $before = $device->only(['app_version', 'revoked_at', 'name']) + ['seen' => (string) $device->last_seen_at];

        $this->notice();

        $device->refresh();
        $this->assertSame($before, $device->only(['app_version', 'revoked_at', 'name']) + ['seen' => (string) $device->last_seen_at]);
    }
}
