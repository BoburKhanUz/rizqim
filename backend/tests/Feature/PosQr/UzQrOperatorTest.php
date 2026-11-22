<?php

namespace Tests\Feature\PosQr;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Pos\QrPayments\QrProviders;
use App\Domain\Pos\QrPayments\Providers\UzQrProvider;
use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosRefund;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Wave P: the real UzQR driver at the till (operator API, the till's own key): create, poll, cancel, refunds, the
 * receipt's payment form, outages and the test/production contour. Every operator call is faked.
 */
class UzQrOperatorTest extends TestCase
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

    private function paid(): PosQrPayment
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');

        return $qr->refresh();
    }

    private function refund(PosQrPayment $qr, string $amount): \Illuminate\Testing\TestResponse
    {
        return $this->pos('POST', '/refunds', ['payment_uuid' => Payment::findOrFail($qr->payment_id)->uuid, 'amount' => $amount, 'reason' => 'Mijoz qaytardi', 'approval_token' => $this->approval('pos.refund')]);
    }

    public function test_a_till_without_its_key_cannot_make_a_uzqr_and_is_offered_the_other_methods(): void
    {
        [, $bill] = $this->billed();

        $this->qr($bill, 'uzqr')->assertStatus(409)
            ->assertJsonPath('data.reason', 'uzqr_till_not_configured')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'API kaliti'));
        $this->assertSame(0, PosQrPayment::count());
        Http::assertNothingSent();
        $this->assertSame(['click', 'payme'], $this->pos('GET', '/bootstrap')->assertOk()->json('data.qr_payments.providers'));

        // With its key the till offers UzQR too
        $this->setTillKey()->assertOk();
        $this->assertContains('uzqr', $this->pos('GET', '/bootstrap')->json('data.qr_payments.providers'));
    }

    public function test_a_store_without_its_stir_cannot_make_a_uzqr(): void
    {
        $this->setTillKey()->assertOk();
        $this->store->forceFill(['tin' => null])->save();
        [, $bill] = $this->billed();

        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'uzqr_till_not_configured')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'STIR'));
        Http::assertNothingSent();
    }

    public function test_it_creates_the_dynamic_qr_with_the_tills_key_the_exact_tiyin_and_our_invoice_id(): void
    {
        $qr = $this->created();

        $request = $this->sentTo(Api::PATH_CREATE);
        $this->assertSame($this->tillKey, $this->keyOf($request));
        $this->assertSame(14300000, $request['amount']);
        $this->assertSame((string) $qr->uuid, $request['external_id']);
        $this->assertSame('301234567', $request['inn']);
        $this->assertSame(5 * 60, $request['lifetime']);
        $this->assertSame(['inv-5001', '00020101021226350011uz.uzqr.dyn', 'uzqr:uzqr_operator'], [$qr->gateway_reference, $qr->checkout_payload, $qr->driver]);
    }

    public function test_a_paid_status_records_the_payment_once_with_pay_id_and_rrn_and_the_receipt_takes_it_as_a_card(): void
    {
        $qr = $this->paid();

        $status = $this->sentTo(Api::PATH_STATUS);
        $this->assertSame(['inv-5001', $this->tillKey], [$status['invoice_id'], $this->keyOf($status)]);
        $this->assertSame('pay-777001', $qr->provider_transaction_id);
        $this->assertEquals(['pay_id' => 'pay-777001', 'rrn' => '612345678901', 'invoice_id' => 'inv-5001', 'card_type' => 'HUMO', 'card_last4' => '4321'], array_intersect_key($qr->provider_payload['confirm'], array_flip(['pay_id', 'rrn', 'invoice_id', 'card_type', 'card_last4'])));
        $this->assertSame('pay-777001', Payment::findOrFail($qr->payment_id)->reference);

        // Polled again: no second payment
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertSame(1, Payment::where('provider', 'qr')->where('status', 'paid')->count());

        // The sale receipt: a card (non-cash) payment with the RRN
        $document = collect($this->fiscal->documents)->first(fn ($document) => $document->subjectType === 'order_bill');
        $this->assertNotNull($document);
        $payment = $document->payments[0];
        $this->assertSame([FiscalPaymentForm::CARD, '612345678901', 'HUMO', '4321'], [$payment->form, $payment->transactionId, $payment->cardType, $payment->cardLast4]);
    }

    public function test_a_paid_answer_of_another_amount_is_kept_for_reconciliation_not_taken(): void
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer(14300001), Api::PATH_CANCEL => ['success' => true]]);

        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.cancel_reason', 'paid_unmatched');
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.qr_paid_unmatched']);
    }

    public function test_pending_and_unknown_statuses_never_confirm_and_a_cancelled_one_cancels(): void
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => Http::sequence()->push($this->statusAnswer('processing'))->push($this->statusAnswer('something_new'))->push($this->statusAnswer('cancelled')), Api::PATH_CANCEL => ['success' => true]]);

        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.status', 'pending');
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.status', 'pending');
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.status', 'cancelled')->assertJsonPath('data.qr.cancel_reason', 'provider_cancelled');
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
    }

    public function test_an_operator_outage_on_create_offers_the_other_methods_and_keeps_nothing(): void
    {
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => Http::response(['message' => 'down'], 503)]);
        [, $bill] = $this->billed();

        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'provider_unavailable');
        $this->assertSame(0, PosQrPayment::count());

        // The bill is still payable another way
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000']])->assertOk();
    }

    public function test_an_outage_while_polling_keeps_the_qr_pending_and_a_paid_after_the_expiry_is_still_taken(): void
    {
        $qr = $this->created();
        $this->fakeOperator([Api::PATH_STATUS => Http::sequence()->pushStatus(502)->pushStatus(502)->push($this->paidAnswer()), Api::PATH_CANCEL => ['success' => true]]);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'pending');

        // The till restarted and nobody polls: the minute job asks before it expires (502: it expires) and asks the
        // recently expired ones again — the customer's payment is recorded
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $this->assertSame('paid', $qr->refresh()->status->value);
        $this->assertSame(1, Payment::where('provider', 'qr')->where('status', 'paid')->count());
    }

    public function test_a_qr_the_cashier_cancelled_but_the_operator_reports_paid_is_kept_for_reconciliation(): void
    {
        $qr = $this->created();
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk()->assertJsonPath('data.qr.status', 'cancelled');
        $cancel = $this->sentTo(Api::PATH_CANCEL);
        $this->assertSame(['inv-5001', $this->tillKey], [$cancel['invoice_id'], $this->keyOf($cancel)]);

        // The customer's bank pushed it just before the cancel reached the operator
        $this->fakeOperator([Api::PATH_STATUS => $this->paidAnswer()]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $qr->refresh();
        $this->assertSame(['cancelled', 'paid_unmatched', 'pay-777001'], [$qr->status->value, $qr->cancel_reason, $qr->provider_transaction_id]);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.qr_paid_unmatched']);
    }

    public function test_a_part_then_the_rest_is_refunded_and_never_more_than_was_paid(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_REFUND => $this->refundAnswer('success')]);

        $this->refund($qr, '43000')->assertCreated();
        $request = $this->sentTo(Api::PATH_REFUND);
        $this->assertSame([4300000, 'inv-5001', 'pay-777001', $this->tillKey], [$request['amount'], $request['invoice_id'], $request['pay_id'], $this->keyOf($request)]);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($request['external_id']));
        $this->assertSame(['paid', '43000.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);

        $this->refund($qr, '100000.01')->assertStatus(422);
        $this->refund($qr, '100000')->assertCreated();
        $this->assertSame(['refunded', '143000.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);
        $this->assertSame(2, $this->countSent(Api::PATH_REFUND));
        $this->assertSame('143000.00', Money::sum(...PosRefund::pluck('amount')->all())->toString());

        // The driver itself refuses more than paid minus the earlier refunds, without asking the operator
        $provider = app(QrProviders::class)->of($qr);
        $this->assertInstanceOf(UzQrProvider::class, $provider);
        $result = $provider->refund($qr, Store::findOrFail($qr->store_id), Money::of('1'), (string) \Illuminate\Support\Str::uuid());
        $this->assertFalse($result->ok);
        $this->assertFalse($result->unknown);
        $this->assertSame(2, $this->countSent(Api::PATH_REFUND));
    }

    public function test_a_refund_the_operator_is_still_processing_is_recorded_by_the_minute_job_once_done(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_REFUND => $this->refundAnswer('processing'), Api::PATH_REFUND_STATUS => $this->refundAnswer('success')]);

        $this->refund($qr, '143000')->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');
        $this->assertSame(0, PosRefund::count());

        $this->travel(2)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $status = $this->sentTo(Api::PATH_REFUND_STATUS);
        $this->assertSame($this->tillKey, $this->keyOf($status));
        $this->assertSame(1, PosRefund::count());
        $this->assertSame('refunded', $qr->refresh()->status->value);
        $this->assertSame(1, $this->countSent(Api::PATH_REFUND));
    }

    public function test_a_refused_refund_changes_nothing(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_REFUND => Http::response($this->errorAnswer('REFUND_NOT_ALLOWED', 'Refund period is over'), 400)]);

        $this->refund($qr, '143000')->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_failed');
        $this->assertSame(['paid', '0.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);
        $this->assertSame(0, PosRefund::count());
    }

    public function test_the_refund_receipt_is_a_card_one_with_the_rrn(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_REFUND => $this->refundAnswer('success')]);
        $this->refund($qr, '43000')->assertCreated();

        $document = collect($this->fiscal->documents)->first(fn ($document) => $document->subjectType === 'pos_refund');
        $this->assertNotNull($document);
        $this->assertSame([FiscalPaymentForm::CARD, '612345678901'], [$document->payments[0]->form, $document->payments[0]->transactionId]);
    }

    public function test_production_never_talks_to_the_test_contour(): void
    {
        $this->setTillKey()->assertOk();
        config(['app.env' => 'production', 'services.payments.allow_test_mode' => false]);
        [, $bill] = $this->billed();

        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'uzqr_till_not_configured');

        // The production URL set to the test one is refused too
        config(['qr_payments.uzqr.operator.test' => false, 'qr_payments.uzqr.operator.url' => $this->base()]);
        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'uzqr_till_not_configured');
        Http::assertNothingSent();

        // The production URL: allowed
        config(['qr_payments.uzqr.operator.url' => 'https://uzqr-prod.example.test/api']);
        Http::fake(['https://uzqr-prod.example.test/api' . Api::PATH_CREATE => Http::response($this->createdAnswer())]);
        $this->qr($bill, 'uzqr')->assertCreated();
    }

    public function test_the_key_never_reaches_the_logs(): void
    {
        $logged = [];
        \Illuminate\Support\Facades\Log::listen(function ($message) use (&$logged) {
            $logged[] = $message->message . json_encode($message->context);
        });
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => Http::response($this->errorAnswer('AUTH', 'bad key'), 401)]);
        [, $bill] = $this->billed();
        $this->qr($bill, 'uzqr')->assertStatus(409);

        $this->assertNotEmpty($logged);
        foreach ($logged as $line) {
            $this->assertStringNotContainsString($this->tillKey, $line);
        }
    }
}
