<?php

namespace Tests\Feature\Paylov;

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\RefundPaylovPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Paylov\PaylovPayments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\AdminOrders\AdminOrdersFixtures;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Review 1 (money) of the Paylov wave: a refund Paylov may already have made is never flagged for a second, manual one,
 * and a `payed` transaction settles only the order Paylov lists it for.
 */
class PaylovRefundSafetyTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;
    use PaylovFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->store->forceFill([
            'payment_account_type' => 'platform_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true]]),
        ])->save();
        $this->setUpPaylov();
    }

    private function payment(string $txn): Payment
    {
        return Payment::where('provider', 'paylov')->where('provider_transaction_id', $txn)->sole();
    }

    /**
     * Paylov did the first payment/cancel but its answer was lost (timeout); the second is refused as
     * transaction_not_available_for_cancel and the read-back of the status has a blip: the payment is flagged for a
     * manual refund although the money already went back, so the team gives it back a second time.
     */
    public function test_a_refund_paylov_already_made_is_never_flagged_for_a_manual_refund_on_a_passing_read_failure(): void
    {
        $order = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());
        $this->assertSame(PaymentStatus::PAID, $this->payment($txn)->status);

        Bus::fake([RefundPaylovPayment::class]);
        app(OrderStatusService::class)->transition($order->fresh(), OrderStatus::CANCELED, null, ['cancel_reason' => CancelReason::STORE_CLOSED->value]);
        $id = (int) $this->payment($txn)->id;

        // 1st try: Paylov cancels the transaction, the answer never arrives
        $this->paylovCancelError = 'connection';
        $this->assertSame('retry', app(PaylovPayments::class)->refund($id));

        // 2nd try: already cancelled at Paylov; the getTransactions read-back times out once
        $this->paylovCancelError = 'transaction_not_available_for_cancel';
        $this->paylovStatus = 'connection';
        $outcome = app(PaylovPayments::class)->refund($id);

        $payment = $this->payment($txn);
        $this->assertEmpty($payment->payload['manual_refund_required'] ?? null, 'flagged for a manual refund of money Paylov already returned');
        $this->assertSame('retry', $outcome);
    }

    /**
     * getTransactions answers for the transaction id: a `payed` transaction of another order (account.order_id) with
     * the same amount settles this order.
     */
    public function test_a_payed_transaction_of_another_order_does_not_settle_this_one(): void
    {
        $order = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $other = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();

        $this->paylovRoutes['/merchant/getTransactions/'] = fn (Request $request) => Http::response(['result' => ['totalTransaction' => 1, 'transactions' => [[
            'id' => $txn,
            'amount' => 50000.0,
            'amount_in_tiyin' => 5000000,
            'status' => 'payed',
            'account' => ['order_id' => $other->uuid],
            'via_link' => true,
            'ofd' => [],
        ]]], 'error' => null]);

        $this->perform($order, $txn)->assertExactJson(self::answer());

        $this->assertNotSame(PaymentStatus::PAID, $order->fresh()->payment_status, 'order settled by a transaction Paylov lists for another order');
        // Not this order's money: closed (never refunded from here: that would give back the other order's payment)
        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::FAILED, $payment->status);
        $this->assertSame('transaction_of_another_order', $payment->payload['paylov']['rejected_reason'] ?? null);
        $this->assertCount(0, $this->sentTo('/merchant/payment/cancel/'));
        $this->assertNotSame(PaymentStatus::PAID, $other->fresh()->payment_status);

        // A transaction listed without its account: not settled on the amount alone, checked again
        $third = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();
        $this->paylovRoutes['/merchant/getTransactions/'] = fn (Request $request) => Http::response(['result' => ['totalTransaction' => 1, 'transactions' => [[
            'id' => $txn, 'amount' => 50000.0, 'amount_in_tiyin' => 5000000, 'status' => 'payed', 'via_link' => true,
        ]]], 'error' => null]);
        $this->perform($third, $txn)->assertExactJson(self::answer());
        $this->assertSame(PaymentStatus::PENDING, $this->payment($txn)->status);
        $this->assertSame('unconfirmed', $this->payment($txn)->payload['paylov']['state']);
        $this->assertSame(PaymentStatus::PENDING, $third->fresh()->payment_status);
    }

    /**
     * The last of the tries: Paylov is read before any manual flag. Cancelled at Paylov: recorded. No answer: never
     * flagged (alerted once, asked again). Paylov still holds the money: flagged.
     */
    public function test_a_refund_out_of_tries_reads_paylov_before_any_manual_flag(): void
    {
        Bus::fake([RefundPaylovPayment::class]);
        $ids = [];
        foreach (['cancel', 'connection', 'payed'] as $status) {
            $order = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
            $txn = (string) Str::uuid();
            $this->paylovStatus = 'payed';
            $this->perform($order, $txn)->assertExactJson(self::answer());
            app(OrderStatusService::class)->transition($order->fresh(), OrderStatus::CANCELED, null, ['cancel_reason' => CancelReason::STORE_CLOSED->value]);
            $payment = $this->payment($txn);
            $payload = $payment->payload;
            $payload['paylov']['refund']['attempts'] = PaylovPayments::REFUND_MAX_ATTEMPTS - 1;
            $payment->forceFill(['payload' => $payload])->save();
            $ids[$status] = (int) $payment->id;
        }
        $this->paylovCancelError = 'connection';
        Log::spy();

        $this->paylovStatus = 'cancel';
        $this->assertSame('refunded', app(PaylovPayments::class)->refund($ids['cancel']));
        $this->assertSame(PaymentStatus::REFUNDED, Payment::find($ids['cancel'])->status);
        $this->assertArrayNotHasKey('manual_refund_required', Payment::find($ids['cancel'])->payload);

        $this->paylovStatus = 'connection';
        foreach (range(1, 2) as $try) {
            $this->assertSame('retry', app(PaylovPayments::class)->refund($ids['connection']), "try {$try}");
        }
        $this->assertArrayNotHasKey('manual_refund_required', Payment::find($ids['connection'])->payload);
        $this->assertSame(PaymentStatus::PAID, Payment::find($ids['connection'])->status);
        Log::shouldHaveReceived('critical')->withArgs(fn ($message) => str_contains($message, 'Paylov refund'))->once();

        $this->paylovStatus = 'payed';
        $this->assertSame('manual', app(PaylovPayments::class)->refund($ids['payed']));
        $this->assertTrue(Payment::find($ids['payed'])->payload['manual_refund_required']);
    }
}
