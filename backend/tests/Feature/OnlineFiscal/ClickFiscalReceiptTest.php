<?php

namespace Tests\Feature\OnlineFiscal;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\PaymentStatus;
use App\Jobs\SubmitClickFiscalData;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Fiscal\ClickMerchantApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P3-14: after a Click payment completes, a queued job sends the receipt items to Click (submit_items) and records the
 * receipt link Click returns (ofd_data). Requests and answers follow https://docs.click.uz/merchant-api/fiscalization.
 */
class ClickFiscalReceiptTest extends TestCase
{
    use RefreshDatabase;
    use OnlineFiscalFixtures;

    private const SECRET = 'platform_click_secret';
    private const QR = 'https://ofd.soliq.uz/epi?t=EZ000000000030&r=123456789&c=20221028171340&s=854971301623';
    private const SUBMIT_URL = 'https://api.click.uz/v2/merchant/payment/ofd_data/submit_items';
    private const DATA_URL = 'https://api.click.uz/v2/merchant/payment/ofd_data/32104/1946296773';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.click.secret_key' => self::SECRET,
            'services.click.service_id' => '32104',
            'services.click.merchant_user_id' => '41234',
        ]);
        $this->setUpOnlineFiscal();
        $this->lineBuilder->charges = ['delivery' => ['mxik' => '10112006002000000', 'package' => '1541880']];
        $this->travelTo(now()->setTimestamp(1760000000));
    }

    private function complete(Order $order, string $clickTransId = '1946296773')
    {
        $amount = number_format((float) $order->total_price, 2, '.', '');
        $signTime = '2026-10-18 12:00:00';

        return $this->postJson('/api/v1/payments/click/webhook', [
            'click_trans_id' => $clickTransId,
            'service_id' => '32104',
            'click_paydoc_id' => '555',
            'merchant_trans_id' => $order->uuid,
            'merchant_prepare_id' => $order->id,
            'amount' => $amount,
            'action' => 1,
            'error' => 0,
            'error_note' => 'Success',
            'sign_time' => $signTime,
            'sign_string' => md5($clickTransId . '32104' . self::SECRET . $order->uuid . $order->id . $amount . '1' . $signTime),
        ]);
    }

    private function successfulAnswer(Order $order): array
    {
        return [
            'click_trans_id' => '1946296773',
            'merchant_trans_id' => $order->uuid,
            'merchant_confirm_id' => $order->id,
            'error' => 0,
            'error_note' => 'Success',
        ];
    }

    private function fakeClick(array $submit = ['error_code' => 0, 'error_note' => 'Success'], ?array $data = null, int $dataStatus = 200): void
    {
        Http::fake([
            self::SUBMIT_URL => Http::response($submit),
            self::DATA_URL => Http::response($data ?? ['paymentId' => 1946296773, 'qrCodeURL' => self::QR], $dataStatus),
        ]);
    }

    private function fiscal(): array
    {
        return Payment::where('provider', 'click')->where('provider_transaction_id', '1946296773')->first()->payload['fiscal'] ?? [];
    }

    public function test_a_completed_payment_sends_the_items_and_records_the_receipt(): void
    {
        $this->fakeClick();
        $order = $this->makeFiscalOrder('click');

        // The Complete answer is exactly what it was before fiscal receipts
        $this->complete($order)->assertExactJson($this->successfulAnswer($order));

        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::SUBMIT_URL) {
                return false;
            }

            $this->assertSame('POST', $request->method());
            $this->assertSame('41234:' . sha1('1760000000' . self::SECRET) . ':1760000000', $request->header('Auth')[0]);
            $this->assertSame(['service_id', 'payment_id', 'items', 'received_ecash', 'received_cash', 'received_card'], array_keys($request->data()));
            $this->assertSame([32104, 1946296773, 0, 0, 13499999], [$request['service_id'], $request['payment_id'], $request['received_ecash'], $request['received_cash'], $request['received_card']]);
            $this->assertSame([
                'Name' => 'Osh', 'SPIC' => '10202001001000001', 'PackageCode' => '1503985', 'Units' => 241092,
                'Price' => 6461538, 'Amount' => 2000, 'VAT' => 692308, 'VATPercent' => 12, 'CommissionInfo' => ['TIN' => '305123456'],
            ], $request['items'][0]);
            // Quantity in thousandths: 1.5 kg
            $this->assertSame(1500, $request['items'][1]['Amount']);
            // The delivery fee is its own line with the store's delivery MXIK
            $this->assertSame(['10112006002000000', 1500000, 1000, 1500000], [$request['items'][2]['SPIC'], $request['items'][2]['Price'], $request['items'][2]['Amount'], $request['items'][2]['GoodPrice']]);
            $this->assertSame(13499999, array_sum(array_column($request['items'], 'Price')));

            return true;
        });
        Http::assertSent(fn (Request $request) => $request->url() === self::DATA_URL && $request->method() === 'GET');

        $recorded = $this->issuer->recorded['click:1946296773:sale'];
        $this->assertSame('click', $recorded['provider']);
        $this->assertSame(FiscalReceiptType::SALE, $recorded['document']->type);
        $this->assertSame('134999.99', $recorded['document']->total->toString());
        $this->assertSame(['EZ000000000030', '123456789', '20221028171340', '854971301623', self::QR],
            [$recorded['data']['terminal_id'], $recorded['data']['receipt_id'], $recorded['data']['date'], $recorded['data']['fiscal_sign'], $recorded['data']['qr_code_url']]);

        $this->assertSame(['recorded', [], self::QR, 'receipt-1'], [$this->fiscal()['status'], $this->fiscal()['problems'], $this->fiscal()['qr_code_url'], $this->fiscal()['receipt_uuid']]);

        // A repeated Complete neither resends nor records again
        $this->complete($order)->assertJsonPath('error', 0);
        (new SubmitClickFiscalData(Payment::where('provider_transaction_id', '1946296773')->value('id')))->handle(...$this->jobDependencies());
        Http::assertSentCount(2);
        $this->assertCount(1, $this->issuer->recorded);
    }

    public function test_the_job_is_queued_and_never_runs_inside_the_callback(): void
    {
        Queue::fake();
        Http::fake();
        $order = $this->makeFiscalOrder('click');

        $this->complete($order)->assertExactJson($this->successfulAnswer($order));

        Queue::assertPushed(SubmitClickFiscalData::class, 1);
        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
    }

    public function test_a_store_with_fiscal_receipts_off_sends_nothing(): void
    {
        Queue::fake();
        $this->setFiscal(false, '305123456');
        $order = $this->makeFiscalOrder('click');

        $this->complete($order)->assertExactJson($this->successfulAnswer($order));

        Queue::assertNotPushed(SubmitClickFiscalData::class);
    }

    public function test_click_refusing_the_items_marks_the_receipt_failed_without_touching_the_payment(): void
    {
        Log::spy();
        $this->fakeClick(['error_code' => -5, 'error_note' => 'Payment not found']);
        $order = $this->makeFiscalOrder('click');

        $this->complete($order)->assertExactJson($this->successfulAnswer($order));

        $this->assertSame(['failed', ['click_submit_failed']], [$this->fiscal()['status'], $this->fiscal()['problems']]);
        $this->assertSame([], $this->issuer->recorded);
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        Http::assertNotSent(fn (Request $request) => $request->url() === self::DATA_URL);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'Click fiscal data failed'
            && $context['problem'] === 'click_submit_failed');
    }

    public function test_outages_and_a_receipt_not_ready_yet_are_retried_with_backoff(): void
    {
        Queue::fake();
        $order = $this->makeFiscalOrder('click');
        $this->complete($order);
        $paymentId = (int) Payment::where('provider_transaction_id', '1946296773')->value('id');

        Http::fake([
            self::SUBMIT_URL => Http::sequence()->push('Bad gateway', 502)->push(['error_code' => 0, 'error_note' => 'Success']),
            self::DATA_URL => Http::sequence()->push(['paymentId' => 1946296773, 'qrCodeURL' => null])->push(['paymentId' => 1946296773, 'qrCodeURL' => self::QR]),
        ]);

        // 1st attempt: Click is down
        $job = (new SubmitClickFiscalData($paymentId))->withFakeQueueInteractions();
        $job->handle(...$this->jobDependencies());
        $job->assertReleased(60);
        $this->assertArrayNotHasKey('fiscal', Payment::find($paymentId)->payload);

        // Next attempt: items accepted, but the receipt link is not there yet
        $job = (new SubmitClickFiscalData($paymentId))->withFakeQueueInteractions();
        $job->handle(...$this->jobDependencies());
        $job->assertReleased(60);
        $this->assertSame('pending', $this->fiscal()['status']);

        // Then: only the link is asked for again, the items are not resent
        $job = (new SubmitClickFiscalData($paymentId))->withFakeQueueInteractions();
        $job->handle(...$this->jobDependencies());
        $job->assertNotReleased();
        $this->assertSame('recorded', $this->fiscal()['status']);
        $this->assertCount(2, Http::recorded(fn (Request $request) => $request->url() === self::SUBMIT_URL));
        $this->assertCount(2, Http::recorded(fn (Request $request) => $request->url() === self::DATA_URL));
        $this->assertCount(1, $this->issuer->recorded);

        // Retries running out mark it failed for the owner
        $job = new SubmitClickFiscalData($paymentId);
        $job->failed(new \RuntimeException('Timed out'));
        $this->assertSame('failed', $this->fiscal()['status']);
    }

    public function test_missing_mxik_tin_or_credentials_block_the_receipt_without_calling_click(): void
    {
        Http::fake();
        Log::spy();

        $order = $this->makeFiscalOrder('click', [], [['Osh', '2', '35000'], ['Chuchvara', '1', '30000']]);
        $this->complete($order)->assertExactJson($this->successfulAnswer($order));
        $this->assertSame(['blocked', ['mxik_missing']], [$this->fiscal()['status'], $this->fiscal()['problems']]);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'Online fiscal receipt not sent'
            && $context['provider'] === 'click' && $context['details'] === ['items' => ['Chuchvara']]);

        $this->setFiscal(true, null);
        config(['services.click.merchant_user_id' => null]);
        $this->lineBuilder->charges = [];
        $order = $this->makeFiscalOrder('click');
        $this->complete($order, '1946296774')->assertJsonPath('error', 0);
        $fiscal = Payment::where('provider_transaction_id', '1946296774')->first()->payload['fiscal'];
        $this->assertSame(['store_tin_missing', 'delivery_mxik_missing', 'click_credentials_missing'], $fiscal['problems']);

        Http::assertNothingSent();
        $this->assertSame([], $this->issuer->recorded);
    }

    public function test_a_sole_proprietor_is_sent_as_pinfl(): void
    {
        // stores.tin holds a company's 9-digit TIN; a sole proprietor's PINFL comes from the older tax_number
        $this->setFiscal(true, null);
        \Illuminate\Support\Facades\DB::table('stores')->where('id', $this->store->id)->update(['tax_number' => '31234567890123']);
        $this->fakeClick();
        $order = $this->makeFiscalOrder('click');

        $this->complete($order);

        Http::assertSent(fn (Request $request) => $request->url() !== self::SUBMIT_URL
            || $request['items'][0]['CommissionInfo'] === ['PINFL' => '31234567890123']);
        $this->assertSame('recorded', $this->fiscal()['status']);
    }

    public function test_the_qr_link_is_parsed_like_the_docs_example(): void
    {
        $this->assertSame(
            ['terminal_id' => 'EZ000000000030', 'receipt_id' => '123456789', 'date' => '20221028171340', 'fiscal_sign' => '854971301623'],
            ClickMerchantApi::parseQrUrl(self::QR),
        );
        $this->assertSame(['terminal_id' => null, 'receipt_id' => null, 'date' => null, 'fiscal_sign' => null], ClickMerchantApi::parseQrUrl('https://ofd.soliq.uz/'));
    }

    /**
     * @return list<object>
     */
    private function jobDependencies(): array
    {
        return [
            app(\App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder::class),
            app(\App\Services\Payment\Fiscal\OnlineFiscalRecorder::class),
            app(ClickMerchantApi::class),
        ];
    }
}
