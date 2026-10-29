<?php

namespace Tests\Feature\PosQueue;

use App\Domain\Pos\Orders\PosOrderService;
use App\Enums\OrderStatus;
use App\Events\Broadcast\QueueNumberBroadcast;
use App\Events\OrderStatusUpdated;
use App\Listeners\SendCustomerStatusAlert;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use App\Services\Telegram\TelegramMessenger;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBooking\FakeTelegramMessenger;
use Tests\TestCase;

/**
 * Review fixes of P6-13 (order numbers and calling): a counter order paid first keeps its kitchen lines in every format
 * (C#H1), an offline order keeps the business day it was taken on (C#M1), a refunded order cancelled leaves the queue
 * and the kitchen (C#M3), no proposed numbers without the module (C#L4), ready/handed finish a number after the module
 * was switched off (C#L6), the customer's status alert under a late queue (C#L5), the unique index migration with
 * duplicates (C#L7), the call cooldown and its customer messages (S#3), the online path audited (S#6).
 */
class PosQueueReviewFixesTest extends TestCase
{
    use PosQueueFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosQueue();
    }

    // --- C#H1: a counter order paid first keeps its kitchen lines whatever the format -------------------------------

    public function test_a_dark_kitchen_order_paid_first_stays_in_the_kitchen_and_is_handed_when_bumped(): void
    {
        $this->useProfile($this->branch, 'dark_kitchen');
        $this->assertPaidFirstStaysInTheKitchen($this->openTakeaway());
    }

    public function test_a_kafe_with_calling_off_keeps_a_paid_first_order_in_the_kitchen(): void
    {
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->pos_settings ?? [], [
            'profile' => 'kafe',
            'modules' => ['queue_calling' => false],
        ])])->save();
        $this->assertPaidFirstStaysInTheKitchen($this->openTakeaway());
    }

    public function test_a_choyxona_counter_order_paid_first_stays_in_the_kitchen(): void
    {
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->pos_settings ?? [], ['profile' => 'choyxona', 'mode' => 'counter'])])->save();

        // A hall order at the counter (no table) and a takeaway
        $this->assertPaidFirstStaysInTheKitchen($this->tillAs('POST', '/pos/orders', [
            'channel' => 'pos_hall',
            'guests_count' => 1,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 2]],
            'send' => true,
        ]));
        $this->assertPaidFirstStaysInTheKitchen($this->openTakeaway(), '36000');
    }

    public function test_a_choyxona_takeaway_ready_before_its_payment_is_handed_by_it(): void
    {
        $this->useProfile($this->branch, 'choyxona');
        Event::fake([QueueNumberBroadcast::class]);
        $response = $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.status', 'preparing');
        $uuid = (string) $response->json('data.uuid');

        $this->bumpAll($uuid);
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(OrderStatus::READY, $order->status);
        $this->assertSame('ready', $order->pickup_status);

        $this->pay($uuid, (int) $order->version);
        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertSame('handed', $order->pickup_status);
        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets?include_ready=1')->assertOk()->assertJsonCount(0, 'data');
        Event::assertNotDispatched(QueueNumberBroadcast::class);
    }

    public function test_the_choyxona_hall_flow_is_unchanged(): void
    {
        $this->useProfile($this->branch, 'choyxona');
        $response = $this->tillAs('POST', '/pos/orders', [
            'channel' => 'pos_hall', 'table_id' => $this->room->id, 'guests_count' => 2,
            'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 2]], 'send' => true,
        ])->assertCreated()->assertJsonPath('data.queue.status', null);
        $uuid = (string) $response->json('data.uuid');

        $this->pay($uuid, (int) Order::where('uuid', $uuid)->value('version'), null);
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNull($order->pickup_status);
        // As before: the payment closes its lines
        $this->assertSame(['ready'], $order->items()->whereNotNull('sent_at')->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());
    }

    // --- C#M1: an offline order keeps its business day ---------------------------------------------------------------

    public function test_an_offline_order_taken_before_the_day_start_keeps_its_day_and_number(): void
    {
        $this->bootstrapQueue();
        $this->openTakeaway(null, 1)->assertCreated();
        // 04:30 local of the 23rd (still the 22nd's business day): the till prints 2 from its block of the 22nd
        Carbon::setTestNow(Carbon::parse('2026-10-22 23:30:00', 'UTC'));
        $op = $this->offlineOpen('K1-050', 2);
        // 06:00 local: a new business day when the till syncs
        Carbon::setTestNow(Carbon::parse('2026-10-23 01:00:00', 'UTC'));
        $this->sync([$op])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.queue_number', 2);

        $order = Order::where('local_number', 'K1-050')->sole();
        $this->assertSame('2026-10-22', $order->business_date?->toDateString());
        $this->assertSame(2, $order->queue_number);
        $this->assertEmpty(array_filter($order->sync_flags ?? [], fn (array $flag) => $flag['flag'] === 'queue_number_taken'));
    }

    // --- C#M3: a refunded order cancelled leaves the queue and the kitchen ------------------------------------------

    public function test_a_refunded_paid_first_order_cancelled_leaves_the_queue_and_the_kitchen(): void
    {
        $this->bootstrapQueue();
        $response = $this->openTakeaway()->assertCreated();
        $uuid = (string) $response->json('data.uuid');
        $this->pay($uuid, (int) $response->json('data.version'));
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame('preparing', $order->pickup_status);
        // Refunded in full
        Payment::where('order_id', $order->id)->update(['status' => 'refunded']);
        $order->forceFill(['payment_status' => 'refunded'])->save();

        Event::fake([QueueNumberBroadcast::class]);
        app(PosOrderService::class)->cancel($order->refresh(), (int) $order->version, $this->manager, 'Mijoz voz kechdi', null);

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELED, $order->status);
        $this->assertNull($order->pickup_status);
        $this->assertSame(['void'], $order->items()->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());
        Event::assertDispatched(QueueNumberBroadcast::class, fn (QueueNumberBroadcast $event) => $event->broadcastAs() === 'queue.canceled');
        $this->tillAs('GET', '/pos/queue')->assertOk()->assertJsonCount(0, 'data.preparing');
        $this->withBearer(null);
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets?include_ready=1')->assertOk()->assertJsonCount(0, 'data');
    }

    // --- C#L4: no proposed numbers without the module ----------------------------------------------------------------

    public function test_without_the_numbers_module_a_proposed_number_is_ignored(): void
    {
        $this->useProfile($this->branch, 'choyxona');

        $this->openTakeaway(null, 999999)->assertCreated()->assertJsonPath('data.queue.number', 1);
        $this->openTakeaway()->assertCreated()->assertJsonPath('data.queue.number', 2);
        $this->sync([$this->offlineOpen('K1-077', 500)])->assertOk()
            ->assertJsonPath('data.results.0.status', 'applied')
            ->assertJsonPath('data.results.0.refs.queue_number', 3);
        $this->assertEmpty(Order::where('local_number', 'K1-077')->sole()->sync_flags ?? []);
    }

    // --- C#L6: ready and handed finish a number after the module was switched off ------------------------------------

    public function test_ready_and_handed_finish_a_number_after_calling_was_switched_off(): void
    {
        $this->bootstrapQueue();
        $first = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $second = (string) $this->openTakeaway()->assertCreated()->json('data.uuid');
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();
        $this->useProfile($this->branch, 'choyxona');

        $this->tillAs('POST', "/pos/orders/{$first}/ready")->assertOk()->assertJsonPath('data.status', 'ready');
        $this->tillAs('POST', "/pos/orders/{$first}/call")->assertForbidden()->assertJsonPath('code', 'module_disabled');
        $this->tillAs('POST', "/pos/orders/{$first}/handed")->assertOk()->assertJsonPath('data.status', 'handed');
        $this->tillAs('POST', "/pos/orders/{$second}/handed")->assertOk()->assertJsonPath('data.status', 'handed');
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/ready")->assertOk()->assertJsonPath('data.order_status', 'ready');
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/handed")->assertOk()->assertJsonPath('data.order_status', 'delivered');

        // An order not in the queue does not enter it without the module
        $fresh = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->tillAs('POST', "/pos/orders/{$fresh->uuid}/accept")->assertOk();
        $this->tillAs('POST', "/pos/orders/{$fresh->uuid}/ready")->assertForbidden()
            ->assertJsonPath('code', 'module_disabled')->assertJsonPath('module', 'queue_calling');
    }

    // --- C#L5: the customer's alert under a late queue ---------------------------------------------------------------

    public function test_a_late_queue_never_drops_a_status_alert_but_a_superseded_one_is_skipped(): void
    {
        $telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $telegram);
        $this->customer->forceFill(['telegram_id' => 7001])->save();
        $order = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $statuses = app(OrderStatusService::class);

        Queue::fake();
        // Two transactions: accepted, then preparing before any worker ran
        $statuses->transition($order->refresh(), OrderStatus::ACCEPTED, $this->manager);
        $statuses->transition($order->refresh(), OrderStatus::PREPARING, $this->manager);
        // One transaction: ready and delivered (handed over at once)
        DB::transaction(function () use ($statuses, $order) {
            $statuses->transition($order->refresh(), OrderStatus::READY, $this->manager);
            $statuses->transition($order->refresh(), OrderStatus::DELIVERED, $this->manager);
        });

        $jobs = Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === SendCustomerStatusAlert::class);
        $this->assertCount(3, $jobs);
        foreach ($jobs as $job) {
            // As a worker would run it: the order read again (it is delivered by now)
            app(SendCustomerStatusAlert::class)->handle(unserialize(serialize($job->data[0])));
        }

        $texts = array_values(array_map(fn (array $message) => $message['text'], array_filter($telegram->sent, fn (array $message) => $message['chat'] === '7001')));
        $this->assertCount(3, $texts);
        $this->assertStringContainsString('qabul qilindi', $texts[0]);
        $this->assertStringContainsString('tayyorlanmoqda', $texts[1]);
        $this->assertStringContainsString('olib ketishingiz mumkin', $texts[2]);
        $this->assertSame(OrderStatus::DELIVERED, $order->refresh()->status);
    }

    public function test_an_event_made_outside_a_move_announces_the_latest_history_row(): void
    {
        $order = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        app(OrderStatusService::class)->transition($order->refresh(), OrderStatus::ACCEPTED, $this->manager);

        $event = new OrderStatusUpdated($order->refresh());
        $this->assertSame('accepted', $event->status);
        $this->assertSame((int) \App\Models\OrderStatusHistory::where('order_id', $order->id)->max('id'), $event->historyId);
    }

    // --- C#L7: the unique index migration with duplicates ------------------------------------------------------------

    public function test_the_unique_index_migration_renumbers_duplicate_numbers(): void
    {
        $migration = require database_path('migrations/2026_11_10_200200_add_pickup_columns_to_orders_table.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('orders', 'pickup_status'));

        $a = $this->numbered(7, '2026-10-22 08:00:00');
        $b = $this->numbered(7, '2026-10-22 09:00:00');
        $c = $this->numbered(7, '2026-10-22 10:00:00');
        $d = $this->numbered(9, '2026-10-22 08:30:00');

        $migration->up();

        $numbers = Order::withTrashed()->whereIn('id', [$a, $b, $c, $d])->orderBy('id')->pluck('queue_number', 'id')->all();
        $this->assertSame([$a => 7, $b => 10, $c => 11, $d => 9], $numbers);
        $this->assertTrue(Schema::hasColumn('orders', 'pickup_status'));
    }

    // --- S#3: the call cooldown and its customer messages ------------------------------------------------------------

    public function test_a_number_is_not_called_again_within_the_cooldown_and_messages_the_customer_three_times(): void
    {
        $telegram = new FakeTelegramMessenger();
        $this->app->instance(TelegramMessenger::class, $telegram);
        $this->customer->forceFill(['telegram_id' => 7001])->save();
        $this->bootstrapQueue();
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/ready")->assertOk();

        Event::fake([QueueNumberBroadcast::class]);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/call")->assertOk()->assertJsonPath('data.call_count', 1);
        Carbon::setTestNow(now()->addSeconds(5));
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/call")->assertStatus(422)
            ->assertJsonPath('data.reason', 'call_too_soon')
            ->assertJsonPath('data.retry_in', 10);

        for ($call = 2; $call <= 5; $call++) {
            Carbon::setTestNow(now()->addSeconds(15));
            $this->tillAs('POST', "/pos/orders/{$online->uuid}/call")->assertOk()->assertJsonPath('data.call_count', $call);
        }

        // Five calls announced on the board; the customer heard "ready" once and the first three calls
        Event::assertDispatchedTimes(QueueNumberBroadcast::class, 5);
        $this->assertCount(4, array_filter($telegram->sent, fn (array $message) => $message['chat'] === '7001' && str_contains($message['text'], 'raqam')));
    }

    // --- S#6: the online path is audited -----------------------------------------------------------------------------

    public function test_ready_and_handed_of_an_online_order_are_audited(): void
    {
        $this->bootstrapQueue();
        $online = $this->orderWithItems(OrderStatus::NEW, ['order_type' => 'pickup']);
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/ready")->assertOk();
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/handed")->assertOk();

        foreach (['pos.queue_ready', 'pos.queue_handed'] as $action) {
            $log = AuditLog::where('action', $action)->where('model_id', $online->id)->sole();
            $this->assertSame((int) $this->cashier->id, (int) $log->user_id);
        }
    }

    // --- helpers ----------------------------------------------------------------------------------------------------

    /**
     * Pays the order's single bill in cash.
     */
    private function pay(string $uuid, int $version, ?string $amount = '36000'): void
    {
        $bill = $this->tillAs('POST', "/pos/orders/{$uuid}/bills", ['version' => $version, 'mode' => 'single'])->assertSuccessful();
        $amount ??= (string) $bill->json('data.bills.0.total');
        $this->tillAs('POST', '/pos/bills/' . $bill->json('data.bills.0.uuid') . '/payments', [
            'shift_uuid' => $this->shift->uuid,
            'tenders' => [['tender' => 'cash', 'amount' => $amount]],
        ])->assertSuccessful();
    }

    /**
     * A counter order paid at once while its lines cook: it stays on the kitchen display, the kitchen's bump hands it
     * over (nobody calls a number here), no `queue.*` event is announced.
     */
    private function assertPaidFirstStaysInTheKitchen(TestResponse $opened, ?string $amount = null): void
    {
        Event::fake([QueueNumberBroadcast::class]);
        $uuid = (string) $opened->assertCreated()->json('data.uuid');
        $this->pay($uuid, (int) $opened->json('data.version'), $amount);

        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertSame('preparing', $order->pickup_status);
        $this->assertSame(['sent'], $order->items()->whereNotNull('sent_at')->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());
        $this->withBearer(null);
        $tickets = $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets')->assertOk()->json('data');
        $this->assertContains($uuid, array_column($tickets, 'uuid'));

        $this->bumpAll($uuid);
        $order->refresh();
        $this->assertSame('handed', $order->pickup_status);
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->actingAs($this->cook, 'sanctum')->getJson('/api/v1/staff/kitchen/tickets?include_ready=1')->assertOk()
            ->assertJsonMissing(['uuid' => $uuid]);
        Event::assertNotDispatched(QueueNumberBroadcast::class);
    }

    private function numbered(int $number, string $createdAt): int
    {
        return (int) DB::table('orders')->insertGetId([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->cashier->id,
            'short_code' => 'POS' . strtoupper(\Illuminate\Support\Str::random(6)),
            'status' => 'delivered',
            'order_type' => 'pickup',
            'channel' => 'pos_takeaway',
            'payment_method' => 'cash',
            'total_price' => 0,
            'commission_amount' => 0,
            'business_date' => '2026-10-22',
            'queue_number' => $number,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
