<?php

namespace Tests\Feature\PosReleases;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\DevicePairingCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\Feature\StaffOrderFixtures;
use Tests\TestCase;

/**
 * Wave M5 S5-a (docs/scale/M5_DESIGN.md section 6): the version header the guard records, `app_release` on
 * GET /terminal, and pairing refusing a build below the minimum (426 app_outdated).
 */
class PosReleaseTerminalTest extends TestCase
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

    private function terminal(array $till, ?string $version = null, array $headers = []): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($till['token'])->withHeaders($headers + ($version !== null ? [self::HEADER => $version] : []))->getJson('/api/v1/terminal');
    }

    /**
     * @return list<string> the UPDATE statements on devices the callback ran
     */
    private function deviceUpdates(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $log = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return array_values(array_filter($log, fn (string $sql) => str_starts_with($sql, 'update "devices"')));
    }

    // ---- the version header ----

    public function test_the_version_header_is_stored_in_the_existing_heartbeat_save_only_when_it_changed(): void
    {
        $till = $this->till();

        // Stale heartbeat + a version never seen: ONE update carries both columns
        $updates = $this->deviceUpdates(fn () => $this->terminal($till, '1.2.0+5')->assertOk());
        $this->assertCount(1, $updates);
        $this->assertStringContainsString('"app_version"', $updates[0]);
        $this->assertStringContainsString('"last_seen_at"', $updates[0]);
        $this->assertSame('1.2.0+5', $till['device']->refresh()->app_version);

        // Heartbeat due again, same version: the save does not touch app_version
        Carbon::setTestNow(now()->addMinutes(2));
        $updates = $this->deviceUpdates(fn () => $this->terminal($till, '1.2.0+5')->assertOk());
        $this->assertCount(1, $updates);
        $this->assertStringNotContainsString('"app_version"', $updates[0]);

        // A changed version inside the minute: no write at all (it rides on the next heartbeat)
        $updates = $this->deviceUpdates(fn () => $this->terminal($till, '1.3.0+6')->assertOk());
        $this->assertSame([], $updates);
        $this->assertSame('1.2.0+5', $till['device']->refresh()->app_version);

        // The next heartbeat carries it
        Carbon::setTestNow(now()->addMinutes(2));
        $updates = $this->deviceUpdates(fn () => $this->terminal($till, '1.3.0+6')->assertOk());
        $this->assertCount(1, $updates);
        $this->assertSame('1.3.0+6', $till['device']->refresh()->app_version);
    }

    public function test_a_missing_or_unreadable_version_header_never_overwrites_the_stored_version(): void
    {
        $till = $this->till(['app_version' => '1.0.0+1']);

        $this->terminal($till)->assertOk();
        Carbon::setTestNow(now()->addMinutes(2));
        $this->terminal($till, "1.0.0'; drop table devices;--")->assertOk();
        Carbon::setTestNow(now()->addMinutes(2));
        $this->terminal($till, str_repeat('9', 40))->assertOk();

        $this->assertSame('1.0.0+1', $till['device']->refresh()->app_version);
    }

    // ---- app_release on GET /terminal ----

    public function test_an_empty_registry_means_no_app_release(): void
    {
        $till = $this->till(['app_version' => '0.1.0']);

        $response = $this->terminal($till, '0.1.0+1')->assertOk();
        $this->assertArrayHasKey('app_release', $response->json('data'));
        $this->assertNull($response->json('data.app_release'));

        // Releases of another app or platform tell this till nothing either
        $this->release('2.0.0', ['app' => 'kds']);
        $this->release('2.0.0', ['platform' => 'windows']);
        $this->assertNull($this->terminal($till, '0.1.0+1')->json('data.app_release'));

        // A till that never said its platform cannot be judged
        $unknown = $this->till(['platform' => null]);
        $this->release('2.0.0');
        $this->assertNull($this->terminal($unknown, '0.1.0+1')->json('data.app_release'));
    }

    public function test_the_states_ok_update_available_and_update_required(): void
    {
        $this->release('1.0.0');
        $latest = $this->release('1.4.0', ['build' => 7, 'notes' => 'Yangi: chek chiqarish tezroq']);
        $till = $this->till();

        // The state is judged on the stored version (the header lands with the next heartbeat)
        $state = function (string $version) use ($till): array {
            $till['device']->forceFill(['app_version' => $version])->save();

            return $this->terminal($till)->assertOk()->json('data.app_release');
        };

        // Up to date
        $this->assertSame([
            'current' => '1.4.0+7', 'latest' => '1.4.0+7', 'minimum' => null, 'state' => 'ok',
            'url' => $latest->file_url, 'notes' => 'Yangi: chek chiqarish tezroq', 'minimum_from' => null,
        ], $state('1.4.0+7'));

        // A newer build of the same version is newer too
        $this->assertSame('update_available', $state('1.4.0+6')['state']);
        $this->assertSame('update_available', $state('1.3.9+99')['state']);
        // The till runs something newer than the registry (a pilot build): nothing to say
        $this->assertSame('ok', $state('1.5.0+1')['state']);

        // A till that never reported a version is not nagged
        $till['device']->forceFill(['app_version' => null])->save();
        $row = $this->terminal($till)->json('data.app_release');
        $this->assertSame(['ok', null], [$row['state'], $row['current']]);
    }

    public function test_below_the_minimum_the_notice_turns_red_only_from_minimum_from(): void
    {
        $this->release('1.0.0');
        $minimum = $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->addDays(14)]);
        $this->release('1.4.0', ['build' => 7]);
        $till = $this->till(['app_version' => '1.1.0+3']);

        // Inside the 14 days: still the quiet notice
        $row = $this->terminal($till)->assertOk()->json('data.app_release');
        $this->assertSame(['update_available', '1.2.0+1', $minimum->minimum_from->toIso8601String()], [$row['state'], $row['minimum'], $row['minimum_from']]);

        // After: red
        Carbon::setTestNow(now()->addDays(14)->addMinute());
        $this->assertSame('update_required', $this->terminal($till)->json('data.app_release.state'));

        // At the minimum or above: no longer required
        $till['device']->forceFill(['app_version' => '1.2.0+1'])->save();
        $this->assertSame('update_available', $this->terminal($till)->json('data.app_release.state'));
        $till['device']->forceFill(['app_version' => '1.4.0+7'])->save();
        $this->assertSame('ok', $this->terminal($till)->json('data.app_release.state'));
    }

    public function test_withdrawn_releases_are_not_latest_or_minimum(): void
    {
        $this->release('1.0.0');
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->subDay(), 'withdrawn_at' => now()]);
        $this->release('2.0.0', ['withdrawn_at' => now()]);
        $till = $this->till(['app_version' => '1.0.0+1']);

        $row = $this->terminal($till)->assertOk()->json('data.app_release');
        $this->assertSame(['ok', '1.0.0+1', null], [$row['state'], $row['latest'], $row['minimum']]);
    }

    public function test_the_app_comes_from_the_device_or_its_type_default(): void
    {
        $this->release('3.0.0', ['app' => 'kds', 'platform' => 'windows']);
        $this->release('9.0.0', ['app' => 'cashier', 'platform' => 'windows']);
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        // An old record: no app, the platform written in capitals
        $kds['device']->forceFill(['app' => null, 'platform' => 'Windows', 'app_version' => '2.0.0+1', 'last_seen_at' => now()->subHour()])->save();

        $row = $this->terminal($kds)->assertOk()->json('data.app_release');
        $this->assertSame(['3.0.0+1', 'update_available'], [$row['latest'], $row['state']]);
    }

    // ---- pairing ----

    private function code(): string
    {
        return app(\App\Services\Auth\DevicePairingService::class)->createCode($this->owner->store, $this->branch, DeviceType::POS, 'Kassa', $this->owner)['code'];
    }

    public function test_pairing_below_the_minimum_answers_426_app_outdated_and_keeps_the_code(): void
    {
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->addDays(14)]);
        $latest = $this->release('1.4.0', ['build' => 7]);
        $code = $this->code();

        $refused = $this->postJson('/api/v1/devices/pair', ['code' => $code, 'platform' => 'android', 'app' => 'cashier', 'app_version' => '1.1.0+3'])->assertStatus(426);
        $this->assertSame(['success' => false, 'reason' => 'app_outdated', 'current' => '1.1.0+3', 'minimum' => '1.2.0+1', 'url' => $latest->file_url], [
            'success' => $refused->json('success'), ...$refused->json('data'),
        ]);
        $this->assertNotEmpty($refused->json('message'));
        // Pairing is setup: the code was not spent, no device appeared
        $this->assertSame(0, DevicePairingCode::whereNotNull('used_at')->count());

        // The version may also come in the header
        $this->withHeaders([self::HEADER => '1.1.9+1', 'X-Rizqim-App' => 'cashier'])
            ->postJson('/api/v1/devices/pair', ['code' => $code, 'platform' => 'Android'])->assertStatus(426);

        // At the minimum: pairs
        $this->postJson('/api/v1/devices/pair', ['code' => $code, 'platform' => 'android', 'app' => 'cashier', 'app_version' => '1.2.0+1'])->assertCreated();
    }

    public function test_pairing_is_never_refused_without_a_registry_minimum_or_without_details(): void
    {
        // No registry at all
        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'android', 'app' => 'cashier', 'app_version' => '0.0.1'])->assertCreated();

        // A registry, but no minimum
        $this->release('1.4.0');
        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'android', 'app' => 'cashier', 'app_version' => '0.0.1'])->assertCreated();

        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => now()->addDays(14)]);
        // Missing, unreadable, other app or other platform: cannot be judged, pairs as before
        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'android', 'app' => 'cashier'])->assertCreated();
        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'android', 'app' => 'cashier', 'app_version' => 'abc'])->assertCreated();
        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'windows', 'app' => 'cashier', 'app_version' => '0.0.1'])->assertCreated();
        $this->postJson('/api/v1/devices/pair', ['code' => $this->code(), 'platform' => 'android', 'app' => 'waiter', 'app_version' => '0.0.1'])->assertCreated();
    }
}
