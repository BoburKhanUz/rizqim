<?php

namespace Tests\Feature\OnlineFiscal;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payment\Fiscal\OnlineFiscalReceiptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * P3-14: Payme registers the fiscal receipt of an online-paid order from CheckPerformTransaction's `detail` and reports
 * it back with SetFiscalData (examples from https://developer.help.paycom.uz/metody-merchant-api/checkperformtransaction
 * and https://developer.help.paycom.uz/metody-merchant-api/setfiscaldata).
 */
class PaymeFiscalReceiptTest extends TestCase
{
    use RefreshDatabase;
    use OnlineFiscalFixtures;

    private const PLATFORM_KEY = 'platform_payme_key';
    private const STORE_KEY = 'store_payme_key';

    // The receipt id of the docs' SetFiscalData example, used as the Payme transaction id
    private const TRX = '61396aaed8b87a4c215ae556';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.payme.secret_key' => self::PLATFORM_KEY]);
        $this->setUpOnlineFiscal();

        // The store's own Payme cashbox: the receipt is registered with the restaurant as komitent
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => self::STORE_KEY]],
        ]);
    }

    private function rpc(string $method, array $params, ?string $key = self::STORE_KEY)
    {
        $headers = $key === null ? [] : ['Authorization' => 'Basic ' . base64_encode('Paycom:' . $key)];

        return $this->postJson('/api/v1/payments/payme/webhook', [
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => $method,
            'params' => $params,
        ], $headers);
    }

    private function storeOrder(array $attributes = [], ?array $lines = null): Order
    {
        return $this->makeFiscalOrder('payme', $attributes + ['payment_owner' => 'STORE'], $lines);
    }

    private function check(Order $order, ?string $key = self::STORE_KEY)
    {
        return $this->rpc('CheckPerformTransaction', [
            'amount' => (int) round((float) $order->total_price * 100),
            'account' => ['order_id' => $order->uuid],
        ], $key);
    }

    private function paidTransaction(Order $order): void
    {
        $amount = (int) round((float) $order->total_price * 100);
        $this->rpc('CreateTransaction', ['id' => self::TRX, 'time' => 1760000000000, 'amount' => $amount, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);
        $this->rpc('PerformTransaction', ['id' => self::TRX])->assertJsonPath('result.state', 2);
    }

    private function fiscalData(string $type, array $overrides = []): array
    {
        return [
            'id' => self::TRX,
            'type' => $type,
            'fiscal_data' => array_merge([
                'receipt_id' => $type === 'PERFORM' ? 121 : 123,
                'status_code' => 0,
                'message' => 'accepted',
                'terminal_id' => 'EP000000000025',
                'fiscal_sign' => $type === 'PERFORM' ? '800031554082' : '900031555055',
                'qr_code_url' => 'https://ofd.soliq.uz/epi?t=EP000000000025&r=121&c=20220706221021&s=800031554082',
                'date' => $type === 'PERFORM' ? '20220706221021' : '20220706231125',
            ], $overrides),
        ];
    }

    /**
     * sum(price × count − discount) + shipping.price, in tiyin.
     */
    private function detailSum(array $detail): int
    {
        $sum = $detail['shipping']['price'] ?? 0;
        foreach ($detail['items'] as $item) {
            $sum += $item['price'] * $item['count'] - ($item['discount'] ?? 0);
        }

        return $sum;
    }

    public function test_check_perform_answers_with_detail_whose_lines_add_up_to_the_amount(): void
    {
        $order = $this->storeOrder();

        $response = $this->check($order)->assertOk()->assertJsonPath('result.allow', true);
        $detail = $response->json('result.detail');

        $this->assertSame(0, $detail['receipt_type']);
        $this->assertSame(['title' => 'Yetkazib berish', 'price' => 1500000], $detail['shipping']);
        $this->assertSame([
            // 10 000.01 promo spread 7:6 over the lines (largest remainder): 6 461 538 + 5 538 461 tiyin
            ['discount' => 538462, 'title' => 'Osh', 'price' => 3500000, 'count' => 2, 'code' => '10202001001000001', 'units' => 241092, 'vat_percent' => 12, 'package_code' => '1503985'],
            // a weighed line goes as count 1 at its amount, the quantity in the title
            ['title' => 'Somsa (1.5)', 'price' => 5538461, 'count' => 1, 'code' => '10701001001000000', 'units' => 1, 'vat_percent' => 12, 'package_code' => '1504142'],
        ], $detail['items']);
        $this->assertSame(13499999, $this->detailSum($detail));
        $this->assertSame((int) round((float) $order->total_price * 100), $this->detailSum($detail));

        // Asked again, the same order gets the same detail
        $this->assertSame($detail, $this->check($order)->json('result.detail'));
    }

    public function test_rounding_is_exact_and_deterministic_for_awkward_splits(): void
    {
        // Three equal lines and a 0.01 discount: one tiyin goes to the first line; uneven unit prices go to `discount`
        $order = $this->storeOrder(['discount' => '0.01', 'delivery_price' => '0'], [['Osh', '3', '33333.33'], ['Osh', '1', '0.01'], ['Somsa', '7', '1']]);

        $detail = $this->check($order)->json('result.detail');

        $this->assertArrayNotHasKey('shipping', $detail);
        $this->assertSame((int) round((float) $order->total_price * 100), $this->detailSum($detail));
        foreach ($detail['items'] as $item) {
            $this->assertGreaterThanOrEqual(0, $item['discount'] ?? 0);
            $this->assertIsInt($item['price']);
        }

        $builder = app(OnlineFiscalReceiptBuilder::class);
        $this->assertSame([34, 33, 33], $builder->allocate([1, 1, 1], 100));
        $this->assertSame([1, 0, 0], $builder->allocate([5, 5, 5], 1));
        $this->assertSame([0, 0], $builder->allocate([0, 0], 0));
        $this->assertSame(array_sum($builder->allocate([999999999999, 1, 7], 123456789012)), 123456789012);
    }

    public function test_service_charge_and_place_fee_become_their_own_lines(): void
    {
        $this->lineBuilder->charges = ['service_charge' => ['mxik' => '10305008002000000', 'package' => '1546855']];
        $order = $this->storeOrder(['discount' => '0', 'delivery_price' => '0', 'service_charge_amount' => '13000', 'total_price' => '143000'], [['Osh', '2', '35000'], ['Somsa', '1.5', '40000']]);

        $detail = $this->check($order)->json('result.detail');

        $this->assertSame('10305008002000000', $detail['items'][2]['code']);
        $this->assertSame(1300000, $detail['items'][2]['price']);
        $this->assertSame(14300000, $this->detailSum($detail));
    }

    public function test_a_store_with_fiscal_receipts_off_gets_the_old_answer(): void
    {
        $this->setFiscal(false, '305123456');
        $order = $this->storeOrder();

        $this->check($order)->assertExactJson(['jsonrpc' => '2.0', 'id' => 7, 'result' => ['allow' => true]]);

        $this->paidTransaction($order);
        $this->assertArrayNotHasKey('fiscal', Payment::where('provider_transaction_id', self::TRX)->first()->payload);
    }

    public function test_missing_mxik_does_not_block_the_payment_but_is_flagged_and_logged(): void
    {
        Log::spy();
        $order = $this->storeOrder([], [['Osh', '2', '35000'], ['Chuchvara', '1', '30000']]);

        $this->check($order)->assertExactJson(['jsonrpc' => '2.0', 'id' => 7, 'result' => ['allow' => true]]);
        $this->paidTransaction($order);

        $payment = Payment::where('provider_transaction_id', self::TRX)->first();
        $this->assertEquals(['provider' => 'payme', 'status' => 'blocked', 'problems' => ['mxik_missing']], $payment->payload['fiscal']);
        $this->assertSame('paid', $order->fresh()->payment_status->value);

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $message === 'Online fiscal receipt not sent'
            && $context['problems'] === ['mxik_missing'] && $context['details'] === ['items' => ['Chuchvara']] && $context['order_id'] === $order->id);
    }

    public function test_missing_store_tin_or_package_code_is_flagged(): void
    {
        $this->setFiscal(true, null);
        $this->lineBuilder->catalog['Somsa']['package'] = null;
        $order = $this->storeOrder();

        $this->check($order)->assertJsonMissingPath('result.detail');
        $this->paidTransaction($order);

        $this->assertSame(['store_tin_missing', 'package_code_missing'], Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal']['problems']);
    }

    public function test_the_platform_cashbox_does_not_register_a_restaurant_receipt(): void
    {
        $order = $this->makeFiscalOrder('payme');

        $this->check($order, self::PLATFORM_KEY)->assertExactJson(['jsonrpc' => '2.0', 'id' => 7, 'result' => ['allow' => true]]);
    }

    public function test_set_fiscal_data_records_the_payment_and_cancel_receipts_once(): void
    {
        $order = $this->storeOrder();
        $this->paidTransaction($order);

        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'))->assertExactJson(['jsonrpc' => '2.0', 'id' => 7, 'result' => ['success' => true]]);
        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'))->assertExactJson(['jsonrpc' => '2.0', 'id' => 7, 'result' => ['success' => true]]);
        // A late report with another receipt never replaces the registered one
        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM', ['receipt_id' => 999]))->assertJsonPath('result.success', true);

        $this->assertCount(1, $this->issuer->recorded);
        $sale = $this->issuer->recorded['payme:' . self::TRX . ':perform'];
        $this->assertSame('payme', $sale['provider']);
        $this->assertSame(FiscalReceiptType::SALE, $sale['document']->type);
        $this->assertSame($this->store->id, $sale['document']->storeId);
        $this->assertSame('order', $sale['document']->subjectType);
        $this->assertSame($order->id, $sale['document']->subjectId);
        $this->assertSame('134999.99', $sale['document']->total->toString());
        $this->assertSame(FiscalPaymentForm::CARD, $sale['document']->payments[0]->form);
        $this->assertSame(self::TRX, $sale['document']->payments[0]->transactionId);
        $this->assertSame(['305123456'], array_values(array_unique(array_map(fn ($line) => $line->commissionerTin, $sale['document']->lines))));
        $this->assertSame(['800031554082', 'EP000000000025', 121], [$sale['data']['fiscal_sign'], $sale['data']['terminal_id'], $sale['data']['receipt_id']]);

        $fiscal = Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal'];
        $this->assertSame('recorded', $fiscal['status']);
        $this->assertSame('receipt-1', $fiscal['receipt_uuid']);
        $this->assertSame(121, $fiscal['perform_data']['receipt_id']);

        // The cancellation receipt is a separate refund receipt tied to the sale
        $this->rpc('CancelTransaction', ['id' => self::TRX, 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->rpc('SetFiscalData', $this->fiscalData('CANCEL'))->assertJsonPath('result.success', true);
        $this->rpc('SetFiscalData', $this->fiscalData('CANCEL'))->assertJsonPath('result.success', true);

        $this->assertCount(2, $this->issuer->recorded);
        $refund = $this->issuer->recorded['payme:' . self::TRX . ':cancel'];
        $this->assertSame(FiscalReceiptType::REFUND, $refund['document']->type);
        $this->assertSame('receipt-1', $refund['document']->originalReceiptUuid);
        $fiscal = Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal'];
        $this->assertSame('900031555055', $fiscal['cancel_data']['fiscal_sign']);
        $this->assertSame('receipt-2', $fiscal['refund_receipt_uuid']);
    }

    public function test_set_fiscal_data_is_authenticated_and_validated(): void
    {
        $order = $this->storeOrder();
        $this->paidTransaction($order);

        // The transaction's own merchant key only
        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'), null)->assertJsonPath('error.code', -32504);
        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'), self::PLATFORM_KEY)->assertJsonPath('error.code', -32504);

        $this->rpc('SetFiscalData', ['id' => 'unknown-receipt'] + $this->fiscalData('PERFORM'), self::PLATFORM_KEY)
            ->assertExactJson(['jsonrpc' => '2.0', 'id' => 7, 'error' => ['code' => -32001, 'message' => 'Receipt not found']]);
        $this->rpc('SetFiscalData', ['type' => 'REFUND'] + $this->fiscalData('PERFORM'))->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', 'Invalid parameter: type');
        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM', ['fiscal_sign' => '']))->assertJsonPath('error.code', -32602)
            ->assertJsonPath('error.message', 'Invalid parameter: fiscal_data.fiscal_sign');
        $this->rpc('SetFiscalData', ['fiscal_data' => 'x'] + $this->fiscalData('PERFORM'))->assertJsonPath('error.code', -32602);

        $this->assertSame([], $this->issuer->recorded);
    }

    public function test_an_ofd_refusal_is_flagged_and_a_later_registration_recorded(): void
    {
        $order = $this->storeOrder();
        $this->paidTransaction($order);

        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM', ['status_code' => 5, 'message' => 'OFD error', 'fiscal_sign' => null]))
            ->assertJsonPath('result.success', true);

        $fiscal = Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal'];
        $this->assertSame(['failed', ['payme_ofd_rejected']], [$fiscal['status'], $fiscal['problems']]);
        $this->assertSame([], $this->issuer->recorded);

        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'))->assertJsonPath('result.success', true);
        $this->assertSame('recorded', Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal']['status']);
        $this->assertCount(1, $this->issuer->recorded);
    }

    public function test_set_fiscal_data_without_the_fiscal_core_is_kept_on_the_payment(): void
    {
        $this->app->offsetUnset(FiscalIssuer::class);
        $order = $this->storeOrder();
        $this->paidTransaction($order);

        $this->rpc('SetFiscalData', $this->fiscalData('PERFORM'))->assertJsonPath('result.success', true);

        $fiscal = Payment::where('provider_transaction_id', self::TRX)->first()->payload['fiscal'];
        $this->assertSame('800031554082', $fiscal['perform_data']['fiscal_sign']);
        $this->assertNull($fiscal['receipt_uuid']);
    }
}
