<?php

namespace Tests\Feature\PosQr;

use App\Domain\Pos\QrPayments\QrPaymentService;
use App\Domain\Pos\QrPayments\UzQr\SandboxUzQrGateway;
use App\Events\Broadcast\QrPaymentBroadcast;
use App\Models\PosQrPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-41/P6-42: the till's QR: made for what the bill owes (Click/Payme checkout link, UzQR EMV string), one pending
 * per bill, cancelled by the cashier, by a newer QR or a bill change, expired, polled; the module gate, the merchant
 * account check, the sandbox drivers and their simulated confirmation, the bootstrap section.
 */
class PosQrTillTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpQr();
        $this->openShift();
    }

    public function test_a_click_qr_is_the_store_merchants_checkout_link_for_what_the_bill_owes(): void
    {
        [, $bill] = $this->billed();

        $response = $this->qr($bill, 'click')->assertCreated();

        $qr = $response->json('data.qr');
        $this->assertSame(['pending', 'click', '143000.00', $bill], [$qr['status'], $qr['provider'], $qr['amount'], $qr['bill_uuid']]);
        $this->assertStringStartsWith('https://my.click.uz/services/pay?service_id=' . self::CLICK_SERVICE . '&merchant_id=22001&amount=143000.00&transaction_param=pos_qr_' . $qr['uuid'], $qr['payload']);
        $this->assertSame(['payload' => $qr['payload'], 'label' => "Click orqali to'lang: 143000.00 so'm", 'provider' => 'click'], $qr['display']);
        $this->assertFalse($qr['simulated']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.qr_created']);

        $payme = $this->qr($bill, 'payme', ['amount' => '43000'])->assertCreated()->json('data.qr');
        $decoded = base64_decode(substr($payme['payload'], strlen('https://checkout.paycom.uz/')));
        $this->assertStringStartsWith('m=' . self::PAYME_MERCHANT . ';ac.order_id=pos_qr_' . $payme['uuid'] . ';a=4300000;', $decoded);
    }

    public function test_one_pending_qr_per_bill_a_new_one_cancels_the_old(): void
    {
        Event::fake([QrPaymentBroadcast::class]);
        [, $bill] = $this->billed();
        $first = $this->qr($bill)->json('data.qr.uuid');

        $second = $this->qr($bill, 'payme')->assertCreated()->json('data.qr.uuid');

        $this->assertSame(['cancelled', 'replaced'], [$this->qrModel($first)->status->value, $this->qrModel($first)->cancel_reason]);
        $this->assertSame('pending', $this->qrModel($second)->status->value);
        Event::assertDispatched(QrPaymentBroadcast::class, fn ($event) => $event->uuid === $first && $event->broadcastAs() === 'qr.cancelled'
            && $event->broadcastOn()[0]->name === "private-pos-branch.{$this->branch->id}");
        $this->pos('GET', "/bills/{$bill}/qr")->assertOk()->assertJsonCount(2, 'data.qrs')->assertJsonPath('data.qrs.0.uuid', $second);
    }

    public function test_the_cashier_cancels_a_qr_and_a_paid_one_cannot_be_cancelled(): void
    {
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');

        $this->pos('DELETE', "/qr/{$uuid}?reason=" . urlencode('Mijoz naqd berdi'))->assertOk()
            ->assertJsonPath('data.qr.status', 'cancelled')->assertJsonPath('data.qr.cancel_reason', 'cashier');
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.qr_cancelled']);
        // Again: as it is
        $this->pos('DELETE', "/qr/{$uuid}")->assertOk()->assertJsonPath('data.qr.status', 'cancelled');

        $paid = $this->qr($bill)->json('data.qr.uuid');
        $this->click(0, $this->qrModel($paid))->assertJsonPath('error', 0);
        $this->click(1, $this->qrModel($paid))->assertJsonPath('error', 0);
        $this->pos('DELETE', "/qr/{$paid}")->assertStatus(409)->assertJsonPath('data.reason', 'qr_already_paid');
    }

    public function test_the_poll_shows_it_paid_with_its_payment(): void
    {
        [$order, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');
        $qr = $this->qrModel($uuid);
        $this->click(0, $qr);
        $this->click(1, $qr);

        $response = $this->pos('GET', "/qr/{$uuid}")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertNotNull($response->json('data.qr.payment_uuid'));
        $this->pos('GET', "/orders/{$order->uuid}/bills")->assertJsonPath('data.bills.0.status', 'paid')
            ->assertJsonPath('data.bills.0.payments.0.tender', 'qr')
            ->assertJsonPath('data.bills.0.payments.0.reference', '7700001');
    }

    public function test_it_expires_after_the_branch_expiry_and_reading_it_expires_it(): void
    {
        Event::fake([QrPaymentBroadcast::class]);
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');
        $this->assertSame(now()->addMinutes(5)->toIso8601String(), $this->qrModel($uuid)->expires_at->toIso8601String());

        $this->travel(6)->minutes();
        $this->pos('GET', "/qr/{$uuid}")->assertOk()->assertJsonPath('data.qr.status', 'expired');
        Event::assertDispatched(QrPaymentBroadcast::class, fn ($event) => $event->uuid === $uuid && $event->broadcastAs() === 'qr.expired');

        $other = $this->qr($bill)->json('data.qr.uuid');
        $this->travel(6)->minutes();
        $this->artisan('pos:expire-qr-payments')->assertSuccessful();
        $this->assertSame('expired', $this->qrModel($other)->status->value);
    }

    public function test_a_qr_the_provider_started_paying_waits_for_it_past_the_expiry(): void
    {
        [, $bill] = $this->billed();
        $qr = $this->qrModel($this->qr($bill)->json('data.qr.uuid'));
        $this->click(0, $qr)->assertJsonPath('error', 0);

        $this->travel(7)->minutes();
        $this->assertSame(0, app(QrPaymentService::class)->expireDue());
        $this->click(1, $qr)->assertJsonPath('error', 0)->assertJsonPath('error_note', 'Success');
    }

    public function test_a_change_of_the_bill_cancels_its_pending_qr(): void
    {
        [$order, $bill] = $this->billed([['Osh', '1', '100000'], ['Choy', '1', '43000']]);
        $uuid = $this->qr($bill)->json('data.qr.uuid');

        // Part of it paid in cash: the QR for the whole bill no longer fits
        $this->pay($bill, [['tender' => 'cash', 'amount' => '43000']])->assertOk();
        $this->assertSame(['cancelled', 'bill_changed'], [$this->qrModel($uuid)->status->value, $this->qrModel($uuid)->cancel_reason]);

        // A QR for the rest, then the order is billed again (split): the old bill's QR goes
        $rest = $this->qr($bill)->assertCreated()->json('data.qr');
        $this->assertSame('100000.00', $rest['amount']);
        $this->pos('DELETE', "/qr/{$rest['uuid']}");

        [$order2, $bill2] = $this->billed();
        $third = $this->qr($bill2)->json('data.qr.uuid');
        $this->bill($order2, 'equal', ['parts' => 2])->assertCreated();
        $this->assertSame(['cancelled', 'bill_changed'], [$this->qrModel($third)->status->value, $this->qrModel($third)->cancel_reason]);
    }

    public function test_a_discount_after_the_bill_cancels_its_pending_qr(): void
    {
        [$order, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');

        $this->pos('POST', "/orders/{$order->uuid}/discount", ['version' => (int) $order->refresh()->version, 'kind' => 'percent', 'value' => '5', 'reason' => 'Doimiy mijoz'])->assertOk();

        $this->assertSame(['cancelled', 'bill_changed'], [$this->qrModel($uuid)->status->value, $this->qrModel($uuid)->cancel_reason]);
        $this->click(0, $this->qrModel($uuid))->assertJsonPath('error', -9);
    }

    public function test_refusals_before_the_customer_pays(): void
    {
        [, $bill] = $this->billed();

        $this->qr($bill, 'click', ['amount' => '200000'])->assertStatus(422)->assertJsonPath('data.reason', 'overpayment')->assertJsonPath('data.due', '143000.00');
        $this->qr($bill, 'bitcoin')->assertStatus(422);

        // Not offered by the branch
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['payme']])->assertOk();
        $this->qr($bill, 'click')->assertStatus(422)->assertJsonPath('data.reason', 'qr_provider_disabled');

        // The store's own Payme merchant is not set up (a POS QR is never the platform's)
        $this->ownMerchant(true, false);
        $this->qr($bill, 'payme')->assertStatus(409)->assertJsonPath('data.reason', 'provider_not_configured');
        $this->store->forceFill(['payment_account_type' => 'platform_account'])->save();
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['click']])->assertOk();
        $this->qr($bill, 'click')->assertStatus(409)->assertJsonPath('data.reason', 'provider_not_configured');
        // UzQR without a gateway
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['uzqr']])->assertOk();
        $this->qr($bill, 'uzqr')->assertStatus(409)->assertJsonPath('data.reason', 'provider_not_configured');
        $this->assertSame(0, PosQrPayment::count());
    }

    public function test_the_module_gates_making_a_qr_but_never_reading_or_cancelling_one(): void
    {
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');

        $this->profile($this->branch, 'restoran', ['qr_payments' => false]);
        $this->qr($bill)->assertStatus(403)->assertJsonPath('data.reason', 'module_disabled');
        $this->pos('GET', "/qr/{$uuid}")->assertOk();
        $this->pos('DELETE', "/qr/{$uuid}")->assertOk()->assertJsonPath('data.qr.status', 'cancelled');
    }

    public function test_it_needs_an_open_shift_and_take_payment_and_stays_in_its_branch(): void
    {
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');

        $waiterToken = $this->pinSession($this->till, $this->waiter);
        $this->pos('POST', "/bills/{$bill}/qr", ['provider' => 'click'], $waiterToken)->assertStatus(403);

        $other = $this->makeStaff(\App\Enums\UserRole::CASHIER, $this->otherBranch);
        $this->posAs($other, 'GET', "/qr/{$uuid}")->assertNotFound();
    }

    public function test_sandbox_drivers_and_their_simulated_confirmation(): void
    {
        config(['qr_payments.sandbox' => true]);
        $this->ownMerchant(false, false);
        [$order, $bill] = $this->billed();

        $click = $this->qr($bill, 'click')->assertCreated()->json('data.qr');
        $this->assertSame('https://qr-sandbox.invalid/click?account=pos_qr_' . $click['uuid'] . '&amount=143000.00', $click['payload']);
        $this->assertTrue($click['simulated']);

        $uzqr = $this->qr($bill, 'uzqr')->assertCreated()->json('data.qr');
        $this->assertStringStartsWith('000201010212', $uzqr['payload']);
        $this->assertStringContainsString('5303860' . '5409143000.00' . '5802UZ', $uzqr['payload']);
        $this->assertSame(SandboxUzQrGateway::crc(substr($uzqr['payload'], 0, -4)), substr($uzqr['payload'], -4));
        $this->assertSame('uzqr:sandbox', $this->qrModel($uzqr['uuid'])->driver);

        $this->pos('POST', "/qr/{$uzqr['uuid']}/simulate-paid")->assertOk()->assertJsonPath('data.qr.status', 'paid');
        $this->assertSame('paid', $order->refresh()->payment_status->value);
        $this->assertSame(\App\Domain\Fiscal\FiscalPaymentForm::QR, $this->fiscal->document("pos-bill:{$bill}:sale")->payments[0]->form);

        config(['qr_payments.simulate' => false]);
        $this->pos('POST', "/qr/{$click['uuid']}/simulate-paid")->assertNotFound();
    }

    public function test_a_real_providers_qr_is_never_simulated(): void
    {
        [, $bill] = $this->billed();
        $uuid = $this->qr($bill)->json('data.qr.uuid');

        $this->pos('POST', "/qr/{$uuid}/simulate-paid")->assertStatus(409)->assertJsonPath('data.reason', 'qr_not_simulated');
    }

    public function test_the_bootstrap_offers_the_configured_providers_default_first(): void
    {
        $this->owner('PUT', '/owner/pos/qr-payments', ['branch_id' => $this->branch->id, 'providers' => ['click', 'payme', 'uzqr'], 'default_provider' => 'payme', 'expiry_minutes' => 10])->assertOk();

        $this->pos('GET', '/bootstrap')->assertOk()->assertJsonPath('data.qr_payments', [
            'enabled' => true,
            'providers' => ['payme', 'click'],
            'default_provider' => 'payme',
            'expiry_minutes' => 10,
        ]);

        $this->profile($this->branch, 'choyxona');
        $this->pos('GET', '/bootstrap')->assertOk()->assertJsonPath('data.qr_payments.enabled', false);
    }
}
