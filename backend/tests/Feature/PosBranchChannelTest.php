<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Events\Broadcast\OrderAssignedBroadcast;
use App\Events\Broadcast\OrderCreatedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Order\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * P2-11: private-pos-branch.{branchId}: everyone working in the branch (orders.view there) with their own token or a
 * PIN session (only on its terminal's branch), and the branch's paired terminals with their terminal token. Nobody
 * from another branch or store, no courier, customer or guest. Order events reach it with the same minimal payload.
 */
class PosBranchChannelTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private Store $foreignStore;
    private Branch $foreignBranch;

    protected function setUp(): void
    {
        parent::setUp();

        // A real signing broadcaster (BroadcastChannelAuthTest): the channels are registered again on it
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $this->setUpLifecycleStore();
        $this->foreignStore = $this->makeStore('Foreign Somsa');
        $this->foreignBranch = $this->makeBranch($this->foreignStore, 'Foreign Main');
    }

    private function join(User|string|null $who, string $channel)
    {
        $body = ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"];

        return $who instanceof User
            ? $this->withBearer(null)->actingAs($who, 'sanctum')->postJson('/api/broadcasting/auth', $body)
            : $this->bearerJson('POST', '/api/broadcasting/auth', $who, $body);
    }

    private function assertCanJoin(User|string $who, string $channel): void
    {
        $response = $this->join($who, $channel)->assertOk();
        $expected = hash_hmac('sha256', "1234.5678:private-{$channel}", 'test-secret');
        $this->assertSame("test-key:{$expected}", $response->json('auth'));
    }

    private function assertCannotJoin(User|string $who, string $channel): void
    {
        $this->join($who, $channel)->assertForbidden();
    }

    public function test_branch_staff_join_their_branch_only(): void
    {
        $pos = "pos-branch.{$this->branch->id}";
        $other = "pos-branch.{$this->otherBranch->id}";

        foreach ([UserRole::COOK, UserRole::CASHIER, UserRole::WAITER, UserRole::MANAGER] as $role) {
            $staff = $this->makeStaff($role, $this->branch);
            $this->assertCanJoin($staff, $pos);
            $this->assertCannotJoin($staff, $other);
            $this->assertCannotJoin($staff, "pos-branch.{$this->foreignBranch->id}");
        }

        // The owner and store-wide staff: every branch of the store
        foreach ([$this->owner, $this->makeStaff(UserRole::COOK), $this->makeStaff(UserRole::MANAGER)] as $storeWide) {
            $this->assertCanJoin($storeWide, $pos);
            $this->assertCanJoin($storeWide, $other);
            $this->assertCannotJoin($storeWide, "pos-branch.{$this->foreignBranch->id}");
        }

        // A cook still cannot join the managers' channels
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->assertCannotJoin($cook, "branch.{$this->branch->id}");
        $this->assertCannotJoin($cook, "store.{$this->store->id}");
    }

    public function test_foreign_stores_couriers_customers_and_guests_are_refused(): void
    {
        $pos = "pos-branch.{$this->branch->id}";

        $this->assertCannotJoin($this->makeStaff(UserRole::OWNER, null, $this->foreignStore), $pos);
        $this->assertCannotJoin($this->makeStaff(UserRole::COOK, $this->foreignBranch, $this->foreignStore), $pos);
        $this->assertCannotJoin($this->courier, $pos);
        $this->assertCannotJoin($this->customer, $pos);
        $this->assertCannotJoin($this->makeStaff(UserRole::COOK, $this->branch, null, ['store_id' => null]), $pos);
        $superAdmin = User::create(['name' => 'Root', 'email' => 'root@rizqim.uz', 'password' => Hash::make('a-strong-password'), 'role' => UserRole::SUPER_ADMIN]);
        $this->assertCannotJoin($superAdmin, $pos);

        $this->join(null, $pos)->assertUnauthorized();
        $this->bearerJson('POST', '/api/broadcasting/auth', '1|not-a-real-token', ['socket_id' => '1234.5678', 'channel_name' => "private-{$pos}"])->assertUnauthorized();

        // Unknown or malformed branches
        $this->assertCannotJoin($this->owner, 'pos-branch.999999');
        $this->assertCannotJoin($this->owner, 'pos-branch.99999999999999999999999');
        $this->assertCannotJoin($this->owner, "pos-branch.{$this->branch->id}x");
    }

    public function test_taking_orders_view_away_closes_the_channel(): void
    {
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        app(PermissionService::class)->setOverride($this->store, UserRole::COOK, Permission::ORDERS_VIEW, false);

        $this->assertCannotJoin($cook, "pos-branch.{$this->branch->id}");
    }

    public function test_a_paired_terminal_joins_its_branch_with_its_own_token(): void
    {
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $pos = $this->pairTerminal($this->owner, $this->branch);

        $this->assertCanJoin($kds['token'], "pos-branch.{$this->branch->id}");
        $this->assertCanJoin($pos['token'], "pos-branch.{$this->branch->id}");
        $this->assertCannotJoin($kds['token'], "pos-branch.{$this->otherBranch->id}");
        $this->assertCannotJoin($kds['token'], "pos-branch.{$this->foreignBranch->id}");

        // A terminal is not a user: none of the other channels
        $this->assertCannotJoin($kds['token'], "store.{$this->store->id}");
        $this->assertCannotJoin($kds['token'], "branch.{$this->branch->id}");
        $device = $kds['device'];
        $this->assertCannotJoin($kds['token'], "user.{$device->id}");
        $this->assertCannotJoin($kds['token'], "courier.{$device->id}");

        // A terminal paired without a branch serves every branch of its store
        $storeTerminal = $this->pairTerminal($this->owner);
        $this->assertCanJoin($storeTerminal['token'], "pos-branch.{$this->branch->id}");
        $this->assertCanJoin($storeTerminal['token'], "pos-branch.{$this->otherBranch->id}");
        $this->assertCannotJoin($storeTerminal['token'], "pos-branch.{$this->foreignBranch->id}");

        // Another store's terminal
        $foreignOwner = $this->makeStaff(UserRole::OWNER, null, $this->foreignStore);
        $foreign = $this->pairTerminal($foreignOwner, $this->foreignBranch);
        $this->assertCannotJoin($foreign['token'], "pos-branch.{$this->branch->id}");
        $this->assertCanJoin($foreign['token'], "pos-branch.{$this->foreignBranch->id}");

        // A revoked terminal is signed out
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/owner/devices/{$device->uuid}/revoke")->assertOk();
        $this->join($kds['token'], "pos-branch.{$this->branch->id}")->assertUnauthorized();
    }

    public function test_a_pin_session_joins_its_terminal_branch_only(): void
    {
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $storeCook = $this->makeStaff(UserRole::COOK);
        $session = $this->pinSession($kds, $storeCook);

        $this->assertCanJoin($session, "pos-branch.{$this->branch->id}");
        $this->assertCannotJoin($session, "pos-branch.{$this->otherBranch->id}");
        $this->assertCannotJoin($session, "store.{$this->store->id}");
    }

    public function test_a_terminal_has_its_own_throttle_bucket(): void
    {
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        // A user whose id is the terminal's id
        $twin = User::find($kds['device']->id) ?? $this->customer;

        for ($i = 0; $i < 60; $i++) {
            $this->join($kds['token'], "pos-branch.{$this->branch->id}")->assertOk();
        }
        $this->join($kds['token'], "pos-branch.{$this->branch->id}")->assertStatus(429);
        $this->join($twin, "user.{$twin->id}")->assertOk();
    }

    public function test_order_events_reach_the_pos_branch_channel_with_the_minimal_payload(): void
    {
        Event::fake([OrderCreatedBroadcast::class, OrderStatusChangedBroadcast::class, OrderAssignedBroadcast::class]);

        $order = $this->checkout($this->customer);
        app(OrderStatusService::class)->transition($order, OrderStatus::CANCELED, $this->manager, ['cancel_reason' => 'other']);

        foreach ([OrderCreatedBroadcast::class, OrderStatusChangedBroadcast::class] as $class) {
            $event = Event::dispatched($class)->first()[0];
            $this->assertContains("private-pos-branch.{$this->branch->id}", array_map('strval', $event->broadcastOn()));
            $payload = $event->broadcastWith();
            $this->assertSame(['order'], array_keys($payload));
            $this->assertSame(['uuid', 'status', 'store_id', 'branch_id', 'courier_id', 'updated_at'], array_keys($payload['order']));
            $this->assertStringNotContainsString((string) $this->customer->phone, json_encode($payload));
        }
        $this->assertSame('canceled', Event::dispatched(OrderStatusChangedBroadcast::class)->first()[0]->broadcastWith()['order']['status']);
    }
}
