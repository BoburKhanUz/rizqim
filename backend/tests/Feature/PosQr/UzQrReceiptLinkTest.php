<?php

namespace Tests\Feature\PosQr;

use App\Domain\Fiscal\FiscalReceiptStatus;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\QrPayments\UzQr\UzQrOperatorProtocol as Api;
use App\Events\Broadcast\FiscalReceiptUpdatedBroadcast;
use App\Models\FiscalReceipt;
use App\Models\OrderBill;
use App\Models\PosQrPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Wave P: once the sale receipt of a bill a UzQR paid is fiscalised, its link goes to UzQR (a queued job, retried,
 * idempotent; a failure is logged and shown on the QR, never blocks the sale).
 */
class UzQrReceiptLinkTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;
    use UzQrOperatorFixtures;

    private const LINK = 'https://ofd.soliq.uz/check?t=EP000000000001&r=100&c=20261122120000&s=123456789012';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
        $this->setUpOperator();
    }

    private function paid(): PosQrPayment
    {
        $this->setTillKey()->assertOk();
        $this->fakeOperator([Api::PATH_CREATE => $this->createdAnswer(), Api::PATH_STATUS => $this->paidAnswer()]);
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid'));
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.status', 'paid');

        return $qr->refresh();
    }

    private function issued(PosQrPayment $qr, ?string $link = self::LINK, FiscalReceiptStatus $status = FiscalReceiptStatus::ISSUED): FiscalReceipt
    {
        return \App\Domain\Fiscal\FiscalReceiptLock::outsideIssuerForTests(fn () => FiscalReceipt::forceCreate([
            'uuid' => (string) Str::uuid(),
            'store_id' => $qr->store_id,
            'branch_id' => $qr->branch_id,
            'device_id' => $qr->device_id,
            'type' => FiscalReceiptType::SALE,
            'payment_form' => 'card',
            'status' => $status,
            'driver' => 'sandbox',
            'idempotency_key' => 'test:' . Str::uuid(),
            'subject_type' => OrderBill::FISCAL_SUBJECT,
            'subject_id' => $qr->bill_id,
            'total' => $qr->amount,
            'lines' => [],
            'payments' => [],
            'qr_url' => $link,
            'issued_at' => now(),
        ]));
    }

    public function test_the_receipt_link_goes_to_uzqr_once_after_fiscalisation(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_RECEIPT => ['success' => true]]);
        $receipt = $this->issued($qr);

        event(new FiscalReceiptUpdatedBroadcast($receipt));

        $request = $this->sentTo(Api::PATH_RECEIPT);
        $this->assertSame([self::LINK, 'inv-5001', 'pay-777001', $this->tillKey], [$request['fiscal_url'], $request['invoice_id'], $request['pay_id'], $this->keyOf($request)]);
        $this->assertSame('sent', $qr->refresh()->provider_payload['receipt_link']['status']);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.receipt_link', 'sent');

        // The same receipt announced again (a retry, the sweeper): not sent twice
        event(new FiscalReceiptUpdatedBroadcast($receipt));
        $this->assertSame(1, $this->countSent(Api::PATH_RECEIPT));
    }

    public function test_a_pending_receipt_or_one_without_a_link_sends_nothing(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_RECEIPT => ['success' => true]]);

        event(new FiscalReceiptUpdatedBroadcast($this->issued($qr, self::LINK, FiscalReceiptStatus::PENDING)));
        event(new FiscalReceiptUpdatedBroadcast($this->issued($qr, null)));

        $this->assertSame(0, $this->countSent(Api::PATH_RECEIPT));
        $this->assertArrayNotHasKey('receipt_link', $qr->refresh()->provider_payload ?? []);
    }

    public function test_a_failure_is_logged_and_shown_and_never_breaks_the_receipt(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_RECEIPT => Http::response($this->errorAnswer('INTERNAL', 'Try later'), 500)]);
        $receipt = $this->issued($qr);

        event(new FiscalReceiptUpdatedBroadcast($receipt));

        $link = $qr->refresh()->provider_payload['receipt_link'];
        $this->assertSame('failed', $link['status']);
        $this->assertStringContainsString('500', $link['error']);
        $this->pos('GET', "/qr/{$qr->uuid}")->assertJsonPath('data.qr.receipt_link', 'failed');
        $this->assertSame('issued', $receipt->refresh()->status->value);

        // A later announcement (the owner's retry) sends it again and it goes through
        $this->fakeOperator([Api::PATH_RECEIPT => ['success' => true]]);
        event(new FiscalReceiptUpdatedBroadcast($receipt));
        $this->assertSame('sent', $qr->refresh()->provider_payload['receipt_link']['status']);
    }

    public function test_the_sandbox_gateway_sends_nothing(): void
    {
        config(['qr_payments.sandbox' => true]);
        $this->store->forceFill(['fiscal_settings' => ['enabled' => true, 'uzqr' => ['driver' => 'sandbox']]])->save();
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill, 'uzqr')->assertCreated()->json('data.qr.uuid');
        $this->pos('POST', "/qr/{$uuid}/simulate-paid")->assertOk();
        $qr = $this->qrModel($uuid);

        event(new FiscalReceiptUpdatedBroadcast($this->issued($qr)));

        Http::assertNothingSent();
        $this->assertSame('sent', $qr->refresh()->provider_payload['receipt_link']['status'] ?? 'sent');
    }

    /**
     * Review 11 LOW: on a sync queue (no worker) the job ran inside the request that fiscalised the bill, holding the
     * cashier's answer for up to the operator's timeout. Inside a request it now runs after the answer was sent.
     */
    public function test_on_a_sync_queue_inside_a_request_the_link_is_sent_after_the_answer(): void
    {
        $qr = $this->paid();
        $this->fakeOperator([Api::PATH_RECEIPT => ['success' => true]]);
        $receipt = $this->issued($qr);

        \App\Domain\Pos\QrPayments\UzQr\SendUzQrReceiptLink::send((int) $qr->id, (int) $receipt->id, inRequest: true);
        $this->assertSame(0, $this->countSent(Api::PATH_RECEIPT));

        $this->app->terminate();
        $this->assertSame(1, $this->countSent(Api::PATH_RECEIPT));
        $this->assertSame('sent', $qr->refresh()->provider_payload['receipt_link']['status']);
    }
}
