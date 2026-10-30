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

        $this->useProfile($this->branch, 'choyxona');
        $this->app['auth']->forgetGuards();
        $this->withBearer(null);
        $this->getJson("/api/v1/stores/{$this->store->id}/branches/{$this->branch->id}/pickup-slots")->assertOk()
            ->assertJsonPath('data.enabled', false)->assertJsonCount(0, 'data.slots');
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
