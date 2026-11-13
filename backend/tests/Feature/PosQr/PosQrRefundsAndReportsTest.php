<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\QrRefunds;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosRefund;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P6-41: refunding a QR payment goes back through its provider (Click payment reversal, Payme receipts.cancel, the
 * sandbox / UzQR gateway): a refused reversal blocks the refund (never a silent cash refund), Click/Payme only in full;
 * the X/Z breakdown of `qr` payments by provider; the owner's settings (module gate, validation, audit).
 */
class PosQrRefundsAndReportsTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    private PosShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpQr();
        $this->shift = $this->openShift();
    }

    private function paidClick(string $clickTransId = '7700001'): PosQrPayment
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'click')->json('data.qr.uuid'));
        $this->click(0, $qr, null, $clickTransId)->assertJsonPath('error', 0);
        $this->click(1, $qr, null, $clickTransId)->assertJsonPath('error', 0);

        return $qr->refresh();
    }

    private function refund(PosQrPayment $qr, string $amount): \Illuminate\Testing\TestResponse
    {
        $payment = Payment::findOrFail($qr->payment_id);

        return $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => $amount, 'reason' => 'Mijoz qaytardi', 'approval_token' => $this->approval('pos.refund')]);
    }

    public function test_a_click_qr_refund_reverses_the_payment_at_click(): void
    {
        $qr = $this->paidClick();
        Http::fake(['api.click.uz/*' => Http::response(['error_code' => 0, 'error_note' => 'Success', 'payment_id' => 7700001])]);

        $this->refund($qr, '143000')->assertCreated();

        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'DELETE'
            && $request->url() === 'https://api.click.uz/v2/merchant/payment/reversal/' . self::CLICK_SERVICE . '/7700001'
            && str_starts_with($request->header('Auth')[0] ?? '', '33001:'));
        $qr->refresh();
        $this->assertSame(['refunded', '143000.00'], [$qr->status->value, $qr->refunded_amount]);
        $this->assertSame('refunded', Payment::findOrFail($qr->payment_id)->status->value);
        $this->assertSame(1, PosRefund::where('method', 'qr')->count());
        $this->assertSame('7700001', $qr->provider_payload['refunds'][0]['reference']);
    }

    public function test_a_refused_reversal_blocks_the_refund(): void
    {
        $qr = $this->paidClick();
        Http::fake(['api.click.uz/*' => Http::response(['error_code' => -5017, 'error_note' => 'Reversal not allowed'], 400)]);

        $this->refund($qr, '143000')->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_failed')
            ->assertJsonPath('data.provider', 'click');

        $this->assertSame(0, PosRefund::count());
        $this->assertSame('paid', Payment::findOrFail($qr->payment_id)->status->value);
        $this->assertSame(['paid', '0.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);
        $this->assertFalse(QrRefunds::requested($qr));

        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $this->refund($qr, '143000')->assertStatus(409)->assertJsonPath('data.reason', 'qr_refund_failed');
        $this->assertSame(0, PosRefund::count());
    }

    public function test_click_and_payme_refund_only_the_whole_payment(): void
    {
        $qr = $this->paidClick();
        Http::fake();

        $this->refund($qr, '43000')->assertStatus(422)->assertJsonPath('data.reason', 'qr_partial_refund_unsupported')->assertJsonPath('data.left', '143000.00');
        Http::assertNothingSent();
        $this->assertSame(0, PosRefund::count());
    }

    public function test_a_payme_qr_refund_asks_receipts_cancel_with_the_flag_for_its_callback(): void
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill, 'payme')->json('data.qr.uuid'));
        $tiyin = \App\Support\Money::of($qr->amount)->toTiyin();
        $this->payme('CreateTransaction', ['id' => 'pm-r-1', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)]);
        $this->payme('PerformTransaction', ['id' => 'pm-r-1'])->assertJsonPath('result.state', 2);
        $qr->refresh();

        $flagged = null;
        Http::fake(function (HttpRequest $request) use ($qr, &$flagged) {
            $flagged = QrRefunds::requested($qr);

            return Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => ['receipt' => ['_id' => 'pm-r-1', 'state' => 50]]]);
        });

        $this->refund($qr, '143000')->assertCreated();

        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://checkout.paycom.uz/api'
            && $request['method'] === 'receipts.cancel' && $request['params'] === ['id' => 'pm-r-1']
            && ($request->header('X-Auth')[0] ?? null) === self::PAYME_MERCHANT . ':' . self::PAYME_SECRET);
        $this->assertTrue($flagged);
        $this->assertSame('refunded', $qr->refresh()->status->value);
    }

    public function test_a_sandbox_qr_refunds_in_part_and_a_manual_qr_as_before(): void
    {
        config(['qr_payments.sandbox' => true]);
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill, 'uzqr')->json('data.qr.uuid');
        $this->pos('POST', "/qr/{$uuid}/simulate-paid")->assertOk();
        $qr = $this->qrModel($uuid);

        $this->refund($qr, '43000')->assertCreated();
        $this->assertSame(['paid', '43000.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);
        $this->refund($qr, '100000')->assertCreated();
        $this->assertSame(['refunded', '143000.00'], [$qr->refresh()->status->value, $qr->refunded_amount]);

        // A static QR confirmed by hand has no request: refunded as before
        [, $manual] = $this->billed();
        $this->pay($manual, [['tender' => 'qr', 'amount' => '143000', 'reference' => 'static-1']])->assertOk();
        $payment = Payment::where('reference', 'static-1')->firstOrFail();
        $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '143000', 'reason' => 'Mijoz qaytardi', 'approval_token' => $this->approval('pos.refund')])->assertCreated();
    }

    public function test_the_x_report_splits_qr_payments_by_provider(): void
    {
        $this->paidClick('7700001');
        $this->paidClick('7700002');
        [, $manual] = $this->billed([['Choy', '1', '10000']]);
        $this->pay($manual, [['tender' => 'qr', 'amount' => '10000']])->assertOk();

        $report = $this->posAs($this->manager, 'GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');

        $this->assertSame([
            ['provider' => 'click', 'count' => 2, 'amount' => '286000.00'],
            ['provider' => 'payme', 'count' => 0, 'amount' => '0.00'],
            ['provider' => 'uzqr', 'count' => 0, 'amount' => '0.00'],
            ['provider' => 'manual', 'count' => 1, 'amount' => '10000.00'],
        ], $report['qr_providers']);
        $this->assertSame('296000.00', collect($report['by_tender'])->firstWhere('tender', 'qr')['amount']);
    }

    public function test_the_owner_reads_and_changes_the_settings_with_the_merchant_status(): void
    {
        $response = $this->owner('GET', '/owner/pos/qr-payments?branch_id=' . $this->branch->id)->assertOk();
        $this->assertSame(['click', 'payme', 'uzqr'], $response->json('data.settings.providers'));
        $this->assertSame([5, 'click', true], [$response->json('data.settings.expiry_minutes'), $response->json('data.settings.default_provider'), $response->json('data.settings.enabled')]);
        $this->assertSame([true, true, false], array_column($response->json('data.merchants'), 'configured'));
        $this->assertStringNotContainsString(self::CLICK_SECRET, $response->getContent());

        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['payme'], 'default_provider' => 'click'])->assertStatus(422);
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'expiry_minutes' => 31])->assertStatus(422);
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['payme', 'click'], 'default_provider' => 'payme', 'expiry_minutes' => 15])->assertOk()
            ->assertJsonPath('data.settings.providers', ['click', 'payme'])
            ->assertJsonPath('data.settings.default_provider', 'payme')
            ->assertJsonPath('data.settings.expiry_minutes', 15);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.qr_payments_changed']);

        // The UzQR gateway is chosen in the fiscal settings
        $this->owner('PUT', '/owner/fiscal/settings', ['fiscal_settings' => ['uzqr' => ['driver' => 'nope']]])->assertStatus(422);

        $this->profile($this->branch, 'choyxona');
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'expiry_minutes' => 10])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->owner('GET', '/owner/pos/qr-payments?branch_id=' . $this->branch->id)->assertOk()->assertJsonPath('data.settings.enabled', false);
        // Never from a terminal PIN session
        $this->app['auth']->forgetGuards();
        $this->withBearer($this->cashierToken)->getJson('/api/v1/owner/pos/qr-payments?branch_id=' . $this->branch->id)->assertForbidden();
    }

    public function test_the_uzqr_gateway_is_set_in_the_fiscal_settings(): void
    {
        $this->owner('PUT', '/owner/fiscal/settings', ['tin' => '123456789', 'tax_regime' => 'vat', 'fiscal_settings' => ['uzqr' => ['driver' => 'sandbox']]])->assertOk();
        $this->assertSame(['driver' => 'sandbox'], $this->store->refresh()->fiscal_settings['uzqr']);
        $this->owner('GET', '/owner/pos/qr-payments?branch_id=' . $this->branch->id)->assertJsonPath('data.merchants.2.configured', true)->assertJsonPath('data.merchants.2.driver', 'uzqr:sandbox');

        [, $bill] = $this->billed();
        $this->qr($bill, 'uzqr')->assertCreated()->assertJsonPath('data.qr.simulated', true);

        $this->owner('PUT', '/owner/fiscal/settings', ['fiscal_settings' => ['uzqr' => ['driver' => null]]])->assertOk();
        $this->assertArrayNotHasKey('uzqr', $this->store->refresh()->fiscal_settings);
    }
}
