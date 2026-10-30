<?php

namespace Tests\Feature\PosHostess;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Setup\PosSettings;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BranchBookingRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\PosBooking\PosBookingFixtures;
use Tests\TestCase;

/**
 * P6-26 (C6): table booking. The slot depends on the kind of the place, the lead time and the furthest day are the
 * branch's rules, a deposit is optional (the existing deposit with its advance receipt), and none of it touches the
 * choyxona's room bookings: no rules row or no table_booking module means exactly the old behaviour.
 */
class PosTableBookingTest extends TestCase
{
    use PosBookingFixtures;
    use RefreshDatabase;

    private int $table;

    private int $tapchan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBookings();
        $this->profile(FormatProfile::RESTORAN);
        $this->table = $this->place('Stol 5', 'table');
        $this->tapchan = $this->place('Tapchan 1', 'tapchan');
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function profile(FormatProfile $profile): void
    {
        $this->branch->forceFill(['pos_settings' => PosSettings::withFormat($this->branch->fresh()->pos_settings, $profile, [])])->save();
    }

    private function place(string $name, string $kind): int
    {
        return (int) DB::table('dining_tables')->insertGetId([
            'store_id' => $this->branch->store_id, 'branch_id' => $this->branch->id,
            'hall_id' => DB::table('halls')->where('branch_id', $this->branch->id)->value('id'), 'name' => $name, 'kind' => $kind,
            'seats' => 4, 'sort_order' => 0, 'is_active' => true, 'min_spend_mode' => 'per_table', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function rules(array $rules): void
    {
        $this->api($this->owner, 'PUT', "/api/v1/owner/branches/{$this->branch->id}/booking-rules", $rules)->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(int $place, string $startsAt, array $overrides = [], ?\App\Models\User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->createBooking($overrides + ['dining_table_id' => $place, 'starts_at' => $startsAt, 'ends_at' => null, 'guests_planned' => 4], $as);
    }

    public function test_without_rules_a_booking_keeps_the_choyxona_slot_and_has_no_lead_time(): void
    {
        $response = $this->book($this->table, '2026-11-05T15:05:00+00:00')->assertCreated();
        $this->assertSame('2026-11-05T20:05:00.000000Z', $response->json('data.ends_at')); // 5 hours
        $this->book($this->roomA, '2026-11-05T10:05:00+00:00')->assertCreated(); // five minutes ahead: no rule
        $this->assertSame('0.00', $response->json('data.deposit_required'));
    }

    public function test_the_slot_follows_the_kind_of_the_place(): void
    {
        $this->rules(['table_slot_minutes' => 90]);

        $table = $this->book($this->table, '2026-11-05T15:00:00+00:00')->assertCreated();
        $this->assertSame('2026-11-05T16:30:00.000000Z', $table->json('data.ends_at'));
        // A room and a tapchan keep the branch's default (5 hours) until a slot is set for them
        $this->assertSame('2026-11-05T20:00:00.000000Z', $this->book($this->roomA, '2026-11-05T15:00:00+00:00')->json('data.ends_at'));
        $this->rules(['tapchan_slot_minutes' => 240]);
        $this->assertSame('2026-11-05T19:00:00.000000Z', $this->book($this->tapchan, '2026-11-05T15:00:00+00:00')->json('data.ends_at'));

        // An end the cashier gives always wins
        $explicit = $this->book($this->table, '2026-11-05T18:00:00+00:00', ['ends_at' => '2026-11-05T21:00:00+00:00'])->assertCreated();
        $this->assertSame('2026-11-05T21:00:00.000000Z', $explicit->json('data.ends_at'));
    }

    public function test_the_table_slot_blocks_the_next_party_only_for_its_own_length(): void
    {
        $this->rules(['table_slot_minutes' => 120]);
        $this->book($this->table, '2026-11-05T15:00:00+00:00')->assertCreated();

        $this->book($this->table, '2026-11-05T16:00:00+00:00')->assertStatus(409)->assertJsonPath('data.reason', 'booking_conflict');
        $this->book($this->table, '2026-11-05T17:00:00+00:00')->assertCreated();
    }

    public function test_a_booking_too_soon_or_too_far_is_refused_by_the_rules(): void
    {
        $this->rules(['table_min_advance_minutes' => 60, 'room_min_advance_minutes' => 180, 'max_advance_days' => 30]);

        $this->book($this->table, '2026-11-05T10:30:00+00:00')->assertStatus(422)->assertJsonPath('data.reason', 'booking_too_soon')
            ->assertJsonPath('data.min_advance_minutes', 60)->assertJsonPath('data.earliest_at', '2026-11-05T11:00:00.000000Z');
        $this->book($this->table, '2026-11-05T11:00:00+00:00')->assertCreated();
        // The choyxona's rule for rooms: at least 3 hours ahead
        $this->book($this->roomA, '2026-11-05T12:00:00+00:00')->assertStatus(422)->assertJsonPath('data.reason', 'booking_too_soon');
        $this->book($this->roomA, '2026-11-05T13:00:00+00:00')->assertCreated();
        // A banquet of a table and a room follows the stricter rule
        $this->book($this->table, '2026-11-05T14:00:00+00:00', ['extra_table_ids' => [$this->roomB], 'ends_at' => '2026-11-05T15:00:00+00:00'])->assertCreated();
        $this->book($this->table, '2026-11-05T12:00:00+00:00', ['dining_table_id' => $this->roomC, 'extra_table_ids' => [$this->table]])->assertStatus(422);
        $this->book($this->table, '2026-12-20T18:00:00+00:00')->assertStatus(422)->assertJsonPath('data.reason', 'booking_too_far');
    }

    public function test_a_manager_books_a_walk_in_inside_the_lead_time_but_a_cashier_cannot(): void
    {
        $this->rules(['table_min_advance_minutes' => 60]);

        $this->book($this->table, '2026-11-05T10:10:00+00:00', ['skip_lead_time' => true])->assertStatus(422)->assertJsonPath('data.reason', 'booking_too_soon');
        $this->book($this->table, '2026-11-05T10:10:00+00:00', ['skip_lead_time' => true], $this->manager)->assertCreated();
        $this->book($this->table, '2026-11-05T17:10:00+00:00', ['skip_lead_time' => true], $this->owner)->assertCreated();
    }

    public function test_moving_a_booking_is_checked_against_the_rules_again(): void
    {
        $this->rules(['table_min_advance_minutes' => 120]);
        $uuid = (string) $this->book($this->table, '2026-11-05T14:00:00+00:00')->assertCreated()->json('data.uuid');

        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['starts_at' => '2026-11-05T11:00:00+00:00'])->assertStatus(422)->assertJsonPath('data.reason', 'booking_too_soon');
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['starts_at' => '2026-11-05T16:00:00+00:00'])->assertOk()
            ->assertJsonPath('data.ends_at', '2026-11-05T18:00:00.000000Z');
        // Changing only the guests never asks (the start is not touched)
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}", ['guests_planned' => 6])->assertOk();
    }

    public function test_the_deposit_is_asked_for_tables_from_the_party_size_the_rule_names(): void
    {
        $this->rules(['deposit_mode' => 'per_guest', 'deposit_amount' => '50000', 'deposit_from_guests' => 6]);

        $small = $this->book($this->table, '2026-11-05T15:00:00+00:00')->assertCreated();
        $this->assertSame('0.00', $small->json('data.deposit_required'));
        $big = $this->book($this->table, '2026-11-05T18:00:00+00:00', ['guests_planned' => 8])->assertCreated();
        $this->assertSame('400000.00', $big->json('data.deposit_required'));
        $this->assertSame('400000.00', $big->json('data.deposit_missing'));
        // A room keeps taking its deposit by hand
        $this->assertSame('0.00', $this->book($this->roomA, '2026-11-05T15:00:00+00:00', ['guests_planned' => 8])->json('data.deposit_required'));

        $this->rules(['deposit_mode' => 'flat', 'deposit_amount' => '100000']);
        $uuid = $big->json('data.uuid');
        $this->assertSame('100000.00', $this->api($this->cashier, 'GET', "/api/v1/pos/bookings/{$uuid}")->json('data.deposit_required'));
    }

    public function test_a_required_deposit_keeps_the_booking_unconfirmed_until_it_is_taken(): void
    {
        $this->rules(['deposit_mode' => 'flat', 'deposit_amount' => '100000', 'deposit_required_to_confirm' => true]);

        $this->book($this->table, '2026-11-05T15:00:00+00:00', ['confirm' => true])->assertStatus(409)->assertJsonPath('data.reason', 'deposit_required')
            ->assertJsonPath('data.deposit_missing', '100000.00');
        $this->assertSame(0, Booking::count());

        $uuid = (string) $this->book($this->table, '2026-11-05T15:00:00+00:00')->assertCreated()->assertJsonPath('data.status', 'requested')->json('data.uuid');
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/confirm")->assertStatus(409)->assertJsonPath('data.reason', 'deposit_required');

        // A part of the deposit does not confirm; the rest does
        $this->takeDeposit($uuid, '40000', 'qr')->assertCreated()->assertJsonPath('data.booking.status', 'requested')->assertJsonPath('data.booking.deposit_missing', '60000.00');
        $this->takeDeposit($uuid, '60000', 'qr')->assertCreated()->assertJsonPath('data.booking.status', 'confirmed')->assertJsonPath('data.booking.deposit_missing', '0.00');
    }

    public function test_a_deposit_not_required_to_confirm_confirms_like_before(): void
    {
        $this->rules(['deposit_mode' => 'flat', 'deposit_amount' => '100000', 'deposit_required_to_confirm' => false]);
        $uuid = (string) $this->book($this->table, '2026-11-05T15:00:00+00:00')->assertCreated()->json('data.uuid');

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_the_choyxona_rooms_and_a_stale_rules_row_change_nothing(): void
    {
        $this->rules(['table_slot_minutes' => 90, 'room_min_advance_minutes' => 600, 'deposit_mode' => 'flat', 'deposit_amount' => '100000', 'deposit_required_to_confirm' => true]);
        $this->profile(FormatProfile::CHOYXONA);

        // The module is off: the row is ignored, a room books five hours, five minutes ahead, confirmed without a deposit
        $room = $this->book($this->roomA, '2026-11-05T10:05:00+00:00', ['confirm' => true])->assertCreated();
        $this->assertSame('2026-11-05T15:05:00.000000Z', $room->json('data.ends_at'));
        $this->assertSame('confirmed', $room->json('data.status'));
        $this->assertSame('0.00', $room->json('data.deposit_required'));
        $this->book($this->table, '2026-11-05T17:00:00+00:00')->assertCreated()->assertJsonPath('data.ends_at', '2026-11-05T22:00:00.000000Z');
    }

    public function test_the_owner_reads_and_changes_the_rules_and_every_change_is_audited(): void
    {
        $url = "/api/v1/owner/branches/{$this->branch->id}/booking-rules";
        $default = $this->api($this->owner, 'GET', $url)->assertOk()->json('data');
        $this->assertFalse($default['configured']);
        $this->assertSame([120, null, null, 0, 0, 0, null, 'off', '0.00', 1, false], [
            $default['table_slot_minutes'], $default['room_slot_minutes'], $default['tapchan_slot_minutes'],
            $default['table_min_advance_minutes'], $default['room_min_advance_minutes'], $default['tapchan_min_advance_minutes'],
            $default['max_advance_days'], $default['deposit_mode'], $default['deposit_amount'], $default['deposit_from_guests'], $default['deposit_required_to_confirm'],
        ]);

        $saved = $this->api($this->owner, 'PUT', $url, ['table_slot_minutes' => 100, 'room_min_advance_minutes' => 180, 'deposit_mode' => 'flat', 'deposit_amount' => '75000.50'])
            ->assertOk()->json('data');
        $this->assertTrue($saved['configured']);
        $this->assertSame([100, 180, 'flat', '75000.50', "Bron uchun bir xil bo'nak"], [$saved['table_slot_minutes'], $saved['room_min_advance_minutes'], $saved['deposit_mode'], $saved['deposit_amount'], $saved['deposit_mode_label']]);

        // Only what is sent changes; null clears a nullable key
        $this->api($this->owner, 'PUT', $url, ['room_slot_minutes' => 240])->assertOk();
        $after = $this->api($this->owner, 'PUT', $url, ['room_slot_minutes' => null, 'max_advance_days' => 45])->assertOk()->json('data');
        $this->assertSame([100, null, 45, '75000.50'], [$after['table_slot_minutes'], $after['room_slot_minutes'], $after['max_advance_days'], $after['deposit_amount']]);

        $log = AuditLog::where('action', 'settings.booking_rules_changed')->orderBy('id')->firstOrFail();
        $this->assertSame((int) $this->owner->id, (int) $log->user_id);
        $this->assertSame(100, $log->after['table_slot_minutes']);
        $this->assertSame(3, AuditLog::where('action', 'settings.booking_rules_changed')->count());
        $this->assertSame(1, BranchBookingRules::count());
    }

    public function test_the_rules_are_validated_and_scoped(): void
    {
        $url = "/api/v1/owner/branches/{$this->branch->id}/booking-rules";
        foreach ([
            ['table_slot_minutes' => 10], ['table_slot_minutes' => 600], ['table_min_advance_minutes' => -1], ['max_advance_days' => 0],
            ['deposit_mode' => 'weird'], ['deposit_amount' => '-5'], ['deposit_amount' => '10.555'], ['deposit_from_guests' => 0],
        ] as $bad) {
            $this->api($this->owner, 'PUT', $url, $bad)->assertStatus(422);
        }

        // Other staff do not hold settings.manage; the other branch's manager acts in their own branch only
        $this->api($this->cashier, 'GET', $url)->assertForbidden();
        $this->api($this->manager, 'GET', $url)->assertForbidden();
        $this->api($this->owner, 'GET', "/api/v1/owner/branches/{$this->foreignBranch->id}/booking-rules")->assertNotFound();
        // Another store's owner reaches nothing of this branch (its own store has no such module, or no such branch)
        $this->assertContains($this->api($this->foreignOwner, 'PUT', $url, ['table_slot_minutes' => 90])->getStatusCode(), [403, 404]);
        $this->assertSame(0, BranchBookingRules::count());
        // Not from a terminal PIN session
        $token = $this->pinSession($this->manager);
        $this->apiToken($token, 'GET', $url)->assertForbidden();
    }

    public function test_the_rules_need_the_table_booking_module(): void
    {
        $url = "/api/v1/owner/branches/{$this->branch->id}/booking-rules";
        $this->profile(FormatProfile::CHOYXONA);

        $this->api($this->owner, 'GET', $url)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->api($this->owner, 'PUT', $url, ['table_slot_minutes' => 90])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->assertSame(0, BranchBookingRules::count());
    }

    public function test_the_bootstrap_carries_the_rules_only_with_the_module(): void
    {
        $this->rules(['table_slot_minutes' => 90]);
        $hostess = $this->makeStaff(UserRole::HOSTESS, $this->branch);

        $rules = $this->api($hostess, 'GET', '/api/v1/pos/bootstrap')->assertOk()->json('data.booking_rules');
        $this->assertSame(90, $rules['table_slot_minutes']);

        $this->profile(FormatProfile::CHOYXONA);
        $this->assertNull($this->api($hostess, 'GET', '/api/v1/pos/bootstrap')->assertOk()->json('data.booking_rules'));
    }

    public function test_the_hostess_holds_booking_permissions_and_books_for_the_branch(): void
    {
        $hostess = $this->makeStaff(UserRole::HOSTESS, $this->branch);
        $this->rules(['table_min_advance_minutes' => 60]);

        $this->book($this->table, '2026-11-05T15:00:00+00:00', [], $hostess)->assertCreated();
        // She cannot skip the rule: only an owner or a manager books a walk-in
        $this->book($this->table, '2026-11-05T10:10:00+00:00', ['skip_lead_time' => true], $hostess)->assertStatus(422);
        $this->assertCount(1, $this->api($hostess, 'GET', '/api/v1/pos/bookings?from=2026-11-05&to=2026-11-05')->assertOk()->json('data.bookings'));
    }
}
