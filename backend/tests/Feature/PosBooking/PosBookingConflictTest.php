<?php

namespace Tests\Feature\PosBooking;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P3-17: a room is never booked twice for overlapping times (research report R14): same room, merged banquet rooms,
 * edge times, moves and changes of rooms, the default slot.
 */
class PosBookingConflictTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
    }

    public function test_a_booking_is_created_with_the_phone_normalized_and_the_room(): void
    {
        $response = $this->createBooking()->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.contact_phone', '+998901234567')
            ->assertJsonPath('data.dining_table_id', $this->roomA)
            ->assertJsonPath('data.table_ids', [$this->roomA])
            ->assertJsonPath('data.starts_at', '2026-11-05T14:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-11-05T18:30:00.000000Z')
            ->assertJsonPath('data.event_type', 'gap')
            ->assertJsonPath('data.event_type_label', 'Gap')
            ->assertJsonPath('data.version', 1);

        $booking = Booking::where('uuid', $response->json('data.uuid'))->firstOrFail();
        $this->assertSame((int) $this->store->id, (int) $booking->store_id);
        $this->assertSame((int) $this->cashier->id, (int) $booking->created_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.created', 'model_id' => $booking->id]);
    }

    public function test_an_overlapping_booking_of_the_same_room_is_refused_with_the_conflicts(): void
    {
        $first = $this->booked();

        $this->createBooking([
            'starts_at' => '2026-11-05T22:00:00+05:00',
            'ends_at' => '2026-11-06T02:00:00+05:00',
            'contact_name' => 'Boshqa guruh',
        ])->assertStatus(409)
            ->assertJsonPath('data.reason', 'booking_conflict')
            ->assertJsonPath('data.conflicts.0.uuid', $first)
            ->assertJsonPath('data.conflicts.0.table_ids', [$this->roomA])
            ->assertJsonPath('message', 'Bu vaqtda band: Kabinka 1. Boshqa vaqt yoki xonani tanlang.');

        // A booking inside the other one, and one around it
        $this->createBooking(['starts_at' => '2026-11-05T20:00:00+05:00', 'ends_at' => '2026-11-05T21:00:00+05:00'])->assertStatus(409);
        $this->createBooking(['starts_at' => '2026-11-05T12:00:00+05:00', 'ends_at' => '2026-11-06T01:00:00+05:00'])->assertStatus(409);

        $this->assertSame(1, Booking::count());
    }

    public function test_touching_slots_and_other_rooms_do_not_conflict(): void
    {
        $this->booked();

        // Lunch ends exactly when the evening starts, and the next one starts exactly when it ends
        $this->createBooking(['starts_at' => '2026-11-05T13:00:00+05:00', 'ends_at' => '2026-11-05T19:00:00+05:00'])->assertCreated();
        $this->createBooking(['starts_at' => '2026-11-05T23:30:00+05:00', 'ends_at' => '2026-11-06T02:00:00+05:00'])->assertCreated();
        // The same evening in another room
        $this->createBooking(['dining_table_id' => $this->roomB])->assertCreated();
        // One minute into the evening is a conflict
        $this->createBooking(['starts_at' => '2026-11-05T13:00:00+05:00', 'ends_at' => '2026-11-05T19:01:00+05:00'])->assertStatus(409);

        $this->assertSame(4, Booking::count());
    }

    public function test_a_banquet_holds_every_merged_room(): void
    {
        $banquet = $this->createBooking([
            'dining_table_id' => $this->roomA,
            'extra_table_ids' => [$this->roomB],
            'guests_planned' => 30,
            'event_type' => 'ziyofat',
        ])->assertCreated()->assertJsonPath('data.table_ids', [$this->roomA, $this->roomB])->json('data.uuid');

        // Room B alone overlaps the banquet
        $this->createBooking(['dining_table_id' => $this->roomB, 'starts_at' => '2026-11-05T21:00:00+05:00'])
            ->assertStatus(409)->assertJsonPath('data.conflicts.0.uuid', $banquet)
            ->assertJsonPath('message', 'Bu vaqtda band: Kabinka 2. Boshqa vaqt yoki xonani tanlang.');

        // A second banquet merging C with B: B is taken
        $this->createBooking(['dining_table_id' => $this->roomC, 'extra_table_ids' => [$this->roomB]])->assertStatus(409);
        // Only extras: the first becomes the main room
        $this->createBooking(['dining_table_id' => null, 'extra_table_ids' => [$this->roomC]])->assertCreated()
            ->assertJsonPath('data.dining_table_id', $this->roomC);

        // The banquet canceled: its rooms are free again
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$banquet}/cancel", ['reason' => "Mijoz qo'ng'iroq qildi"])->assertOk();
        $this->createBooking(['dining_table_id' => $this->roomB])->assertCreated();
    }

    public function test_moving_a_booking_or_changing_its_rooms_is_checked_again(): void
    {
        $this->booked();
        $other = $this->booked(['dining_table_id' => $this->roomB, 'starts_at' => '2026-11-05T12:00:00+05:00', 'ends_at' => '2026-11-05T16:00:00+05:00']);

        // Into room A's evening: refused; the booking keeps its length when only the start moves
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$other}", ['starts_at' => '2026-11-05T18:00:00+05:00'])
            ->assertStatus(200)->assertJsonPath('data.ends_at', '2026-11-05T17:00:00.000000Z');
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$other}", ['dining_table_id' => $this->roomA])->assertStatus(409);
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$other}", ['extra_table_ids' => [$this->roomA]])->assertStatus(409);
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$other}", ['extra_table_ids' => [$this->roomC]])->assertOk()
            ->assertJsonPath('data.table_ids', [$this->roomB, $this->roomC]);

        // Changing itself never conflicts with itself
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$other}", ['guests_planned' => 15, 'note' => 'Osh 19:00 ga'])->assertOk()
            ->assertJsonPath('data.guests_planned', 15)->assertJsonPath('data.version', 4);
    }

    public function test_without_an_end_the_default_slot_applies_and_a_branch_may_change_it(): void
    {
        $this->createBooking(['ends_at' => null])->assertCreated()
            ->assertJsonPath('data.ends_at', '2026-11-05T19:00:00.000000Z'); // 5 hours

        DB::table('branches')->where('id', $this->branch->id)->update(['pos_settings' => json_encode(['booking_slot_minutes' => 240])]);
        $this->createBooking(['dining_table_id' => $this->roomB, 'ends_at' => null])->assertCreated()
            ->assertJsonPath('data.ends_at', '2026-11-05T18:00:00.000000Z');

        // A local time without an offset is the store's (Asia/Tashkent)
        $this->createBooking(['dining_table_id' => $this->roomC, 'starts_at' => '2026-11-06 19:00', 'ends_at' => '2026-11-06 23:00'])->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-11-06T14:00:00.000000Z');
    }

    public function test_invalid_times_and_tables_are_refused(): void
    {
        $this->createBooking(['ends_at' => '2026-11-05T18:00:00+05:00'])->assertStatus(422)->assertJsonPath('data.reason', 'invalid_time');
        $this->createBooking(['ends_at' => '2026-11-07T19:00:00+05:00'])->assertStatus(422)->assertJsonPath('data.reason', 'invalid_time');
        $this->createBooking(['dining_table_id' => $this->otherBranchRoom])->assertStatus(422)->assertJsonPath('data.reason', 'table_not_found');
        $this->createBooking(['dining_table_id' => 999999])->assertStatus(422)->assertJsonPath('data.reason', 'table_not_found');
        $this->createBooking(['dining_table_id' => '9999999999999999999'])->assertStatus(422);

        $closed = $this->makeRoom($this->branch, 'Yozgi tapchan', false);
        $this->createBooking(['dining_table_id' => $closed])->assertStatus(422)->assertJsonPath('data.reason', 'table_inactive');

        $this->createBooking(['contact_phone' => '12345'])->assertStatus(422)->assertJsonValidationErrors('contact_phone');
        $this->createBooking(['event_type' => 'toy'])->assertStatus(422)->assertJsonValidationErrors('event_type');
        $this->createBooking(['contact_name' => null])->assertStatus(422)->assertJsonValidationErrors('contact_name');

        $this->assertSame(0, Booking::count());
    }

    public function test_a_booking_without_a_room_never_conflicts(): void
    {
        $this->createBooking(['dining_table_id' => null])->assertCreated()->assertJsonPath('data.table_ids', []);
        $this->createBooking(['dining_table_id' => null])->assertCreated();
        $this->createBooking()->assertCreated();
    }
}
