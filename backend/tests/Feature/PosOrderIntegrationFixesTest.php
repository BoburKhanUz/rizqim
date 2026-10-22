<?php

namespace Tests\Feature;

use App\Domain\Pos\Orders\PosOrderAlerts;
use App\Enums\KitchenItemStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\Broadcast\KitchenItemStatusChangedBroadcast;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\MenuBatch;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosApproval;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P3 integration fixes on POS hall orders: cancelling a whole order, the open orders queue of every channel, a line
 * served, the waiter rule, the owner's alerts (void after send, waived charge, cancel, long-open room) through the
 * notification preferences, the halls and tables layout for owners and managers only, and the daily prune.
 */
class PosOrderIntegrationFixesTest extends TestCase
{
    use PosHallFixtures;
    use RefreshDatabase;

    protected FakeTelegramMessenger $telegram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosHall();
        $this->telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $this->telegram);
    }

    private function kazan(string $quantity = '3'): MenuBatch
    {
        return MenuBatch::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'product_id' => $this->plov->product_id,
            'quantity_produced' => $quantity, 'quantity_left' => $quantity, 'started_at' => now(),
        ]);
    }

    /**
     * @return list<string>
     */
    private function ownerAlerts(): array
    {
        return array_values(array_map(fn (array $sent) => $sent['text'], array_filter($this->telegram->sent, fn (array $sent) => $sent['chat'] === (string) $this->owner->telegram_id)));
    }

    public function test_cancelling_an_order_voids_its_lines_gives_unsent_portions_back_and_frees_the_room(): void
    {
        Event::fake([KitchenItemStatusChangedBroadcast::class]);
        $batch = $this->kazan('3');
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'send' => true, 'items' => [
            $this->line($this->plov, '1'), $this->line($this->shashlik, 2),
        ]])->assertOk();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'items' => [$this->line($this->tea, 2), $this->line($this->plov, '0.5')]])->assertOk();
        $this->assertSame('1.500', $batch->refresh()->quantity_left);
        $teaStock = (string) $this->tea->refresh()->stock;
        $shashlikStock = (string) $this->shashlik->refresh()->stock;

        // A cashier has no orders.cancel: a manager's approval for it
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 3, 'reason' => 'Mehmonlar ketdi'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'orders.cancel');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 3])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 2, 'reason' => 'Mehmonlar ketdi', 'approval_token' => $this->approval('orders.cancel', $this->manager)])
            ->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');

        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 3, 'reason' => 'Mehmonlar ketdi', 'approval_token' => $this->approval('orders.cancel', $this->manager)])
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.version', 4);

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertSame($this->cashier->id, (int) $order->cancelled_by);
        $this->assertSame(0, OrderItem::where('order_id', $order->id)->where('kitchen_status', '!=', 'void')->count());
        $this->assertSame(['Mehmonlar ketdi'], OrderItem::where('order_id', $order->id)->distinct()->pluck('void_reason')->all());
        // The sent kazan plov and shashlik were cooked: not given back; the unsent tea and 0.5 kg plov are
        $this->assertSame('2.000', $batch->refresh()->quantity_left);
        $this->assertSame($shashlikStock, (string) $this->shashlik->refresh()->stock);
        $this->assertEquals((float) $teaStock + 2, (float) $this->tea->refresh()->stock);
        Event::assertDispatchedTimes(KitchenItemStatusChangedBroadcast::class, 2);

        $audit = AuditLog::where('action', 'pos.order_cancelled')->firstOrFail();
        $this->assertSame($this->manager->id, $audit->payload_json['approved_by']);
        $this->assertSame(2, $audit->payload_json['restocked_items']);
        $this->assertStringContainsString('Buyurtma bekor qilindi', $this->ownerAlerts()[0]);
        $this->assertStringContainsString('Mehmonlar ketdi', $this->ownerAlerts()[0]);

        // The room is free again; a cancelled order takes no more changes
        $this->openedOrder($this->openPos($this->cashier, $this->room, 2));
        $this->pos($this->owner, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 4, 'reason' => 'Yana'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'order_closed');
    }

    public function test_an_order_with_a_payment_is_not_cancelled_and_its_open_bills_are_voided_otherwise(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->tea, 2)]])->assertOk();
        $bill = OrderBill::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $order->id, 'number' => 1, 'total' => '10000', 'basis' => 'x', 'status' => 'printed']);
        $payment = Payment::create(['order_id' => $order->id, 'bill_id' => $bill->id, 'provider' => 'cash', 'amount' => '5000', 'status' => PaymentStatus::PAID, 'paid_at' => now()]);

        // The owner holds orders.cancel; the money must be refunded first
        $this->pos($this->owner, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 2, 'reason' => 'Xato buyurtma'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'has_payments')->assertJsonPath('data.paid_amount', '5000.00');
        $this->assertSame(OrderStatus::ACCEPTED, $order->refresh()->status);

        $payment->forceFill(['status' => PaymentStatus::REFUNDED])->save();
        $this->pos($this->owner, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 2, 'reason' => 'Xato buyurtma'])->assertOk();
        $this->assertSame('void', $bill->refresh()->status->value);
    }

    public function test_cancelling_a_booking_order_completes_the_booking_and_keeps_its_deposit_for_refund_or_retention(): void
    {
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 4));
        $booking = Booking::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'dining_table_id' => $this->room->id,
            'starts_at' => now()->subHour(), 'ends_at' => now()->addHours(4), 'contact_name' => 'Jamshid',
            'status' => 'arrived', 'arrived_at' => now()->subHour(), 'order_id' => $order->id,
        ]);
        $order->forceFill(['booking_id' => $booking->id])->save();
        $deposit = BookingPayment::create([
            'booking_id' => $booking->id, 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $order->id,
            'amount' => '300000', 'tender' => 'cash', 'status' => 'taken', 'user_id' => $this->cashier->id,
        ]);

        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/cancel", ['version' => 1, 'reason' => "To'y bekor bo'ldi"])->assertOk();

        $this->assertSame('completed', $booking->refresh()->status->value);
        $this->assertSame('taken', $deposit->refresh()->status->value);
        $this->assertStringContainsString("Bron avansi qoldi: 300 000 so'm", $this->ownerAlerts()[0]);
    }

    public function test_the_open_orders_queue_holds_every_channel_of_the_branch(): void
    {
        $hall = $this->openedOrder($this->openPos($this->cashier, $this->room, 3));
        $takeaway = $this->openedOrder($this->pos($this->cashier, 'POST', '/pos/orders', ['branch_id' => $this->branch->id, 'channel' => 'pos_takeaway']));
        $telegram = $this->makeOrder(OrderStatus::ACCEPTED, ['order_type' => 'pickup', 'channel' => 'telegram']);
        $this->makeOrder(OrderStatus::NEW, ['channel' => 'telegram']);
        $this->makeOrder(OrderStatus::ACCEPTED, ['branch_id' => $this->otherBranch->id]);
        $closed = $this->openedOrder($this->openPos($this->cashier, $this->table, 1));
        $closed->forceFill(['status' => OrderStatus::DELIVERED, 'closed_at' => now()])->save();
        // Paid, waiting for its receipt
        OrderBill::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $takeaway->id, 'number' => 1, 'total' => '0', 'basis' => 'x', 'status' => 'open', 'fiscal_status' => 'pending']);

        $list = $this->pos($this->cashier, 'GET', '/pos/orders?scope=open')->assertOk()->json('data');
        $this->assertSame([$hall->uuid, $takeaway->uuid, $telegram->uuid], array_column($list, 'uuid'));
        $this->assertSame(['pos_hall', 'pos_takeaway', 'telegram'], array_column($list, 'channel'));
        $this->assertSame([false, true, false], array_column($list, 'fiscal_pending'));
        $this->assertSame('1-xona', $list[0]['table']['name']);
        $this->assertSame('room', $list[0]['table']['kind']);
        $this->assertSame(3, $list[0]['guests_count']);
        $this->assertSame(1, $list[0]['version']);

        // orders.view: a cook sees it too; a courier does not; another branch's filter is refused; unknown scope 422
        $this->pos($this->makeCook($this->branch), 'GET', '/pos/orders')->assertOk()->assertJsonCount(3, 'data');
        $this->pos($this->courier, 'GET', '/pos/orders')->assertForbidden();
        $this->pos($this->cashier, 'GET', '/pos/orders?branch_id=' . $this->otherBranch->id)->assertForbidden();
        // (P3-26: `closed` and `incoming` are scopes now)
        $this->pos($this->cashier, 'GET', '/pos/orders?scope=archived')->assertStatus(422);
        $this->pos($this->owner, 'GET', '/pos/orders?branch_id=' . $this->otherBranch->id)->assertOk()->assertJsonCount(1, 'data');

        // A PIN session on another branch's terminal sees that branch only
        $terminal = $this->pairTerminal($this->owner, $this->otherBranch);
        $this->pos($this->pinSession($terminal, $this->owner), 'GET', '/pos/orders')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_sent_line_is_marked_served(): void
    {
        Event::fake([KitchenItemStatusChangedBroadcast::class]);
        $order = $this->openedOrder($this->openPos($this->waiter, $this->room, 2));
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'send' => true, 'items' => [$this->line($this->shashlik, 2)]])->assertOk();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 2, 'items' => [$this->line($this->tea, 1)]])->assertOk();
        [$shashlik, $tea] = OrderItem::where('order_id', $order->id)->orderBy('id')->get()->all();

        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$tea->id}/served", ['version' => 3])
            ->assertStatus(422)->assertJsonPath('data.reason', 'item_not_ready');

        $shashlik->forceFill(['kitchen_status' => KitchenItemStatus::READY->value, 'ready_at' => now()])->save();
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$shashlik->id}/served", ['version' => 3])
            ->assertOk()->assertJsonPath('data.items.0.kitchen_status', 'served')->assertJsonPath('data.version', 4);
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/{$shashlik->id}/served", ['version' => 4])
            ->assertStatus(422)->assertJsonPath('data.reason', 'already_served');
        $this->pos($this->waiter, 'POST', "/pos/orders/{$order->uuid}/items/999999/served", ['version' => 4])->assertNotFound();
        Event::assertDispatched(KitchenItemStatusChangedBroadcast::class, fn ($event) => $event->itemId === (int) $shashlik->id && $event->kitchenStatus === 'served');
        $this->assertSame(1, AuditLog::where('action', 'pos.item_served')->count());

        // A cook has no orders.manage
        $this->pos($this->makeCook($this->branch), 'POST', "/pos/orders/{$order->uuid}/items/{$shashlik->id}/served", ['version' => 4])->assertForbidden();
    }

    public function test_a_waiter_changes_only_their_own_orders(): void
    {
        $other = $this->makeStaff(UserRole::WAITER, $this->branch);
        $mine = $this->openedOrder($this->openPos($this->waiter, $this->room, 2));
        $this->assertSame($this->waiter->id, (int) $mine->waiter_id);

        // Another waiter: a manager's approval for orders.manage
        $round = ['version' => 1, 'items' => [$this->line($this->tea, 1)]];
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/rounds", $round)
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'orders.manage')->assertJsonPath('data.waiter_id', $this->waiter->id);
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/rounds", $round + ['approval_token' => $this->approval('orders.manage', $this->cashier)])
            ->assertForbidden()->assertJsonPath('data.reason', 'approver_not_allowed');
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/rounds", $round + ['approval_token' => 'nope'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_invalid');
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/rounds", $round + ['approval_token' => $this->approval('orders.manage', $this->manager)])
            ->assertOk()->assertJsonPath('data.waiter.id', $this->waiter->id);
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/guests", ['version' => 2, 'guests_count' => 3])->assertForbidden();
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/bills", ['version' => 2, 'mode' => 'single'])->assertForbidden()->assertJsonPath('data.reason', 'approval_required');

        // The waiter themselves, the cashier and the manager are not affected
        $this->pos($this->waiter, 'POST', "/pos/orders/{$mine->uuid}/guests", ['version' => 2, 'guests_count' => 3])->assertOk();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$mine->uuid}/guests", ['version' => 3, 'guests_count' => 4])->assertOk();

        // A void after sending: the manager's approval for the void covers the other waiter's order too
        $this->pos($this->waiter, 'POST', "/pos/orders/{$mine->uuid}/send", ['version' => 4])->assertOk();
        $item = OrderItem::where('order_id', $mine->id)->firstOrFail();
        $this->pos($other, 'POST', "/pos/orders/{$mine->uuid}/items/{$item->id}/void", ['version' => 5, 'reason' => "Sovib qoldi", 'approval_token' => $this->approval('orders.void_item', $this->manager)])
            ->assertOk();

        // An order nobody serves becomes the waiter's with their first round
        $free = $this->openedOrder($this->openPos($this->cashier, $this->tapchan, 2));
        $this->assertNull($free->waiter_id);
        $this->pos($other, 'POST', "/pos/orders/{$free->uuid}/rounds", ['version' => 1, 'items' => [$this->line($this->bread, 2)]])
            ->assertOk()->assertJsonPath('data.waiter.id', $other->id);
        $this->pos($this->waiter, 'POST', "/pos/orders/{$free->uuid}/send", ['version' => 2])->assertForbidden();
    }

    public function test_the_owner_hears_about_voids_after_sending_waived_charges_and_rooms_open_too_long(): void
    {
        $this->branch->forceFill(['pos_settings' => ['alerts' => ['void_amount' => '100000', 'open_room_hours' => 5]]])->save();
        $this->serviceFee('10', $this->hall);
        $order = $this->openedOrder($this->openPos($this->cashier, $this->room, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 1, 'send' => true, 'items' => [
            $this->line($this->shashlik, 6), $this->line($this->shashlik, 1),
        ]])->assertOk();
        [$big, $small] = OrderItem::where('order_id', $order->id)->orderBy('id')->get()->all();

        // 18 000 under the threshold: no alert; 108 000: alert
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/items/{$small->id}/void", ['version' => 2, 'reason' => 'Xato'])->assertOk();
        $this->assertSame([], $this->ownerAlerts());
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/items/{$big->id}/void", ['version' => 3, 'reason' => 'Kuyib qoldi'])->assertOk();
        $this->assertCount(1, $this->ownerAlerts());
        $this->assertStringContainsString("Oshxonaga yuborilgan taom o'chirildi: 108 000 so'm", $this->ownerAlerts()[0]);
        $this->assertStringContainsString('Kuyib qoldi', $this->ownerAlerts()[0]);

        // A waived charge: always
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/rounds", ['version' => 4, 'items' => [$this->line($this->shashlik, 5)]])->assertOk();
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/charges/service_charge/waive", ['version' => 5, 'reason' => 'Mehmon rozi emas', 'approval_token' => $this->approval('orders.discount', $this->manager)])->assertOk();
        $this->assertCount(2, $this->ownerAlerts());
        $this->assertStringContainsString("Xizmat haqi olib tashlandi: 9 000 so'm", $this->ownerAlerts()[1]);
        $this->assertStringContainsString('Tasdiqladi: ' . $this->manager->name, $this->ownerAlerts()[1]);

        // Open longer than 5 hours: told once
        $alerts = app(PosOrderAlerts::class);
        $this->assertSame(0, $alerts->checkLongOpen());
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(6)]);
        $this->assertSame(1, $alerts->checkLongOpen());
        $this->assertSame(0, $alerts->checkLongOpen());
        $this->artisan('pos:alert-long-open')->expectsOutputToContain('0 long-open')->assertSuccessful();
        $this->assertStringContainsString('Xona 6 soatdan beri ochiq (chegara: 5 soat)', $this->ownerAlerts()[2]);
        $this->assertNotNull($order->refresh()->long_open_alerted_at);

        // Switched off: none
        $this->branch->forceFill(['pos_settings' => ['alerts' => ['open_room_hours' => null]]])->save();
        $second = $this->openedOrder($this->openPos($this->cashier, $this->vipRoom, 2));
        Order::whereKey($second->id)->update(['created_at' => now()->subDays(1)]);
        $this->assertSame(0, $alerts->checkLongOpen());

        // The owner who switched POS alerts off on Telegram does not get them there
        NotificationPreference::create(['user_id' => $this->owner->id, 'type' => NotificationType::POS_ALERTS->value, 'channel' => NotificationChannel::TELEGRAM->value, 'enabled' => false]);
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/charges/service_charge/restore", ['version' => 6])->assertOk();
        $this->pos($this->manager, 'POST', "/pos/orders/{$order->uuid}/charges/service_charge/waive", ['version' => 7, 'reason' => 'Yana'])->assertOk();
        $this->assertCount(3, $this->ownerAlerts());
    }

    public function test_only_owners_and_managers_change_the_floor_layout(): void
    {
        foreach ([$this->cashier, $this->waiter] as $staff) {
            $this->pos($staff, 'GET', '/owner/halls')->assertOk();
            $this->pos($staff, 'POST', '/owner/halls', ['branch_id' => $this->branch->id, 'name' => 'Hovli'])->assertForbidden();
            $this->pos($staff, 'PATCH', "/owner/halls/{$this->hall->id}", ['name' => 'X'])->assertForbidden();
            $this->pos($staff, 'DELETE', "/owner/halls/{$this->vipHall->id}")->assertForbidden();
            $this->pos($staff, 'POST', '/owner/dining-tables', ['hall_id' => $this->hall->id, 'name' => 'Stol 9'])->assertForbidden();
            $this->pos($staff, 'PATCH', "/owner/dining-tables/{$this->table->id}", ['seats' => 6])->assertForbidden();
            $this->pos($staff, 'PUT', '/owner/dining-tables/layout', ['branch_id' => $this->branch->id, 'tables' => [['id' => $this->table->id, 'layout' => ['x' => 1, 'y' => 1, 'w' => 1, 'h' => 1]]]])->assertForbidden();
            $this->pos($staff, 'DELETE', "/owner/dining-tables/{$this->tapchan->id}")->assertForbidden();
        }

        $this->pos($this->manager, 'POST', '/owner/halls', ['branch_id' => $this->branch->id, 'name' => 'Hovli'])->assertCreated();
        $this->pos($this->owner, 'PATCH', "/owner/dining-tables/{$this->table->id}", ['seats' => 6])->assertOk();

        // A manager whose tables.manage the owner took away
        $this->pos($this->owner, 'PUT', '/owner/permissions', ['role' => 'manager', 'permission' => 'tables.manage', 'allowed' => false])->assertOk();
        $this->pos($this->manager, 'POST', '/owner/halls', ['branch_id' => $this->branch->id, 'name' => 'Yozgi'])->assertForbidden();

        // Seating and moving on the POS keep tables.manage
        $order = $this->openedOrder($this->openPos($this->cashier, $this->table, 2));
        $this->pos($this->cashier, 'POST', "/pos/orders/{$order->uuid}/move", ['version' => 1, 'table_id' => $this->tapchan->id])->assertOk();
    }

    public function test_expired_manager_approvals_are_pruned_daily(): void
    {
        $device = $this->pairTerminal($this->owner, $this->branch)['device'];
        $row = fn (string $expires) => PosApproval::create([
            'token_hash' => hash('sha256', Str::random(20)), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id,
            'device_id' => $device->id, 'permission' => 'orders.cancel', 'manager_id' => $this->manager->id, 'expires_at' => $expires,
        ]);
        $old = $row(now()->subDays(8)->toDateTimeString());
        $fresh = $row(now()->addMinute()->toDateTimeString());

        $this->artisan('schedule:list')->expectsOutputToContain('model:prune')->assertSuccessful();
        $this->artisan('schedule:list')->expectsOutputToContain('pos:alert-long-open')->assertSuccessful();
        $this->artisan('model:prune')->assertSuccessful();

        $this->assertNull(PosApproval::find($old->id));
        $this->assertNotNull(PosApproval::find($fresh->id));
    }
}
