<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosQrRefundAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Review 11 M2: which till's key a UzQR uses, and what happens to its money when that key changes. Each request keeps
 * the key that created it (pos_qr_payments.uzqr_api_key): its status, cancel, refunds and receipt link use that key
 * after the owner removed or replaced the till's one. The two review proofs first.
 */
class UzQrTillKeyChangeTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use UzQrOperatorFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
        $this->setUpOperator();
    }

    private function created(): PosQrPayment
    {
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer(), Api::PATH_STATUS => $this->statusAnswer('pending'), Api::PATH_CANCEL => ['success' => true]]);
        [, $bill] = $this->billed();

        return $this->qrModel($this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid'));
    }

    /**
     * Review finding (MEDIUM): the owner removes (or replaces: a new QR made for the till in uzqr.uz) a till's key while one of its
     * QRs is pending and the customer has paid. Every later call for that QR uses the till's CURRENT key
     * (credentials()): with none, status() throws before asking, the QR expires, the late recheck gets nothing:
     * the paid QR is lost (no payment, no paid_unmatched).
     */
    public function test_removing_a_tills_key_never_loses_its_paid_qr(): void
    {
        $qr = $this->created();
        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['remove_api_key' => true])->assertOk();

        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk();
        $this->travel(6)->minutes();
        for ($minute = 0; $minute < 12; $minute++) {
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
            $this->travel(1)->minutes();
        }

        $qr->refresh();
        $this->assertTrue(Payment::where('provider', 'qr')->count() === 1 || $qr->cancel_reason === 'paid_unmatched',
            "paid at the operator, here {$qr->status->value}/{$qr->cancel_reason}; status asked " . $this->countSent(Api::PATH_STATUS) . ' time(s)');
    }

    /**
     * Review finding (MEDIUM): a refund attempt left `unknown` (timeout) can never resolve once the till's key is gone: refundStatus()
     * turns the missing credential into `unknown` every minute, forever, and the open attempt blocks every other
     * refund of that payment (409 qr_refund_pending).
     */
    public function test_an_unknown_refund_resolves_after_the_tills_key_was_removed(): void
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $qr->refresh();
        $payment = Payment::findOrFail($qr->payment_id);

        $this->fakeOperator([Api::PATH_REFUND => Http::response(['message' => 'timeout'], 504)]);
        $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '143000', 'reason' => 'Mijoz qaytardi', 'approval_token' => $this->approval('pos.refund')])
            ->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');

        $this->owner('PUT', "/owner/fiscal/uzqr/tills/{$this->till['device']->uuid}", ['remove_api_key' => true])->assertOk();
        // The operator finished the reversal meanwhile (the review's proof left the status call unanswered: an
        // unclear answer keeps a refund unknown by design, so a reversal is never sent twice on a misread)
        $this->fakeOperator([Api::PATH_REFUND_STATUS => $this->refundAnswer('success')]);
        for ($minute = 0; $minute < 30; $minute++) {
            $this->travel(2)->minutes();
            $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        }

        $this->assertNotSame('unknown', PosQrRefundAttempt::firstOrFail()->status->value, 'still unknown after an hour, asked ' . $this->countSent(Api::PATH_REFUND_STATUS) . ' time(s) at the operator');
        $this->assertSame('refunded', PosQrRefundAttempt::firstOrFail()->status->value);
        $this->assertSame($this->tillKey, $this->keyOf($this->sentTo(Api::PATH_REFUND_STATUS)));
    }

    public function test_a_replaced_key_keeps_the_old_one_for_the_old_qr_and_the_new_one_for_new_qrs(): void
    {
        $qr = $this->created();
        $oldKey = $this->tillKey;
        $newKey = 'uzk' . bin2hex(random_bytes(20));
        $this->setTillKey(null, $newKey)->assertOk();

        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertSame($oldKey, $this->keyOf($this->sentTo(Api::PATH_STATUS)));

        [, $bill] = $this->billed();
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer('inv-new')]);
        $this->qr($bill, 'uzqr')->assertCreated();
        $creates = Http::recorded(fn ($request) => $request->url() === $this->base() . Api::PATH_CREATE)->map(fn ($pair) => $this->keyOf($pair[0]))->values()->all();
        $this->assertSame([$oldKey, $newKey], $creates);
    }
}
