<?php

namespace Tests\Feature\PosBooking;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Models\BookingPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P3-17: deposits (research report R16, §5): an ADVANCE receipt when taken, idempotent; refund and retention with a
 * manager's approval and a reason, their receipts behind the store's fiscal settings, the owner told.
 */
class PosBookingDepositTest extends TestCase
{
    use RefreshDatabase;
    use PosBookingFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBookings();
    }

    public function test_taking_a_deposit_issues_one_advance_receipt_with_a_single_deposit_line(): void
    {
        $uuid = $this->booked();

        $response = $this->takeDeposit($uuid, '1000000', 'qr', 'dep-1')->assertCreated()
            ->assertJsonPath('data.payment.amount', '1000000.00')
            ->assertJsonPath('data.payment.tender', 'qr')
            ->assertJsonPath('data.payment.status', 'taken')
            ->assertJsonPath('data.payment.remaining', '1000000.00')
            ->assertJsonPath('data.payment.fiscal_status', 'issued')
            // A deposit confirms the booking
            ->assertJsonPath('data.booking.status', 'confirmed')
            ->assertJsonPath('data.booking.deposit_total', '1000000.00')
            ->assertJsonPath('data.booking.deposit_available', '1000000.00');

        $this->assertCount(1, $this->fiscal->documents());
        $document = $this->fiscal->documents()[0];
        $this->assertSame(FiscalReceiptType::ADVANCE, $document->type);
        $this->assertSame(['deposit'], $this->fiscalLines->charges);
        $this->assertCount(1, $document->lines);
        $this->assertSame('10202002004000001', $document->lines[0]->mxikCode);
        $this->assertSame('1000000.00', $document->total->toString());
        $this->assertSame(FiscalPaymentForm::QR, $document->payments[0]->form);
        $this->assertFalse($document->payments[0]->prepayment);
        $this->assertSame('booking_payment', $document->subjectType);
        $this->assertSame((int) $this->store->id, $document->storeId);
        $this->assertSame((int) $this->branch->id, $document->branchId);
        $this->assertSame($document->idempotencyKey, 'booking_deposit:' . $response->json('data.payment.uuid') . ':advance');
        $this->assertSame($this->fiscal->receipts[$document->idempotencyKey]['result']->receiptUuid, $response->json('data.payment.fiscal_receipt_uuid'));
    }

    public function test_a_retried_deposit_is_one_deposit_and_one_receipt(): void
    {
        $uuid = $this->booked();

        $first = $this->takeDeposit($uuid, '500000', 'cash', 'same-key')->assertCreated();
        $again = $this->takeDeposit($uuid, '500000', 'cash', 'same-key')->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('data.payment.uuid'), $again->json('data.payment.uuid'));

        // Even past the stored response (another actor's replay window): the deposit row is keyed too
        DB::table('idempotency_keys')->delete();
        $third = $this->takeDeposit($uuid, '500000', 'cash', 'same-key')->assertCreated();
        $this->assertSame($first->json('data.payment.uuid'), $third->json('data.payment.uuid'));

        $this->assertSame(1, BookingPayment::count());
        $this->assertCount(1, $this->fiscal->documents());

        // The same key with another amount is another request: 409
        $this->takeDeposit($uuid, '600000', 'cash', 'same-key')->assertStatus(409);

        // No key: refused (money)
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits", ['amount' => '1000', 'tender' => 'cash'])
            ->assertStatus(400)->assertJsonPath('data.reason', 'idempotency_key_required');
    }

    public function test_a_receipt_that_fails_keeps_the_deposit_and_a_retry_issues_it(): void
    {
        $uuid = $this->booked();
        $this->fiscal->fail = true;

        $this->takeDeposit($uuid, '300000', 'card', 'k-1')->assertCreated()->assertJsonPath('data.payment.fiscal_status', 'error')
            ->assertJsonPath('data.payment.fiscal_receipt_uuid', null);

        // Sent again with its key once the stored answer is gone: the same deposit, now with its receipt
        $this->fiscal->fail = false;
        DB::table('idempotency_keys')->delete();
        $this->takeDeposit($uuid, '300000', 'card', 'k-1')->assertCreated()->assertJsonPath('data.payment.fiscal_status', 'issued');
        $this->assertSame(1, BookingPayment::count());
        $this->assertCount(1, $this->fiscal->documents());

        // A fiscal setup problem (no MXIK for deposits) refuses the deposit before any money is recorded
        $this->fiscalLines->missingMxik = true;
        $this->takeDeposit($uuid, '200000', 'cash', 'k-2')->assertStatus(422)->assertJsonPath('data.reason', 'mxik_missing');
        $this->assertSame(1, BookingPayment::count());
    }

    public function test_deposits_only_while_the_booking_is_ahead_and_with_a_valid_amount_and_tender(): void
    {
        $uuid = $this->booked();
        $this->takeDeposit($uuid, '0')->assertStatus(422);
        $this->takeDeposit($uuid, '100', 'prepayment')->assertStatus(422);
        $this->takeDeposit($uuid, '100', 'account')->assertStatus(422);

        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/cancel", ['reason' => 'x'])->assertOk();
        $this->takeDeposit($uuid, '1000')->assertStatus(422)->assertJsonPath('data.reason', 'booking_not_editable');
    }

    public function test_a_cashier_refunds_a_deposit_only_with_a_managers_approval(): void
    {
        $uuid = $this->booked();
        $payment = $this->takeDeposit($uuid, '1000000', 'cash')->json('data.payment.uuid');
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/cancel", ['reason' => 'Mijoz kasal'])->assertOk()
            ->assertJsonPath('data.deposit_unsettled', true);

        $url = "/api/v1/pos/bookings/{$uuid}/deposits/{$payment}/refund";
        $this->api($this->cashier, 'POST', $url, [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Mijoz kasal'])->assertStatus(403)->assertJsonPath('data.reason', 'approval_required');
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Mijoz kasal', 'approval_token' => 'bogus'])->assertStatus(403)->assertJsonPath('data.reason', 'approval_invalid');
        // A token for another action does not do
        $wrong = $this->approvals->grant($this->manager->id, 'orders.cancel');
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Mijoz kasal', 'approval_token' => $wrong])->assertStatus(403);

        $token = $this->approvals->grant($this->manager->id, 'pos.refund');
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Mijoz kasal', 'approval_token' => $token])->assertOk()
            ->assertJsonPath('data.payment.status', 'refunded')
            ->assertJsonPath('data.payment.refunded_amount', '1000000.00')
            ->assertJsonPath('data.payment.remaining', '0.00')
            ->assertJsonPath('data.payment.approved_by', $this->manager->id)
            ->assertJsonPath('data.payment.settle_reason', 'Mijoz kasal')
            ->assertJsonPath('data.booking.deposit_unsettled', false);

        // No receipt for a refund by default (unverified rule), only the advance receipt exists
        $this->assertCount(1, $this->fiscal->documents());
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.deposit_refunded']);
        $this->assertCount(1, $this->telegram->sent);
        $this->assertStringContainsString("Avans qaytarildi: 1 000 000 so'm", $this->telegram->sent[0]['text']);

        // Done once
        $again = $this->approvals->grant($this->manager->id, 'pos.refund');
        $this->api($this->cashier, 'POST', $url, ['reason' => 'x', 'approval_token' => $again])->assertStatus(422)->assertJsonPath('data.reason', 'deposit_settled');
    }

    public function test_a_manager_refunds_without_a_token_and_a_store_may_ask_for_a_refund_receipt(): void
    {
        DB::table('stores')->where('id', $this->store->id)->update(['fiscal_settings' => json_encode(['deposit_refund_receipt' => true])]);
        $uuid = $this->booked();
        $payment = $this->takeDeposit($uuid, '400000', 'card')->json('data.payment');

        // Before the evening, still confirmed: a refund is fine
        $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits/{$payment['uuid']}/refund", ['reason' => 'Kamroq odam'])->assertOk()
            ->assertJsonPath('data.payment.approved_by', $this->manager->id)
            ->assertJsonPath('data.payment.settle_fiscal_status', 'issued');

        $documents = $this->fiscal->documents();
        $this->assertCount(2, $documents);
        $this->assertSame(FiscalReceiptType::REFUND, $documents[1]->type);
        $this->assertSame($payment['fiscal_receipt_uuid'], $documents[1]->originalReceiptUuid);
        $this->assertSame(FiscalPaymentForm::CARD, $documents[1]->payments[0]->form);
        $this->assertSame('400000.00', $documents[1]->total->toString());
    }

    public function test_retaining_a_deposit_after_a_no_show_issues_a_sale_paid_by_the_advance(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 5)->setTime(10, 0));
        $uuid = $this->booked();
        $payment = $this->takeDeposit($uuid, '750000', 'qr')->json('data.payment.uuid');
        $url = "/api/v1/pos/bookings/{$uuid}/deposits/{$payment}/retain";

        // Not while the booking is ahead
        $token = $this->approvals->grant($this->manager->id, 'orders.cancel');
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Kelmadi', 'approval_token' => $token])->assertStatus(422)
            ->assertJsonPath('data.reason', 'invalid_deposit_state');

        $this->travelTo(now()->setTime(15, 0));
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/no-show")->assertOk();
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Kelmadi'])->assertStatus(403);
        $this->api($this->cashier, 'POST', $url, ['reason' => 'Kelmadi', 'approval_token' => $token])->assertOk()
            ->assertJsonPath('data.payment.status', 'retained')
            ->assertJsonPath('data.payment.retained_amount', '750000.00')
            ->assertJsonPath('data.payment.approved_by', $this->manager->id)
            ->assertJsonPath('data.payment.settle_fiscal_status', 'issued');

        $documents = $this->fiscal->documents();
        $this->assertCount(2, $documents);
        $sale = $documents[1];
        $this->assertSame(FiscalReceiptType::SALE, $sale->type);
        $this->assertSame(['deposit', 'booking_fee'], $this->fiscalLines->charges);
        $this->assertSame('10202002003000002', $sale->lines[0]->mxikCode);
        $this->assertTrue($sale->payments[0]->prepayment);
        $this->assertSame(FiscalPaymentForm::QR, $sale->payments[0]->form);
        $this->assertSame('750000.00', $sale->total->toString());
        $this->assertStringContainsString('Avans ushlab qolindi', $this->telegram->sent[0]['text']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'booking.deposit_retained']);
    }

    public function test_a_store_may_retain_without_a_receipt_and_a_manager_needs_no_token(): void
    {
        DB::table('stores')->where('id', $this->store->id)->update(['fiscal_settings' => json_encode(['retained_deposit_receipt' => false])]);
        $uuid = $this->booked();
        $payment = $this->takeDeposit($uuid, '100000', 'cash')->json('data.payment.uuid');
        $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/cancel", ['reason' => 'Kech bekor qildi'])->assertOk();

        $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits/{$payment}/retain", ['reason' => 'Kech bekor qildi'])->assertOk()
            ->assertJsonPath('data.payment.status', 'retained')
            ->assertJsonPath('data.payment.settle_receipt_uuid', null);
        $this->assertCount(1, $this->fiscal->documents());
    }

    public function test_no_refund_while_the_guests_are_seated(): void
    {
        $uuid = $this->booked();
        $payment = $this->takeDeposit($uuid, '100000', 'cash')->json('data.payment.uuid');
        $this->api($this->cashier, 'POST', "/api/v1/pos/bookings/{$uuid}/arrive")->assertOk();

        $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits/{$payment}/refund", ['reason' => 'x'])->assertStatus(422)
            ->assertJsonPath('data.reason', 'invalid_deposit_state');
        $this->api($this->manager, 'POST', "/api/v1/pos/bookings/{$uuid}/deposits/" . '00000000-0000-0000-0000-000000000000' . '/refund', ['reason' => 'x'])->assertNotFound();
    }
}
