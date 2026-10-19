<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * POST /api/broadcasting/auth signs a private channel subscription only for those OrderPolicy lets see its orders:
 * store.{id} the owner and store-wide managers, branch.{id} its managers and the owner, courier.{id} and user.{id}
 * only that user. Storeless staff and guests get nothing.
 */
class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected Store $otherStore;
    protected Branch $foreignBranch;
    protected User $otherOwner;
    protected User $storeManager;
    protected User $otherCourier;
    protected User $otherCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        // A real signing broadcaster (no network: Pusher-protocol auth is computed locally). The channels were
        // registered on the default "null" broadcaster at boot, so they are registered again on this one.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        require base_path('routes/channels.php');

        $this->setUpLifecycleStore();
        $this->storeManager = $this->makeStaff(UserRole::MANAGER);
        $this->otherCourier = $this->makeStaff(UserRole::COURIER, $this->branch);
        $this->otherCustomer = $this->makeCustomer();

        $this->otherStore = $this->makeStore('Other Pizza');
        $this->foreignBranch = $this->makeBranch($this->otherStore, 'Other Main');
        $this->otherOwner = $this->makeStaff(UserRole::OWNER, null, $this->otherStore);
    }

    protected function join(?User $user, string $channel)
    {
        $request = $user ? $this->actingAs($user, 'sanctum') : $this;

        return $request->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-{$channel}",
        ]);
    }

    protected function assertCanJoin(User $user, string $channel): void
    {
        $response = $this->join($user, $channel);
        $response->assertOk();
        $this->assertStringStartsWith('test-key:', (string) $response->json('auth'), "{$user->name} should join {$channel}");
    }

    protected function assertCannotJoin(?User $user, string $channel): void
    {
        $this->join($user, $channel)->assertForbidden();
    }

    public function test_a_sanctum_bearer_token_signs_a_subscription(): void
    {
        $token = $this->owner->createToken('mini-app')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-store.{$this->store->id}"]);

        $response->assertOk();
        $expected = hash_hmac('sha256', "1234.5678:private-store.{$this->store->id}", 'test-secret');
        $this->assertSame("test-key:{$expected}", $response->json('auth'));
    }

    public function test_guests_and_bad_tokens_are_refused(): void
    {
        $this->join(null, "store.{$this->store->id}")->assertUnauthorized();
        $this->join(null, "user.{$this->customer->id}")->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer 1|not-a-real-token')
            ->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-user.{$this->customer->id}"])
            ->assertUnauthorized();
    }

    public function test_an_owner_joins_only_their_own_store_and_its_branches(): void
    {
        $this->assertCanJoin($this->owner, "store.{$this->store->id}");
        $this->assertCanJoin($this->owner, "branch.{$this->branch->id}");
        $this->assertCanJoin($this->owner, "branch.{$this->otherBranch->id}");

        $this->assertCannotJoin($this->owner, "store.{$this->otherStore->id}");
        $this->assertCannotJoin($this->owner, "branch.{$this->foreignBranch->id}");
        $this->assertCannotJoin($this->otherOwner, "store.{$this->store->id}");
        $this->assertCannotJoin($this->otherOwner, "branch.{$this->branch->id}");
    }

    public function test_a_store_wide_manager_joins_the_store_channel_only(): void
    {
        $this->assertCanJoin($this->storeManager, "store.{$this->store->id}");

        $this->assertCannotJoin($this->storeManager, "store.{$this->otherStore->id}");
        $this->assertCannotJoin($this->storeManager, "branch.{$this->branch->id}");
        $this->assertCannotJoin($this->storeManager, "branch.{$this->foreignBranch->id}");
    }

    public function test_a_branch_manager_joins_only_their_branch(): void
    {
        $this->assertCanJoin($this->manager, "branch.{$this->branch->id}");

        $this->assertCannotJoin($this->manager, "branch.{$this->otherBranch->id}");
        $this->assertCannotJoin($this->manager, "branch.{$this->foreignBranch->id}");
        $this->assertCannotJoin($this->manager, "store.{$this->store->id}");
        $this->assertCannotJoin($this->manager, "store.{$this->otherStore->id}");
    }

    public function test_a_courier_joins_only_their_own_channel(): void
    {
        $this->assertCanJoin($this->courier, "courier.{$this->courier->id}");

        $this->assertCannotJoin($this->courier, "courier.{$this->otherCourier->id}");
        $this->assertCannotJoin($this->courier, "store.{$this->store->id}");
        $this->assertCannotJoin($this->courier, "branch.{$this->branch->id}");
        $this->assertCannotJoin($this->courier, "user.{$this->customer->id}");
        $this->assertCannotJoin($this->otherCourier, "courier.{$this->courier->id}");
        $this->assertCannotJoin($this->customer, "courier.{$this->courier->id}");
    }

    public function test_a_customer_joins_only_their_own_channel(): void
    {
        $this->assertCanJoin($this->customer, "user.{$this->customer->id}");

        $this->assertCannotJoin($this->customer, "user.{$this->otherCustomer->id}");
        $this->assertCannotJoin($this->customer, "courier.{$this->customer->id}");
        $this->assertCannotJoin($this->customer, "store.{$this->store->id}");
        $this->assertCannotJoin($this->customer, "branch.{$this->branch->id}");
        $this->assertCannotJoin($this->owner, "user.{$this->customer->id}");
        $this->assertCannotJoin($this->courier, "user.{$this->customer->id}");
    }

    public function test_storeless_staff_and_the_super_admin_join_no_order_channel(): void
    {
        foreach ([UserRole::OWNER, UserRole::MANAGER, UserRole::COURIER] as $role) {
            $storeless = $this->makeStaff($role, null, null, ['store_id' => null]);

            $this->assertCannotJoin($storeless, "store.{$this->store->id}");
            $this->assertCannotJoin($storeless, "branch.{$this->branch->id}");
            $this->assertCannotJoin($storeless, "courier.{$storeless->id}");
        }

        // A manager whose store was taken away keeps a dangling branch_id: still refused
        $detached = $this->makeStaff(UserRole::MANAGER, $this->branch, null, ['store_id' => null]);
        $this->assertCannotJoin($detached, "branch.{$this->branch->id}");

        $superAdmin = User::create(['name' => 'Root', 'email' => 'root@rizqim.uz', 'password' => Hash::make('a-strong-password'), 'role' => UserRole::SUPER_ADMIN]);
        $this->assertCannotJoin($superAdmin, "store.{$this->store->id}");
        $this->assertCannotJoin($superAdmin, "branch.{$this->branch->id}");
    }

    public function test_unknown_and_malformed_channels_are_refused(): void
    {
        $this->assertCannotJoin($this->owner, "store.{$this->store->id}x");
        $this->assertCannotJoin($this->owner, 'branch.999999');
        $this->assertCannotJoin($this->owner, 'orders');
        $this->assertCannotJoin($this->customer, "user.{$this->customer->id}.1");

        // Without the private- prefix the same rules apply (events only go to the private channels anyway)
        $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "store.{$this->store->id}"])
            ->assertForbidden();
    }

    public function test_channel_auth_is_throttled_per_user(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->join($this->customer, "user.{$this->customer->id}")->assertOk();
        }

        $this->join($this->customer, "user.{$this->customer->id}")->assertStatus(429)->assertJsonPath('success', false);
        // Another user has a bucket of their own
        $this->assertCanJoin($this->otherCustomer, "user.{$this->otherCustomer->id}");
    }
}
