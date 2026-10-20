<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Device;
use App\Models\DevicePairingCode;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Personal devices, store device lists, one-time pairing codes and terminal tokens, with tenant isolation.
 */
class DevicePairingTest extends TestCase
{
    use RefreshDatabase;
    use AccessFixtures;

    private Store $foreignStore;
    private Branch $foreignBranch;
    private User $foreignOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->foreignStore = $this->makeStore('Foreign Grill');
        $this->foreignBranch = $this->makeBranch($this->foreignStore, 'Foreign Branch');
        $this->foreignOwner = $this->makeStaff(UserRole::OWNER, null, $this->foreignStore);
    }

    private function createCode(User $user, array $payload = ['type' => 'pos'])
    {
        return $this->actingAs($user, 'sanctum')->postJson('/api/v1/owner/devices/pairing-codes', $payload);
    }

    private function pair(string $code, array $extra = [])
    {
        return $this->withBearer(null)->postJson('/api/v1/devices/pair', ['code' => $code] + $extra);
    }

    public function test_owner_pairs_a_terminal_with_a_one_time_code(): void
    {
        $created = $this->createCode($this->owner, ['type' => 'pos', 'branch_id' => $this->branch->id, 'name' => 'Kassa 1'])
            ->assertCreated()
            ->json('data');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $created['code']);
        $this->assertDatabaseMissing('device_pairing_codes', ['code_hash' => $created['code']]);
        $this->assertDatabaseHas('device_pairing_codes', ['code_hash' => DevicePairingService::hash($created['code'])]);

        $paired = $this->pair($created['code'], ['platform' => 'sunmi', 'app_version' => '0.1.0'])->assertCreated()->json('data');

        $this->assertSame('pos', $paired['device']['type']);
        $this->assertSame('Kassa 1', $paired['device']['name']);
        $this->assertSame($this->branch->id, $paired['device']['branch_id']);
        $this->assertNull($paired['device']['user']);

        $device = Device::where('uuid', $paired['device']['uuid'])->sole();
        $this->assertNull($device->user_id);
        $this->assertSame($this->store->id, $device->store_id);

        // The terminal is itself, not a user
        $this->bearerJson('GET', '/api/v1/terminal', $paired['token'])->assertOk()->assertJsonPath('data.device.uuid', $device->uuid);

        // Single use
        $this->pair($created['code'])->assertUnprocessable()->assertJsonPath('data.reason', 'pairing_invalid');
    }

    public function test_a_terminal_token_outlives_the_user_token_lifetime_but_not_its_device(): void
    {
        ['device' => $device, 'token' => $token] = $this->pairTerminal($this->owner);

        $this->travel(60)->days();
        $this->bearerJson('GET', '/api/v1/terminal', $token)->assertOk();

        // The scheduled pruning keeps it too (sanctum:prune-expired would not)
        $this->artisan('auth:prune-tokens')->assertSuccessful();
        $this->bearerJson('GET', '/api/v1/terminal', $token)->assertOk();

        $device->forceFill(['revoked_at' => now()])->save();
        $this->bearerJson('GET', '/api/v1/terminal', $token)->assertUnauthorized();
    }

    public function test_terminal_and_user_tokens_never_cross_over(): void
    {
        ['token' => $terminalToken] = $this->pairTerminal($this->owner);
        $userToken = $this->owner->createToken('mobile-api')->plainTextToken;

        $this->bearerJson('GET', '/api/v1/user', $terminalToken)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/orders', $terminalToken)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/owner/devices', $terminalToken)->assertUnauthorized();
        $this->bearerJson('GET', '/api/v1/terminal', $userToken)->assertUnauthorized();
    }

    public function test_expired_and_wrong_codes_get_the_same_answer(): void
    {
        $code = $this->createCode($this->owner)->assertCreated()->json('data.code');
        $wrong = $code === '000000' ? '000001' : '000000';

        $a = $this->pair($wrong)->assertUnprocessable()->json();
        $this->travel(11)->minutes();
        $b = $this->pair($code)->assertUnprocessable()->json();
        $c = $this->pair('abc')->assertUnprocessable()->json();

        $this->assertSame($a, $b);
        $this->assertSame($a, $c);
        $this->assertSame(0, Device::count());
    }

    public function test_pairing_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->pair(str_pad((string) $i, 6, '9', STR_PAD_LEFT))->assertUnprocessable();
        }

        $this->pair('123456')->assertStatus(429);
    }

    public function test_code_creation_is_capped_and_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createCode($this->owner)->assertCreated();
        }

        // Five live codes per store at most (past the per-minute request limit, the codes still live)
        $this->travel(61)->seconds();
        $this->createCode($this->owner)->assertStatus(429)->assertJsonPath('data.reason', 'pairing_limit');
        $this->assertSame(5, DevicePairingCode::count());

        // And five requests a minute per person
        $this->travel(11)->minutes();
        for ($i = 0; $i < 5; $i++) {
            $this->createCode($this->owner)->assertCreated();
        }
        $this->createCode($this->owner)->assertStatus(429)->assertJsonMissingPath('data.reason');
    }

    public function test_who_may_create_codes(): void
    {
        $cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $customer = $this->makeCustomer();

        $this->createCode($cashier)->assertForbidden();
        $this->createCode($this->courier)->assertForbidden();
        $this->createCode($customer)->assertForbidden();

        // A branch manager: their own branch only, which is also the default
        $this->createCode($this->manager, ['type' => 'kds'])->assertCreated()->assertJsonPath('data.branch_id', $this->branch->id);
        $this->createCode($this->manager, ['type' => 'kds', 'branch_id' => $this->otherBranch->id])->assertUnprocessable();

        // Nobody pairs for another store's branch
        $this->createCode($this->owner, ['type' => 'pos', 'branch_id' => $this->foreignBranch->id])->assertUnprocessable();
        // Only terminals are paired with a code
        $this->createCode($this->owner, ['type' => 'customer_mobile'])->assertUnprocessable();
    }

    public function test_owners_list_rename_and_revoke_only_their_own_store_devices(): void
    {
        ['device' => $ours, 'token' => $ourToken] = $this->pairTerminal($this->owner, $this->branch);
        ['device' => $theirs, 'token' => $theirToken] = $this->pairTerminal($this->foreignOwner, $this->foreignBranch);

        $list = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/devices')->assertOk()->json('data');
        $this->assertSame([$ours->uuid], array_column($list, 'uuid'));
        $this->assertArrayNotHasKey('push_token', $list[0]);

        // Another store's device simply does not exist here
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/devices/{$theirs->uuid}", ['name' => 'Hacked'])->assertNotFound();
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/devices/{$theirs->uuid}/revoke")->assertNotFound();
        $this->assertNull($theirs->fresh()->revoked_at);
        $this->bearerJson('GET', '/api/v1/terminal', $theirToken)->assertOk();

        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/devices/{$ours->uuid}", ['name' => 'Kassa 2'])->assertOk();
        $this->assertSame('Kassa 2', $ours->fresh()->name);

        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/devices/{$ours->uuid}/revoke")->assertOk();
        $this->assertNotNull($ours->fresh()->revoked_at);
        $this->bearerJson('GET', '/api/v1/terminal', $ourToken)->assertUnauthorized();
        $this->assertSame(0, PersonalAccessToken::where('device_id', $ours->id)->count());
    }

    public function test_a_branch_manager_sees_and_manages_their_branch_devices_only(): void
    {
        ['device' => $main] = $this->pairTerminal($this->owner, $this->branch);
        ['device' => $second] = $this->pairTerminal($this->owner, $this->otherBranch);
        ['device' => $storeWide] = $this->pairTerminal($this->owner);

        $list = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/devices')->assertOk()->json('data');
        $this->assertSame([$main->uuid], array_column($list, 'uuid'));

        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/owner/devices/{$second->uuid}/revoke")->assertNotFound();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/owner/devices/{$storeWide->uuid}/revoke")->assertNotFound();
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/owner/devices/{$main->uuid}/revoke")->assertOk();

        // Floor staff do not manage devices at all
        $this->actingAs($this->makeStaff(UserRole::CASHIER, $this->branch), 'sanctum')->getJson('/api/v1/owner/devices')->assertForbidden();
    }

    public function test_users_register_update_list_and_revoke_their_own_devices(): void
    {
        $courierDevice = $this->actingAs($this->courier, 'sanctum')->postJson('/api/v1/devices', [
            'type' => 'courier', 'name' => 'Redmi', 'platform' => 'android', 'app_version' => '1.0', 'push_token' => 'fcm-secret',
        ])->assertCreated()->json('data');

        $this->assertTrue($courierDevice['has_push_token']);
        $this->assertArrayNotHasKey('push_token', $courierDevice);
        $this->assertSame($this->store->id, $courierDevice['store_id']);

        $this->actingAs($this->courier, 'sanctum')->postJson('/api/v1/devices', [
            'type' => 'courier', 'uuid' => $courierDevice['uuid'], 'app_version' => '1.1',
        ])->assertOk()->assertJsonPath('data.app_version', '1.1');
        $this->assertSame(1, Device::count());

        $this->actingAs($this->courier, 'sanctum')->postJson('/api/v1/devices', ['type' => 'pos'])->assertUnprocessable();

        // Somebody else's device: not found
        $this->actingAs($this->customer, 'sanctum')->deleteJson("/api/v1/devices/{$courierDevice['uuid']}")->assertNotFound();
        $this->assertSame([], $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/devices')->assertOk()->json('data'));

        $this->assertSame([$courierDevice['uuid']], array_column($this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/devices')->json('data'), 'uuid'));
        $this->actingAs($this->courier, 'sanctum')->deleteJson("/api/v1/devices/{$courierDevice['uuid']}")->assertOk();
        $this->assertNull(Device::first()->push_token);
        $this->assertSame([], $this->actingAs($this->courier, 'sanctum')->getJson('/api/v1/devices')->json('data'));

        // The owner sees the staff member's app in the store list (until revoked)
        $this->assertSame(DeviceType::COURIER, Device::first()->type);
    }

    public function test_a_user_has_a_bounded_number_of_devices(): void
    {
        config(['auth.tokens.max_devices_per_user' => 3]);

        foreach (range(1, 5) as $i) {
            $this->actingAs($this->customer, 'sanctum')->postJson('/api/v1/devices', ['type' => 'customer_mobile', 'name' => "Phone {$i}"])->assertCreated();
            $this->travel(1)->seconds();
        }

        $this->assertSame(3, Device::where('user_id', $this->customer->id)->active()->count());
        $this->assertEqualsCanonicalizing(['Phone 3', 'Phone 4', 'Phone 5'], Device::active()->pluck('name')->all());
    }
}
