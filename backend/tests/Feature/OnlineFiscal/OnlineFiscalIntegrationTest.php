<?php

namespace Tests\Feature\OnlineFiscal;

use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Feature\ManagementFixtures;
use Tests\TestCase;

/**
 * P3-14 end to end with the real fiscal core (P3-11 MenuFiscalLineBuilder and FiscalService): a real checkout paid
 * through Click or Payme ends as an `external` receipt in fiscal_receipts, and the owner's payments list shows where
 * each payment's receipt stands.
 */
class OnlineFiscalIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use ManagementFixtures;

    private const QR = 'https://ofd.soliq.uz/epi?t=EZ000000000030&r=123456789&c=20221028171340&s=854971301623';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.click.secret_key' => 'platform_click_secret',
            'services.click.service_id' => '32104',
            'services.click.merchant_user_id' => '41234',
        ]);
        $this->setUpManagement();
        $this->product->forceFill(['mxik_code' => '10202001001000001', 'package_code' => '1503985'])->save();
        $this->store->forceFill(['tin' => '305123456', 'tax_regime' => 'vat', 'fiscal_settings' => ['enabled' => true, 'driver' => 'sandbox']])->save();
    }

    private function completeClick(Order $order, string $clickTransId = '1946296773')
    {
        $amount = number_format((float) $order->total_price, 2, '.', '');
        $signTime = '2026-10-21 12:00:00';

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
            'sign_string' => md5($clickTransId . '32104' . 'platform_click_secret' . $order->uuid . $order->id . $amount . '1' . $signTime),
        ]);
    }

    public function test_a_click_payment_ends_as_an_external_receipt_with_the_menu_mxik(): void
    {
        Http::fake([
            'api.click.uz/v2/merchant/payment/ofd_data/submit_items' => Http::response(['error_code' => 0, 'error_note' => 'Success']),
            'api.click.uz/v2/merchant/payment/ofd_data/32104/1946296773' => Http::response(['paymentId' => 1946296773, 'qrCodeURL' => self::QR]),
        ]);
        $order = $this->checkout($this->customer, 2, 'click');

        $this->completeClick($order)->assertJsonPath('error', 0)->assertJsonPath('error_note', 'Success');

        Http::assertSent(fn (Request $request) => !str_ends_with($request->url(), 'submit_items') || $request['items'] === [[
            'Name' => 'Cheeseburger (Regular)',
            'SPIC' => '10202001001000001',
            'PackageCode' => '1503985',
            'GoodPrice' => 5000000,
            'Price' => 10000000,
            'Amount' => 2000,
            'VAT' => 1071429,
            'VATPercent' => 12,
            'CommissionInfo' => ['TIN' => '305123456'],
        ]]);

        $receipt = FiscalReceipt::where('store_id', $this->store->id)->sole();
        $this->assertSame(['external', 'click', 'issued', '854971301623', 'EZ000000000030', self::QR],
            [$receipt->driver, $receipt->provider, $receipt->status->value, $receipt->fiscal_sign, $receipt->terminal_id, $receipt->qr_url]);
        $this->assertSame(['order', $order->id], [$receipt->subject_type, $receipt->subject_id]);

        $rows = $this->as($this->owner, 'GET', '/api/v1/owner/payments?fiscal_status=recorded')->assertOk()->json('data.data');
        $this->assertSame([['status' => 'recorded', 'problems' => []]], array_column($rows, 'fiscal'));
        $this->assertSame([], $this->as($this->owner, 'GET', '/api/v1/owner/payments?fiscal_status=blocked')->json('data.data'));
    }

    public function test_a_dish_without_mxik_is_listed_for_the_owner_and_the_payment_still_goes_through(): void
    {
        Http::fake();
        $this->product->forceFill(['mxik_code' => null])->save();
        $order = $this->checkout($this->customer, 1, 'click');

        $this->completeClick($order)->assertJsonPath('error', 0);

        Http::assertNothingSent();
        $this->assertSame('paid', $order->fresh()->payment_status->value);
        $rows = $this->as($this->owner, 'GET', '/api/v1/owner/payments?fiscal_status=blocked')->assertOk()->json('data.data');
        $this->assertCount(1, $rows);
        $this->assertSame(['status' => 'blocked', 'problems' => ['mxik_missing']], $rows[0]['fiscal']);
        $this->assertStringNotContainsString('click_paydoc_id', $this->as($this->owner, 'GET', '/api/v1/owner/payments')->getContent());
        $this->as($this->owner, 'GET', '/api/v1/owner/payments?fiscal_status=lost')->assertStatus(422);

        // A cash order has no online receipt state
        $this->checkout($this->customer, 1, 'cash');
        $all = $this->as($this->owner, 'GET', '/api/v1/owner/payments?provider=cash')->json('data.data');
        $this->assertSame([null], array_column($all, 'fiscal'));
    }

    public function test_payme_detail_and_set_fiscal_data_with_the_real_fiscal_core(): void
    {
        $this->store->update([
            'payment_account_type' => 'own_account',
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own-cashbox', 'secret_key' => 'store_payme_key']],
        ]);
        $order = $this->checkout($this->customer, 3, 'payme');
        $this->assertSame('STORE', $order->payment_owner);
        $rpc = fn (string $method, array $params) => $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params],
            ['Authorization' => 'Basic ' . base64_encode('Paycom:store_payme_key')]);

        $detail = $rpc('CheckPerformTransaction', ['amount' => 15000000, 'account' => ['order_id' => $order->uuid]])->json('result.detail');
        $this->assertSame(['receipt_type' => 0, 'items' => [[
            'title' => 'Cheeseburger (Regular)', 'price' => 5000000, 'count' => 3, 'code' => '10202001001000001', 'vat_percent' => 12, 'package_code' => '1503985',
        ]]], $detail);

        $rpc('CreateTransaction', ['id' => '61396aaed8b87a4c215ae556', 'time' => 1760000000000, 'amount' => 15000000, 'account' => ['order_id' => $order->uuid]]);
        $rpc('PerformTransaction', ['id' => '61396aaed8b87a4c215ae556'])->assertJsonPath('result.state', 2);
        $rpc('SetFiscalData', ['id' => '61396aaed8b87a4c215ae556', 'type' => 'PERFORM', 'fiscal_data' => [
            'receipt_id' => 121, 'status_code' => 0, 'message' => 'accepted', 'terminal_id' => 'EP000000000025',
            'fiscal_sign' => '800031554082', 'qr_code_url' => 'fiscal receipt url', 'date' => '20220706221021',
        ]])->assertExactJson(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['success' => true]]);

        $receipt = FiscalReceipt::where('store_id', $this->store->id)->sole();
        $this->assertSame(['payme', '800031554082', '121', 'EP000000000025'], [$receipt->provider, $receipt->fiscal_sign, $receipt->receipt_number, $receipt->terminal_id]);
        $this->assertSame($receipt->uuid, Payment::where('provider_transaction_id', '61396aaed8b87a4c215ae556')->first()->payload['fiscal']['receipt_uuid']);
    }
}
