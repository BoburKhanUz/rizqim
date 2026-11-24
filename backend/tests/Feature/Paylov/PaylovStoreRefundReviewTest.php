<?php

namespace Tests\Feature\Paylov;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Jobs\RefundPaylovFiscalReceipt;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Admin\Attention\AttentionCollector;
use App\Services\Admin\Orders\RefundRequestService;
use App\Services\Order\OrderStatusService;
use App\Services\Payment\Paylov\PaylovPayments;
use App\Services\Payment\StoreRefundReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Feature\AdminOrders\AdminOrdersFixtures;
use Tests\TestCase;

/**
 * Owner decision (payment decisions, decision 2): a refund of a DELIVERED, store-owned Paylov order made in the store's
 * own Paylov cabinet goes on hold for a manager, as Payme's CancelTransaction on the store's own cashbox does
 * (StoreRefundReview, PaymentStoreRefundReviewTest). The cancel webhook is still verified with Paylov first
 * (PaylovPayments::verifyCancel); once Paylov confirms it the payment is refunded and the order's payment status with
 * it, the order stays delivered, and RIZQIM's commission and ledger are untouched until a manager approves (two
 * members: the commission reversed once through the order's refund path) or dismisses (nothing reversed).
 *
 * Kept as before: RIZQIM's own payment/cancel (a refund RIZQIM asked for), platform-collected orders, orders still in
 * progress.
 *
 * Numbers: a 50 000 so'm order, commission 5 000 (10 %) booked on the store's debt at delivery.
 */
class PaylovStoreRefundReviewTest extends TestCase
{
    use RefreshDatabase;
    use AdminOrdersFixtures;
    use PaylovFixtures;

