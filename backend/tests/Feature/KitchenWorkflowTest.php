<?php

namespace Tests\Feature;

use App\Enums\DeviceType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Events\Broadcast\KitchenItemStatusChangedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P2-11: staff status changes by permission (a cook starts and finishes cooking only), the kitchen display's per line
 * bump and recall, the order becoming ready when its last line is, and the kitchen tickets.
 */
class KitchenWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use StaffOrderFixtures;

    private User $cook;
    private User $cashier;
    private User $waiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->cashier = $this->makeStaff(UserRole::CASHIER, $this->branch);
        $this->waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
    }

    private function move(User $actor, Order $order, string $status, array $extra = [])
    {
        return $this->actingAs($actor, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/status", ['status' => $status] + $extra);
    }

    private function bump(User|string $actor, Order $order, OrderItem $item, string $action = 'ready')
    {
        $uri = "/api/v1/staff/orders/{$order->uuid}/items/{$item->id}/{$action}";

        return is_string($actor) ? $this->bearerJson('POST', $uri, $actor) : $this->actingAs($actor, 'sanctum')->postJson($uri);
    }

    public function test_a_cook_only_starts_and_finishes_cooking(): void
    {
        $new = $this->orderWithItems(OrderStatus::NEW);
        $this->move($this->cook, $new, 'accepted')->assertForbidden();
        $this->move($this->cook, $new, 'rejected', ['reason' => 'other'])->assertForbidden();

        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        $this->move($this->cook, $order, 'preparing')->assertOk()->assertJsonPath('data.status', 'preparing');
        $this->move($this->cook, $order, 'ready')->assertOk()->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.items_ready', 2)
            ->assertJsonPath('data.customer', null);
        $this->move($this->cook, $order, 'delivered')->assertForbidden();
        $this->move($this->cook, $order, 'canceled', ['reason' => 'other'])->assertForbidden();
        $this->assertSame(OrderStatus::READY, $order->fresh()->status);

        // The old endpoint is still owner/manager/courier only
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/orders/{$order->uuid}/status", ['status' => 'delivered'])->assertForbidden();

        // Staff changes are in the audit log with the actor
        $this->assertTrue(AuditLog::where('action', 'order.status_changed')->where('user_id', $this->cook->id)->exists());
    }

    public function test_cashiers_and_waiters_run_orders_but_do_not_cancel(): void
    {
        $order = $this->orderWithItems(OrderStatus::NEW);

        $this->move($this->cashier, $order, 'accepted')->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->move($this->waiter, $order, 'preparing')->assertOk();
        $this->move($this->cashier, $order, 'ready')->assertOk();
        $this->move($this->cashier, $order, 'canceled', ['reason' => 'other'])->assertForbidden();
        $this->move($this->waiter, $order, 'delivered')->assertOk()->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.closed_at', fn ($value) => $value !== null);

        // The manager cancels, with a reason
        $other = $this->orderWithItems(OrderStatus::NEW);
        $this->move($this->manager, $other, 'canceled')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->move($this->manager, $other, 'canceled', ['reason' => 'other'])->assertOk()->assertJsonPath('data.cancel_reason', 'other');

        // Illegal moves are 422, the same status again a 200 no-op
        $accepted = $this->orderWithItems(OrderStatus::ACCEPTED);
        $this->move($this->cashier, $accepted, 'delivered')->assertUnprocessable();
        $this->move($this->cashier, $accepted, 'accepted')->assertOk()->assertJsonPath('message', 'Buyurtma allaqachon shu holatda.');

        // Couriers are handed orders through assign-courier, not here
        $this->move($this->manager, $accepted, 'courier_assigned')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_another_branch_or_store_cannot_move_the_order(): void
    {
        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        $otherCook = $this->makeStaff(UserRole::COOK, $this->otherBranch);
        $foreignStore = $this->makeStore('Foreign Lavash');
        $foreignManager = $this->makeStaff(UserRole::MANAGER, null, $foreignStore);

        $this->move($otherCook, $order, 'preparing')->assertNotFound();
        $this->move($foreignManager, $order, 'preparing')->assertNotFound();
        $this->bump($otherCook, $order, $order->items->first())->assertNotFound();
        $this->bump($foreignManager, $order, $order->items->first())->assertNotFound();
        $this->move($this->courier, $order, 'preparing')->assertForbidden();
        $this->move($this->customer, $order, 'preparing')->assertForbidden();
        $this->assertSame(OrderStatus::ACCEPTED, $order->fresh()->status);
    }

    public function test_bumping_every_line_makes_a_pickup_order_ready(): void
    {
        Event::fake([KitchenItemStatusChangedBroadcast::class, OrderStatusChangedBroadcast::class]);
        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        [$burger, $osh] = $order->items->sortBy('id')->values()->all();

        // The first bump starts the order
        $this->bump($this->cook, $order, $burger)->assertOk()
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.items_ready', 1)
            ->assertJsonPath('data.items.0.kitchen_status', 'ready');
        $this->assertNotNull($burger->fresh()->ready_at);

        // Again: nothing changes
        $this->bump($this->cook, $order, $burger)->assertOk()->assertJsonPath('message', "O'zgarish yo'q.");

        // Recall and bump again
        $this->bump($this->cook, $order, $burger, 'recall')->assertOk()->assertJsonPath('data.items_ready', 0);
        $this->assertNull($burger->fresh()->ready_at);
        $this->bump($this->cook, $order, $burger)->assertOk();

        // The last line: the order is ready by itself
        $this->bump($this->cook, $order, $osh)->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.items_ready', 2);
        $this->assertSame(OrderStatus::READY, $order->fresh()->status);
        $this->assertSame(['new', 'accepted', 'preparing', 'ready'], $order->statusHistories()->orderBy('id')->pluck('status')->map->value->all());

        // A recall once it is ready: test_a_line_recalled_after_the_order_became_ready_takes_it_back_to_the_kitchen (P2-30)

        // Four line changes, two status changes; payloads carry ids and kitchen state only
        Event::assertDispatchedTimes(KitchenItemStatusChangedBroadcast::class, 4);
        Event::assertDispatchedTimes(OrderStatusChangedBroadcast::class, 2);
        $last = Event::dispatched(KitchenItemStatusChangedBroadcast::class)->last()[0];
        $this->assertSame('kitchen.item_status_changed', $last->broadcastAs());
        $this->assertSame('broadcasts', $last->broadcastQueue());
        $this->assertSame([
            "private-store.{$this->store->id}",
            "private-branch.{$this->branch->id}",
            "private-pos-branch.{$this->branch->id}",
        ], array_map('strval', $last->broadcastOn()));
        $payload = $last->broadcastWith();
        $this->assertSame(['order', 'item', 'items_total', 'items_ready'], array_keys($payload));
        $this->assertSame(['uuid', 'status', 'store_id', 'branch_id', 'courier_id', 'updated_at'], array_keys($payload['order']));
        $this->assertSame('ready', $payload['order']['status']);
        $this->assertSame(['id' => $osh->id, 'kitchen_status' => 'ready', 'ready_at' => $osh->fresh()->ready_at->toJSON()], $payload['item']);
        $this->assertSame([2, 2], [$payload['items_total'], $payload['items_ready']]);
        $json = json_encode($payload);
        $this->assertStringNotContainsString($this->customer->phone, $json);
        $this->assertStringNotContainsString('Amir Temur', $json);
        $this->assertStringNotContainsString('Cheeseburger', $json);
    }

    /**
     * P2-30: the last bump made the pickup order ready, then the cook finds a line was not ready after all: the recall
     * takes the order back to preparing (kitchen.manage only, through the recall only), and the next last bump makes
     * it ready again. Once it is handed over (or with a courier) nothing can be recalled.
     */
    public function test_a_line_recalled_after_the_order_became_ready_takes_it_back_to_the_kitchen(): void
    {
        Event::fake([KitchenItemStatusChangedBroadcast::class, OrderStatusChangedBroadcast::class]);
        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        [$burger, $osh] = $order->items->sortBy('id')->values()->all();
        $this->bump($this->cook, $order, $burger)->assertOk();
        $this->bump($this->cook, $order, $osh)->assertOk()->assertJsonPath('data.status', 'ready');

        // Neither a cashier (no kitchen.manage) nor the status endpoint takes a ready order back to the kitchen
        $this->bump($this->cashier, $order, $osh, 'recall')->assertForbidden();
        $this->move($this->cook, $order, 'preparing')->assertUnprocessable();
        $this->move($this->manager, $order, 'preparing')->assertUnprocessable();
        $this->assertSame(OrderStatus::READY, $order->fresh()->status);

        $this->bump($this->cook, $order, $osh, 'recall')->assertOk()
            ->assertJsonPath('message', 'Taom qaytarildi.')
            ->assertJsonPath('data.status', 'preparing')
            ->assertJsonPath('data.items_ready', 1)
            ->assertJsonPath('data.items.1.kitchen_status', 'pending');
        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
        $this->assertNull($osh->fresh()->ready_at);
        $this->assertSame(['new', 'accepted', 'preparing', 'ready', 'preparing'], $order->statusHistories()->orderBy('id')->pluck('status')->map->value->all());
        $this->assertTrue(AuditLog::where('action', 'order.status_changed')->where('model_id', $order->id)->where('after->status', 'preparing')->exists());
        Event::assertDispatched(OrderStatusChangedBroadcast::class, fn ($event) => $event->broadcastWith()['order']['status'] === 'preparing');

        // The next last bump: ready again
        $this->bump($this->cook, $order, $osh)->assertOk()->assertJsonPath('data.status', 'ready');

        // Handed over: too late
        $this->move($this->cashier, $order, 'delivered')->assertOk();
        $this->bump($this->cook, $order, $osh, 'recall')->assertUnprocessable()->assertJsonPath('data.reason', 'kitchen_status');
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);

        // A delivery order already with a courier: P2-33 the line comes back, the order stays with the courier
        $delivery = $this->orderWithItems(OrderStatus::ACCEPTED, ['order_type' => 'delivery']);
        foreach ($delivery->items as $item) {
            $this->bump($this->cook, $delivery, $item)->assertOk();
        }
        $this->assertSame(OrderStatus::READY, $delivery->fresh()->status);
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/orders/{$delivery->uuid}/assign-courier", ['courier_id' => $this->courier->id])->assertOk();
        $this->bump($this->cook, $delivery, $delivery->items->first(), 'recall')->assertOk()
            ->assertJsonPath('data.status', 'courier_assigned')->assertJsonPath('data.items_ready', 1);
        $this->assertSame(OrderStatus::COURIER_ASSIGNED, $delivery->fresh()->status);

        // On its way: too late (and the line left with the food)
        $this->actingAs($this->manager, 'sanctum')->postJson("/api/v1/staff/orders/{$delivery->uuid}/status", ['status' => 'on_the_way'])->assertOk()
            ->assertJsonPath('data.items_ready', 2);
        $this->bump($this->cook, $delivery, $delivery->items->first(), 'recall')->assertUnprocessable()->assertJsonPath('data.reason', 'kitchen_status');
        $this->assertSame(OrderStatus::ON_THE_WAY, $delivery->fresh()->status);
    }

    public function test_a_hall_order_stays_preparing_when_every_line_is_ready(): void
    {
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'dine_in', 'channel' => 'pos_hall', 'table_ref' => '7']);

        foreach ($order->items as $item) {
            $this->bump($this->cook, $order, $item)->assertOk();
        }

        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);
        $this->assertSame(['ready', 'ready'], $order->items()->pluck('kitchen_status')->map->value->all());

        // The cook may still mark it ready explicitly
        $this->move($this->cook, $order, 'ready')->assertOk();
    }

    public function test_a_delivery_order_becomes_ready_too(): void
    {
        $order = $this->orderWithItems(OrderStatus::PREPARING, ['order_type' => 'delivery']);

        foreach ($order->items as $item) {
            $this->bump($this->manager, $order, $item)->assertOk();
        }

        $this->assertSame(OrderStatus::READY, $order->fresh()->status);
    }

    public function test_who_may_bump_and_when(): void
    {
        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        $item = $order->items->first();

        $this->bump($this->cashier, $order, $item)->assertForbidden();
        $this->bump($this->waiter, $order, $item)->assertForbidden();
        $this->bump($this->courier, $order, $item)->assertForbidden();
        $this->bump($this->customer, $order, $item)->assertForbidden();

        // A line of another order, an id past bigint: 404
        $other = $this->orderWithItems(OrderStatus::ACCEPTED);
        $this->bump($this->cook, $order, $other->items->first())->assertNotFound();
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/999999999999999999/ready")->assertNotFound();
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/9999999999999999999999/ready")->assertNotFound();

        // Not in the kitchen: a new order, a delivered one
        $new = $this->orderWithItems(OrderStatus::NEW);
        $this->bump($this->cook, $new, $new->items->first())->assertUnprocessable();
        $done = $this->orderWithItems(OrderStatus::DELIVERED);
        $this->bump($this->cook, $done, $done->items->first())->assertUnprocessable();
        $this->assertSame('pending', $new->items->first()->fresh()->kitchen_status->value);
    }

    public function test_marking_the_order_ready_marks_every_line(): void
    {
        $order = $this->orderWithItems(OrderStatus::PREPARING);

        // From any path: here the manager's old endpoint
        $this->setStatus($order, $this->manager, 'ready')->assertOk();

        $this->assertSame(['ready', 'ready'], $order->items()->pluck('kitchen_status')->map->value->all());
        $this->assertSame(0, $order->items()->whereNull('ready_at')->count());
    }

    public function test_kitchen_tickets(): void
    {
        $this->travelTo(now()->subMinutes(10));
        $first = $this->orderWithItems(OrderStatus::ACCEPTED, ['order_type' => 'delivery']);
        $this->travelBack();
        $second = $this->orderWithItems(OrderStatus::PREPARING);
        $ready = $this->orderWithItems(OrderStatus::READY);
        $this->orderWithItems(OrderStatus::NEW);
        $this->orderWithItems(OrderStatus::DELIVERED);
        $this->orderWithItems(OrderStatus::ACCEPTED, ['branch_id' => $this->otherBranch->id]);

        $response = $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk();
        $this->assertSame([$first->uuid, $second->uuid], $response->json('data.*.uuid'));
        $this->assertFalse($response->json('meta.truncated'));

        $ticket = $response->json('data.0');
        $this->assertSame(['id', 'product_name', 'variant_name', 'quantity', 'unit', 'quantity_label', 'modifiers', 'kitchen_status', 'ready_at'], array_keys($ticket['items'][0]));
        $this->assertSame([['name' => 'Achchiq sous', 'group_name' => 'Sous', 'quantity' => 1]], $ticket['items'][0]['modifiers']);
        $this->assertSame('1.5 kg', $ticket['items'][1]['quantity_label']);
        $this->assertGreaterThanOrEqual(590, $ticket['waiting_seconds']);
        $this->assertNotNull($ticket['created_at']);
        $this->assertNotNull($ticket['accepted_at']);
        $this->assertStringContainsString('Piyozsiz', $ticket['notes']);
        $json = json_encode($response->json());
        foreach ([$this->customer->phone, $this->customer->name, 'Amir Temur', 'total_price', 'unit_price', '901234567'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $json);
        }

        $withReady = $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets?include_ready=1')->json('data.*.uuid');
        $this->assertSame([$first->uuid, $second->uuid, $ready->uuid], $withReady);

        // The owner picks a branch; a branch manager cannot read another branch's kitchen
        $this->assertCount(1, $this->actingAs($this->owner, 'sanctum')->getJson("/api/v1/staff/kitchen/tickets?branch_id={$this->otherBranch->id}")->json('data'));
        $this->actingAs($this->manager, 'sanctum')->getJson("/api/v1/staff/kitchen/tickets?branch_id={$this->otherBranch->id}")->assertForbidden();
    }

    public function test_a_cook_works_the_kitchen_display_with_a_pin_session(): void
    {
        $order = $this->orderWithItems(OrderStatus::ACCEPTED);
        $kds = $this->pairTerminal($this->owner, $this->branch, DeviceType::KDS);
        $session = $this->pinSession($kds, $this->cook);

        $this->assertSame([$order->uuid], $this->bearerJson('GET', '/api/v1/staff/kitchen/tickets', $session)->assertOk()->json('data.*.uuid'));
        foreach ($order->items as $item) {
            $this->bump($session, $order, $item)->assertOk();
        }
        $this->assertSame(OrderStatus::READY, $order->fresh()->status);

        // The audit log names the terminal of the PIN session
        $log = AuditLog::where('action', 'order.status_changed')->where('user_id', $this->cook->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($kds['device']->id, (int) $log->device_id);

        // A store-wide cook on branch A's display cannot bump branch B's order
        $storeCook = $this->makeStaff(UserRole::COOK);
        $storeSession = $this->pinSession($kds, $storeCook, '90517');
        $orderB = $this->orderWithItems(OrderStatus::ACCEPTED, ['branch_id' => $this->otherBranch->id]);
        $this->bump($storeSession, $orderB, $orderB->items->first())->assertNotFound();
        $this->bearerJson('POST', "/api/v1/staff/orders/{$orderB->uuid}/status", $storeSession, ['status' => 'preparing'])->assertNotFound();
    }
}
