<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\QrRefunds;
use App\Domain\Pos\QrPayments\QrRefundStatus;
use App\Domain\Pos\QrPayments\Webhooks\PaymePosQrWebhook;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosQrRefundAttempt;
use App\Models\PosRefund;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Review P6W4 C2: a QR refund in two phases: the provider is asked with no transaction open (the shift's money
 * lock is free for the shift's other money actions). A reversal not answered is recorded (`unknown`, 409
 * qr_refund_unknown); a retry asks the provider's status first ("already reversed" is success); the minute job
 * resolves unknown attempts; Payme's CancelTransaction callback that committed the cancel finalises the refund.
 */
class PosQrRefundTwoPhaseTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
    }

    private function paidClick(): PosQrPayment
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'click')->json('data.qr.uuid'));
        $this->click(0, $qr)->assertJsonPath('error', 0);
        $this->click(1, $qr)->assertJsonPath('error', 0);

        return $qr->refresh();
    }

    private function paidPayme(): PosQrPayment
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'payme')->json('data.qr.uuid'));
        $this->payme('CreateTransaction', ['id' => 'pm-2p-1', 'time' => 1763550000000, 'amount' => Money::of($qr->amount)->toTiyin(), 'account' => $this->paymeAccount($qr)]);
        $this->payme('PerformTransaction', ['id' => 'pm-2p-1'])->assertJsonPath('result.state', 2);

        return $qr->refresh();
    }

    private function refund(PosQrPayment $qr, ?string $key = null, bool $approval = true): TestResponse
    {
        $payment = Payment::findOrFail($qr->payment_id);
        $data = ['payment_uuid' => $payment->uuid, 'amount' => '143000', 'reason' => 'Mijoz qaytardi'];
        if ($approval) {
            $data['approval_token'] = $this->approval('pos.refund');
        }

        return $this->pos('POST', '/refunds', $data, null, $key);
    }

    /**
     * Fakes the providers anew (Http::fake adds to the earlier fakes, the first one answering wins).
     *
     * @param  array<string, mixed>|\Closure  $fake
     */
    private function fakeHttp(array|\Closure $fake): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory(app('events')));
        Http::fake($fake);
    }

    private function timeout(int &$sent = 0): void
    {
        $this->fakeHttp(function () use (&$sent) {
            $sent++;
            throw new ConnectionException('cURL error 28: Operation timed out after 20001 milliseconds');
        });
    }

    public function test_no_transaction_and_no_shift_lock_is_held_while_click_is_asked(): void
    {
        $qr = $this->paidClick();
        // The test's own transaction (RefreshDatabase) is the baseline: the request must not have opened another one
        // (RefundService's lockForMoney lives inside a transaction, so none open = the shift's money lock is free)
        $baseline = DB::transactionLevel();
        $levels = [];
        $this->fakeHttp(function () use (&$levels) {
            $levels[] = DB::transactionLevel();

            return Http::response(['error_code' => 0, 'error_note' => 'Success', 'payment_id' => 7700001]);
        });

        $this->refund($qr)->assertCreated();

        $this->assertSame([$baseline], $levels, 'Click is asked inside a DB transaction (the shift money lock is held during the HTTP call)');
        $attempt = PosQrRefundAttempt::sole();
        $this->assertSame(QrRefundStatus::REFUNDED, $attempt->status);
        $this->assertSame($attempt->uuid, PosRefund::sole()->uuid);
    }

    public function test_a_reversal_not_answered_is_recorded_as_unknown(): void
    {
        $qr = $this->paidClick();
        $sent = 0;
        $this->timeout($sent);

        $this->refund($qr)->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown')
            ->assertJsonPath('message', fn (string $message) => str_contains($message, "To'lov tizimi javob bermadi — holat tekshirilmoqda"));

        $this->assertSame(1, $sent);
        $this->assertSame(0, PosRefund::count());
        $this->assertSame('paid', Payment::findOrFail($qr->payment_id)->status->value);
        $attempt = PosQrRefundAttempt::sole();
        $this->assertSame([QrRefundStatus::UNKNOWN, '143000.00'], [$attempt->status, $attempt->amount]);
        $this->assertTrue(QrRefunds::requested($qr));

        // Another refund of the payment (another amount) waits for it: nothing is sent
        $payment = Payment::findOrFail($qr->payment_id);
        $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '1000', 'reason' => 'Boshqa', 'approval_token' => $this->approval('pos.refund')])
            ->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');
        $this->assertSame(1, $sent);
    }

    public function test_a_retry_after_a_timeout_asks_the_status_and_takes_already_reversed_as_done(): void
    {
        $qr = $this->paidClick();
        $key = (string) Str::uuid();
        $this->timeout();
        $this->refund($qr, $key)->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');

        // Click did reverse it: the retry (same Idempotency-Key, the manager's approval already used) asks the status
        $this->fakeHttp(['api.click.uz/*/payment/status/*' => Http::response(['error_code' => 0, 'error_note' => 'Success', 'payment_id' => 7700001, 'payment_status' => -99])]);
        $response = $this->refund($qr, $key, false)->assertCreated();

        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'GET'
            && $request->url() === 'https://api.click.uz/v2/merchant/payment/status/' . self::CLICK_SERVICE . '/7700001');
        Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'DELETE');
        $attempt = PosQrRefundAttempt::sole();
        $this->assertSame(QrRefundStatus::REFUNDED, $attempt->status);
        $this->assertSame([$attempt->uuid], PosRefund::pluck('uuid')->all());
        $this->assertSame($attempt->uuid, $response->json('data.uuid'));
        $this->assertSame(['refunded', '143000.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);
        $this->assertSame('refunded', Payment::findOrFail($qr->payment_id)->status->value);
        $this->assertSame('issued', PosRefund::sole()->fiscal_status, (string) PosRefund::sole()->fiscal_error);
    }

    public function test_a_retry_after_a_timeout_sends_the_reversal_again_when_click_did_not_reverse(): void
    {
        $qr = $this->paidClick();
        $this->timeout();
        $this->refund($qr)->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');

        $this->fakeHttp([
            'api.click.uz/*/payment/status/*' => Http::response(['error_code' => 0, 'payment_id' => 7700001, 'payment_status' => 2]),
            'api.click.uz/*/payment/reversal/*' => Http::response(['error_code' => 0, 'error_note' => 'Success', 'payment_id' => 7700001]),
        ]);
        $this->refund($qr, null, false)->assertCreated();

        Http::assertSentCount(2);
        $this->assertSame(1, PosRefund::count());
        $this->assertSame(2, PosQrRefundAttempt::sole()->provider_calls);
    }

    public function test_the_minute_job_resolves_unknown_refunds(): void
    {
        $reversed = $this->paidClick();
        $this->timeout();
        $this->refund($reversed)->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');
        [, $bill] = $this->billed();
        $kept = $this->qrModel($this->qr($bill, 'click')->json('data.qr.uuid'));
        $this->click(0, $kept, null, '7700002')->assertJsonPath('error', 0);
        $this->click(1, $kept, null, '7700002')->assertJsonPath('error', 0);
        $this->refund($kept->refresh())->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_unknown');

        // Click reversed the first one, not the second; the job only asks (never sends a reversal itself)
        $this->fakeHttp([
            'api.click.uz/*/payment/status/*/7700001' => Http::response(['error_code' => 0, 'payment_id' => 7700001, 'payment_status' => -99]),
            'api.click.uz/*/payment/status/*/7700002' => Http::response(['error_code' => 0, 'payment_id' => 7700002, 'payment_status' => 2]),
        ]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'DELETE');
        $this->assertSame(QrRefundStatus::REFUNDED, PosQrRefundAttempt::where('qr_payment_id', $reversed->id)->sole()->status);
        $this->assertSame(QrRefundStatus::FAILED, PosQrRefundAttempt::where('qr_payment_id', $kept->id)->sole()->status);
        $this->assertSame([(int) $reversed->payment_id], PosRefund::pluck('payment_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('refunded', Payment::findOrFail($reversed->payment_id)->status->value);
        $this->assertSame('paid', Payment::findOrFail($kept->payment_id)->status->value);
        $this->assertSame('issued', PosRefund::sole()->fiscal_status, (string) PosRefund::sole()->fiscal_error);

        // Asked again within the minute: nothing more
        $this->fakeHttp([]);
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        Http::assertNothingSent();
    }

    public function test_a_payme_cancel_callback_that_committed_finalises_the_refund(): void
    {
        $qr = $this->paidPayme();
        // Payme calls CancelTransaction while receipts.cancel runs, then the answer to receipts.cancel is lost
        $this->fakeHttp(function () use ($qr) {
            $this->assertSame(-2, $this->paymeCancel($qr)['result']['state']);
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $this->refund($qr)->assertCreated();

        $this->assertSame(QrRefundStatus::REFUNDED, PosQrRefundAttempt::sole()->status);
        $this->assertSame(1, PosRefund::count());
        $this->assertSame('refunded', $qr->refresh()->status->value);
    }

    public function test_a_payme_cancel_callback_after_a_refused_receipts_cancel_is_recorded_by_the_job(): void
    {
        $qr = $this->paidPayme();
        $this->fakeHttp(fn () => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'error' => ['code' => -31504, 'message' => 'Busy']]));
        $this->refund($qr)->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_failed');
        $this->assertSame(QrRefundStatus::FAILED, PosQrRefundAttempt::sole()->status);

        // Payme cancelled it after all: its callback is accepted and the job records the refund
        $this->assertSame(-2, $this->paymeCancel($qr)['result']['state']);
        $this->fakeHttp(fn () => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['state' => 50]]));
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();

        $this->assertSame(QrRefundStatus::REFUNDED, PosQrRefundAttempt::sole()->status);
        $this->assertSame(1, PosRefund::count());
        $this->assertSame('refunded', $qr->refresh()->status->value);
    }

    /**
     * Payme's CancelTransaction (reason 5: a refund), handled in this process as the webhook does.
     *
     * @return array<string, mixed>
     */
    private function paymeCancel(PosQrPayment $qr): array
    {
        $request = Request::create('/api/v1/payments/payme/webhook', 'POST', server: ['HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('Paycom:' . self::PAYME_SECRET)]);

        return app(PaymePosQrWebhook::class)->handle($request, 'CancelTransaction', ['id' => (string) $qr->refresh()->provider_transaction_id, 'reason' => 5], 7)->getData(true);
    }
}
