<?php

namespace Tests\Feature\PosBooking;

use App\Enums\UserRole;
use App\Models\BookingPayment;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * P6 wave 2 review S4: a deposit is a payment. Taking one needs pos.take_payment (or a manager's one-time approval
 * token for it), and a cash one goes into a drawer: it needs a shift open on the terminal that takes it (409
 * `deposit_needs_shift`, "Bo'nakni kassada oling"), so the shift's X/Z counts it. Card and QR deposits are recorded as
 * before (with or without a shift).
 */
class PosBookingDepositAtTillTest extends TestCase
{
    use PosBookingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_hostess_takes_no_cash_deposit_outside_a_shift(): void
    {
        $hostess = $this->makeStaff(UserRole::HOSTESS, $this->branch);
        $session = $this->terminalSession($hostess, $this->branch);
        $uuid = $this->booked();

        // Without the till's right: a manager's PIN is asked for, nothing is recorded
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '500000', 'tender' => 'card'], ['Idempotency-Key' => 'h-1'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'pos.take_payment');

        // Even with the manager's approval: cash goes into a drawer, and no shift is open on her terminal
        $token = $this->approvals->grant((int) $this->manager->id, 'pos.take_payment', $session['device_id']);
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '500000', 'tender' => 'cash', 'approval_token' => $token], ['Idempotency-Key' => 'h-2'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'deposit_needs_shift')->assertJsonPath('message', "Bo'nakni kassada oling: naqd bo'nak ochiq smenali kassada olinadi.");
        $this->assertSame(0, BookingPayment::count());
        $this->assertSame([], $this->approvals->consumed, 'a refused deposit leaves the approval unused');

        // A card deposit with the approval is recorded as before, and the approval is used once
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '500000', 'tender' => 'card', 'card_type' => 'humo', 'approval_token' => $token], ['Idempotency-Key' => 'h-3'])
            ->assertCreated()->assertJsonPath('data.payment.tender', 'card');
        $this->assertCount(1, $this->approvals->consumed);
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '100000', 'tender' => 'qr', 'approval_token' => $token], ['Idempotency-Key' => 'h-4'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_invalid');
        $this->assertSame(1, BookingPayment::count());
        $this->assertSame(0, PosShift::count());
    }

    public function test_a_cash_deposit_at_the_till_with_an_open_shift_counts_in_its_drawer(): void
    {
        $session = $this->terminalSession($this->cashier, $this->branch);
        $uuid = $this->booked();

        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '300000', 'tender' => 'cash'], ['Idempotency-Key' => 'c-1'])
            ->assertStatus(409)->assertJsonPath('data.reason', 'deposit_needs_shift');

        $shift = (string) $this->apiToken($session['token'], 'POST', '/api/v1/pos/shifts', ['opening_cash' => '100000'], ['Idempotency-Key' => 'c-2'])
            ->assertCreated()->json('data.uuid');
        $this->apiToken($session['token'], 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '300000', 'tender' => 'cash'], ['Idempotency-Key' => 'c-3'])
            ->assertCreated()->assertJsonPath('data.payment.tender', 'cash');

        $report = $this->apiToken($session['token'], 'GET', "/api/v1/pos/shifts/{$shift}/x-report")->assertOk()->json('data.report');
        $this->assertSame(['300000.00', '400000.00'], [$report['deposits']['taken']['cash'], $report['cash']['expected']]);
    }

    public function test_a_cash_deposit_without_a_terminal_is_refused_but_card_and_qr_are_recorded(): void
    {
        $uuid = $this->booked();

        // A normal sign-in has no drawer at all
        $this->takeDeposit($uuid, '200000', 'cash')->assertStatus(409)->assertJsonPath('data.reason', 'deposit_needs_shift');
        $this->takeDeposit($uuid, '200000', 'qr')->assertCreated();
        $this->takeDeposit($uuid, '200000', 'card')->assertCreated();
        $this->assertSame(2, BookingPayment::count());
    }
}
