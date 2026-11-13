<?php

namespace Tests\Feature\PosQr;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Events\Broadcast\QrPaymentBroadcast;
use App\Jobs\SubmitClickFiscalData;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\Store;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P6-41: the Click SHOP API (Prepare/Complete) and Payme Merchant API (CheckPerform/Create/Perform/Cancel/Check,
 * SetFiscalData) for a POS QR: the bill takes a `qr` payment once, auto-confirmed; the amount must match to the
 * tiyin; only the store's own key signs; expired, cancelled or changed requests are refused so the money is not taken;
 * the provider's fiscal submission never runs (Rizqim issues the receipt).
 */
class PosQrWebhooksTest extends TestCase
{
    use PosQrFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpQr();
        $this->openShift();
    }

    private function clickQr(): PosQrPayment
    {
        [, $bill] = $this->billed();

        return $this->qrModel($this->qr($bill, 'click')->assertCreated()->json('data.qr.uuid'));
    }

    private function paymeQr(): PosQrPayment
    {
        [, $bill] = $this->billed();

        return $this->qrModel($this->qr($bill, 'payme')->assertCreated()->json('data.qr.uuid'));
    }

    public function test_click_prepare_and_complete_pay_the_bill_with_rizqims_receipt_and_no_click_fiscal(): void
    {
        Bus::fake([SubmitClickFiscalData::class]);
        Event::fake([QrPaymentBroadcast::class]);
        $qr = $this->clickQr();

        $this->click(0, $qr)->assertExactJson([
            'click_trans_id' => '7700001',
            'merchant_trans_id' => $qr->account(),
            'merchant_prepare_id' => $qr->id,
            'error' => 0,
            'error_note' => 'Success',
        ]);
        $this->assertNotNull($qr->refresh()->held_at);

        $this->click(1, $qr)->assertJsonPath('error', 0)->assertJsonPath('error_note', 'Success')->assertJsonPath('merchant_confirm_id', $qr->id);

        $qr->refresh();
        $payment = Payment::findOrFail($qr->payment_id);
        $this->assertSame(['paid', '7700001'], [$qr->status->value, $qr->provider_transaction_id]);
        $this->assertSame(['qr', '143000.00', '7700001', $qr->bill_id, $this->cashier->id, $this->till['device']->id], [$payment->provider, $payment->amount, $payment->reference, $payment->bill_id, $payment->received_by, $payment->device_id]);
        $this->assertEquals(['qr_payment_uuid' => $qr->uuid, 'qr_provider' => 'click'], $payment->payload);
        $this->assertSame('paid', DB::table('order_bills')->where('id', $qr->bill_id)->value('status'));

        // Rizqim's sale receipt for the whole bill, the QR as its payment; Click is never asked to register one
        $receipt = $this->fiscal->document("pos-bill:{$qr->bill_uuid}:sale");
        $this->assertSame(FiscalPaymentForm::QR, $receipt->payments[0]->form);
        $this->assertSame('143000.00', $receipt->payments[0]->amount->toString());
        Bus::assertNotDispatched(SubmitClickFiscalData::class);
        $this->assertSame(0, DB::table('fiscal_receipts')->where('provider', 'click')->count());
        Event::assertDispatched(QrPaymentBroadcast::class, fn ($event) => $event->uuid === $qr->uuid && $event->broadcastAs() === 'qr.paid');
    }

    public function test_a_repeated_click_complete_is_a_no_op_with_the_same_answer(): void
    {
        $qr = $this->clickQr();
        $this->click(0, $qr);
        $this->click(1, $qr)->assertJsonPath('error', 0);

        $this->click(1, $qr)->assertJsonPath('error', 0)->assertJsonPath('error_note', 'Already confirmed');
        $this->click(0, $qr)->assertJsonPath('error', -4);
        $this->click(1, $qr, null, '7700999')->assertJsonPath('error', -4);
        $this->assertSame(1, Payment::where('provider', 'qr')->count());
        $this->assertCount(1, $this->fiscal->documents);
    }

    public function test_click_refuses_a_wrong_amount_signature_or_store(): void
    {
        $qr = $this->clickQr();

        $this->click(0, $qr, '142999.99')->assertJsonPath('error', -2);
        $this->click(0, $qr, null, '7700001', 'platform_click_secret')->assertJsonPath('error', -1);

        // Another store's own key never confirms this store's QR
        $other = Store::forceCreate(['name' => 'Boshqa', 'slug' => 'boshqa-qr', 'status' => 'active', 'payment_account_type' => 'own_account',
            'payment_settings' => ['click' => ['enabled' => true, 'service_id' => self::CLICK_SERVICE, 'merchant_id' => '1', 'secret_key' => 'other_store_key']]]);
        $this->click(0, $qr, null, '7700001', 'other_store_key')->assertJsonPath('error', -1);
        $this->assertNotNull($other->id);

        $this->click(1, $qr, null, '7700001', self::CLICK_SECRET, $qr->id + 1)->assertJsonPath('error', -6);
        $this->click(0, $qr, null, '7700001', self::CLICK_SECRET, null, ['merchant_trans_id' => 'pos_qr_' . \Illuminate\Support\Str::uuid(), 'sign_string' => 'x'])->assertJsonPath('error', -5);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
    }

    public function test_click_refuses_an_expired_or_cancelled_request_so_the_money_is_not_taken(): void
    {
        $qr = $this->clickQr();
        $this->travel(6)->minutes();
        $this->click(0, $qr)->assertJsonPath('error', -9);
        $this->assertSame('expired', $qr->refresh()->status->value);
        $this->travelBack();

        $qr = $this->clickQr();
        $this->click(0, $qr)->assertJsonPath('error', 0);
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk();
        $this->click(1, $qr)->assertJsonPath('error', -9);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
    }

    public function test_a_complete_the_bill_cannot_take_cancels_the_request(): void
    {
        $qr = $this->clickQr();
        $this->click(0, $qr)->assertJsonPath('error', 0);
        // Paid in cash meanwhile (the QR is cancelled by the bill change)
        $this->pay($qr->bill_uuid, [['tender' => 'cash', 'amount' => '143000']])->assertOk();

        $this->click(1, $qr)->assertJsonPath('error', -9);
        $this->assertSame('cancelled', $qr->refresh()->status->value);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());
    }

    public function test_a_closed_shift_refuses_the_confirmation_unless_the_till_opened_a_new_one(): void
    {
        $qr = $this->clickQr();
        \App\Models\PosShift::query()->update(['status' => 'closed', 'closed_at' => now()]);

        $this->click(0, $qr)->assertJsonPath('error', 0);
        $this->openShift();
        $this->click(1, $qr)->assertJsonPath('error', 0);
        $this->assertNotSame($qr->shift_id, $qr->refresh()->shift_id);

        $second = $this->clickQr();
        \App\Models\PosShift::query()->update(['status' => 'closed', 'closed_at' => now()]);
        $this->click(0, $second, null, '7700002');
        $this->click(1, $second, null, '7700002')->assertJsonPath('error', -9);
        $this->assertSame(['cancelled', 'shift_closed'], [$second->refresh()->status->value, $second->cancel_reason]);
    }

    public function test_payme_check_create_perform_pay_the_bill_without_payme_fiscal_detail(): void
    {
        $qr = $this->paymeQr();
        $tiyin = Money::of($qr->amount)->toTiyin();

        $check = $this->payme('CheckPerformTransaction', ['amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertOk();
        $this->assertSame(['allow' => true], $check->json('result'));

        $this->payme('CreateTransaction', ['id' => 'pm-tx-1', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)])
            ->assertJsonPath('result.state', 1)->assertJsonPath('result.create_time', 1763550000000)->assertJsonPath('result.transaction', (string) $qr->id);
        // The same transaction again: the same answer; another one: refused while this one is on it
        $this->payme('CreateTransaction', ['id' => 'pm-tx-1', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertJsonPath('result.state', 1);
        $this->payme('CreateTransaction', ['id' => 'pm-tx-2', 'time' => 1763550000001, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertJsonPath('error.code', -31099);

        $perform = $this->payme('PerformTransaction', ['id' => 'pm-tx-1'])->assertJsonPath('result.state', 2);
        $this->payme('PerformTransaction', ['id' => 'pm-tx-1'])->assertJsonPath('result.state', 2)->assertJsonPath('result.perform_time', $perform->json('result.perform_time'));
        $this->payme('CheckTransaction', ['id' => 'pm-tx-1'])->assertJsonPath('result.state', 2)->assertJsonPath('result.create_time', 1763550000000);

        $this->assertSame(1, Payment::where('provider', 'qr')->where('reference', 'pm-tx-1')->count());
        $this->assertSame('paid', DB::table('order_bills')->where('id', $qr->bill_id)->value('status'));
        $this->assertSame(FiscalPaymentForm::QR, $this->fiscal->document("pos-bill:{$qr->bill_uuid}:sale")->payments[0]->form);

        // SetFiscalData for it is answered and never recorded: the bill has Rizqim's receipt
        $this->payme('SetFiscalData', ['id' => 'pm-tx-1', 'type' => 'PERFORM', 'fiscal_data' => ['status_code' => 0, 'receipt_id' => 9, 'terminal_id' => 'EZ1', 'fiscal_sign' => '1']])
            ->assertJsonPath('result.success', true);
        $this->assertSame(0, DB::table('fiscal_receipts')->where('provider', 'payme')->count());
        $this->assertArrayNotHasKey('fiscal', Payment::where('provider', 'qr')->firstOrFail()->payload ?? []);
        $this->assertSame(0, Payment::where('provider', 'payme')->count());
    }

    public function test_payme_refuses_wrong_amount_key_and_payable_state(): void
    {
        $qr = $this->paymeQr();
        $tiyin = Money::of($qr->amount)->toTiyin();

        $this->payme('CheckPerformTransaction', ['amount' => $tiyin - 1, 'account' => $this->paymeAccount($qr)])->assertJsonPath('error.code', -31001);
        $this->payme('CheckPerformTransaction', ['amount' => $tiyin, 'account' => $this->paymeAccount($qr)], 'platform_payme_key')->assertJsonPath('error.code', -32504);
        $this->payme('CheckPerformTransaction', ['amount' => $tiyin, 'account' => ['order_id' => 'pos_qr_' . \Illuminate\Support\Str::uuid()]], 'platform_payme_key')->assertJsonPath('error.code', -31050);

        // A Click request is not a Payme account
        [, $bill] = $this->billed();
        $click = $this->qrModel($this->qr($bill, 'click')->json('data.qr.uuid'));
        $this->payme('CheckPerformTransaction', ['amount' => Money::of($click->amount)->toTiyin(), 'account' => $this->paymeAccount($click)], 'platform_payme_key')->assertJsonPath('error.code', -32504);

        $this->travel(6)->minutes();
        $this->payme('CheckPerformTransaction', ['amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertJsonPath('error.code', -31051);
        $this->assertSame('expired', $qr->refresh()->status->value);
    }

    public function test_payme_perform_after_the_cashier_cancelled_is_refused_and_cancel_states(): void
    {
        $qr = $this->paymeQr();
        $tiyin = Money::of($qr->amount)->toTiyin();
        $this->payme('CreateTransaction', ['id' => 'pm-tx-9', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)])->assertJsonPath('result.state', 1);
        $this->pos('DELETE', "/qr/{$qr->uuid}")->assertOk();

        $this->payme('PerformTransaction', ['id' => 'pm-tx-9'])->assertJsonPath('error.code', -31008);
        $this->payme('CancelTransaction', ['id' => 'pm-tx-9', 'reason' => 3])->assertJsonPath('result.state', -1);
        $this->payme('CheckTransaction', ['id' => 'pm-tx-9'])->assertJsonPath('result.state', -1);
        $this->assertSame(0, Payment::where('provider', 'qr')->count());

        // Payme cancels a pending one itself (its timeout): cancelled with its reason
        $other = $this->paymeQr();
        $this->payme('CreateTransaction', ['id' => 'pm-tx-10', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($other)]);
        $this->payme('CancelTransaction', ['id' => 'pm-tx-10', 'reason' => 4])->assertJsonPath('result.state', -1);
        $this->assertSame(['cancelled', 'payme_cancel:4'], [$other->refresh()->status->value, $other->cancel_reason]);
        $this->payme('CheckTransaction', ['id' => 'pm-tx-10'])->assertJsonPath('result.reason', 4);
    }

    public function test_payme_cancel_of_a_performed_transaction_only_while_rizqims_refund_asks(): void
    {
        $qr = $this->paymeQr();
        $tiyin = Money::of($qr->amount)->toTiyin();
        $this->payme('CreateTransaction', ['id' => 'pm-tx-5', 'time' => 1763550000000, 'amount' => $tiyin, 'account' => $this->paymeAccount($qr)]);
        $this->payme('PerformTransaction', ['id' => 'pm-tx-5'])->assertJsonPath('result.state', 2);

        $this->payme('CancelTransaction', ['id' => 'pm-tx-5', 'reason' => 5])->assertJsonPath('error.code', -31007);
        $this->assertSame('paid', $qr->refresh()->status->value);

        Cache::put('pos-qr-refund:' . $qr->uuid, true, 60);
        $cancel = $this->payme('CancelTransaction', ['id' => 'pm-tx-5', 'reason' => 5])->assertJsonPath('result.state', -2);
        $this->payme('CancelTransaction', ['id' => 'pm-tx-5', 'reason' => 5])->assertJsonPath('result.cancel_time', $cancel->json('result.cancel_time'));
        $this->payme('CheckTransaction', ['id' => 'pm-tx-5'])->assertJsonPath('result.state', -2)->assertJsonPath('result.reason', 5);
    }

    public function test_online_order_webhooks_are_untouched(): void
    {
        // A regular order reference still goes to the order logic (unknown order: Click -5, Payme -31050)
        $this->postJson('/api/v1/payments/click/webhook', ['merchant_trans_id' => (string) \Illuminate\Support\Str::uuid(), 'action' => 0])->assertJsonPath('error', -5);
        $this->payme('CheckPerformTransaction', ['amount' => 100, 'account' => ['order_id' => (string) \Illuminate\Support\Str::uuid()]], 'platform_payme_key')->assertJsonPath('error.code', -31050);
    }
}
