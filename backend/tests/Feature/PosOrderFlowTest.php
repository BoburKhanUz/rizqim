<?php

namespace Tests\Feature;

use App\Domain\Pos\Contracts\PosOrderOpener;
use App\Enums\OrderStatus;
use App\Events\Broadcast\KitchenItemStatusChangedBroadcast;
use App\Events\Broadcast\OrderCreatedBroadcast;
use App\Events\Broadcast\OrderItemsChangedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Models\AuditLog;
use App\Models\MenuBatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StopListEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P3-12: a POS hall order's life: open (with the choy-non items), rounds with now and held lines, sending to the
 * kitchen (stations, held lines only by id, the order starts), the kitchen display seeing only what was sent, voids
 * (before and after send, the approval token), guests, move and merge, the version check, the Idempotency-Key, the
 * kazan and the stop-list, fiscal readiness, and the realtime events.
 */
class PosOrderFlowTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
    }

    public function test_a_waiter_opens_a_room_order_with_a_first_round_and_choy_non_per_guest(): void
    {
        Event::fake([OrderCreatedBroadcast::class, OrderItemsChangedBroadcast::class]);
        $this->choyNonRule();

        $response = $this->openPos($this->waiter, $this->room, 5, [
            'items' => [$this->line($this->shashlik, 4, ['note' => 'Achchiqroq', 'seat_no' => 2])],
        ]);
        $order = $this->openedOrder($response);

        $response->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.channel', 'pos_hall')
            ->assertJsonPath('data.type', 'dine_in')
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.table.name', '1-xona')
            ->assertJsonPath('data.hall.name', 'Asosiy zal')
            ->assertJsonPath('data.waiter.id', $this->waiter->id)
            ->assertJsonPath('data.guests_count', 5);

        $items = collect($response->json('data.items'));
        $this->assertCount(3, $items);
        $this->assertSame([1, 1, 1], $items->pluck('round_no')->all());
        $this->assertSame('Achchiqroq', $items->firstWhere('product_name', 'Shashlik')['note']);
        $this->assertSame(2, $items->firstWhere('product_name', 'Shashlik')['seat_no']);
        // 0.5 choynak per guest, 5 guests: 3 choynaks (pieces rounded up); a bread each
        $this->assertSame(3, $items->firstWhere('product_name', 'Choy')['quantity']);
        $this->assertSame(5, $items->firstWhere('product_name', 'Non')['quantity']);
        $this->assertSame(['pending'], $items->pluck('kitchen_status')->unique()->values()->all());

        // 4 × 18 000 + 3 × 5 000 + 5 × 4 000
        $this->assertSame('107000.00', $order->subtotal);
        $this->assertSame('107000.00', $order->total_price);
        $this->assertSame((int) $this->waiter->id, (int) $order->user_id);
        $this->assertSame('1-xona', $order->table_ref);
        $this->assertSame('0.00', $order->commission_amount);

        Event::assertDispatched(OrderCreatedBroadcast::class);
        $this->assertTrue(AuditLog::where('action', 'pos.order_opened')->where('model_id', $order->id)->exists());
    }

    public function test_rounds_hold_and_send_with_stations_and_the_first_send_starts_the_order(): void
    {
        Event::fake([OrderItemsChangedBroadcast::class, OrderStatusChangedBroadcast::class, OrderCreatedBroadcast::class]);
        $order = $this->openedOrder($this->openPos($this->cashier));

        $round = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", [
            'version' => 1,
            'items' => [
                $this->line($this->tea, 2),
                $this->line($this->shashlik, 6, ['fire_mode' => 'hold']),
            ],
        ])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.rounds', 1);
        $held = collect($round->json('data.items'))->firstWhere('fire_mode', 'hold');
        $this->assertSame((int) $this->cashier->id, $round->json('data.items.0.added_by.id'));

        // Send: the `now` line only; the order starts cooking
        $sent = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 2])
            ->assertOk()->assertJsonPath('data.status', 'preparing')->assertJsonPath('data.version', 3);
        $items = collect($sent->json('data.items'))->keyBy('id');
        $this->assertSame('sent', $items->firstWhere('product_name', 'Choy')['kitchen_status']);
        $this->assertSame(77, $items->firstWhere('product_name', 'Choy')['station_id']);
        $this->assertNotNull($items->firstWhere('product_name', 'Choy')['sent_at']);
        $this->assertSame('pending', $items[$held['id']]['kitchen_status']);

        // Nothing new to send
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 3])
            ->assertStatus(422)->assertJsonPath('data.reason', 'nothing_to_send');

        // A second round, sent at once; the held line fires only by id
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", [
            'version' => 3, 'send' => true, 'items' => [$this->line($this->bread, 4)],
        ])->assertOk()->assertJsonPath('data.rounds', 2);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 4, 'item_ids' => [$held['id']]])
            ->assertOk();
        $this->assertSame('sent', OrderItem::find($held['id'])->kitchen_status->value);

        Event::assertDispatched(OrderStatusChangedBroadcast::class, fn ($event) => $event->status === 'preparing');
        Event::assertDispatched(OrderItemsChangedBroadcast::class, fn ($event) => $event->change === 'sent' && $event->broadcastWith()['version'] === 3);
        Event::assertDispatched(OrderItemsChangedBroadcast::class, fn ($event) => $event->change === 'round_added');
        $channels = collect((new OrderItemsChangedBroadcast($order->refresh(), 'sent'))->broadcastOn())->pluck('name')->all();
        $this->assertContains('private-pos-branch.' . $this->branch->id, $channels);
        $this->assertSame('order.items_changed', (new OrderItemsChangedBroadcast($order, 'sent'))->broadcastAs());
    }

    public function test_the_kitchen_display_sees_only_sent_lines_and_held_pre_orders_with_a_due_time(): void
    {
        $cook = $this->makeCook();
        $order = $this->openedOrder($this->openPos($this->cashier, null, 4, [
            'items' => [$this->line($this->tea, 1), $this->line($this->shashlik, 2, ['fire_mode' => 'hold'])],
        ]));

        // Nothing sent yet: not a ticket
        $this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->assertJsonCount(0, 'data');

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 1])->assertOk();
        $ticket = $this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data.0');
        $this->assertSame(['Choy'], array_column($ticket['items'], 'product_name'));
        $this->assertSame('sent', $ticket['items'][0]['kitchen_status']);
        $this->assertSame(1, $ticket['items_total']);

        // The cook bumps it ready and recalls it back to sent; the order (hall) stays preparing
        $itemId = $ticket['items'][0]['id'];
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$itemId}/ready")->assertOk()
            ->assertJsonPath('data.items_ready', 1);
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$itemId}/recall")->assertOk();
        $this->assertSame('sent', OrderItem::find($itemId)->kitchen_status->value);

        // The held line is not the kitchen's: it cannot be bumped
        $held = OrderItem::where('order_id', $order->id)->where('fire_mode', 'hold')->firstOrFail();
        $this->actingAs($cook, 'sanctum')->postJson("/api/v1/staff/orders/{$order->uuid}/items/{$held->id}/ready")
            ->assertStatus(422)->assertJsonPath('data.reason', 'kitchen_status');

        // A pre-order line held with its due time is on the ticket (the kitchen plans it by time)
        $held->forceFill(['due_at' => now()->addHours(2)])->save();
        $ticket = $this->actingAs($cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->json('data.0');
        $this->assertSame(['Choy', 'Shashlik'], array_column($ticket['items'], 'product_name'));
        $this->assertSame('hold', $ticket['items'][1]['fire_mode']);
        $this->assertSame(OrderStatus::PREPARING, $order->refresh()->status);
    }

    public function test_voiding_before_send_returns_stock_and_needs_no_reason(): void
    {
        Event::fake([OrderItemsChangedBroadcast::class, OrderCreatedBroadcast::class]);
        $order = $this->openedOrder($this->openPos($this->waiter, null, 4, ['items' => [$this->line($this->shashlik, 3)]]));
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(997, (int) $this->shashlik->refresh()->stock);

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/void", ['version' => 1])
            ->assertOk()
            ->assertJsonPath('data.items.0.kitchen_status', 'void')
            ->assertJsonPath('data.subtotal', '0.00')
            ->assertJsonPath('data.total_price', '0.00');
        $this->assertSame(1000, (int) $this->shashlik->refresh()->stock);

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/void", ['version' => 2])
            ->assertStatus(422)->assertJsonPath('data.reason', 'already_void');
        Event::assertDispatched(OrderItemsChangedBroadcast::class, fn ($event) => $event->change === 'voided' && $event->itemIds === [(int) $item->id]);
    }

    public function test_voiding_a_sent_line_needs_a_reason_and_void_item_or_a_manager_approval(): void
    {
        Event::fake([KitchenItemStatusChangedBroadcast::class, OrderItemsChangedBroadcast::class, OrderCreatedBroadcast::class]);
        $order = $this->openedOrder($this->openPos($this->waiter, null, 4, ['items' => [$this->line($this->shashlik, 2)], 'send' => true]));
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();
        $uri = "/pos/orders/{$order->uuid}/items/{$item->id}/void";

        // No reason
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1])->assertStatus(422)->assertJsonValidationErrors('reason');
        // A waiter has no orders.void_item: a manager's approval is needed
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1, 'reason' => 'Mehmon bekor qildi'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1, 'reason' => 'Mehmon bekor qildi', 'approval_token' => 'wrong'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_invalid');
        // A token for another permission is refused too
        $discountToken = $this->approval('orders.discount', $this->manager);
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1, 'reason' => 'Mehmon bekor qildi', 'approval_token' => $discountToken])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_invalid');

        $token = $this->approval('orders.void_item', $this->manager);
        $this->pos($this->waiter, 'POST', $uri, ['version' => 1, 'reason' => 'Mehmon bekor qildi', 'approval_token' => $token])
            ->assertOk()->assertJsonPath('data.items.0.void_reason', 'Mehmon bekor qildi');

        // Sent and cooked: the portion is not given back unless asked
        $this->assertSame(998, (int) $this->shashlik->refresh()->stock);
        $log = AuditLog::where('action', 'pos.item_voided')->where('model_id', $order->id)->firstOrFail();
        $this->assertSame((int) $this->manager->id, $log->payload_json['approved_by']);
        $this->assertSame('Mehmon bekor qildi', $log->payload_json['reason']);
        Event::assertDispatched(KitchenItemStatusChangedBroadcast::class, fn ($event) => $event->kitchenStatus === 'void');

        // A manager voids a sent line with their own orders.void_item, returning the portion
        $second = $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'send' => true, 'items' => [$this->line($this->shashlik, 1)]])
            ->assertOk()->json('data.items.1.id');
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/items/{$second}/void", ['version' => 3, 'reason' => 'Xato', 'restock' => true])
            ->assertOk();
        $this->assertSame(998, (int) $this->shashlik->refresh()->stock);
    }

    public function test_guests_joining_get_choy_non_and_per_guest_charges_follow(): void
    {
        $this->choyNonRule();
        $this->serviceFee('0', null, '10000', 'per_person');
        $order = $this->openedOrder($this->openPos($this->waiter, $this->room, 3));
        $this->assertSame('30000.00', $order->place_fee_amount);

        $response = $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/guests", ['version' => 1, 'guests_count' => 6])
            ->assertOk()->assertJsonPath('data.guests_count', 6)->assertJsonPath('data.place_fee_amount', '60000.00');

        // 3 guests: 2 choynaks and 3 breads; 6 guests: 3 choynaks and 6 breads, so round 2 adds 1 + 3
        $round2 = collect($response->json('data.items'))->where('round_no', 2);
        $this->assertSame(1, $round2->firstWhere('product_name', 'Choy')['quantity']);
        $this->assertSame(3, $round2->firstWhere('product_name', 'Non')['quantity']);

        // Fewer guests take nothing off
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/guests", ['version' => 2, 'guests_count' => 2])
            ->assertOk()->assertJsonCount(4, 'data.items')->assertJsonPath('data.place_fee_amount', '20000.00');
    }

    public function test_a_hall_rule_replaces_the_branch_rule_and_a_takeaway_gets_no_choy_non(): void
    {
        $this->choyNonRule();
        \App\Models\AutoGuestItem::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->vipHall->id,
            'product_variant_id' => $this->tea->id, 'quantity_per_guest' => '1', 'is_active' => true,
        ]);
        \App\Models\AutoGuestItem::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'hall_id' => $this->vipHall->id,
            'product_variant_id' => $this->bread->id, 'quantity_per_guest' => '1', 'is_active' => false,
        ]);

        $vip = $this->openPos($this->cashier, $this->vipRoom, 4)->assertCreated();
        $this->assertSame([['Choy', 4]], collect($vip->json('data.items'))->map(fn ($item) => [$item['product_name'], $item['quantity']])->all());

        $takeaway = $this->pos($this->cashier, 'POST', '/pos/orders', ['branch_id' => $this->branch->id, 'channel' => 'pos_takeaway'])
            ->assertCreated()->assertJsonPath('data.type', 'pickup')->assertJsonPath('data.table', null);
        $this->assertSame([], $takeaway->json('data.items'));
    }

    public function test_a_stale_version_is_a_409_and_changes_nothing(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 1)]])->assertOk();

        // A second device still holding version 1
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->bread, 9)]])
            ->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict')->assertJsonPath('data.version', 2);
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/guests", ['version' => 1, 'guests_count' => 9])->assertStatus(409);

        $this->assertSame(1, OrderItem::where('order_id', $order->id)->count());
        $this->assertSame(4, $order->refresh()->guests_count);
        $this->assertSame(2, $order->version);
    }

    public function test_the_idempotency_key_is_required_and_a_retry_replays_the_first_answer(): void
    {
        $this->actingAs($this->cashier, 'sanctum')->postJson('/api/v1/pos/orders', [
            'branch_id' => $this->branch->id, 'channel' => 'pos_hall', 'guests_count' => 2,
        ])->assertStatus(428)->assertJsonPath('data.reason', 'idempotency_key_required');

        $key = (string) Str::uuid();
        $first = $this->openPos($this->cashier, $this->table, 2, ['items' => [$this->line($this->tea, 1)]]);
        $order = $this->openedOrder($first);

        $round = ['version' => 1, 'items' => [$this->line($this->bread, 2)]];
        $a = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", $round, $key)->assertOk();
        $b = $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", $round, $key)->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($a->json('data'), $b->json('data'));
        $this->assertSame(2, OrderItem::where('order_id', $order->id)->count());

        // The same key with another body
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'items' => [$this->line($this->bread, 5)]], $key)
            ->assertStatus(409);

        // external_id: the device's retry of an open with a new key returns the same order
        $one = $this->openPos($this->cashier, $this->tapchan, 2, ['external_id' => 'K1-0001'])->assertCreated()->json('data.uuid');
        $two = $this->openPos($this->cashier, $this->tapchan, 2, ['external_id' => 'K1-0001'])->assertCreated()->json('data.uuid');
        $this->assertSame($one, $two);
    }

    public function test_a_room_takes_one_open_order_and_a_closed_zone_none(): void
    {
        $this->openedOrder($this->openPos($this->cashier, $this->room));
        $this->openPos($this->waiter, $this->room)->assertStatus(422)->assertJsonPath('data.reason', 'table_occupied');

        // Tables and tapchans take several parties
        $this->openPos($this->cashier, $this->table, 2)->assertCreated();
        $this->openPos($this->cashier, $this->table, 2)->assertCreated();

        $this->vipHall->forceFill(['is_active' => false])->save();
        $this->openPos($this->cashier, $this->vipRoom)->assertStatus(422)->assertJsonPath('data.reason', 'table_inactive');

        // A hall order needs a guest
        $this->openPos($this->cashier, $this->table, 0)->assertStatus(422);
    }

    public function test_an_order_moves_to_another_table_with_its_charges(): void
    {
        $this->serviceFee('10');
        $this->serviceFee('15', $this->vipHall);
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2, ['items' => [$this->line($this->shashlik, 10)]]));
        $this->assertSame('18000.00', $order->service_charge_amount);

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/move", ['version' => 1, 'table_id' => $this->vipRoom->id])
            ->assertOk()
            ->assertJsonPath('data.table.name', 'VIP-1')
            ->assertJsonPath('data.hall.name', 'VIP')
            ->assertJsonPath('data.service_charge_amount', '27000.00')
            ->assertJsonPath('data.charges.service_charge_percent', '15.00');
        $this->assertSame('VIP-1', $order->refresh()->table_ref);
        $this->assertSame('VIP', $order->hall_ref);

        // Into an occupied room: merge instead
        $other = $this->openedOrder($this->openPos($this->cashier, $this->room, 2));
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/move", ['version' => 2, 'table_id' => $this->room->id])
            ->assertStatus(422)->assertJsonPath('data.reason', 'table_occupied');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/move", ['version' => 2, 'table_id' => $this->vipRoom->id])
            ->assertStatus(422)->assertJsonPath('data.reason', 'same_table');
        $this->assertNotNull($other);
        $this->assertTrue(AuditLog::where('action', 'pos.order_moved')->where('model_id', $order->id)->exists());
    }

    public function test_two_orders_merge_into_one_bill(): void
    {
        Event::fake([OrderStatusChangedBroadcast::class, OrderItemsChangedBroadcast::class, OrderCreatedBroadcast::class]);
        $target = $this->openedOrder($this->openPos($this->cashier, $this->room, 4, ['items' => [$this->line($this->tea, 2)]]));
        $source = $this->openedOrder($this->openPos($this->waiter, $this->table, 3, ['items' => [$this->line($this->shashlik, 5)]]));
        $this->pos($this->waiter, 'POST', "/pos/orders/{$source->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->bread, 2)]])->assertOk();

        // A stale source version
        $this->pos($this->cashier, 'POST', "/pos/orders/{$target->uuid}/merge", ['version' => 1, 'source_uuid' => $source->uuid, 'source_version' => 1])
            ->assertStatus(409);

        $response = $this->pos($this->cashier, 'POST', "/pos/orders/{$target->uuid}/merge", ['version' => 1, 'source_uuid' => $source->uuid, 'source_version' => 2])
            ->assertOk()->assertJsonPath('data.guests_count', 7)->assertJsonPath('data.version', 2);

        $items = collect($response->json('data.items'));
        $this->assertCount(3, $items);
        $this->assertSame([1, 2, 3], $items->pluck('round_no')->sort()->values()->all());
        // 2 × 5 000 + 5 × 18 000 + 2 × 4 000
        $this->assertSame('108000.00', $response->json('data.subtotal'));

        $source->refresh();
        $this->assertSame(OrderStatus::CANCELED, $source->status);
        $this->assertSame('0.00', $source->total_price);
        $this->assertStringContainsString($target->short_code, (string) $source->cancel_note);
        Event::assertDispatched(OrderStatusChangedBroadcast::class, fn ($event) => $event->uuid === $source->uuid && $event->status === 'canceled');
        Event::assertDispatched(OrderItemsChangedBroadcast::class, fn ($event) => $event->change === 'merged');

        // A closed order takes no more changes
        $this->pos($this->waiter, 'POST', "/pos/orders/{$source->uuid}/guests", ['version' => 3, 'guests_count' => 2])
            ->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');
    }

    public function test_the_kazan_portions_are_taken_and_a_sold_out_or_stop_listed_item_is_refused(): void
    {
        $batch = MenuBatch::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $this->plov->product_id,
            'quantity_produced' => '3', 'quantity_left' => '3', 'started_at' => now(),
        ]);
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 6));

        // Kg in 0.5 steps, at least 0.5
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->plov, '0.3')]])
            ->assertStatus(422);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->plov, '2.5')]])
            ->assertOk()->assertJsonPath('data.items.0.quantity_label', '2.5 kg')->assertJsonPath('data.items.0.total_price', '300000.00');
        $this->assertSame('0.500', $batch->refresh()->quantity_left);

        // More than is left in the kazan
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'items' => [$this->line($this->plov, '1')]])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        // Voided before send: back to the kazan
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/items/{$item->id}/void", ['version' => 2])->assertOk();
        $this->assertSame('3.000', $batch->refresh()->quantity_left);

        StopListEntry::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'subject_type' => 'product', 'subject_id' => $this->shashlik->product_id, 'reason' => "Go'sht tugadi"]);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 3, 'items' => [$this->line($this->shashlik, 1)]])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        // The batch-tracked plov without an open kazan is "tugadi"
        $batch->forceFill(['closed_at' => now()])->save();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 3, 'items' => [$this->line($this->plov, '1')]])
            ->assertStatus(422);
    }

    public function test_the_pos_channel_price_applies(): void
    {
        \App\Models\ChannelPrice::create([
            'store_id' => $this->store->id, 'product_id' => $this->shashlik->product_id, 'product_variant_id' => $this->shashlik->id,
            'channel' => 'pos_hall', 'price' => '20000',
        ]);
        \App\Models\ChannelPrice::create([
            'store_id' => $this->store->id, 'product_id' => $this->tea->product_id, 'product_variant_id' => null,
            'channel' => 'pos_takeaway', 'is_hidden' => true,
        ]);

        $this->openPos($this->cashier, $this->table, 2, ['items' => [$this->line($this->shashlik, 2)]])
            ->assertCreated()->assertJsonPath('data.items.0.unit_price', '20000.00')->assertJsonPath('data.subtotal', '40000.00');
        $this->pos($this->cashier, 'POST', '/pos/orders', ['branch_id' => $this->branch->id, 'channel' => 'pos_takeaway', 'items' => [$this->line($this->tea, 1)]])
            ->assertStatus(422);
    }

    public function test_in_fiscal_mode_an_item_without_mxik_is_not_sold(): void
    {
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox']])->save();
        $this->shashlik->product->forceFill(['mxik_code' => '10202001001000000'])->save();

        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2, ['items' => [$this->line($this->shashlik, 1)]]));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 1)]])
            ->assertStatus(422)->assertJsonPath('data.reason', 'mxik_missing');
    }

    public function test_a_telegram_order_is_not_a_pos_order(): void
    {
        $online = $this->checkout($this->customer);

        $this->pos($this->cashier, 'POST', "/pos/orders/{$online->uuid}/rounds", ['version' => 0, 'items' => [$this->line($this->tea, 1)]])
            ->assertStatus(422)->assertJsonPath('data.reason', 'not_pos_order');
        $this->pos($this->cashier, 'GET', "/pos/orders/{$online->uuid}")->assertStatus(422);
    }

    public function test_the_opener_contract_adds_a_bookings_pre_orders_on_hold(): void
    {
        $due = now()->addHours(3)->startOfMinute();
        $order = app(PosOrderOpener::class)->open(
            (int) $this->branch->id,
            'pos_hall',
            (int) $this->vipRoom->id,
            12,
            (int) $this->waiter->id,
            null,
            (int) $this->cashier->id,
            null,
            [['product_variant_id' => (int) $this->shashlik->id, 'quantity' => '20', 'note' => 'Qo\'y go\'shti', 'due_at' => $due->toIso8601String()]],
        );

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(OrderStatus::ACCEPTED, $order->status);
        $this->assertSame((int) $this->waiter->id, (int) $order->waiter_id);
        $line = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('hold', $line->fire_mode->value);
        $this->assertSame(1, $line->round_no);
        $this->assertTrue($due->equalTo($line->due_at));
        $this->assertNull($line->sent_at);
        $this->assertSame('360000.00', $order->subtotal);
    }
}
