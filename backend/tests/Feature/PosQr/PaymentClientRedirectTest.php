<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\Providers\ClickQrProvider;
use App\Domain\Pos\QrPayments\Providers\PaymeQrProvider;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Services\Payment\Fiscal\ClickMerchantApi;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review 12 (MEDIUM, the same exposure as the UzQR driver's): an outbound payment client that sends its secret in a
 * header of its own (Payme's X-Auth merchant:key, Click's Auth digest) or in the body never follows a redirect —
 * Guzzle strips only Authorization and Cookie on a cross-origin redirect and re-sends the body on 307/308, to http
 * too. A 3xx is an answer like any refusal.
 */
class PaymentClientRedirectTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    private const ELSEWHERE = 'http://attacker.example.test/steal';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpQr();
        $this->openShift();
    }

    private function redirectEverything(int $status = 307): void
    {
        Http::preventStrayRequests();
        Http::fake(fn (Request $request) => str_starts_with($request->url(), 'http://attacker.')
            ? Http::response(['ok' => true])
            : Http::response('', $status, ['Location' => self::ELSEWHERE]));
    }

    private function assertNothingLeft(): void
    {
        $this->assertGreaterThan(0, Http::recorded()->count(), 'nothing was sent at all');
        $this->assertCount(0, Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'http://attacker.')), 'a request followed the redirect');
    }

    private function paidQr(string $provider): PosQrPayment
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, $provider)->json('data.qr.uuid'));
        $qr->forceFill(['status' => 'paid', 'provider_transaction_id' => (string) random_int(7700000, 7799999), 'paid_at' => now()])->save();

        return $qr->refresh();
    }

    public function test_a_payme_refund_never_follows_a_redirect(): void
    {
        $qr = $this->paidQr('payme');
        $this->redirectEverything();

        $result = app(PaymeQrProvider::class)->refund($qr, Store::findOrFail($qr->store_id), Money::of($qr->amount), (string) Str::uuid());

        $this->assertNothingLeft();
        $this->assertFalse($result->ok);
    }

    public function test_a_click_reversal_and_its_status_never_follow_a_redirect(): void
    {
        $qr = $this->paidQr('click');
        $this->redirectEverything(308);
        $store = Store::findOrFail($qr->store_id);

        $this->assertFalse(app(ClickQrProvider::class)->refund($qr, $store, Money::of($qr->amount), (string) Str::uuid())->ok);
        app(ClickQrProvider::class)->refundStatus($qr, $store, Money::of($qr->amount), (string) Str::uuid());

        $this->assertNothingLeft();
    }

    public function test_the_click_fiscal_api_never_follows_a_redirect(): void
    {
        $this->redirectEverything();
        $credentials = ['merchant_user_id' => '33001', 'secret_key' => Str::random(24), 'service_id' => self::CLICK_SERVICE];

        $response = app(ClickMerchantApi::class)->submitItems($credentials, (int) self::CLICK_SERVICE, 7700001, [], 0);

        $this->assertNothingLeft();
        $this->assertTrue($response->redirect());
    }
}
