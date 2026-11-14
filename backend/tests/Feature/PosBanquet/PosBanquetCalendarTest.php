<?php

namespace Tests\Feature\PosBanquet;

use App\Domain\Pos\Banquet\BanquetEventService;
use App\Models\AuditLog;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-51: the event calendar — holds, the double-booking rule (same hall, merged halls, custom times), rescheduling,
 * versions, states, hold expiry and room bookings inside a banquet's hall.
 */
class PosBanquetCalendarTest extends TestCase
{
    use RefreshDatabase;
    use PosBanquetFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBanquets();
    }

    public function test_a_hold_takes_the_hall_for_the_slot_with_the_store_times(): void
    {
        $response = $this->hold()->assertCreated()
            ->assertJsonPath('data.status', 'hold')
            ->assertJsonPath('data.slot', 'evening')
            ->assertJsonPath('data.start_time', '18:00')
            ->assertJsonPath('data.end_time', '23:00')
            ->assertJsonPath('data.hall_ids', [$this->hallA])
            ->assertJsonPath('data.customer_phone', '+998901234567')
            ->assertJsonPath('data.guests_minimum', 200);

        $this->assertSame('2027-03-20T13:00:00.000000Z', $response->json('data.starts_at'));
        $this->assertSame(now()->addHours(72)->toJSON(), $response->json('data.hold_expires_at'));
        $this->assertTrue(AuditLog::where('action', 'banquet.created')->exists());
    }

    public function test_the_same_hall_and_slot_is_taken_once(): void
    {
        $first = $this->held();

        $this->hold(['customer_name' => 'Boshqa'])->assertStatus(409)
            ->assertJsonPath('data.reason', 'slot_taken')
            ->assertJsonPath('data.conflicts.0.uuid', $first);

        // Another slot of the same day, another hall the same evening: free
        $this->hold(['slot' => 'day'])->assertCreated();
        $this->hold(['hall_ids' => [$this->hallB]])->assertCreated();
        $this->assertSame(3, BanquetEvent::count());
    }

    public function test_merged_halls_hold_every_one_of_them(): void
    {
        $this->held(['hall_ids' => [$this->hallA, $this->hallB]]);

        $this->hold(['hall_ids' => [$this->hallB]])->assertStatus(409)->assertJsonPath('data.reason', 'slot_taken');
    }

    public function test_custom_times_overlap_the_named_slots(): void
    {
        $this->held(['slot' => 'day']); // 12:00–16:00

        $this->hold(['slot' => 'custom', 'start_time' => '15:00', 'end_time' => '17:00'])->assertStatus(409)->assertJsonPath('data.reason', 'slot_taken');
        $this->hold(['slot' => 'custom', 'start_time' => '16:00', 'end_time' => '17:30'])->assertCreated()
            ->assertJsonPath('data.start_time', '16:00')->assertJsonPath('data.end_time', '17:30');
        // Past midnight: the end is the next day, and overlaps the evening
        $this->hold(['slot' => 'custom', 'start_time' => '22:00', 'end_time' => '01:00'])->assertCreated();
        $this->hold(['slot' => 'evening'])->assertStatus(409);
    }

    public function test_a_cancelled_event_frees_its_hall(): void
    {
        $uuid = $this->held();
        app(BanquetEventService::class)->cancel(BanquetEvent::where('uuid', $uuid)->firstOrFail(), 'Mijoz rad etdi', $this->manager);

        $this->hold()->assertCreated();
    }

    public function test_months_ahead_is_fine_the_past_is_not(): void
    {
        $this->hold(['event_date' => '2028-06-01'])->assertCreated();
        $this->hold(['event_date' => '2026-11-13'])->assertStatus(422)->assertJsonPath('data.reason', 'event_in_past');
    }

    public function test_rescheduling_keeps_the_payments_and_checks_conflicts(): void
    {
        $uuid = $this->held();
        $other = $this->held(['event_date' => '2027-04-10']);
        $event = BanquetEvent::where('uuid', $uuid)->firstOrFail();
        BanquetPayment::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'banquet_event_id' => $event->id,
            'kind' => 'deposit', 'due_date' => '2026-11-20', 'amount' => '3000000', 'status' => 'paid', 'tender' => 'cash', 'paid_at' => now(),
        ]);

        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['event_date' => '2027-04-10', 'version' => 1])
            ->assertStatus(409)->assertJsonPath('data.reason', 'slot_taken')->assertJsonPath('data.conflicts.0.uuid', $other);

        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['event_date' => '2027-04-11', 'slot' => 'day', 'version' => 1])
            ->assertOk()
            ->assertJsonPath('data.event_date', '2027-04-11')
            ->assertJsonPath('data.start_time', '12:00')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.payments.0.status', 'paid')
            ->assertJsonPath('data.payments.0.amount', '3000000.00');
        $this->assertTrue(AuditLog::where('action', 'banquet.rescheduled')->exists());

        // Its old evening is free again
        $this->hold()->assertCreated();
        // A stale version
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 250, 'version' => 1])
            ->assertStatus(409)->assertJsonPath('data.reason', 'stale_version')->assertJsonPath('data.version', 2);
    }

    public function test_moving_to_another_hall_is_checked_too(): void
    {
        $uuid = $this->held();
        $this->held(['hall_ids' => [$this->hallB]]);

        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['hall_ids' => [$this->hallA, $this->hallB]])
            ->assertStatus(409)->assertJsonPath('data.reason', 'slot_taken');
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['hall_ids' => [$this->otherBranchHall]])
            ->assertStatus(422)->assertJsonPath('data.reason', 'hall_not_found');
    }

    public function test_states_move_only_forward(): void
    {
        $uuid = $this->held();
        $service = app(BanquetEventService::class);
        $event = BanquetEvent::where('uuid', $uuid)->firstOrFail();

        $event = $service->markConfirmed($event, $this->manager);
        $this->assertSame('confirmed', $event->status->value);
        $this->assertNull($event->hold_expires_at);
        $this->assertNotNull($event->confirmed_at);
        // Confirming again changes nothing
        $this->assertSame($event->version, $service->markConfirmed($event, $this->manager)->version);

        $event = $service->transition($event, \App\Domain\Pos\Banquet\BanquetEventStatus::IN_PROGRESS, $this->manager, null, 'banquet.started', function (BanquetEvent $event): void {
            $event->started_at = now();
        });
        $this->api($this->manager, 'PATCH', "/api/v1/pos/banquets/{$uuid}", ['guests_guaranteed' => 10])
            ->assertStatus(409)->assertJsonPath('data.reason', 'event_not_editable');

        $this->expectException(\App\Domain\Pos\Banquet\BanquetException::class);
        $service->cancel($event, 'kech', $this->manager);
    }

    public function test_an_expired_hold_is_released_by_the_minute_job(): void
    {
        $expired = $this->held();
        $withMoney = $this->held(['hall_ids' => [$this->hallB]]);
        BanquetPayment::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'banquet_event_id' => BanquetEvent::where('uuid', $withMoney)->value('id'),
            'kind' => 'deposit', 'amount' => '1000000', 'status' => 'paid', 'tender' => 'cash', 'paid_at' => now(),
        ]);

        $this->travel(71)->hours();
        $this->artisan('pos:banquet-release-holds')->assertSuccessful();
        $this->assertSame('hold', BanquetEvent::where('uuid', $expired)->firstOrFail()->status->value);

        $this->travel(2)->hours();
        $this->artisan('pos:banquet-release-holds')->assertSuccessful();
        $event = BanquetEvent::where('uuid', $expired)->firstOrFail();
        $this->assertSame('cancelled', $event->status->value);
        $this->assertNotNull($event->released_at);
        $this->assertSame('hold', BanquetEvent::where('uuid', $withMoney)->firstOrFail()->status->value);
        $this->assertTrue(AuditLog::where('action', 'banquet.hold_expired')->exists());

        // The hall is free for another family
        $this->hold()->assertCreated();
    }

    public function test_a_hold_without_expiry_when_the_store_says_so(): void
    {
        $this->api($this->owner, 'PUT', '/api/v1/owner/pos/banquets/settings', ['hold_hours' => 0])->assertOk();

        $this->hold()->assertCreated()->assertJsonPath('data.hold_expires_at', null);
    }

    public function test_a_room_inside_a_banquet_hall_is_not_booked_and_the_other_way_round(): void
    {
        // The rooms of the fixtures sit in "Ichki zal"
        $this->held(['hall_ids' => [$this->roomsHall]]);

        $this->createBooking(['starts_at' => '2027-03-20T19:00:00+05:00', 'ends_at' => '2027-03-20T22:00:00+05:00'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'banquet_conflict');
        // Another day: fine
        $this->createBooking(['starts_at' => '2027-03-21T19:00:00+05:00', 'ends_at' => '2027-03-21T22:00:00+05:00'])->assertCreated();

        $this->hold(['event_date' => '2027-03-21', 'hall_ids' => [$this->roomsHall]])
            ->assertStatus(409)->assertJsonPath('data.reason', 'hall_has_bookings');
        // A hall without that room: fine
        $this->hold(['event_date' => '2027-03-21'])->assertCreated();
    }

    public function test_the_calendar_lists_events_under_each_hall(): void
    {
        $merged = $this->held(['hall_ids' => [$this->hallA, $this->hallB]]);
        $this->held(['event_date' => '2027-03-21']);
        $this->held(['event_date' => '2027-05-01']);

        $response = $this->api($this->manager, 'GET', '/api/v1/pos/banquets/calendar?from=2027-03-01&to=2027-03-31')->assertOk()
            ->assertJsonPath('data.slots.evening.start', '18:00');
        $halls = collect($response->json('data.halls'))->keyBy('id');
        $this->assertSame([$merged, $halls[$this->hallA]['events'][1]['uuid']], array_column($halls[$this->hallA]['events'], 'uuid'));
        $this->assertSame([$merged], array_column($halls[$this->hallB]['events'], 'uuid'));

        $this->api($this->manager, 'GET', '/api/v1/pos/banquets/calendar?from=2027-01-01&to=2027-03-31')
            ->assertStatus(422);
        $this->api($this->manager, 'GET', '/api/v1/pos/banquets?phone=1234567')->assertOk()->assertJsonCount(3, 'data.events');
    }
}
