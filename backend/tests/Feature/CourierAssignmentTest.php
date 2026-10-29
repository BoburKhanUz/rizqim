<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Jobs\CheckUnacceptedAssignmentsJob;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\OrderAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CourierAssignmentTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
    }

    protected function giveOrders(User $courier, int $count, OrderStatus $status = OrderStatus::ON_THE_WAY): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->makeOrder($status, ['courier_id' => $courier->id]);
        }
    }

    protected function dispatchReadyOrder(string $status = 'courier_assigned', array $attributes = []): Order
    {
        $order = $this->makeOrder(OrderStatus::READY, $attributes);
        $this->setStatus($order, $this->manager, $status)->assertOk();

        return $order->fresh();
    }

    public function test_auto_assign_picks_the_courier_with_the_fewest_orders_in_hand(): void
    {
        $idle = $this->makeStaff(UserRole::COURIER, $this->branch);
        $busy = $this->makeStaff(UserRole::COURIER, $this->branch);
        $this->giveOrders($this->courier, 2);
        $this->giveOrders($busy, 1, OrderStatus::COURIER_ASSIGNED);
        // Finished work is not load
        $this->giveOrders($idle, 3, OrderStatus::DELIVERED);
        $this->giveOrders($idle, 1, OrderStatus::CANCELED);

        $order = $this->dispatchReadyOrder();

        $this->assertSame($idle->id, $order->courier_id);
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->status);
        $this->assertTrue(OrderAssignment::where('order_id', $order->id)->where('courier_id', $idle->id)->where('is_current', true)->exists());
    }

    public function test_ties_go_to_the_lowest_id_and_load_spreads_across_couriers(): void
    {
        $second = $this->makeStaff(UserRole::COURIER, $this->branch);

        $first = $this->dispatchReadyOrder('on_the_way');
        $next = $this->dispatchReadyOrder('delivery');

        $this->assertSame($this->courier->id, $first->courier_id);
        $this->assertSame($second->id, $next->courier_id);
    }

    public function test_auto_assign_prefers_the_orders_branch_then_store_wide_couriers(): void
    {
        $storeWide = $this->makeStaff(UserRole::COURIER);
        $otherBranch = $this->makeStaff(UserRole::COURIER, $this->otherBranch);
        $this->giveOrders($this->courier, 1);

        // The branch's own courier wins even with more orders in hand
        $this->assertSame($this->courier->id, $this->dispatchReadyOrder()->courier_id);

        // An order of a branch without couriers goes to the store-wide courier before another branch's one
        $thirdBranch = $this->makeBranch($this->store, 'Third Branch');
        $order = $this->makeOrder(OrderStatus::READY, ['branch_id' => $thirdBranch->id]);
        $this->setStatus($order, $this->owner, 'courier_assigned')->assertOk();

        $this->assertSame($storeWide->id, $order->fresh()->courier_id);
        $this->assertNotSame($otherBranch->id, $order->fresh()->courier_id);
    }

    public function test_unclaimed_invites_never_get_orders(): void
    {
        // Invited from the mini-app (placeholder Telegram id) and from the admin panel (no Telegram id): both still pending
        $appInvite = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => 'temp_6716a1b2c3', 'telegram_invite_token' => 'emp_pending1']);
        $panelInvite = $this->makeStaff(UserRole::COURIER, $this->branch, null, ['telegram_id' => null, 'telegram_invite_token' => 'b0c1d2e3-pending']);
        $pendingManager = $this->makeStaff(UserRole::MANAGER, $this->branch, null, ['telegram_id' => 'temp_6716a1b2c4', 'telegram_invite_token' => 'emp_pending2']);
        $this->giveOrders($this->courier, 2);

        // Zero load, yet the joined courier gets the order
        $this->assertSame($this->courier->id, $this->dispatchReadyOrder()->courier_id);

        foreach ([$appInvite, $panelInvite, $pendingManager] as $invited) {
            $this->actingAs($this->manager, 'sanctum')
                ->postJson("/api/v1/orders/{$this->makeOrder(OrderStatus::READY)->uuid}/assign-courier", ['courier_id' => $invited->id])
                ->assertStatus(422);
        }

        $listed = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/couriers')->json('data'))->pluck('id');
        $this->assertNotContains($appInvite->id, $listed);
        $this->assertNotContains($panelInvite->id, $listed);
        $this->assertNotContains($pendingManager->id, $listed);

        // With only pending couriers left, the owner stands in
        $this->courier->delete();
        $this->assertSame($this->owner->id, $this->dispatchReadyOrder()->courier_id);

        // Once the invite is claimed the courier is a candidate like any other
        $appInvite->update(['telegram_id' => '5550001', 'telegram_invite_token' => null]);
        $this->assertSame($appInvite->id, $this->dispatchReadyOrder()->courier_id);
    }

    public function test_blocked_staff_never_get_orders(): void
    {
        // P4-31: an idle blocked courier loses to a busy one, and cannot be picked by hand either
        $blocked = $this->makeStaff(UserRole::COURIER, $this->branch);
        $blocked->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Firibgarlik'])->save();
        $this->giveOrders($this->courier, 2);

        $this->assertSame($this->courier->id, $this->dispatchReadyOrder()->courier_id);
        $this->actingAs($this->manager, 'sanctum')
            ->postJson("/api/v1/orders/{$this->makeOrder(OrderStatus::READY)->uuid}/assign-courier", ['courier_id' => $blocked->id])
            ->assertStatus(422);
        $listed = collect($this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/couriers')->json('data'))->pluck('id');
        $this->assertNotContains($blocked->id, $listed);

        // With the only courier gone and the owner blocked, nobody stands in: the order is not dispatched
        $this->courier->delete();
        $this->owner->forceFill(['blocked_at' => now()])->save();
        $order = $this->makeOrder(OrderStatus::READY);
        $this->setStatus($order, $this->manager, 'courier_assigned')->assertStatus(422);
        $this->assertNull($order->fresh()->courier_id);
    }

    public function test_owner_stands_in_when_the_store_has_no_courier(): void
    {
        $this->courier->delete();

        $this->assertSame($this->owner->id, $this->dispatchReadyOrder()->courier_id);
    }

    public function test_late_assignment_warning_is_sent_once_per_assignment(): void
    {
        config(['services.telegram.bot_token' => '123456:fake-token']);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->manager->update(['telegram_id' => '5001']);

        $order = $this->makeOrder(OrderStatus::COURIER_ASSIGNED, ['courier_id' => $this->courier->id]);
        OrderAssignment::create([
            'order_id' => $order->id,
            'courier_id' => $this->courier->id,
            'assigned_by' => $this->manager->id,
            'assigned_branch_id' => $this->branch->id,
            'is_current' => true,
            'assigned_at' => now()->subMinutes(20),
        ]);

        // The scheduler runs it every minute
        (new CheckUnacceptedAssignmentsJob)->handle();
        (new CheckUnacceptedAssignmentsJob)->handle();
        (new CheckUnacceptedAssignmentsJob)->handle();

        Http::assertSentCount(1);
        $this->assertSame(1, NotificationLog::where('order_id', $order->id)->where('type', 'telegram_manager_courier_delay_warning')->count());
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $order->fresh()->status);
    }
}
