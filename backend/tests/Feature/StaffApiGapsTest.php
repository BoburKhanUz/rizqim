<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P2-30: what the wave 2 screens were missing from the staff API: courier endpoints by permission, the pick-up
 * (on_the_way) on the staff status endpoint, the waiting time on the board, the store's time zone, how a login phone
 * was proven, batch_tracked on the branch overrides, who stopped an item, and the terminal's staff filter and branches.
 */
class StaffApiGapsTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private User $cashier;
    private User $cook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpLifecycleStore();
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->cook = $this->makeStaff(UserRole::COOK, $this->branch);
    }

    private function grant(UserRole $role, Permission $permission, bool $allowed = true): void
    {
        app(PermissionService::class)->setOverride($this->store, $role, $permission, $allowed);
    }

    public function test_courier_endpoints_go_by_orders_assign_courier(): void
    {
        $order = $this->orderWithItems(OrderStatus::READY, ['order_type' => 'delivery']);
        $otherCourier = $this->makeStaff(UserRole::COURIER, $this->otherBranch);

        // Owner and manager hold it by default; a manager tied to a branch picks from it (and store-wide staff)
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/couriers')->assertOk();
        $ids = array_column($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/couriers')->assertOk()->json('data'), 'id');
        $this->assertContains($this->courier->id, $ids);
        $this->assertNotContains($otherCourier->id, $ids);

        foreach ([$this->cashier, $this->cook, $this->courier, $this->customer] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/couriers')->assertForbidden();
            $this->actingAs($user, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertForbidden();
        }

        // Granted to cashiers: a branch cashier assigns from their branch, like a branch manager
        $this->grant(UserRole::CASHIER, Permission::ORDERS_ASSIGN_COURIER);
        $ids = array_column($this->actingAs($this->cashier, 'sanctum')->getJson('/api/v1/couriers')->assertOk()->json('data'), 'id');
        $this->assertContains($this->courier->id, $ids);
        $this->assertNotContains($otherCourier->id, $ids);
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $otherCourier->id])->assertStatus(422);
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertOk()
            ->assertJsonPath('data.courier_id', $this->courier->id);

        // Taken from managers: no couriers for them any more; another branch's order stays hidden
        $this->grant(UserRole::MANAGER, Permission::ORDERS_ASSIGN_COURIER, false);
        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/couriers')->assertForbidden();
        $foreign = $this->orderWithItems(OrderStatus::READY, ['order_type' => 'delivery', 'branch_id' => $this->otherBranch->id]);
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/orders/{$foreign->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertNotFound();
    }

    public function test_the_staff_status_endpoint_marks_the_assigned_couriers_pick_up(): void
    {
        $order = $this->orderWithItems(OrderStatus::READY, ['order_type' => 'delivery']);
        $move = fn (User $user, string $status = 'on_the_way') => $this->actingAs($user, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => $status]);

        // No courier yet: assign one first (the state machine would otherwise pick one by itself)
        $move($this->manager)->assertUnprocessable()->assertJsonPath('data.reason', 'courier_required');
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertOk();

        // Only who may assign couriers marks the pick-up: not a cashier, not a cook, never a courier here
        $move($this->cashier)->assertForbidden();
        $move($this->cook)->assertForbidden();
        $move($this->courier)->assertForbidden();
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status);

        $move($this->manager)->assertOk()->assertJsonPath('data.status', 'on_the_way')->assertJsonPath('data.courier.id', $this->courier->id);
        $move($this->manager)->assertOk()->assertJsonPath('message', 'Buyurtma allaqachon shu holatda.');
        $this->assertSame(['accepted', 'courier_assigned', 'on_the_way'], array_slice($order->statusHistories()->orderBy('id')->pluck('status')->map->value->all(), -3));

        // A manager of another branch does not see it
        $otherManager = $this->makeStaff(UserRole::MANAGER, $this->otherBranch);
        $move($otherManager, 'delivered')->assertNotFound();

        // A pickup order has no courier: refused the same way
        $pickup = $this->orderWithItems(OrderStatus::READY);
        $this->actingAs($this->owner, 'sanctum')->postJson("/api/v1/staff/orders/{$pickup->uuid}/status", ['status' => 'on_the_way'])
            ->assertUnprocessable()->assertJsonPath('data.reason', 'courier_required');
    }

    public function test_the_board_shows_the_kitchens_waiting_time(): void
    {
        Carbon::setTestNow('2026-10-21 10:00:00');
        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        Carbon::setTestNow('2026-10-21 10:07:30');

        $listed = $this->actingAs($this->cashier, 'sanctum')->getJson('/api/v1/staff/orders')->assertOk()->json('data.0');
        $ticket = $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame($order->uuid, $listed['uuid']);
        $this->assertSame(450, $listed['waiting_seconds']);
        $this->assertSame($ticket['waiting_seconds'], $listed['waiting_seconds']);
        $this->actingAs($this->cashier, 'sanctum')->getJson("/api/v1/staff/orders/{$order->uuid}")->assertJsonPath('data.waiting_seconds', 450);

        // Ready: the clock stops; finished without passing ready: it stops when the order closed
        OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => 'accepted', 'status' => 'ready']);
        $order->update(['status' => OrderStatus::READY]);
        Carbon::setTestNow('2026-10-21 10:30:00');
        $this->assertSame(450, $this->actingAs($this->cashier, 'sanctum')->getJson('/api/v1/staff/orders')->json('data.0.waiting_seconds'));

        $pickup = $this->orderWithItems(OrderStatus::ACCEPTED);
        Carbon::setTestNow('2026-10-21 10:31:00');
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/staff/orders/{$pickup->uuid}/status", ['status' => 'preparing'])->assertOk();
        $this->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/staff/orders/{$pickup->uuid}/status", ['status' => 'delivered'])->assertOk();
        Carbon::setTestNow('2026-10-21 12:00:00');
        $this->assertSame(60, $this->actingAs($this->cashier, 'sanctum')->getJson("/api/v1/staff/orders/{$pickup->uuid}")->json('data.waiting_seconds'));
        Carbon::setTestNow();
    }

    public function test_staff_who_read_orders_or_reports_get_the_stores_time_zone(): void
    {
        $this->store->forceFill(['timezone' => 'Asia/Samarkand'])->save();

        foreach ([$this->owner, $this->manager, $this->cashier, $this->cook] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/permissions')->assertOk()->assertJsonPath('data.timezone', 'Asia/Samarkand');
        }
        foreach ([$this->courier, $this->customer->refresh()] as $user) {
            $this->actingAs($user, 'sanctum')->getJson('/api/v1/me/permissions')->assertOk()->assertJsonPath('data.timezone', null);
        }

        // Not a valid one: the default
        $this->store->forceFill(['timezone' => 'Mars/Olympus'])->save();
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/me/permissions')->assertJsonPath('data.timezone', 'Asia/Tashkent');
    }

    public function test_the_staff_list_says_how_a_login_phone_was_proven(): void
    {
        $this->courier->forceFill(['phone' => '+998901110000', 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_SMS])->save();
        $this->cashier->forceFill(['phone' => '+998901110001', 'phone_verified_at' => now(), 'phone_verified_via' => User::PHONE_VIA_VOUCHED])->save();
        $this->cook->forceFill(['phone' => '+998901110002', 'phone_verified_at' => null, 'phone_verified_via' => null])->save();

        $staff = collect($this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/staff')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(['sms', true], [$staff[$this->courier->id]['login_phone_proof'], $staff[$this->courier->id]['login_phone_enabled']]);
        $this->assertSame(['vouched', true], [$staff[$this->cashier->id]['login_phone_proof'], $staff[$this->cashier->id]['login_phone_enabled']]);
        $this->assertSame([null, false], [$staff[$this->cook->id]['login_phone_proof'], $staff[$this->cook->id]['login_phone_enabled']]);
        $this->assertArrayNotHasKey('phone_verified_via', $staff[$this->courier->id]);

        // The proof decides what the form may do: an SMS-proven phone cannot be replaced
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/staff/{$this->courier->id}", ['phone' => '+998901119999'])->assertUnprocessable();
        $this->actingAs($this->owner, 'sanctum')->patchJson("/api/v1/owner/staff/{$this->cashier->id}", ['phone' => '+998901119998'])->assertOk()->assertJsonPath('data.login_phone_proof', 'vouched');
    }

    public function test_branch_overrides_say_which_products_sell_from_a_batch(): void
    {
        $this->product->forceFill(['batch_tracked' => true])->save();

        $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/owner/branch-variants')->assertOk()
            ->assertJsonPath('data.0.product_id', $this->product->id)
            ->assertJsonPath('data.0.batch_tracked', true);
        $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/owner/branch-variants')->assertUnprocessable()->assertJsonPath('message', 'Filialni tanlang (branch_id).');
    }

    public function test_stop_list_entries_name_who_added_them_for_those_who_manage_the_list(): void
    {
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/owner/stop-list', ['type' => 'product', 'id' => $this->product->id, 'reason' => 'Tugadi'])
            ->assertCreated()->assertJsonPath('data.created_by_name', $this->manager->name);

        $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/owner/stop-list?branch_id={$this->branch->id}")->assertOk()
            ->assertJsonPath('data.entries.0.created_by', $this->manager->id)
            ->assertJsonPath('data.entries.0.created_by_name', $this->manager->name);

        // A cook reads the list (menu.view) without names
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/owner/stop-list')->assertOk()
            ->assertJsonPath('data.entries.0.created_by', $this->manager->id)
            ->assertJsonPath('data.entries.0.created_by_name', null);
    }

    public function test_a_terminal_filters_its_staff_by_role_and_lists_the_branches_it_may_watch(): void
    {
        foreach ([$this->cook, $this->cashier, $this->manager] as $user) {
            $this->givePin($user, '5926');
        }
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);

        $all = array_column($this->bearerJson('GET', '/api/v1/terminal/staff', $kds['token'])->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$this->cook->id, $this->cashier->id, $this->manager->id], $all);
        $kitchen = array_column($this->bearerJson('GET', '/api/v1/terminal/staff?role=cook,manager', $kds['token'])->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$this->cook->id, $this->manager->id], $kitchen);
        $this->bearerJson('GET', '/api/v1/terminal/staff?role=courier', $kds['token'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->bearerJson('GET', '/api/v1/terminal/staff?role=cook', null)->assertUnauthorized();

        // A branch terminal watches its branch; a store-wide one every branch of its store
        $this->bearerJson('GET', '/api/v1/terminal', $kds['token'])->assertOk()
            ->assertJsonPath('data.branches', [['id' => $this->branch->id, 'name' => $this->branch->name]]);
        $storeWide = $this->pairTerminal($this->owner, null, DeviceType::KDS);
        $this->bearerJson('GET', '/api/v1/terminal', $storeWide['token'])->assertOk()
            ->assertJsonPath('data.branch', null)
            ->assertJsonPath('data.branches', [['id' => $this->branch->id, 'name' => $this->branch->name], ['id' => $this->otherBranch->id, 'name' => $this->otherBranch->name]]);
    }
}
