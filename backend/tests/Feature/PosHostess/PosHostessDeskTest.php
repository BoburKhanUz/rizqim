<?php

namespace Tests\Feature\PosHostess;

use App\Domain\Pos\Setup\FormatProfile;
use App\Domain\Pos\Waitlist\WaitlistChangedBroadcast;
use App\Domain\Pos\Hall\TableStateChangedBroadcast;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\PosWaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-24 / P6-26 (C5): the hostess role, her waitlist (name, phone, party size, quoted wait, status) and the cleaning
 * mark of a table; the owner reads the waitlist with the numbers masked.
 */
class PosHostessDeskTest extends TestCase
{
    use PosHostessFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRestaurantFloor();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function write(array $extra = []): string
    {
        return (string) $this->pos($this->hostess, 'POST', '/pos/waitlist', $extra + [
            'guest_name' => 'Akmal Karimov',
            'guest_phone' => '90 123 45 67',
            'party_size' => 4,
            'quoted_wait_minutes' => 25,
        ])->assertCreated()->json('data.uuid');
    }

    public function test_the_hostess_role_holds_the_desk_permissions_and_takes_no_payment(): void
    {
        $this->assertSame('hostess', $this->hostess->role->value);
        $this->assertSame('Hostes', UserRole::HOSTESS->labelUz());
        $this->assertTrue($this->hostess->role->isStoreStaff());
        $this->assertContains(UserRole::HOSTESS, UserRole::terminalRoles());
        $this->assertContains(UserRole::HOSTESS, UserRole::assignableStaffRoles());

        $permissions = collect($this->pos($this->hostess, 'GET', '/pos/bootstrap')->assertOk()->json('data.user.permissions'));
        foreach (['hostess.manage', 'bookings.manage', 'tables.manage', 'orders.view', 'menu.view'] as $allowed) {
            $this->assertTrue($permissions->contains($allowed), $allowed);
        }
        foreach (['pos.take_payment', 'orders.manage', 'orders.transfer', 'pos.open_shift', 'staff_pay.view', 'orders.discount'] as $denied) {
            $this->assertFalse($permissions->contains($denied), $denied);
        }

        // The permission screen lists the role; the owner may adjust it (not hostess.manage's absence for others)
        $roles = array_column($this->pos($this->owner, 'GET', '/owner/permissions')->assertOk()->json('data.roles'), 'role');
        $this->assertContains('hostess', $roles);
    }

    public function test_the_owner_can_add_a_hostess_through_the_staff_api(): void
    {
        $this->pos($this->owner, 'POST', '/owner/staff', ['name' => 'Dilfuza', 'role' => 'hostess', 'branch_id' => $this->branch->id, 'phone' => '+998901112233'])
            ->assertCreated()->assertJsonPath('data.staff.role', 'hostess');
    }

    public function test_a_guest_goes_on_the_waitlist_in_order_and_is_masked_in_the_audit_log(): void
    {
        Event::fake([WaitlistChangedBroadcast::class]);
        $first = $this->write();
        $second = $this->write(['guest_name' => 'Dilnoza', 'guest_phone' => null, 'party_size' => 2, 'quoted_wait_minutes' => 40]);

        $list = $this->pos($this->hostess, 'GET', '/pos/waitlist')->assertOk()->json('data');
        $this->assertSame([$first, $second], array_column($list['entries'], 'uuid'));
        $this->assertSame([1, 2], array_column($list['entries'], 'position'));
        $this->assertSame('+998901234567', $list['entries'][0]['guest_phone']);
        $this->assertSame('+99890*****67', $list['entries'][0]['guest_phone_masked']);
        $this->assertSame(['total' => 2, 'waiting' => 2, 'seated' => 0, 'canceled' => 0, 'no_show' => 0, 'party_size_total' => 6, 'avg_quoted_wait_minutes' => 33, 'avg_seated_wait_minutes' => null], array_diff_key($list['summary'], ['longest_waiting_minutes' => 1]));

        Event::assertDispatched(WaitlistChangedBroadcast::class, fn (WaitlistChangedBroadcast $event) => $event->broadcastAs() === 'waitlist.changed' && $event->broadcastOn()[0]->name === "private-pos-branch.{$this->branch->id}");

        $log = AuditLog::where('action', 'waitlist.created')->orderBy('id')->firstOrFail();
        $this->assertStringNotContainsString('90123', json_encode($log->after));
        $this->assertSame('+99890*****67', $log->after['guest_phone']);
        $this->assertSame((int) $this->hostess->id, (int) $log->user_id);
    }

