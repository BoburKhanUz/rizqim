<?php

namespace Tests\Feature\PosBooking;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Device;
use App\Services\Auth\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-17: who may work with bookings (bookings.manage: owner, manager, cashier by default) and where: the caller's store,
 * the branch they act in (a branch-bound staff member, a terminal PIN session: the terminal's branch).
 */
class PosBookingAccessTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
    }

    public function test_bookings_manage_is_needed(): void
    {
        $uuid = $this->booked();
        $waiter = $this->makeStaff(UserRole::WAITER, $this->branch);
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);

        foreach ([$waiter, $cook, $this->courier, $this->customer] as $user) {
            $this->api($user, 'GET', "/api/v1/pos/bookings/{$uuid}")->assertForbidden();
            $this->api($user, 'POST', '/api/v1/pos/bookings', $this->bookingPayload(['dining_table_id' => $this->roomB]))->assertForbidden();
        }
        $this->api($this->owner, 'GET', "/api/v1/pos/bookings/{$uuid}")->assertOk();
        $this->api($this->manager, 'GET', "/api/v1/pos/bookings/{$uuid}")->assertOk();

        // The owner takes it away from cashiers
        app(PermissionService::class)->setOverride($this->store, UserRole::CASHIER, \App\Enums\Permission::BOOKINGS_MANAGE, false);
        $this->api($this->cashier->fresh(), 'GET', "/api/v1/pos/bookings/{$uuid}")->assertForbidden();
        $this->withBearer(null)->getJson('/api/v1/pos/bookings')->assertUnauthorized();
    }

    public function test_another_store_and_another_branch_never_see_or_book(): void
    {
        $uuid = $this->booked();
        $otherCashier = $this->makeStaff(UserRole::CASHIER, $this->otherBranch);

        $this->api($this->foreignOwner, 'GET', "/api/v1/pos/bookings/{$uuid}")->assertNotFound();
        $this->api($this->foreignOwner, 'POST', "/api/v1/pos/bookings/{$uuid}/cancel", ['reason' => 'x'])->assertNotFound();
        $this->api($this->foreignOwner, 'GET', '/api/v1/pos/bookings?from=2026-11-05')->assertOk()->assertJsonPath('data.bookings', []);
        $this->api($this->foreignOwner, 'GET', '/api/v1/pos/bookings/calendar?branch_id=' . $this->branch->id)->assertNotFound();
        $this->api($this->foreignOwner, 'POST', '/api/v1/pos/bookings', $this->bookingPayload())->assertNotFound();

        $this->api($otherCashier, 'GET', "/api/v1/pos/bookings/{$uuid}")->assertNotFound();
        $this->api($otherCashier, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '1000', 'tender' => 'cash'], ['Idempotency-Key' => 'a'])->assertNotFound();
        $this->api($otherCashier, 'GET', '/api/v1/pos/bookings?from=2026-11-05')->assertOk()->assertJsonPath('data.bookings', []);
        $this->api($otherCashier, 'GET', '/api/v1/pos/bookings?branch_id=' . $this->branch->id)->assertForbidden();
        $this->api($otherCashier, 'POST', '/api/v1/pos/bookings', $this->bookingPayload())->assertForbidden();
        // Their own branch, their own room
        $this->api($otherCashier, 'POST', '/api/v1/pos/bookings', $this->bookingPayload(['branch_id' => null, 'dining_table_id' => $this->otherBranchRoom]))->assertCreated()
            ->assertJsonPath('data.branch_id', $this->otherBranch->id);

        // The owner sees both branches
        $this->assertCount(2, $this->api($this->owner, 'GET', '/api/v1/pos/bookings?from=2026-11-05')->json('data.bookings'));
        $this->api($this->owner, 'GET', '/api/v1/pos/bookings/calendar?branch_id=99999999999999999999')->assertStatus(422);
    }

    public function test_a_terminal_session_works_in_its_branch_only(): void
    {
        $uuid = $this->booked();

        // The owner signed in on the second branch's till: that branch only
        $session = $this->terminalSession($this->owner, $this->otherBranch);
        $this->apiToken($session['token'], 'GET', "/api/v1/pos/bookings/{$uuid}")->assertNotFound();
        $this->apiToken($session['token'], 'GET', '/api/v1/pos/bookings/calendar')->assertOk()->assertJsonPath('data.branch_id', $this->otherBranch->id);
        $created = $this->apiToken($session['token'], 'POST', '/api/v1/pos/bookings', $this->bookingPayload(['branch_id' => null, 'dining_table_id' => $this->otherBranchRoom]))
            ->assertCreated()->json('data.uuid');
        $this->assertSame((int) $this->otherBranch->id, (int) Booking::where('uuid', $created)->value('branch_id'));

        // The terminal is revoked: the session no longer works (fails closed)
        Device::whereKey($session['device_id'])->update(['revoked_at' => now()]);
        $this->assertContains($this->apiToken($session['token'], 'GET', "/api/v1/pos/bookings/{$created}")->getStatusCode(), [401, 403]);

        // The cashier on the first branch's till
        $mine = $this->terminalSession($this->cashier, $this->branch);
        $this->apiToken($mine['token'], 'GET', "/api/v1/pos/bookings/{$uuid}")->assertOk();
        $this->apiToken($mine['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '100000', 'tender' => 'cash'], ['Idempotency-Key' => 't-1'])
            ->assertCreated()->assertJsonPath('data.payment.user_id', $this->cashier->id);
        $this->assertSame($mine['device_id'], $this->fiscal->documents()[0]->deviceId);
    }

    public function test_malformed_ids_are_not_found_or_invalid(): void
    {
        $uuid = $this->booked();
        $this->api($this->cashier, 'GET', '/api/v1/pos/bookings/not-a-uuid')->assertNotFound();
        $this->api($this->cashier, 'PATCH', "/api/v1/pos/bookings/{$uuid}/items/9999999999999999999", ['quantity' => 1])->assertNotFound();
        $this->api($this->cashier, 'DELETE', "/api/v1/pos/bookings/{$uuid}/items/123")->assertNotFound();
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/items", ['product_variant_id' => '99999999999999999999', 'quantity' => 1])->assertStatus(422);
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/arrive", ['dining_table_id' => '99999999999999999999'])->assertStatus(422);
    }
}
