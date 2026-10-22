<?php

namespace Tests\Feature;

use App\Enums\KitchenItemStatus;
use App\Enums\OrderStatus;
use App\Events\Broadcast\OrderBillRequestedBroadcast;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3-26 (wave 2 backend gaps) on POS orders: a new online order accepted or rejected at the till (rejection by
 * orders.cancel or a manager's approval), the incoming and closed lists, the waiter's bill request, the floor's
 * ready/unsent counts, bill-requested flag and the reservation's guest, and an order's booking deposit.
 */
class PosBackendGapsOrdersTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->app->instance(TelegramMessenger::class, new FakeTelegramMessenger());
    }

    private function onlineNew(array $attributes = []): Order
    {
        return $this->orderWithItems(OrderStatus::NEW, $attributes + ['channel' => 'telegram']);
    }

    public function test_incoming_lists_new_online_orders_of_the_branch_only(): void
    {
        $mine = $this->onlineNew();
        $this->onlineNew(['branch_id' => $this->otherBranch->id]);
        $this->orderWithItems(OrderStatus::ACCEPTED, ['channel' => 'telegram']);
        $this->openedOrder($this->openPos($this->cashier));

        $rows = $this->pos($this->cashier, 'GET', '/pos/orders?scope=incoming')->assertOk()->json('data');
        $this->assertSame([$mine->uuid], array_column($rows, 'uuid'));
        $this->assertSame('new', $rows[0]['status']);
        $this->assertFalse($rows[0]['bill_requested']);
        // The online card: lines and the customer for staff who run orders
        $this->assertSame($this->customer->name, $rows[0]['online']['customer']['name']);
        $this->assertSame(['Cheeseburger', 'Osh'], array_column($rows[0]['online']['items'], 'product_name'));
        $this->assertSame('new', $rows[0]['online']['status']);
        $cookRows = $this->pos($this->makeCook($this->branch), 'GET', '/pos/orders?scope=incoming')->assertOk()->json('data');
        $this->assertNull($cookRows[0]['online']['customer'], 'no contact details for the kitchen');

        // The open queue does not list it (it is not accepted yet)
        $open = $this->pos($this->cashier, 'GET', '/pos/orders')->assertOk()->json('data');
        $this->assertNotContains($mine->uuid, array_column($open, 'uuid'));

        $this->pos($this->cashier, 'GET', '/pos/orders?scope=bogus')->assertUnprocessable()->assertJsonPath('data.reason', 'validation');
    }

    public function test_accept_moves_a_new_online_order_to_accepted_once(): void
    {
        $order = $this->onlineNew();

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/accept")->assertOk()
            ->assertJsonPath('data.uuid', $order->uuid)
            ->assertJsonPath('data.status', 'accepted');
        $this->assertSame(OrderStatus::ACCEPTED, $order->refresh()->status);

        // Again: unchanged
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');

        // Without a key: 428; a stale version: 409; a POS order: 422; another branch's: 404
        $this->withHeaders(['Idempotency-Key' => ''])->actingAs($this->cashier, 'sanctum')->postJson("/api/v1/pos/orders/{$this->onlineNew()->uuid}/accept")->assertStatus(428);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$this->onlineNew()->uuid}/accept", ['version' => 99])->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');
        $pos = $this->openedOrder($this->openPos($this->cashier));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$pos->uuid}/accept")->assertUnprocessable()->assertJsonPath('data.reason', 'not_online_order');
        $other = $this->onlineNew(['branch_id' => $this->otherBranch->id]);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$other->uuid}/accept")->assertNotFound();
        $this->assertSame(OrderStatus::NEW, $other->refresh()->status);
    }

    public function test_reject_needs_orders_cancel_or_a_manager_approval(): void
    {
        $order = $this->onlineNew();

        // A cashier has no orders.cancel
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/reject", ['reason' => 'Osh tugadi'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'orders.cancel');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/reject", [])->assertUnprocessable()->assertJsonPath('data.reason', 'validation');
        $this->assertSame(OrderStatus::NEW, $order->refresh()->status);

        $token = $this->approval('orders.cancel', $this->manager);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/reject", ['reason' => 'Osh tugadi', 'cancel_reason' => 'out_of_stock', 'approval_token' => $token])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $order->refresh();
        $this->assertSame(OrderStatus::REJECTED, $order->status);
        $this->assertSame('out_of_stock', $order->cancel_reason?->value);
        $this->assertSame('Osh tugadi', $order->cancel_note);
        $this->assertSame((int) $this->cashier->id, (int) $order->cancelled_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.online_order_rejected']);

        // The manager rejects with their own permission; an accepted order can no longer be rejected here
        $second = $this->onlineNew();
        $this->pos($this->manager, 'POST', "/pos/orders/{$second->uuid}/reject", ['reason' => 'Yopilmoqdamiz'])->assertOk();
        $this->assertSame('other', $second->refresh()->cancel_reason?->value);
        $accepted = $this->orderWithItems(OrderStatus::ACCEPTED, ['channel' => 'telegram']);
        $this->pos($this->manager, 'POST', "/pos/orders/{$accepted->uuid}/reject", ['reason' => 'Kech'])->assertUnprocessable()->assertJsonPath('data.reason', 'order_not_new');
    }

    public function test_closed_scope_lists_the_pos_orders_closed_on_a_business_day(): void
    {
        $today = $this->openedOrder($this->openPos($this->cashier));
        $today->forceFill(['closed_at' => now(), 'status' => OrderStatus::DELIVERED, 'business_date' => '2026-10-21'])->save();
        $yesterday = $this->openedOrder($this->openPos($this->cashier, $this->table));
        $yesterday->forceFill(['closed_at' => now()->subDay(), 'status' => OrderStatus::DELIVERED, 'business_date' => '2026-10-20'])->save();
        $this->openedOrder($this->openPos($this->cashier, $this->tapchan));

        $rows = $this->pos($this->cashier, 'GET', '/pos/orders?scope=closed&business_date=2026-10-21')->assertOk()->json('data');
        $this->assertSame([$today->uuid], array_column($rows, 'uuid'));
        $this->assertSame('2026-10-21', $rows[0]['business_date']);
        $this->assertNotNull($rows[0]['closed_at']);

        $this->assertSame([$yesterday->uuid], array_column($this->pos($this->cashier, 'GET', '/pos/orders?scope=closed&business_date=2026-10-20')->json('data'), 'uuid'));
        $this->pos($this->cashier, 'GET', '/pos/orders?scope=closed&business_date=21.10.2026')->assertUnprocessable();
    }

    public function test_a_waiter_requests_the_bill_once_and_the_till_hears_it(): void
    {
        Event::fake([OrderBillRequestedBroadcast::class]);
        $order = $this->openedOrder($this->openPos($this->waiter));

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/request-bill", ['version' => 1])->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.bill_requested_at', fn ($value) => is_string($value));
        // Again (even with the old version): unchanged, nothing announced twice
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/request-bill")->assertOk()->assertJsonPath('data.version', 2);
        Event::assertDispatchedTimes(OrderBillRequestedBroadcast::class, 1);
        Event::assertDispatched(OrderBillRequestedBroadcast::class, fn (OrderBillRequestedBroadcast $event) => $event->broadcastAs() === 'order.bill_requested'
            && $event->broadcastWith()['requested_by'] === (int) $this->waiter->id
            && in_array("private-pos-branch.{$this->branch->id}", array_map(fn ($channel) => $channel->name, $event->broadcastOn()), true));
        $this->assertSame((int) $this->waiter->id, (int) $order->refresh()->bill_requested_by);

        // The floor and the queue show it
        $place = collect($this->pos($this->cashier, 'GET', '/pos/floor')->assertOk()->json('data.halls.0.tables'))->firstWhere('id', $this->room->id);
        $this->assertTrue($place['bill_requested']);
        $this->assertTrue($place['orders'][0]['bill_requested']);
        $this->assertTrue(collect($this->pos($this->cashier, 'GET', '/pos/orders')->json('data'))->firstWhere('uuid', $order->uuid)['bill_requested']);

        // Another waiter's order needs a manager's approval; a stale version is refused
        $other = $this->openedOrder($this->openPos($this->waiter, $this->table));
        $second = $this->makeStaff(\App\Enums\UserRole::WAITER, $this->branch);
        $this->pos($second, 'POST', "/pos/orders/{$other->uuid}/request-bill")->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        $this->pos($second, 'POST', "/pos/orders/{$other->uuid}/request-bill", ['version' => 7])->assertStatus(409);
    }

    public function test_the_floor_counts_ready_and_unsent_lines_and_names_the_reservation_for_staff(): void
    {
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table));
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'send' => true, 'items' => [$this->line($this->shashlik, 2), $this->line($this->tea)]])->assertOk();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'items' => [$this->line($this->bread)]])->assertOk();
        OrderItem::where('order_id', $order->id)->where('product_variant_id', $this->shashlik->id)->update(['kitchen_status' => KitchenItemStatus::READY->value, 'ready_at' => now()]);

        Booking::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'dining_table_id' => $this->room->id,
            'starts_at' => now()->addMinutes(30), 'ends_at' => now()->addHours(3), 'contact_name' => 'Jamshid aka',
            'contact_phone' => '+998901112233', 'status' => 'confirmed', 'guests_planned' => 12,
        ]);

        $floor = $this->pos($this->waiter, 'GET', '/pos/floor')->assertOk()->json('data.halls.0.tables');
        $summary = collect($floor)->firstWhere('id', $this->table->id)['orders'][0];
        $this->assertSame(1, $summary['ready_count']);
        $this->assertSame(1, $summary['unsent_count']);
        $this->assertSame(3, $summary['items_count']);
        $this->assertFalse($summary['bill_requested']);
        $reservation = collect($floor)->firstWhere('id', $this->room->id)['reservation'];
        $this->assertSame('Jamshid aka', $reservation['contact_name']);
        $this->assertSame('+998901112233', $reservation['contact_phone']);
        $this->assertSame(12, $reservation['guests']);

        // A cook's screen sees the reservation, not the guest's name and phone
        $cook = $this->makeCook($this->branch);
        $reservation = collect($this->pos($cook, 'GET', '/pos/floor')->assertOk()->json('data.halls.0.tables'))->firstWhere('id', $this->room->id)['reservation'];
        $this->assertNotNull($reservation['booking_uuid']);
        $this->assertNull($reservation['contact_name']);
        $this->assertNull($reservation['contact_phone']);
    }

    public function test_an_orders_bills_and_the_order_name_its_booking_and_the_deposit_left(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 2, ['items' => [$this->line($this->shashlik, 3)]]));
        $this->pos($this->cashier, 'GET', "/pos/orders/{$order->uuid}/bills")->assertOk()
            ->assertJsonPath('data.order.booking_uuid', null)
            ->assertJsonPath('data.order.deposit_available', '0.00');

        $booking = Booking::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'dining_table_id' => $this->room->id,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(4), 'contact_name' => 'Jamshid',
            'status' => 'arrived', 'arrived_at' => now()->subHour(), 'order_id' => $order->id,
        ]);
        $order->forceFill(['booking_id' => $booking->id])->save();
        BookingPayment::create([
            'booking_id' => $booking->id, 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $order->id,
            'amount' => '300000', 'tender' => 'cash', 'status' => 'taken', 'user_id' => $this->cashier->id, 'applied_amount' => '50000',
        ]);

        $this->pos($this->cashier, 'GET', "/pos/orders/{$order->uuid}/bills")->assertOk()
            ->assertJsonPath('data.order.booking_uuid', $booking->uuid)
            ->assertJsonPath('data.order.deposit_available', '250000.00');
        $this->pos($this->cashier, 'GET', "/pos/orders/{$order->uuid}")->assertOk()
            ->assertJsonPath('data.booking_uuid', $booking->uuid)
            ->assertJsonPath('data.deposit_available', '250000.00')
            ->assertJsonPath('data.bill_requested_at', null);
    }
}
