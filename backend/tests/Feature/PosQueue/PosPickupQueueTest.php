<?php

namespace Tests\Feature\PosQueue;

use App\Domain\Pos\Orders\PosOrderService;
use App\Enums\OrderStatus;
use App\Events\Broadcast\QueueNumberBroadcast;
use App\Models\Order;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * P6-13 (A2): the pickup queue of counter orders with a number — preparing → ready → handed — from the kitchen
 * display and the till, announced on pos-branch (queue.*), with a re-announce; a counter order paid first stays in the
 * kitchen until its number is ready; a Telegram pickup order is called by its number; the board and new calls gated
 * by queue_calling.
 */
class PosPickupQueueTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
        $this->bootstrapQueue();
    }

    public function test_a_takeaway_is_prepared_bumped_ready_called_again_and_handed(): void
    {
        Event::fake([QueueNumberBroadcast::class]);

        $uuid = (string) $this->openTakeaway()->assertCreated()
            ->assertJsonPath('data.queue.number', 1)
            ->assertJsonPath('data.queue.status', 'preparing')
            ->json('data.uuid');
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.preparing'
            && $event->broadcastOn()[0]->name === "private-pos-branch.{$this->branch->id}"
            && $event->broadcastWith()['queue']['number'] === 1);

        $this->tillAs('GET', '/pos/queue')->assertOk()
            ->assertJsonPath('data.business_date', '2026-10-22')
            ->assertJsonPath('data.preparing.0.order_uuid', $uuid)
            ->assertJsonPath('data.preparing.0.number', 1)
            ->assertJsonCount(0, 'data.ready');

        // Calling a number not ready yet is refused
        $this->tillAs('POST', "/pos/orders/{$uuid}/call")->assertStatus(422)->assertJsonPath('data.reason', 'not_ready');

        // The kitchen bumps the last line: the number is ready and announced
        $this->bumpAll($uuid);
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('ready', $order->pickup_status);
        $this->assertSame(OrderStatus::READY, $order->status);
        $this->assertNotNull($order->pickup_ready_at);
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.ready' && !$event->called);
        $this->tillAs('GET', '/pos/queue')->assertOk()->assertJsonCount(0, 'data.preparing')->assertJsonPath('data.ready.0.number', 1);

        // The till calls it again, twice (not within 15 s of the last call)
        $this->tillAs('POST', "/pos/orders/{$uuid}/call")->assertOk()->assertJsonPath('data.call_count', 1)->assertJsonPath('data.status', 'ready');
        $this->tillAs('POST', "/pos/orders/{$uuid}/call")->assertStatus(422)->assertJsonPath('data.reason', 'call_too_soon');
        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(15));
        $this->tillAs('POST', "/pos/orders/{$uuid}/call")->assertOk()->assertJsonPath('data.call_count', 2);
        Event::assertDispatchedTimes(QueueNumberBroadcast::class, 4);
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.ready' && $event->called
            && $event->broadcastWith()['queue']['call_count'] === 2);

        // Handed: the number leaves the board, the lines are served; the bill is still to pay
        $this->tillAs('POST', "/pos/orders/{$uuid}/handed")->assertOk()->assertJsonPath('data.status', 'handed');
        $this->tillAs('POST', "/pos/orders/{$uuid}/handed")->assertOk()->assertJsonPath('data.status', 'handed');
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.handed');
        $order->refresh();
        $this->assertNotNull($order->pickup_handed_at);
        $this->assertSame(['served'], $order->items()->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());
        $this->assertNull($order->closed_at);
        $this->tillAs('GET', '/pos/queue')->assertOk()->assertJsonCount(0, 'data.preparing')->assertJsonCount(0, 'data.ready');
        $this->tillAs('POST', "/pos/orders/{$uuid}/ready")->assertStatus(422)->assertJsonPath('data.reason', 'already_handed');
    }

    public function test_a_counter_order_paid_first_stays_in_the_kitchen_until_its_number_is_ready(): void
    {
        $response = $this->openTakeaway()->assertCreated();
        $uuid = (string) $response->json('data.uuid');

        // Paid at once (avval to'lov): the order is closed, the kitchen still cooks it
        $bill = (string) $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $response->json('data.version'), 'mode' => 'single'])->assertSuccessful()->json('data.bills.0.uuid');
        $this->tillAs('POST', "/pos/bills/{$bill}/payments", ['shift_uuid' => $this->shift->uuid, 'tenders' => [['tender' => 'cash', 'amount' => '36000']]])->assertSuccessful()
            ->assertJsonPath('data.bill.ticket.number', 1)
            ->assertJsonPath('data.bill.ticket.order_label', 'Olib ketish');
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertSame('preparing', $order->pickup_status);
        $this->assertSame(['sent'], $order->items()->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());

        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()
            ->assertJsonPath('data.0.uuid', $uuid);

        // The kitchen bumps it: the number is ready, the order stays closed
        $this->bumpAll($uuid);
        $order->refresh();
        $this->assertSame('ready', $order->pickup_status);
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets?include_ready=1')->assertOk()->assertJsonPath('data.0.uuid', $uuid);

        // The cook recalls a line: preparing again; bumped again: ready
        $item = $order->items()->firstOrFail();
        $this->actingAs($this->cook, 'sanctum')->postJson("/api/v1/staff/orders/{$uuid}/items/{$item->id}/recall")->assertOk();
        $this->assertSame('preparing', $order->refresh()->pickup_status);
        $this->bumpAll($uuid);
        $this->assertSame('ready', $order->refresh()->pickup_status);

        $this->tillAs('POST', "/pos/orders/{$uuid}/handed")->assertOk()->assertJsonPath('data.status', 'handed')->assertJsonPath('data.order_status', 'delivered');
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets?include_ready=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_counter_without_a_kitchen_display_marks_the_number_ready_itself(): void
    {
        $uuid = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');

        $this->tillAs('POST', "/pos/orders/{$uuid}/ready")->assertOk()->assertJsonPath('data.status', 'ready')->assertJsonPath('data.order_status', 'ready');
        $this->tillAs('POST', "/pos/orders/{$uuid}/ready")->assertOk()->assertJsonPath('data.status', 'ready');
        $this->assertSame(['ready'], Order::where('uuid', $uuid)->firstOrFail()->items()->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());

        // A hall order at a table is not a counter order
        $hall = (string) $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2, 'items' => [['variant_id' => $this->tea->uuid, 'quantity' => 1]], 'send' => true])
            ->assertCreated()->assertJsonPath('data.queue.status', null)->json('data.uuid');
        $this->tillAs('POST', "/pos/orders/{$hall}/ready")->assertStatus(422)->assertJsonPath('data.reason', 'not_in_queue');
    }

    public function test_a_telegram_pickup_order_is_called_by_its_number(): void
    {
        $telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $telegram);
        $this->customer->forceFill(['telegram_id' => 7001])->save();
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);

        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk()->assertJsonPath('data.queue.number', 101);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/ready")->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.order_status', 'ready')
            ->assertJsonPath('data.channel', 'telegram');
        $ready = array_values(array_filter($telegram->sent, fn (array $message) => $message['chat'] === '7001' && str_contains($message['text'], 'raqam 101')));
        $this->assertCount(1, $ready);
        $this->assertStringContainsString('Buyurtmangiz tayyor, raqam 101', $ready[0]['text']);

        $this->tillAs('POST', "/pos/orders/{$online->uuid}/call")->assertOk()->assertJsonPath('data.call_count', 1);
        $this->assertCount(2, array_filter($telegram->sent, fn (array $message) => str_contains($message['text'], 'raqam 101')));

        $this->tillAs('POST', "/pos/orders/{$online->uuid}/handed")->assertOk()->assertJsonPath('data.status', 'handed')->assertJsonPath('data.order_status', 'delivered');
        $this->assertSame(OrderStatus::DELIVERED, $online->refresh()->status);

        // The customer's own order JSON carries the number (the Mini App order card)
        $this->withBearer(null);
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/orders?scope=history')->assertOk()
            ->assertJsonPath('data.0.queue_number', 101)
            ->assertJsonPath('data.0.pickup_status', 'handed');
    }

    public function test_a_cancelled_order_leaves_the_board(): void
    {
        Event::fake([QueueNumberBroadcast::class]);
        $response = $this->openTakeaway()->assertCreated();
        $order = Order::where('uuid', (string) $response->json('data.uuid'))->firstOrFail();

        app(PosOrderService::class)->cancel($order, (int) $response->json('data.version'), $this->manager, 'Mijoz ketdi', null);

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertNull($order->pickup_status);
        $this->assertSame(1, $order->queue_number);
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.canceled'
            && $event->broadcastWith()['queue']['number'] === 1);
        $this->tillAs('GET', '/pos/queue')->assertOk()->assertJsonCount(0, 'data.preparing');
    }

    public function test_calling_is_gated_by_the_queue_calling_module(): void
    {
        $uuid = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $this->useProfile($this->branch, 'choyxona');

        $this->tillAs('GET', '/pos/queue')->assertForbidden()->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', 'queue_calling');
        // A new call is the module's; a number already in the queue is still finished (review P6 C#L6)
        $this->tillAs('POST', "/pos/orders/{$uuid}/call")->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->tillAs('POST', "/pos/orders/{$uuid}/ready")->assertOk()->assertJsonPath('data.status', 'ready');
        $this->tillAs('POST', "/pos/orders/{$uuid}/handed")->assertOk()->assertJsonPath('data.status', 'handed');

        // A new takeaway of the choyxona is numbered on (above the day's blocks: a till may still print from its block);
        // its pickup state is kept for the kitchen (review P6 C#H1) but it is not on the board
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 101)->assertJsonPath('data.queue.status', 'preparing');
        $this->tillAs('GET', '/pos/queue')->assertForbidden();
    }
}
