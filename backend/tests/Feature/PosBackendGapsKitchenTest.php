<?php

namespace Tests\Feature;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3-26 (KDS gaps): a held pre-order line with a due time is bumped ready before the till sends it (ready implies
 * sent), and the tickets feed keeps the orders cancelled or rejected lately (`canceled_within`) with when they went.
 */
class PosBackendGapsKitchenTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());
    }

    public function test_a_held_pre_order_line_with_a_due_time_is_bumped_ready_and_counts_as_sent(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 10, ['items' => [
            $this->line($this->shashlik, 10, ['fire_mode' => 'hold', 'due_at' => now()->addHours(3)->toIso8601String()]),
            $this->line($this->tea, 2, ['fire_mode' => 'hold']),
        ]]));
        $cook = $this->makeCook($this->branch);
        [$held, $plain] = OrderItem::where('order_id', $order->id)->orderBy('id')->get()->all();
        $this->assertNull($held->sent_at);

        // The kitchen sees the pre-order (due time) and bumps it hours ahead
        $tickets = $this->pos($cook, 'GET', '/staff/kitchen/tickets')->assertOk()->json('data');
        $this->assertSame([$held->id], array_column($tickets[0]['items'], 'id'));
        $this->pos($cook, 'POST', "/staff/orders/{$order->uuid}/items/{$held->id}/ready")->assertOk()
            ->assertJsonPath('data.items.0.kitchen_status', 'ready');

        $held->refresh();
        $this->assertSame(KitchenItemStatus::READY, $held->kitchen_status);
        $this->assertNotNull($held->sent_at);
        $this->assertSame(77, (int) $held->station_id);
        $this->assertSame(2, (int) $order->refresh()->version, 'devices holding the order refetch it');
        $this->assertSame(OrderStatus::PREPARING, $order->status);

        // The till no longer sends it; the held line without a due time is not the kitchen's yet
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/send", ['version' => 2, 'item_ids' => [$held->id]])->assertUnprocessable()->assertJsonPath('data.reason', 'items_not_sendable');
        $this->pos($cook, 'POST', "/staff/orders/{$order->uuid}/items/{$plain->id}/ready")->assertUnprocessable()->assertJsonPath('data.reason', 'kitchen_status');

        // A recall takes it back to "sent" (still in the kitchen), not to the till
        $this->pos($cook, 'POST', "/staff/orders/{$order->uuid}/items/{$held->id}/recall")->assertOk();
        $this->assertSame(KitchenItemStatus::SENT, $held->refresh()->kitchen_status);
    }

    public function test_tickets_keep_the_orders_cancelled_lately_when_asked(): void
    {
        $cook = $this->makeCook($this->branch);
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 2, ['send' => true, 'items' => [$this->line($this->shashlik, 2)]]));
        $neverSent = $this->openedOrder($this->openPos($this->cashier, $this->table, 2, ['items' => [$this->line($this->tea)]]));
        $online = $this->orderWithItems(OrderStatus::ACCEPTED, ['channel' => 'telegram']);
        $live = $this->orderWithItems(OrderStatus::PREPARING, ['channel' => 'telegram']);

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => (int) $order->refresh()->version, 'reason' => 'Mehmon ketdi'])->assertOk();
        $this->pos($this->manager, 'POST', "/pos/orders/{$neverSent->uuid}/cancel", ['version' => (int) $neverSent->refresh()->version, 'reason' => 'Xato'])->assertOk();
        $online->forceFill(['status' => OrderStatus::CANCELED])->save();
        OrderStatusHistory::create(['order_id' => $online->id, 'from_status' => 'accepted', 'status' => OrderStatus::CANCELED->value]);

        // Without the parameter: the live tickets only
        $this->assertSame([$live->uuid], array_column($this->pos($cook, 'GET', '/staff/kitchen/tickets')->assertOk()->json('data'), 'uuid'));

        $tickets = collect($this->pos($cook, 'GET', '/staff/kitchen/tickets?canceled_within=10')->assertOk()->json('data'))->keyBy('uuid');
        $this->assertEqualsCanonicalizing([$live->uuid, $order->uuid, $online->uuid], $tickets->keys()->all(), 'an order that never reached the kitchen stays out');
        $this->assertNull($tickets[$live->uuid]['canceled_at']);
        $this->assertSame('canceled', $tickets[$order->uuid]['status']);
        $this->assertNotNull($tickets[$order->uuid]['canceled_at']);
        $this->assertSame('void', $tickets[$order->uuid]['items'][0]['kitchen_status']);
        $this->assertNotNull($tickets[$order->uuid]['items'][0]['voided_at']);
        $this->assertNotNull($tickets[$online->uuid]['canceled_at']);

        // Older than the window: gone; a window out of range: 422
        $this->travel(11)->minutes();
        $this->assertSame([$live->uuid], array_column($this->pos($cook, 'GET', '/staff/kitchen/tickets?canceled_within=10')->json('data'), 'uuid'));
        $this->pos($cook, 'GET', '/staff/kitchen/tickets?canceled_within=61')->assertUnprocessable();

        // Another branch's cancellations never show
        $this->pos($cook, 'GET', "/staff/kitchen/tickets?canceled_within=60&branch_id={$this->otherBranch->id}")->assertForbidden();
    }
}