    public function test_the_entry_is_called_changed_and_seated_with_its_order(): void
    {
        $uuid = $this->write();

        $this->pos($this->hostess, 'PATCH', "/pos/waitlist/{$uuid}", ['party_size' => 5, 'quoted_wait_minutes' => 10, 'note' => 'Bolalar kreslosi'])
            ->assertOk()->assertJsonPath('data.party_size', 5)->assertJsonPath('data.note', 'Bolalar kreslosi');
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$uuid}/notify")->assertOk()->assertJsonPath('data.status', 'waiting');
        $this->assertNotNull($this->pos($this->hostess, 'GET', '/pos/waitlist')->json('data.entries.0.notified_at'));

        // A table that does not work is refused; the hostess alone cannot open an order (orders.manage)
        $this->table->update(['is_active' => false]);
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$uuid}/seat", ['dining_table_id' => $this->table->id])->assertStatus(422)->assertJsonPath('data.reason', 'table_not_found');
        $this->table->update(['is_active' => true]);
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$uuid}/seat", ['dining_table_id' => $this->table->id, 'open_order' => true])->assertForbidden();

        $seated = $this->pos($this->hostess, 'POST', "/pos/waitlist/{$uuid}/seat", ['dining_table_id' => $this->table->id])
            ->assertOk()->assertJsonPath('data.status', 'seated')->assertJsonPath('data.table_name', 'Stol 5')->assertJsonPath('data.position', null)->json('data');
        $this->assertNull($seated['order_uuid']);

        // Closed: nothing changes any more
        $this->pos($this->hostess, 'PATCH', "/pos/waitlist/{$uuid}", ['party_size' => 2])->assertStatus(409)->assertJsonPath('data.reason', 'entry_closed');
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$uuid}/cancel")->assertStatus(409);
    }

    public function test_seating_can_open_the_order_for_a_cashier_who_may(): void
    {
        $uuid = $this->write(['party_size' => 3]);
        $this->pos($this->owner, 'POST', "/pos/waitlist/{$uuid}/seat", ['dining_table_id' => $this->table->id, 'open_order' => true, 'waiter_id' => $this->waiter->id])
            ->assertOk()->assertJsonPath('data.status', 'seated');

        $order = Order::where('uuid', $this->pos($this->owner, 'GET', '/pos/waitlist?branch_id=' . $this->branch->id)->json('data.entries.0.order_uuid'))->firstOrFail();
        $this->assertSame(3, (int) $order->guests_count);
        $this->assertSame((int) $this->waiter->id, (int) $order->waiter_id);
        $this->assertSame((int) $this->table->id, (int) $order->table_id);
    }

    public function test_cancel_no_show_and_the_stale_version(): void
    {
        $a = $this->write();
        $b = $this->write(['guest_name' => 'Vali']);

        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$a}/cancel", ['reason' => 'Ketdi'])->assertOk()->assertJsonPath('data.status', 'canceled');
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$b}/no-show", ['version' => 99])->assertStatus(409)->assertJsonPath('data.reason', 'stale_version');
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$b}/no-show")->assertOk()->assertJsonPath('data.status', 'no_show');

        $this->assertSame('Ketdi', AuditLog::where('action', 'waitlist.canceled')->firstOrFail()->payload_json['reason']);
        $summary = $this->pos($this->hostess, 'GET', '/pos/waitlist')->json('data.summary');
        $this->assertSame([2, 0, 0, 1, 1], [$summary['total'], $summary['waiting'], $summary['seated'], $summary['canceled'], $summary['no_show']]);
        $this->assertCount(1, $this->pos($this->hostess, 'GET', '/pos/waitlist?status=no_show')->json('data.entries'));
    }

    public function test_validation_and_a_retried_write(): void
    {
        $this->pos($this->hostess, 'POST', '/pos/waitlist', ['guest_name' => 'X', 'party_size' => 0])->assertStatus(422);
        $this->pos($this->hostess, 'POST', '/pos/waitlist', ['guest_name' => 'X', 'party_size' => 2, 'guest_phone' => '12345'])->assertStatus(422)->assertJsonPath('data.reason', 'phone_invalid');
        $this->pos($this->hostess, 'POST', '/pos/waitlist', ['guest_name' => 'X', 'party_size' => 201])->assertStatus(422);

        $body = ['guest_name' => 'Akmal', 'party_size' => 2];
        $this->pos($this->hostess, 'POST', '/pos/waitlist', $body, 'wl-1')->assertCreated();
        $this->pos($this->hostess, 'POST', '/pos/waitlist', $body, 'wl-1')->assertCreated();
        $this->assertSame(1, PosWaitlistEntry::count());

        // Without a key a change is refused
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->hostess, 'sanctum')->json('POST', '/api/v1/pos/waitlist', $body)->assertStatus(428);
    }

    public function test_only_the_desk_staff_of_the_branch_with_the_module_use_the_waitlist(): void
    {
        $uuid = $this->write();

        // A waiter has no hostess.manage; a cook neither; another branch's hostess finds nothing
        $this->pos($this->waiter, 'GET', '/pos/waitlist')->assertForbidden();
        $this->pos($this->waiter, 'POST', '/pos/waitlist', ['guest_name' => 'X', 'party_size' => 2])->assertForbidden();
        $this->otherBranch->forceFill(['pos_settings' => \App\Domain\Pos\Setup\PosSettings::withFormat(null, FormatProfile::RESTORAN, [])])->save();
        $elsewhere = $this->makeStaff(UserRole::HOSTESS, $this->otherBranch);
        $this->pos($elsewhere, 'POST', "/pos/waitlist/{$uuid}/notify")->assertNotFound();
        $this->assertSame([], $this->pos($elsewhere, 'GET', '/pos/waitlist')->assertOk()->json('data.entries'));

        // The choyxona has no hostess module
        $this->profile(FormatProfile::CHOYXONA);
        $this->pos($this->hostess, 'GET', '/pos/waitlist')->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->pos($this->owner, 'GET', '/owner/waitlist?branch_id=' . $this->branch->id)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }

    public function test_the_waitlist_is_per_business_day(): void
    {
        $this->write();
        $tomorrow = Carbon::now()->addDay();
        Carbon::setTestNow($tomorrow);
        try {
            $this->assertSame([], $this->pos($this->hostess, 'GET', '/pos/waitlist')->json('data.entries'));
            $yesterday = $tomorrow->copy()->subDay()->toDateString();
            $this->assertCount(1, $this->pos($this->hostess, 'GET', "/pos/waitlist?date={$yesterday}")->json('data.entries'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_the_owner_reads_the_waitlist_with_numbers_masked(): void
    {
        $a = $this->write();
        $this->write(['guest_name' => 'Vali']);
        $this->pos($this->hostess, 'POST', "/pos/waitlist/{$a}/seat", ['dining_table_id' => $this->table->id])->assertOk();

        $data = $this->pos($this->owner, 'GET', '/owner/waitlist?branch_id=' . $this->branch->id)->assertOk()->json('data');
        $this->assertCount(2, $data['entries']);
        foreach ($data['entries'] as $entry) {
            $this->assertNull($entry['guest_phone']);
            $this->assertNull($entry['position']);
        }
        $this->assertSame('+99890*****67', $data['entries'][1]['guest_phone_masked']);
        $this->assertSame([2, 1, 1], [$data['summary']['total'], $data['summary']['waiting'], $data['summary']['seated']]);
        $this->assertNotNull($data['summary']['avg_seated_wait_minutes']);

        // The hostess has no reports.view; a range over 31 days is refused
        $this->pos($this->hostess, 'GET', '/owner/waitlist')->assertForbidden();
        $this->pos($this->owner, 'GET', '/owner/waitlist?branch_id=' . $this->branch->id . '&from=2026-01-01&to=2026-03-01')->assertStatus(422);
    }

    public function test_marking_a_table_for_cleaning_shows_on_the_floor_until_an_order_opens_there(): void
    {
        Event::fake([TableStateChangedBroadcast::class]);
        $this->pos($this->hostess, 'PUT', "/pos/tables/{$this->table->id}/state", ['state' => 'cleaning'])
            ->assertOk()->assertJsonPath('data.service_state', 'cleaning');
        Event::assertDispatched(TableStateChangedBroadcast::class, fn (TableStateChangedBroadcast $event) => $event->serviceState === 'cleaning' && $event->tableId === $this->table->id);

        $tables = fn () => collect($this->pos($this->hostess, 'GET', '/pos/floor')->assertOk()->json('data.halls'))->pluck('tables')->flatten(1)->keyBy('id');
        $this->assertSame('cleaning', $tables()[$this->table->id]['service_state']);
        $this->assertSame('free', $tables()[$this->table->id]['status']); // the existing status is unchanged
        $this->assertNull($tables()[$this->room->id]['service_state']);

        // The next guests: an order opens there and the mark no longer shows, even after the order closes
        $this->travel(2)->minutes();
        $order = $this->openedOrder($this->openPos($this->waiter, $this->table, 2));
        $this->assertNull($tables()[$this->table->id]['service_state']);
        $order->forceFill(['status' => \App\Enums\OrderStatus::CANCELED, 'closed_at' => now()])->save();
        $this->assertNull($tables()[$this->table->id]['service_state']);

        // Free clears it; the log keeps both changes
        $this->pos($this->hostess, 'PUT', "/pos/tables/{$this->table->id}/state", ['state' => 'cleaning'])->assertOk();
        $this->pos($this->hostess, 'PUT', "/pos/tables/{$this->table->id}/state", ['state' => 'free'])->assertOk()->assertJsonPath('data.service_state', null);
        $this->assertSame(3, AuditLog::where('action', 'table.service_state_changed')->count());
    }

    public function test_the_table_state_is_for_the_desk_or_the_floor_staff_and_validated(): void
    {
        $this->pos($this->waiter, 'PUT', "/pos/tables/{$this->table->id}/state", ['state' => 'cleaning'])->assertOk(); // tables.manage
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->pos($cook, 'PUT', "/pos/tables/{$this->table->id}/state", ['state' => 'cleaning'])->assertForbidden();
        $this->pos($this->hostess, 'PUT', "/pos/tables/{$this->table->id}/state", ['state' => 'broken'])->assertStatus(422);
        $this->pos($this->hostess, 'PUT', '/pos/tables/999999/state', ['state' => 'cleaning'])->assertNotFound();
    }

    public function test_the_hostess_takes_table_bookings_with_the_booking_book(): void
    {
        $response = $this->pos($this->hostess, 'POST', '/pos/bookings', [
            'branch_id' => $this->branch->id, 'dining_table_id' => $this->table->id, 'starts_at' => now()->addDays(2)->toIso8601String(),
            'guests_planned' => 4, 'contact_name' => 'Karim', 'contact_phone' => '901234567',
        ]);
        $response->assertCreated();
    }

    public function test_a_store_wide_owner_is_gated_on_the_records_own_branch(): void
    {
        // Branch one has the hostess module (restoran); the second stays a choyxona
        $uuid = $this->write();
        $this->pos($this->owner, 'GET', '/pos/waitlist?branch_id=' . $this->otherBranch->id)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->pos($this->owner, 'POST', '/pos/waitlist', ['branch_id' => $this->otherBranch->id, 'guest_name' => 'X', 'party_size' => 2])->assertForbidden();
        $this->pos($this->owner, 'GET', '/owner/waitlist?branch_id=' . $this->otherBranch->id)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->pos($this->owner, 'GET', '/pos/waitlist?branch_id=' . $this->branch->id)->assertOk()->assertJsonPath('data.entries.0.uuid', $uuid);

        $hall = \App\Models\Hall::create(['store_id' => $this->store->id, 'branch_id' => $this->otherBranch->id, 'name' => 'Zal', 'sort_order' => 1]);
        $elsewhere = $this->place($hall, 'Stol 1', 'table');
        $this->pos($this->owner, 'PUT', "/pos/tables/{$elsewhere->id}/state", ['state' => 'cleaning'])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }
}
