<?php

namespace Tests\Feature\PosTakeaway;

use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6-34 (module scheduled_pickup): a takeaway or a Telegram pickup for a later time — the lead time, the window and the
 * opening hours checked; the kitchen gets it at pickup − prep time by the minute job (fake clock), the board shows it
 * then; the till changes or clears the time; the Mini App's pickup times.
 *
 * The clock: Thursday 22.10.2026 18:00 in Tashkent (13:00 UTC); the store is open all day unless a test sets hours.
 */
class PosScheduledPickupTest extends TestCase
{
    use PosTakeawayFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosTakeaway();
        $this->bootstrapQueue();
    }

    public function test_a_takeaway_for_later_waits_and_the_minute_job_fires_it_at_pickup_minus_prep(): void
    {
        // 19:00 pickup, 15 minutes of prep: the kitchen gets it at 18:45 (13:45 UTC)
        $opened = $this->takeaway([[$this->shashlik, 2]], ['send' => true, 'pickup_at' => '2026-10-22T14:00:00Z'])->assertCreated()
            ->assertJsonPath('data.pickup.pickup_at', '2026-10-22T14:00:00.000000Z')
            ->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T13:45:00.000000Z')
            ->assertJsonPath('data.pickup.waiting', true)
            ->assertJsonPath('data.pickup.local_time', '22.10 19:00')
            ->assertJsonPath('data.items.0.fire_mode', 'hold')
            ->assertJsonPath('data.items.0.sent_at', null)
            ->assertJsonPath('data.queue.status', null);
        $uuid = (string) $opened->json('data.uuid');
        $this->assertNotContains($uuid, array_column($this->kitchenTickets(), 'uuid'));

        // "Oshxonaga yuborish" does not send it early
        $this->tillAs('POST', "/pos/orders/{$uuid}/send", ['version' => (int) $opened->json('data.version')])
            ->assertStatus(422)->assertJsonPath('data.reason', 'nothing_to_send');

        Carbon::setTestNow('2026-10-22 13:44:00');
        $this->assertSame(0, app(ScheduledPickup::class)->fireDue());

        Carbon::setTestNow('2026-10-22 13:45:00');
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertNotNull($order->kitchen_fired_at);
        $this->assertSame('preparing', $order->pickup_status);
        $this->assertSame(OrderStatus::PREPARING, $order->status);
        $this->assertSame(['sent'], OrderItem::where('order_id', $order->id)->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());
        $this->assertContains($uuid, array_column($this->kitchenTickets(), 'uuid'));
        $this->assertTrue(AuditLog::where('action', 'pos.pickup_fired')->where('model_id', $order->id)->exists());

        // Fired once
        $this->assertSame(0, app(ScheduledPickup::class)->fireDue());
    }

    public function test_the_prep_time_is_the_longest_dish_and_a_paid_order_fires_from_the_closed_state(): void
    {
        $this->shashlik->product->forceFill(['wait_minutes' => 40])->save();
        $opened = $this->takeaway([[$this->shashlik, 1], [$this->tea, 1]], ['send' => true, 'pickup_at' => '2026-10-22T15:00:00Z'])->assertCreated()
            ->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T14:20:00.000000Z');
        $uuid = (string) $opened->json('data.uuid');

        // Paid first: closed, still waiting for its kitchen time
        $this->payAll($uuid, (int) $opened->json('data.version'));
        $order = Order::where('uuid', $uuid)->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNull($order->pickup_status);
        $this->assertNotContains($uuid, array_column($this->kitchenTickets(), 'uuid'));

        Carbon::setTestNow('2026-10-22 14:20:30');
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());
        $this->assertSame('preparing', $order->refresh()->pickup_status);
        $this->assertContains($uuid, array_column($this->kitchenTickets(), 'uuid'));
    }

    public function test_the_lead_time_the_window_and_the_opening_hours_are_checked(): void
    {
        // 20 minutes at least
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T13:10:00Z'])->assertStatus(422)
            ->assertJsonPath('data.reason', 'pickup_too_soon')
            ->assertJsonPath('data.earliest_at', '2026-10-22T13:20:00.000000Z');
        // Today's and tomorrow's business days: up to 24.10 05:00 local (24.10 00:00 UTC)
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-24T00:00:00Z'])->assertStatus(422)->assertJsonPath('data.reason', 'pickup_too_far');
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-23T23:30:00Z'])->assertCreated();
        // Only a takeaway
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T14:00:00Z', 'guests_count' => 1], 'pos_hall')->assertStatus(422)->assertJsonValidationErrors('pickup_at');

        // Open 09:00-22:00: 23:30 is closed, the 24/7 module takes it
        $this->openHours('09:00', '22:00');
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T18:30:00Z'])->assertStatus(422)->assertJsonPath('data.reason', 'pickup_closed');
        $this->branch->forceFill(['pos_settings' => array_merge($this->branch->pos_settings ?? [], ['modules' => ['round_the_clock' => true]])])->save();
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T18:30:00Z'])->assertCreated();

        // A branch without the module
        $this->useProfile($this->branch, 'choyxona');
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T14:00:00Z'])->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_the_till_changes_or_clears_the_time_until_the_kitchen_has_it(): void
    {
        $opened = $this->takeaway([[$this->shashlik, 1]], ['pickup_at' => '2026-10-22T14:00:00Z'])->assertCreated();
        $uuid = (string) $opened->json('data.uuid');

        $changed = $this->tillAs('PUT', "/pos/orders/{$uuid}/pickup-time", ['version' => (int) $opened->json('data.version'), 'pickup_at' => '2026-10-22T15:30:00Z'])
            ->assertOk()
            ->assertJsonPath('data.pickup.pickup_at', '2026-10-22T15:30:00.000000Z')
            ->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T15:15:00.000000Z');
        $this->assertTrue(AuditLog::where('action', 'pos.pickup_time_changed')->exists());
        $this->tillAs('PUT', "/pos/orders/{$uuid}/pickup-time", ['version' => (int) $changed->json('data.version'), 'pickup_at' => '2026-10-22T13:05:00Z'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'pickup_too_soon');

        // Cleared: the kitchen gets it now
        $cleared = $this->tillAs('PUT', "/pos/orders/{$uuid}/pickup-time", ['version' => (int) $changed->json('data.version'), 'pickup_at' => null])
            ->assertOk()
            ->assertJsonPath('data.pickup', null)
            ->assertJsonPath('data.items.0.kitchen_status', 'sent')
            ->assertJsonPath('data.queue.status', 'preparing');
        $this->tillAs('PUT', "/pos/orders/{$uuid}/pickup-time", ['version' => (int) $cleared->json('data.version'), 'pickup_at' => '2026-10-22T15:30:00Z'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'already_in_kitchen');
    }

    public function test_a_line_added_while_it_waits_waits_too(): void
    {
        $opened = $this->takeaway([[$this->shashlik, 1]], ['pickup_at' => '2026-10-22T14:00:00Z'])->assertCreated();
        $this->tillAs('POST', '/pos/orders/' . $opened->json('data.uuid') . '/rounds', ['version' => (int) $opened->json('data.version'), 'send' => true, 'items' => [['variant_id' => $this->tea->uuid, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.items.1.fire_mode', 'hold')
            ->assertJsonPath('data.items.1.sent_at', null);
    }

    public function test_a_telegram_pickup_for_later_joins_the_kitchen_and_the_board_at_its_time(): void
    {
        $this->order([['variant_id' => $this->shashlik->uuid, 'quantity' => 1]], ['pickup_at' => '2026-10-22T14:30:00Z'])->assertCreated();
        $online = Order::where('channel', 'telegram')->latest('id')->firstOrFail();
        $this->assertSame('2026-10-22T14:30:00+00:00', $online->pickup_at?->utc()->toIso8601String());
        $this->assertSame('2026-10-22T14:15:00+00:00', $online->kitchen_fire_at?->utc()->toIso8601String());

        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();
        $online->refresh();
        $this->assertSame(OrderStatus::ACCEPTED, $online->status);
        $this->assertNotNull($online->queue_number);
        $this->assertNull($online->pickup_status);
        $this->assertNotContains($online->uuid, array_column($this->kitchenTickets(), 'uuid'));

        Carbon::setTestNow('2026-10-22 14:15:00');
        $this->assertSame(1, app(ScheduledPickup::class)->fireDue());
        $this->assertSame('preparing', $online->refresh()->pickup_status);
        $this->assertContains($online->uuid, array_column($this->kitchenTickets(), 'uuid'));

        // Too soon, and not for a delivery
        Carbon::setTestNow('2026-10-22 13:00:00');
        $this->order([['variant_id' => $this->shashlik->uuid, 'quantity' => 1]], ['pickup_at' => '2026-10-22T13:05:00Z'])->assertStatus(422)->assertJsonValidationErrors('pickup_at');
    }

    public function test_the_till_lists_the_orders_for_later_with_their_time(): void
    {
        // Stage B (M1): the open orders list carries each order's pickup, so the till shows the scheduled ones with their time
        $later = $this->takeaway([[$this->shashlik, 1]], ['send' => true, 'pickup_at' => '2026-10-22T14:00:00Z'])->assertCreated();
        $now = $this->takeaway([[$this->tea, 1]], ['send' => true])->assertCreated();
        $this->order([['variant_id' => $this->shashlik->uuid, 'quantity' => 1]], ['pickup_at' => '2026-10-22T14:30:00Z'])->assertCreated();
        $online = Order::where('channel', 'telegram')->latest('id')->firstOrFail();

        $rows = collect($this->tillAs('GET', '/pos/orders')->assertOk()->json('data'))->keyBy('uuid');
        $this->assertSame('2026-10-22T14:00:00.000000Z', $rows[$later->json('data.uuid')]['pickup']['pickup_at']);
        $this->assertSame('22.10 19:00', $rows[$later->json('data.uuid')]['pickup']['local_time']);
        $this->assertTrue($rows[$later->json('data.uuid')]['pickup']['waiting']);
        $this->assertNull($rows[$now->json('data.uuid')]['pickup']);

        // A Telegram pickup waiting for the till: its row says the time too
        $incoming = collect($this->tillAs('GET', '/pos/orders?scope=incoming')->assertOk()->json('data'))->keyBy('uuid');
        $this->assertSame('22.10 19:30', $incoming[$online->uuid]['pickup']['local_time']);
    }

    public function test_the_mini_app_and_the_till_read_the_pickup_times(): void
    {
        $this->openHours('09:00', '22:00');
        $this->app['auth']->forgetGuards();
        $this->withBearer(null);
        $data = $this->getJson("/api/v1/stores/{$this->store->id}/branches/{$this->branch->id}/pickup-slots")->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.min_lead_minutes', 20)
            ->assertJsonPath('data.slot_minutes', 15)
            ->json('data');
        // 18:00 + 20 minutes, rounded up to the step: 18:30 today to 22:00 tomorrow, inside the hours
        $this->assertSame(['at' => '2026-10-22T13:30:00.000000Z', 'time' => '18:30', 'business_date' => '2026-10-22'], $data['slots'][0]);
        $this->assertSame('22:00', end($data['slots'])['time']);
        $this->assertSame('2026-10-23', end($data['slots'])['business_date']);
        $this->assertCount(15 + 53, $data['slots']);
        foreach ($data['slots'] as $slot) {
            $this->assertTrue($slot['time'] >= '09:00' && $slot['time'] <= '22:00');
        }

        $this->getJson("/api/v1/stores/{$this->store->id}/branches/999999/pickup-slots")->assertNotFound();
        $this->assertSame($data['slots'], $this->tillAs('GET', '/pos/pickup-slots')->assertOk()->json('data.slots'));

        // The public answer is kept for the minute (review P6W3 S2): the module switched off shows from the next one
        $this->useProfile($this->branch, 'choyxona');
        Carbon::setTestNow(now()->addMinute());
        $this->app['auth']->forgetGuards();
        $this->withBearer(null);
        $this->getJson("/api/v1/stores/{$this->store->id}/branches/{$this->branch->id}/pickup-slots")->assertOk()
            ->assertJsonPath('data.enabled', false)->assertJsonCount(0, 'data.slots');
    }

    /**
     * Review P6W3 C4: a round added while the order waits re-runs the schedule — a slower dish brings the kitchen time
     * forward, and fires it at once when that time has passed.
     */
    public function test_a_slower_dish_added_while_it_waits_brings_the_kitchen_time_forward(): void
    {
        $opened = $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T15:00:00Z'])->assertCreated()
            ->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T14:45:00.000000Z');
        $uuid = (string) $opened->json('data.uuid');

        $this->shashlik->product->forceFill(['wait_minutes' => 60])->save();
        $round = $this->tillAs('POST', "/pos/orders/{$uuid}/rounds", ['version' => (int) $opened->json('data.version'), 'items' => [['variant_id' => $this->shashlik->uuid, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T14:00:00.000000Z')
            ->assertJsonPath('data.pickup.waiting', true)
            ->assertJsonPath('data.items.1.fire_mode', 'hold');

        // A dish that needs longer than is left: the kitchen gets the order now
        $this->soup->product->forceFill(['wait_minutes' => 150])->save();
        $this->tillAs('POST', "/pos/orders/{$uuid}/rounds", ['version' => (int) $round->json('data.version'), 'items' => [['variant_id' => $this->soup->uuid, 'quantity' => 1]]])
            ->assertOk()
            ->assertJsonPath('data.pickup.kitchen_fire_at', '2026-10-22T12:30:00.000000Z')
            ->assertJsonPath('data.pickup.waiting', false)
            ->assertJsonPath('data.queue.status', 'preparing');
        $this->assertSame(['sent'], OrderItem::where('order_id', Order::where('uuid', $uuid)->value('id'))->pluck('kitchen_status')->map(fn ($status) => $status->value)->unique()->values()->all());
        $this->assertContains($uuid, array_column($this->kitchenTickets(), 'uuid'));
    }

    /**
     * Review P6W3 C5: a pickup on a later business day belongs to that day — its number comes from that day's numbering
     * and the board shows it that day; no number repeats within one business day. The day starts at 05:00 Tashkent
     * (00:00 UTC).
     */
    public function test_a_pickup_on_a_later_business_day_takes_its_number_from_that_day(): void
    {
        // 04:30 Tashkent on 23.10: still the 22nd's business day
        Carbon::setTestNow('2026-10-22 23:30:00');
        $this->bootstrapQueue();
        $sameDay = $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T23:55:00Z', 'send' => true])->assertCreated()
            ->assertJsonPath('data.queue.business_date', '2026-10-22');
        $tomorrow = $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-23T00:00:00Z', 'send' => true])->assertCreated()
            ->assertJsonPath('data.queue.business_date', '2026-10-23')
            ->assertJsonPath('data.queue.number', 1);
        $later = $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-23T02:00:00Z', 'send' => true])->assertCreated()
            ->assertJsonPath('data.queue.business_date', '2026-10-23')
            ->assertJsonPath('data.queue.number', 2);

        // Moved back into the 22nd's business day: that day's next number; then on to the 23rd again: the 23rd's next
        $moved = $this->tillAs('PUT', '/pos/orders/' . $later->json('data.uuid') . '/pickup-time', ['version' => (int) $later->json('data.version'), 'pickup_at' => '2026-10-22T23:58:00Z'])
            ->assertOk()->assertJsonPath('data.queue.business_date', '2026-10-22');
        $this->assertNotSame($sameDay->json('data.queue.number'), $moved->json('data.queue.number'));
        $again = $this->tillAs('PUT', '/pos/orders/' . $moved->json('data.uuid') . '/pickup-time', ['version' => (int) $moved->json('data.version'), 'pickup_at' => '2026-10-23T01:00:00Z'])
            ->assertOk()->assertJsonPath('data.queue.business_date', '2026-10-23');
        $this->assertNotSame(1, $again->json('data.queue.number'));

        // A Telegram pickup for the new day accepted now: the new day's number too
        $this->order([['variant_id' => $this->tea->uuid, 'quantity' => 1]], ['pickup_at' => '2026-10-23T01:30:00Z'])->assertCreated();
        $online = Order::where('channel', 'telegram')->latest('id')->firstOrFail();
        $this->tillAs('POST', "/pos/orders/{$online->uuid}/accept")->assertOk();
        $this->assertSame('2026-10-23', $online->refresh()->business_date?->toDateString());

        // 05:50 on the 23rd: the till rolls over; its new orders never repeat a number of the day
        Carbon::setTestNow('2026-10-23 00:50:00');
        app(ScheduledPickup::class)->fireDue();
        $this->bootstrapQueue();
        $this->bootstrapQueue($this->cashier2Token);
        $now = $this->takeaway([[$this->tea, 1]], ['send' => true])->assertCreated()->assertJsonPath('data.queue.business_date', '2026-10-23');
        $other = $this->tillAs('POST', '/pos/orders', ['channel' => 'pos_takeaway', 'items' => [['variant_id' => $this->tea->uuid, 'quantity' => 1]], 'send' => true], $this->cashier2Token)->assertCreated();

        $numbers = Order::where('branch_id', $this->branch->id)->whereDate('business_date', '2026-10-23')->whereNotNull('queue_number')->pluck('queue_number')->map(fn ($n) => (int) $n)->all();
        $this->assertCount(5, $numbers);
        $this->assertSame($numbers, array_values(array_unique($numbers)));
        $this->assertContains((int) $now->json('data.queue.number'), $numbers);
        $this->assertContains((int) $other->json('data.queue.number'), $numbers);

        // The board of the 23rd shows the fired pickup with its own number
        $this->app['auth']->forgetGuards();
        $board = $this->tillAs('GET', '/pos/queue')->assertOk()->json('data');
        $this->assertSame('2026-10-23', $board['business_date']);
        $this->assertContains($tomorrow->json('data.uuid'), array_column($board['preparing'], 'order_uuid'));
        $shown = array_column($board['preparing'], 'number');
        $this->assertSame($shown, array_values(array_unique($shown)));
    }

    /**
     * Review P6W3 S1: the waiter rule applies to the pickup time too.
     */
    public function test_a_waiter_changes_the_pickup_time_of_another_waiters_order_only_with_a_manager(): void
    {
        $other = $this->makeStaff(\App\Enums\UserRole::WAITER, $this->branch);
        $opened = $this->takeaway([[$this->shashlik, 1]], ['pickup_at' => '2026-10-22T14:00:00Z'])->assertCreated();
        $uuid = (string) $opened->json('data.uuid');
        Order::where('uuid', $uuid)->update(['waiter_id' => $other->id]);

        $waiterToken = $this->pinSession($this->till, $this->waiter, '6194');
        $this->tillAs('PUT', "/pos/orders/{$uuid}/pickup-time", ['version' => (int) $opened->json('data.version'), 'pickup_at' => '2026-10-22T15:00:00Z'], $waiterToken)
            ->assertStatus(403)->assertJsonPath('data.reason', 'approval_required');
        $this->assertSame('2026-10-22T14:00:00+00:00', Order::where('uuid', $uuid)->firstOrFail()->pickup_at?->utc()->toIso8601String());

        $token = (string) $this->tillAs('POST', '/pos/approvals', ['permission' => 'orders.manage', 'user_id' => $this->manager->id, 'pin' => self::MANAGER_PIN], $waiterToken)->assertCreated()->json('data.token');
        $this->tillAs('PUT', "/pos/orders/{$uuid}/pickup-time", ['version' => (int) $opened->json('data.version'), 'pickup_at' => '2026-10-22T15:00:00Z', 'approval_token' => $token], $waiterToken)
            ->assertOk()->assertJsonPath('data.pickup.pickup_at', '2026-10-22T15:00:00.000000Z');
    }

    /**
     * Review P6W3 S2: the public pickup times cost a fixed handful of queries whatever the window (short opening hours,
     * 5-minute steps, a week ahead), are cached per branch for a minute, and a store that does not sell gets 404.
     */
    public function test_the_public_pickup_times_are_cheap_cached_and_only_for_a_selling_store(): void
    {
        $this->openHours('08:00', '08:30');
        \App\Models\BranchTakeawaySettings::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id,
            'pickup_min_lead_minutes' => 0, 'pickup_slot_minutes' => 5, 'pickup_max_days_ahead' => 7, 'pickup_prep_minutes' => 15]);
        $this->app['auth']->forgetGuards();
        $this->withBearer(null);
        $url = "/api/v1/stores/{$this->store->id}/branches/{$this->branch->id}/pickup-slots";

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $data = $this->getJson($url)->assertOk()->json('data');
        $queries = count(\Illuminate\Support\Facades\DB::getQueryLog());
        \Illuminate\Support\Facades\DB::disableQueryLog();
        $this->assertLessThanOrEqual(30, $queries, "{$queries} queries");
        // 08:00–08:30 every 5 minutes (7 a day) from tomorrow (today's hours are over) to the 7th day ahead
        $this->assertSame(['at' => '2026-10-23T03:00:00.000000Z', 'time' => '08:00', 'business_date' => '2026-10-23'], $data['slots'][0]);
        $this->assertCount(7 * 7, $data['slots']);
        $this->assertSame('2026-10-29', end($data['slots'])['business_date']);
        // The same answer as the till's (uncached) one
        $this->assertSame($data['slots'], app(ScheduledPickup::class)->slots($this->branch->refresh()));

        // Cached for the minute: a settings change shows from the next minute
        \App\Models\BranchTakeawaySettings::where('branch_id', $this->branch->id)->update(['pickup_slot_minutes' => 10]);
        $this->assertCount(49, $this->getJson($url)->assertOk()->json('data.slots'));
        Carbon::setTestNow('2026-10-22 13:01:00');
        $this->assertCount(4 * 7, $this->getJson($url)->assertOk()->json('data.slots'));

        foreach ([\App\Enums\StoreStatus::FINANCE_HOLD, \App\Enums\StoreStatus::SUSPENDED] as $status) {
            $this->store->forceFill(['status' => $status])->save();
            $this->getJson($url)->assertNotFound()->assertJsonPath('data.reason', 'branch_not_found');
        }
    }

    private function openHours(string $open, string $close): void
    {
        // The store's hours live in payment_settings.working_hours (Store keeps open_time / close_time in step)
        $this->store->forceFill(['payment_settings' => array_merge($this->store->payment_settings ?? [], ['working_hours' => ['open' => $open, 'close' => $close]])])->save();
        $this->assertSame("{$open}:00", substr((string) $this->store->refresh()->open_time, 0, 8));
    }

    public function test_the_owner_sets_the_rules(): void
    {
        $this->withBearer(null);
        $url = "/api/v1/owner/branches/{$this->branch->id}/pickup-settings";
        $this->actingAs($this->owner, 'sanctum')->getJson($url)->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.pickup_min_lead_minutes', 20)
            ->assertJsonPath('data.round_the_clock', false)
            ->assertJsonPath('data.opening_hours.open', '00:00');
        $this->actingAs($this->owner, 'sanctum')->putJson($url, ['pickup_min_lead_minutes' => 30, 'pickup_prep_minutes' => 10, 'pickup_max_days_ahead' => 0])->assertOk()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.pickup_min_lead_minutes', 30)
            ->assertJsonPath('data.pickup_slot_minutes', 15);
        $this->actingAs($this->owner, 'sanctum')->putJson($url, ['pickup_slot_minutes' => 1])->assertStatus(422)->assertJsonValidationErrors('pickup_slot_minutes');
        $this->assertTrue(AuditLog::where('action', 'settings.pickup_rules_changed')->exists());

        // Today only now: tomorrow's times are refused
        $this->app['auth']->forgetGuards();
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-23T05:00:00Z'])->assertStatus(422)->assertJsonPath('data.reason', 'pickup_too_far');
        $this->takeaway([[$this->tea, 1]], ['pickup_at' => '2026-10-22T13:25:00Z'])->assertStatus(422)->assertJsonPath('data.reason', 'pickup_too_soon');
    }
}
