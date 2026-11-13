<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Domain\Pos\QrPayments\UzQr\UzQrDynamicQr;
use App\Domain\Pos\QrPayments\UzQr\UzQrGateway;
use App\Events\Broadcast\QrPaymentBroadcast;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Review P6W4 C5 (a UzQR its gateway reports paid around the expiry is taken, or kept for reconciliation), C9 (a held
 * Payme transaction stays payable for Payme's own pending window) and C10 (a Click Complete with an error releases
 * the hold).
 */
class PosQrHoldsAndLatePaymentsTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
        LateUzQrGateway::$status = 'pending';
        config(['qr_payments.uzqr.drivers.late' => LateUzQrGateway::class]);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'uzqr' => ['driver' => 'late']]])->save();
    }

    private function uzqr(): PosQrPayment
    {
        [, $bill] = $this->billed();

        return $this->qrModel($this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid'));
    }

    public function test_a_uzqr_paid_at_the_bank_is_taken_although_its_time_ran_out(): void
    {
        $qr = $this->uzqr();
        $this->travel(6)->minutes();
        LateUzQrGateway::$status = 'paid';

        // The till's poll asks the gateway before expiring it: the money moved, the bill takes it
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertSame(['paid', 'late-tx-1'], [$qr->refresh()->status->value, $qr->provider_transaction_id]);
        $this->assertSame('paid', Payment::findOrFail($qr->payment_id)->status->value);
    }

    public function test_the_expiry_job_asks_the_uzqr_gateway_first_and_a_late_paid_after_expiry_is_taken(): void
    {
        $first = $this->uzqr();
        $this->travel(6)->minutes();
        LateUzQrGateway::$status = 'paid';
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame('paid', $first->refresh()->status->value);

        // Expired (the bank still said pending), then the bank reports it paid: taken, money is never dropped
        LateUzQrGateway::$status = 'pending';
        $second = $this->uzqr();
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame('expired', $second->refresh()->status->value);
        LateUzQrGateway::$status = 'paid';
        $this->pos('GET', "/qr/{$second->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertSame(2, Payment::where('provider', 'qr')->where('status', 'paid')->count());
    }

    public function test_a_late_uzqr_the_bill_cannot_take_is_kept_for_reconciliation(): void
    {
        $qr = $this->uzqr();
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        // Meanwhile the customer paid cash
        $this->pay($qr->bill_uuid, [['tender' => 'cash', 'amount' => '143000']])->assertOk();

        LateUzQrGateway::$status = 'paid';
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.cancel_reason', 'paid_unmatched');

        $qr->refresh();
        $this->assertSame(['expired', 'late-tx-1'], [$qr->status->value, $qr->provider_transaction_id]);
        $this->assertSame('late-tx-1', $qr->provider_payload['unmatched']['transaction_id']);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.qr_paid_unmatched']);
    }

    public function test_a_held_payme_transaction_stays_payable_for_paymes_pending_window(): void
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'payme')->json('data.qr.uuid'));
        $tiyin = Money::of($qr->amount)->toTiyin();
        $this->payme('CreateTransaction', ['id' => 'pm-late-1', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertJsonPath('result.state', 1);

        // 40 minutes later (past the expiry and the 10-minute hold) the customer confirms in the Payme app
        $this->travel(40)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.status', 'pending')->assertJsonPath('data.qr.held', true);
        $this->payme('PerformTransaction', ['id' => 'pm-late-1'])->assertJsonPath('result.state', 2);
        $this->assertSame('paid', $qr->refresh()->status->value);

        // Past Payme's own window (12 h) it is over
        [, $other] = $this->billed();
        $late = $this->qrModel($this->qr($other, 'payme')->json('data.qr.uuid'));
        $this->payme('CreateTransaction', ['id' => 'pm-late-2', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($late)])->assertJsonPath('result.state', 1);
        $this->travel(721)->minutes();
        $this->payme('PerformTransaction', ['id' => 'pm-late-2'])->assertJsonPath('error.code', -31008);
        $this->assertSame('expired', $late->refresh()->status->value);
    }

    public function test_a_bill_change_during_a_payme_hold_still_cancels_it(): void
    {
        [$order, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'payme')->json('data.qr.uuid'));
        $tiyin = Money::of($qr->amount)->toTiyin();
        $this->payme('CreateTransaction', ['id' => 'pm-late-3', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertJsonPath('result.state', 1);

        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000']])->assertOk();

        $this->assertSame('cancelled', $qr->refresh()->status->value);
        $this->payme('PerformTransaction', ['id' => 'pm-late-3'])->assertJsonPath('error.code', -31008);
        $this->payme('CancelTransaction', ['id' => 'pm-late-3', 'reason' => 4])->assertJsonPath('result.state', -1);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
    }

    public function test_a_click_complete_with_an_error_releases_the_hold(): void
    {
        Event::fake([QrPaymentBroadcast::class]);
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'click')->json('data.qr.uuid'));
        $this->click(0, $qr)->assertJsonPath('error', 0);
        $this->assertNotNull($qr->refresh()->held_at);

        $this->click(1, $qr, overrides: ['error' => -5017, 'error_note' => 'Insufficient funds'])->assertJsonPath('error', -9);

        $qr->refresh();
        $this->assertSame(['pending', null, null], [$qr->status->value, $qr->held_at, $qr->provider_transaction_id]);
        Event::assertDispatched(QrPaymentBroadcast::class, fn ($event) => $event->uuid === $qr->uuid && $event->broadcastAs() === 'qr.pending');
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.held', false);

        // The customer tries again with a new Click transaction: payable as before
        $this->click(0, $qr, null, '7700009')->assertJsonPath('error', 0);
        $this->click(1, $qr, null, '7700009')->assertJsonPath('error', 0);
        $this->assertSame('paid', $qr->refresh()->status->value);
    }

    public function test_a_released_click_hold_past_the_expiry_expires_the_qr(): void
    {
        Event::fake([QrPaymentBroadcast::class]);
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'click')->json('data.qr.uuid'));
        $this->click(0, $qr)->assertJsonPath('error', 0);
        $this->travel(6)->minutes();

        $this->click(1, $qr, overrides: ['error' => -1])->assertJsonPath('error', -9);

        $this->assertSame(['expired', null], [$qr->refresh()->status->value, $qr->held_at]);
        Event::assertDispatched(QrPaymentBroadcast::class, fn ($event) => $event->uuid === $qr->uuid && $event->broadcastAs() === 'qr.expired');
    }
}

/**
 * A UzQR gateway whose bank answers what the test says.
 */
final class LateUzQrGateway implements UzQrGateway
{
    public static string $status = 'pending';

    public function createDynamicQr(Money $amount, PosQrPayment $qr, Store $store): UzQrDynamicQr
    {
        return new UzQrDynamicQr('late-' . $qr->uuid, '000201late' . $qr->uuid);
    }

    public function status(string $id, Store $store): array
    {
        return ['status' => self::$status, 'transaction_id' => self::$status === 'paid' ? 'late-tx-1' : null];
    }

    public function cancel(string $id, Store $store): void {}

    public function supportsPartialRefund(): bool
    {
        return true;
    }

    public function refund(string $id, Money $amount, Store $store): QrRefundResult
    {
        return QrRefundResult::done('late-refund');
    }

    public function refundStatus(string $id, Money $amount, Store $store): QrRefundResult
    {
        return QrRefundResult::done('late-refund');
    }
}
