<?php

namespace Tests\Feature\PosLan;

use App\Domain\Pos\Lan\BranchLan;
use App\Domain\Pos\Lan\LanKeys;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\DeviceApp;
use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Events\Broadcast\PosLanChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLanSettings;
use App\Models\Device;
use App\Models\Order;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\DeviceService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\PosSync\PosSyncFixtures;
use Tests\TestCase;

/**
 * P6 review fixes (S4, C5, S8, S5, C8; docs/POS_SPEC.md §5.5): a terminal paired before the app was recorded (`app`
 * null) is the default hub only when it is the printing till or has had a shift on it (a waiter's phone never opens
 * one); the apps say which app they are on every request (X-Rizqim-App: recorded on such a terminal, and a waiter,
 * kitchen or display app never gets the hub's role); the hub stays where its secret went while that till is active
 * (pairing a new cashier till does not move it); the secret moving is audited and every change the hub must hear
 * (revoke, rotation, hub move) is announced on the branch channel; the server measures a relayed operation's staff
 * session against the operation's time, and the grant ends 36 hours after the PIN sign-in.
 */
class PosLanHubTrustTest extends TestCase
{
    use PosSyncFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosSync();
    }

    /**
     * @return array{device: Device, token: string}
     */
    private function pairApp(Branch $branch, ?string $app): array
    {
        $service = app(DevicePairingService::class);
        $code = $service->createCode($this->owner->store, $branch, DeviceType::POS, 'Qurilma', $this->owner)['code'];

        return $service->pair($code, array_filter(['platform' => 'android', 'app' => $app]));
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>|null
     */
    private function lanWith(string $token, array $headers = []): ?array
    {
        $this->withBearer($token);

        return $this->withHeaders($headers)->getJson('/api/v1/pos/lan')->assertOk()->json('data');
    }

    private function openShift(Device $device, \App\Models\User $cashier): void
    {
        app(ShiftService::class)->open($cashier, $device, Money::of('0'));
    }

    public function test_a_legacy_waiter_phone_never_gets_the_branch_secret_nor_relays_as_the_till(): void
    {
        $branch = $this->otherBranch;
        $phone = $this->pairApp($branch, null);   // the waiter's phone, paired first
        $till = $this->pairApp($branch, null);    // the cashier's till, no shift yet
        $waiter = $this->makeStaff(UserRole::WAITER, $branch);
        $cashier = $this->makeStaff(UserRole::CASHIER, $branch);
        $this->pinSession($phone, $waiter, '7391');
        $this->pinSession($till, $cashier, '8264');

        $lan = $this->lanWith($phone['token']);
        $this->assertSame('client', $lan['role']);
        $this->assertNull($lan['hub_secret'], 'a legacy terminal without a shift was given the branch secret');
        $this->assertNull($lan['hub']['device'], 'no till proved to be a till yet: no hub');

        // The phone relays an operation as the till and its cashier: refused
        $order = (string) Str::uuid();
        $result = $this->sync([
            $this->op('order.open', [
                'order' => $order,
                'local_number' => 'K9-' . random_int(100, 999),
                'channel' => 'pos_hall',
                'table_id' => null,
                'guests_count' => 2,
                'items' => [$this->line($this->shashlik, '1')],
                'send' => true,
                'menu_taken_at' => now()->subHour()->toJSON(),
            ], $cashier, 5) + ['origin_device' => (string) $till['device']->uuid],
        ], [], $phone['token'])->assertOk()->json('data.results.0');
        $this->assertSame(['rejected', 'relay_not_hub'], [$result['status'], $result['reason']]);
        $this->assertFalse(Order::where('external_id', $order)->exists());
    }

    public function test_the_default_hub_is_the_legacy_till_that_has_had_a_shift_never_the_phone_paired_before_it(): void
    {
        $branch = $this->otherBranch;
        $phone = $this->pairApp($branch, null);
        $waiter = $this->makeStaff(UserRole::WAITER, $branch);
        $this->pinSession($phone, $waiter, '7391');
        $till = $this->pairApp($branch, null);
        $cashier = $this->makeStaff(UserRole::CASHIER, $branch);
        $token = $this->pinSession($till, $cashier, '8264');
        $this->openShift($till['device'], $cashier);

        $this->assertNull(Device::find($phone['device']->id)->app);
        $lan = $this->tillAs('GET', '/pos/bootstrap', [], $token)->assertOk()->json('data.lan');
        $this->assertSame((string) $till['device']->uuid, $lan['hub']['device']);
        $this->assertSame('hub', $lan['role']);
        $this->assertSame('client', $this->lanWith($phone['token'])['role']);

        // The owner still sees both and may choose either (their explicit choice)
        $this->assertCount(2, app(BranchLan::class)->candidates($branch));
    }

    public function test_the_apps_say_which_app_they_are_and_only_the_cashier_app_takes_the_hub_role(): void
    {
        $branch = $this->otherBranch;
        $phone = $this->pairApp($branch, null);
        $till = $this->pairApp($branch, null);

        // An updated waiter app on a legacy phone: recorded, never the hub
        $lan = $this->lanWith($phone['token'], ['X-Rizqim-App' => 'waiter']);
        $this->assertSame('client', $lan['role']);
        $this->assertSame(DeviceApp::WAITER, Device::find($phone['device']->id)->app);

        // An updated cashier app on the legacy till: recorded, the hub
        $lan = $this->lanWith($till['token'], ['X-Rizqim-App' => 'cashier']);
        $this->assertSame('hub', $lan['role']);
        $this->assertNotNull($lan['hub_secret']);
        $this->assertSame(DeviceApp::CASHIER, Device::find($till['device']->id)->app);

        // What was recorded stays; an app of another terminal type or an unknown one is ignored
        $this->lanWith($till['token'], ['X-Rizqim-App' => 'kassa']);
        $this->assertSame(DeviceApp::CASHIER, Device::find($till['device']->id)->app);
        $display = $this->pairTerminal($this->owner, $branch, DeviceType::DISPLAY);
        $this->lanWith($display['token'], ['X-Rizqim-App' => 'waiter']);
        $this->assertNull(Device::find($display['device']->id)->app);

        // The hub's own token, saying it is another app (a waiter app put on the till), gets no secret
        $lan = $this->lanWith($till['token'], ['X-Rizqim-App' => 'waiter']);
        $this->assertSame('client', $lan['role']);
        $this->assertNull($lan['hub_secret']);
        $this->assertNull($lan['devices']);
        // Even when the owner picked it
        $this->withBearer($this->owner->createToken('web')->plainTextToken);
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $branch->id, 'hub_device_id' => (string) $till['device']->uuid])->assertOk();
        $this->assertNull($this->lanWith($till['token'], ['X-Rizqim-App' => 'kds'])['hub_secret']);
        $this->assertSame('hub', $this->lanWith($till['token'], ['X-Rizqim-App' => 'cashier'])['role']);
    }

    public function test_pairing_a_new_cashier_till_does_not_move_the_hub_from_the_working_till(): void
    {
        // The fixture's till (paired before the app was recorded, with a shift) holds the hub's secret
        $this->assertSame('hub', $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.lan.role'));

        $new = $this->pairApp($this->branch, 'cashier');
        $this->assertSame('client', $this->lanWith($new['token'])['role']);
        $this->assertSame('hub', $this->tillAs('GET', '/pos/bootstrap')->assertOk()->json('data.lan.role'));
        $this->assertSame((string) $this->till['device']->uuid, (string) app(BranchLan::class)->hubOf($this->branch)?->uuid);

        // Revoked: the new cashier till takes over
        app(DeviceService::class)->revoke(Device::find($this->till['device']->id), $this->owner);
        $this->assertSame('hub', $this->lanWith($new['token'])['role']);
    }

    public function test_the_secret_moving_to_another_device_is_audited_without_the_key(): void
    {
        $this->tillAs('GET', '/pos/bootstrap')->assertOk()->assertJsonPath('data.lan.role', 'hub');
        $first = AuditLog::where('action', 'pos.lan_hub_changed')->sole();
        $this->assertSame((string) $this->till['device']->uuid, $first->after['device'] ?? null);
        // Asked again by the same hub: nothing new
        $this->tillAs('GET', '/pos/bootstrap')->assertOk();
        $this->assertSame(1, AuditLog::where('action', 'pos.lan_hub_changed')->count());

        $next = $this->pairApp($this->branch, 'cashier');
        $this->withBearer($this->owner->createToken('web')->plainTextToken);
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $this->branch->id, 'hub_device_id' => (string) $next['device']->uuid])->assertOk();
        $this->assertSame('hub', $this->lanWith($next['token'])['role']);

        $moved = AuditLog::where('action', 'pos.lan_hub_changed')->orderByDesc('id')->first();
        $this->assertSame(2, AuditLog::where('action', 'pos.lan_hub_changed')->count());
        $this->assertSame((string) $this->till['device']->uuid, $moved->before['device'] ?? null);
        $this->assertSame((string) $next['device']->uuid, $moved->after['device'] ?? null);
        $this->assertSame((int) $this->branch->id, (int) $moved->branch_id);
        $this->assertSame((int) $next['device']->id, (int) $moved->device_id);
        $secret = (string) BranchLanSettings::where('branch_id', $this->branch->id)->value('secret');
        $this->assertStringNotContainsString($secret, (string) json_encode($moved->toArray()));
    }

    public function test_a_revoke_a_rotation_and_a_hub_move_are_announced_to_the_branch(): void
    {
        Event::fake([PosLanChangedBroadcast::class]);
        $ownerToken = $this->owner->createToken('web')->plainTextToken;
        $this->tillAs('GET', '/pos/bootstrap')->assertOk();
        $phone = $this->pairApp($this->branch, 'waiter');
        $second = $this->pairApp($this->branch, 'cashier');

        $seen = function (string $reason, ?string $device): void {
            Event::assertDispatched(PosLanChangedBroadcast::class, function (PosLanChangedBroadcast $event) use ($reason, $device) {
                $payload = $event->broadcastWith();

                return $event->broadcastAs() === 'pos.lan_changed'
                    && array_map(fn ($channel) => $channel->name, $event->broadcastOn()) === ["private-pos-branch.{$this->branch->id}"]
                    && $payload['reason'] === $reason
                    && $payload['branch_id'] === (int) $this->branch->id
                    && $payload['device'] === $device
                    && array_keys($payload) === ['branch_id', 'reason', 'device', 'changed_at'];
            });
        };

        app(DeviceService::class)->revoke(Device::find($phone['device']->id), $this->owner);
        $seen('device_revoked', (string) $phone['device']->uuid);

        $this->withBearer($ownerToken);
        $this->postJson('/api/v1/owner/pos/lan/rotate', ['branch_id' => $this->branch->id])->assertOk();
        $seen('key_rotated', null);

        $this->withBearer($ownerToken);
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $this->branch->id, 'hub_device_id' => (string) $second['device']->uuid])->assertOk();
        $seen('hub_changed', (string) $second['device']->uuid);

        $this->withBearer($ownerToken);
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $this->branch->id, 'port' => 19001])->assertOk();
        $seen('port_changed', null);

        // A device of no branch, or the same settings again: nothing
        $count = count(Event::dispatched(PosLanChangedBroadcast::class));
        $this->withBearer($ownerToken);
        $this->putJson('/api/v1/owner/pos/lan', ['branch_id' => $this->branch->id, 'port' => 19001])->assertOk();
        app(DeviceService::class)->revoke(Device::find($this->pairTerminal($this->owner, null)['device']->id), $this->owner);
        $this->assertCount($count, Event::dispatched(PosLanChangedBroadcast::class));
        // No key in any of them
        $secret = (string) BranchLanSettings::where('branch_id', $this->branch->id)->value('secret');
        foreach (Event::dispatched(PosLanChangedBroadcast::class) as [$event]) {
            $this->assertStringNotContainsString($secret, (string) json_encode($event->broadcastWith()));
        }
    }

    public function test_a_long_outage_late_in_a_session_syncs_and_the_grant_ends_with_the_session_window(): void
    {
        // The waiter signs in on their phone; the phone reads its grant 10 hours later
        $phone = $this->pairApp($this->branch, 'waiter');
        $signedIn = now();
        $token = $this->pinSession($phone, $this->waiter);
        Carbon::setTestNow($signedIn->copy()->addHours(10));
        $lan = $this->tillAs('GET', '/pos/bootstrap', [], $token)->assertOk()->json('data.lan');
        $secret = (string) base64_decode((string) BranchLanSettings::where('branch_id', $this->branch->id)->value('secret'), true);
        $grant = LanKeys::verifyGrant($secret, (string) $lan['grant']);
        $this->assertSame($signedIn->copy()->addHours(36)->getTimestamp(), $grant['exp'], 'the grant ends 36 h after the PIN sign-in');

        // 30 hours after the sign-in (the grant still good) the internet goes; the hub takes a round on the LAN and
        // relays it when the internet comes back 20 hours later
        Carbon::setTestNow($signedIn->copy()->addHours(50));
        $order = (string) Str::uuid();
        $op = $this->op('order.open', [
            'order' => $order,
            'local_number' => 'K7-' . random_int(100, 999),
            'channel' => 'pos_hall',
            'table_id' => null,
            'guests_count' => 2,
            'items' => [$this->line($this->shashlik, '1')],
            'send' => true,
            'menu_taken_at' => now()->subHours(21)->toJSON(),
        ], $this->waiter, 20 * 60) + ['origin_device' => (string) $phone['device']->uuid];
        $result = $this->sync([$op])->assertOk()->json('data.results.0');
        $this->assertNotSame('staff_not_signed_in', $result['reason'] ?? null, (string) json_encode($result));
        $this->assertTrue(Order::where('external_id', $order)->exists(), (string) json_encode($result));

        // Done after the grant would have ended (37 hours after the sign-in): still refused
        $late = $this->op('order.open', ['order' => (string) Str::uuid()] + $op['payload'], $this->waiter, 13 * 60) + ['origin_device' => (string) $phone['device']->uuid];
        $this->assertSame('staff_not_signed_in', $this->sync([$late])->assertOk()->json('data.results.0.reason'));
    }
}