    /** @var array<string, string> the store's own Paylov account */
    private array $own = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLifecycleStore();
        $this->setUpPaylov();
        $this->own = self::makeAccount();
        $this->store->forceFill([
            'payment_account_type' => 'own_account',
            'credit_limit' => -1000000,
            'payment_settings' => array_merge($this->store->payment_settings ?? [], ['paylov' => ['enabled' => true] + $this->own]),
        ])->save();
    }

    private function ledgerRows(): int
    {
        return StoreTransaction::count() + StoreWalletTransaction::count();
    }

    private function payment(string $txn): Payment
    {
        return Payment::where('provider', 'paylov')->where('provider_transaction_id', $txn)->sole();
    }

    /**
     * A STORE-owned Paylov order of 50 000 so'm, paid into the store's own account and delivered: 5 000 on the debt.
     */
    private function deliveredOrder(string $txn): Order
    {
        $order = $this->paylovOrder($this->store, ['payment_owner' => 'STORE', 'branch_id' => $this->branch->id]);
        $this->perform($order, $txn, account: $this->own)->assertExactJson(self::answer());
        $this->assertSame(PaymentStatus::PAID, $order->fresh()->payment_status);
        foreach ([OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }
        $this->assertSame('5000.00', (string) $this->store->fresh()->debt_balance);

        return $order->fresh();
    }

    /** The store refunds in its own Paylov cabinet; Paylov then reports the transaction cancelled and sends the webhook. */
    private function cabinetRefund(Order $order, string $txn): void
    {
        $this->paylovStatus = 'cancel';
        $this->cancelWebhook($order, $txn, account: $this->own)->assertOk()->assertJsonPath('result.status', '0');
    }

    public function test_a_cabinet_refund_of_a_delivered_store_owned_order_is_held_and_keeps_the_commission(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        $rows = $this->ledgerRows();
        $reads = count($this->sentTo('/merchant/getTransactions/'));

        Log::spy();
        $this->cabinetRefund($order, $txn);
        // Paylov's retry changes nothing
        $this->cancelWebhook($order, $txn, account: $this->own)->assertOk();

        // Still verified with Paylov before anything changed
        $this->assertGreaterThan($reads, count($this->sentTo('/merchant/getTransactions/')));

        // The commission stays: 5 000 on the debt, no ledger row, no reversal
        $this->assertSame('5000.00', (string) $this->store->fresh()->debt_balance);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(0, StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->count());
        $this->assertSame(0, OrderLedgerReversal::where('order_id', $order->id)->count());

        // The customer and the store see the money given back; the food was handed over, the order stays delivered
        $order->refresh();
        $payment = $this->payment($txn);
        $this->assertSame(PaymentStatus::REFUNDED, $payment->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertSame('webhook', $payment->payload['paylov']['cancelled_by']);

        // Recorded as a store-initiated refund waiting for a manager, as Payme's
        $this->assertNotNull($order->store_refund_review_at);
        $review = StoreRefundReview::of($payment);
        $this->assertSame('pending', $review['status']);
        $this->assertSame('paylov', $review['provider']);
        $this->assertNull($review['reason']);
        $this->assertTrue($payment->payload['refunded_after_delivery']);
        $this->assertSame(1, AuditLog::where('action', 'order.store_refund_held')->where('model_id', $order->id)->count());
        Log::shouldHaveReceived('critical')->once()->withArgs(fn ($message, $context = []) => ($context['provider'] ?? null) === 'paylov'
            && ($context['order_id'] ?? null) === $order->id && !str_contains((string) json_encode([$message, $context]), $this->own['callback_password']));

        // RIZQIM never asked Paylov to cancel anything, nothing is flagged for a manual refund
        $this->assertCount(0, $this->sentTo('/merchant/payment/cancel/'));
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);

        // Bugun → Diqqat talab qiladi lists it, named by its provider
        $group = collect(app(AttentionCollector::class)->collect($this->makeMember(PlatformRole::FINANCE), 5))->keyBy('kind')['store_refund_review'];
        $this->assertSame(1, $group['count']);
        $this->assertSame('/orders/' . $order->uuid, $group['items'][0]['href']);
        $this->assertStringContainsString('Paylov', $group['items'][0]['subtitle']);

        // No refund request can be asked for money Paylov already gave back; the held payment counts in what delivery
        // booked the commission on and nothing of it as given back by a request (RefundRequestService::deliveryShareOf)
        $this->assertTrue(app(RefundRequestService::class)->balance($order)['refundable']->isZero());
        $share = RefundRequestService::deliveryShareOf($order, Payment::where('order_id', $order->id)->get(), []);
        $this->assertSame(['50000.00', '0.00'], [$share['paid']->toString(), $share['refunded']->toString()]);

        // The admin card shows the review with its provider
        $member = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('GET', "/orders/{$order->uuid}", $this->confirmedSession($member))->assertOk()
            ->assertJsonPath('data.store_refund_review.provider', 'paylov')
            ->assertJsonPath('data.store_refund_review.status', 'pending')
            ->assertJsonPath('data.store_refund_review.reason_code', null)
            ->assertJsonPath('data.actions.can_decide_store_refund', true);
    }

    public function test_a_held_order_can_never_be_paid_again(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        $this->cabinetRefund($order, $txn);
        $this->paylovStatus = 'payed';

        $this->assertFalse(PaylovPayments::isPayable($order->fresh()));
        $this->check($order->fresh(), account: $this->own)->assertExactJson(self::answer('+1', 'order_not_payable'));

        // A perform anyway is recorded as money to give back, never the order's payment
        $again = (string) Str::uuid();
        $this->perform($order->fresh(), $again, account: $this->own)->assertExactJson(self::answer('+1', 'order_not_payable'));
        $this->assertSame('order_not_payable', $this->payment($again)->payload['paylov']['refused']);
        $order->refresh();
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertSame('5000.00', (string) $this->store->fresh()->debt_balance);
    }

    public function test_approving_takes_two_members_and_reverses_the_commission_once(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        $this->cabinetRefund($order, $txn);

        $proposer = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $this->confirmedSession($proposer), ['reason' => 'Paylov kabinetida tekshirildi'])
            ->assertOk()->assertJsonPath('data.store_refund_review.status', 'pending')
            ->assertJsonPath('data.store_refund_review.proposed_by', $proposer->id);
        // A proposal books nothing; the proposer cannot confirm it
        $this->assertSame('5000.00', (string) $this->store->fresh()->debt_balance);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $this->confirmedSession($proposer), ['reason' => 'Yana'])
            ->assertStatus(403)->assertJsonPath('code', 'four_eyes');

        $member = $this->makeMember(PlatformRole::FINANCE);
        $token = $this->confirmedSession($member);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, ['reason' => 'Paylov kabinetida tekshirildi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded')
            ->assertJsonPath('data.store_refund_review.status', 'approved');

        // 5 000 commission reversed once: the debt 5 000 → 0, one reversal record
        $order->refresh();
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame('0.00', (string) $this->store->fresh()->debt_balance);
        $this->assertSame(1, OrderLedgerReversal::where('order_id', $order->id)->count());
        $reversal = OrderLedgerReversal::where('order_id', $order->id)->sole();
        $this->assertTrue($reversal->is_full);
        $this->assertSame(0, \App\Support\Money::of((string) $reversal->components['commission_debt'])->compareTo(\App\Support\Money::of('5000')));
        $payment = $this->payment($txn);
        $this->assertSame('approved', StoreRefundReview::of($payment)['status']);
        // Paylov already gave the money back: not asked again, nothing flagged for a manual refund
        $this->assertArrayNotHasKey('manual_refund_required', $payment->payload);
        $this->assertCount(0, $this->sentTo('/merchant/payment/cancel/'));

        // Once: a second decision finds nothing, Paylov's repeat changes nothing
        $rows = $this->ledgerRows();
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/approve", $token, ['reason' => 'Yana bir marta'])
            ->assertStatus(409)->assertJsonPath('code', 'no_store_refund_review');
        $this->cancelWebhook($order, $txn, account: $this->own)->assertOk();
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame('0.00', (string) $this->store->fresh()->debt_balance);
    }

    public function test_dismissing_reverses_nothing(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        $this->cabinetRefund($order, $txn);
        $rows = $this->ledgerRows();

        $member = $this->makeMember(PlatformRole::FINANCE);
        $this->adminJson('POST', "/orders/{$order->uuid}/store-refund-review/dismiss", $this->confirmedSession($member), ['reason' => "Ovqatxona o'z hisobidan qaytargan"])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.store_refund_review.status', 'dismissed');

        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertSame(PaymentStatus::REFUNDED, $order->payment_status);
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame('5000.00', (string) $this->store->fresh()->debt_balance);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(0, OrderLedgerReversal::where('order_id', $order->id)->count());
        $this->assertSame('dismissed', StoreRefundReview::of($this->payment($txn))['status']);
        // A dismissed order still takes no payment again
        $this->assertFalse(PaylovPayments::isPayable($order));
    }

    public function test_the_refund_receipt_follows_the_money_back_as_payme_does(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        // The sale receipt was recorded through Paylov (RegisterPaylovFiscalReceipt)
        $payment = $this->payment($txn);
        $payment->forceFill(['payload' => array_merge($payment->payload, ['fiscal' => ['provider' => 'paylov', 'status' => 'recorded', 'receipt_id' => 173723]])])->save();

        Bus::fake([RefundPaylovFiscalReceipt::class]);
        $this->cabinetRefund($order, $txn);

        $this->assertNotNull($order->fresh()->store_refund_review_at);
        Bus::assertDispatched(RefundPaylovFiscalReceipt::class, fn (RefundPaylovFiscalReceipt $job) => $job->paymentId === (int) $payment->id);
    }

    public function test_a_refund_rizqim_asked_paylov_for_is_not_held(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        // RIZQIM's own payment/cancel is under way (a refund request's share, RefundRequestService::spread): Paylov's
        // cancel webhook for it arrives first
        $payment = $this->payment($txn);
        $payload = $payment->payload;
        $payload['paylov']['refund'] = ['status' => PaylovPayments::REFUND_REQUESTED, 'reason' => 'refund_request_1', 'requested_at' => now()->toIso8601String()];
        $payment->forceFill(['payload' => $payload])->save();

        $this->cabinetRefund($order, $txn);

        $order->refresh();
        $this->assertNull($order->store_refund_review_at);
        $this->assertNull(StoreRefundReview::of($this->payment($txn)));
        // As before: the order is refunded through RIZQIM's refund path
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
        $this->assertSame('0.00', (string) $this->store->fresh()->debt_balance);
    }

    public function test_rizqims_own_payment_cancel_is_not_held(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);
        $payment = $this->payment($txn);
        $payload = $payment->payload;
        $payload['paylov']['refund'] = ['status' => PaylovPayments::REFUND_REQUESTED, 'reason' => 'refund_request_1', 'requested_at' => now()->toIso8601String()];
        $payment->forceFill(['payload' => $payload])->save();

        $this->assertSame('refunded', app(PaylovPayments::class)->refund((int) $payment->id));

        $order->refresh();
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame('merchant', $this->payment($txn)->payload['paylov']['cancelled_by']);
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
    }

    public function test_a_cancel_of_a_store_owned_order_still_in_progress_is_not_held(): void
    {
        $order = $this->paylovOrder($this->store, ['payment_owner' => 'STORE', 'branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn, account: $this->own)->assertExactJson(self::answer());

        $this->cabinetRefund($order, $txn);

        $order->refresh();
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame(PaymentStatus::PENDING, $order->payment_status);
        $this->assertSame(0, $this->ledgerRows());
    }

    public function test_a_platform_collected_delivered_order_is_refunded_as_before(): void
    {
        $this->store->forceFill(['payment_account_type' => 'platform_account'])->save();
        $order = $this->paylovOrder($this->store, ['branch_id' => $this->branch->id]);
        $txn = (string) Str::uuid();
        $this->perform($order, $txn)->assertExactJson(self::answer());
        foreach ([OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            app(OrderStatusService::class)->transition($order->fresh(), $status, null);
        }

        $this->paylovStatus = 'cancel';
        $this->cancelWebhook($order, $txn)->assertOk();

        $order->refresh();
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame(OrderStatus::REFUNDED, $order->status);
    }

    public function test_a_cancel_paylov_does_not_confirm_changes_nothing(): void
    {
        $txn = (string) Str::uuid();
        $order = $this->deliveredOrder($txn);

        // Paylov still reports the money in the store's account: the webhook alone is no proof
        $this->cancelWebhook($order, $txn, account: $this->own)->assertOk();

        $order->refresh();
        $this->assertNull($order->store_refund_review_at);
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame(PaymentStatus::PAID, $this->payment($txn)->status);
    }
}
