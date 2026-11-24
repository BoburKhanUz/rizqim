<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Order\OrderStatusService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Security review F1: the share of a delivered order's commission a partial refund reverses is (what was refunded after
 * delivery) / (what was paid when the commission was booked). Payme transactions paid and cancelled while the order was
 * still in progress (each leaves a refunded payment) take no part in it: before, every one of them counted as both paid
 * and refunded, so a store with its own cashbox key could forge k pay/cancel cycles and have a 1 UZS refund reverse
 * k/(k+1) of the commission.
 */
class FinanceRefundShareBaseTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    private Store $store;
    private string $key;
    private int $trx = 0;

    protected function setUp(): void
    {
        parent::setUp();
        // Built at runtime: no merchant key is written in the code base
        $this->key = 'own-' . Str::random(32);
        config(['services.payme.secret_key' => 'platform-' . Str::random(32)]);
        $this->store = Store::forceCreate(['name' => 'Own Cashbox', 'status' => 'active', 'payment_account_type' => 'own_account', 'credit_limit' => -1000000,
            'payment_settings' => ['payme' => ['enabled' => true, 'merchant_id' => 'own', 'secret_key' => $this->key]]]);
    }

    private function payme(string $method, array $params)
    {
        return $this->postJson('/api/v1/payments/payme/webhook', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], [
            'Authorization' => 'Basic ' . base64_encode('Paycom:' . $this->key),
        ]);
    }

    private function storeOrder(): Order
    {
        return $this->financeOrder($this->store, [
            'status' => OrderStatus::NEW, 'payment_method' => 'payme', 'payment_owner' => 'STORE',
            'payment_status' => PaymentStatus::PENDING, 'total_price' => 50000, 'commission_amount' => 5000,
        ]);
    }

    private function pay(Order $order): string
    {
        $id = 'trx-' . (++$this->trx);
        $this->payme('CreateTransaction', ['id' => $id, 'time' => 1764000000000 + $this->trx, 'amount' => 5000000, 'account' => ['order_id' => $order->uuid]])
            ->assertJsonPath('result.state', 1);
        $this->payme('PerformTransaction', ['id' => $id])->assertJsonPath('result.state', 2);

        return $id;
    }

    private function cancel(string $id): void
    {
        $this->payme('CancelTransaction', ['id' => $id, 'reason' => 5])->assertJsonPath('result.state', -2);
    }

    private function deliver(Order $order): Order
    {
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING, OrderStatus::DELIVERED] as $status) {
            if ($order->fresh()->status !== $status && $order->fresh()->status !== OrderStatus::DELIVERED) {
                app(OrderStatusService::class)->transition($order->fresh(), $status, null);
            }
        }
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame('5000.00', $this->store->fresh()->debt_balance, 'delivery booked the commission as debt');

        return $order->fresh();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function members(): array
    {
        return [$this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), $this->confirmedSession($this->makeMember(PlatformRole::FINANCE))];
    }

    /**
     * @return array<string, mixed> the approved request
     */
    private function refund(Order $order, string $amount): array
    {
        [$requester, $approver] = $this->members();
        $request = $this->financeJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => $amount, 'reason' => 'Qaytarish'])
            ->assertCreated()->json('data');

        return $this->financeJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk()->json('data');
    }

    private function commissionReversed(Order $order): string
    {
        return Money::of(StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->sum('amount'))
            ->negated()->plus(StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->sum('amount'))
            ->toString();
    }

    /** The review's probe: X = 50 000, C = 5 000, k = 4 forged cycles, a 1 UZS refund reversed 4 000,02. */
    public function test_forged_pay_cancel_cycles_before_delivery_do_not_inflate_a_partial_refunds_reversal(): void
    {
        $order = $this->storeOrder();
        foreach (range(1, 4) as $cycle) {
            $this->cancel($this->pay($order));
        }
        $this->pay($order);
        $order = $this->deliver($order);
        $this->assertSame(4, Payment::where('order_id', $order->id)->where('status', PaymentStatus::REFUNDED->value)->count());

        $approved = $this->refund($order, '1');

        // 5 000 × 1 / 50 000
        $this->assertSame('0.10', $approved['execution']['ledger_reversal']['commission']);
        $this->assertSame('0.10', $this->commissionReversed($order));
        $this->assertSame('4999.90', $this->store->fresh()->debt_balance);
    }

    public function test_an_honest_payment_after_a_cancel_reverses_exactly_its_share(): void
    {
        $order = $this->storeOrder();
        $this->cancel($this->pay($order));
        $this->pay($order);
        $order = $this->deliver($order);

        $approved = $this->refund($order, '10000');

        $this->assertSame('1000.00', $approved['execution']['ledger_reversal']['commission']);
        $this->assertSame('4000.00', $this->store->fresh()->debt_balance);
    }

    public function test_several_partial_refunds_summing_to_the_total_reverse_the_commission_exactly_once(): void
    {
        $order = $this->storeOrder();
        $this->cancel($this->pay($order));
        $this->cancel($this->pay($order));
        $this->pay($order);
        $order = $this->deliver($order);

        $reversed = [];
        foreach (['10000', '15000', '25000'] as $amount) {
            $reversed[] = $this->refund($order->fresh(), $amount)['execution']['ledger_reversal']['commission'];
        }

        $this->assertSame(['1000.00', '1500.00', '2500.00'], $reversed);
        $this->assertSame('5000.00', $this->commissionReversed($order));
        $this->assertSame('0.00', $this->store->fresh()->debt_balance);
        $this->assertTrue(OrderLedgerReversal::where('order_id', $order->id)->latest('id')->first()->is_full);

        // The order's own refund afterwards finds nothing left to reverse
        app(OrderStatusService::class)->refund($order->fresh(), null, 'Tizim');
        $this->assertSame('5000.00', $this->commissionReversed($order));
    }

    public function test_a_full_refund_after_forged_cycles_reverses_the_commission_once_and_never_more(): void
    {
        $order = $this->storeOrder();
        foreach (range(1, 4) as $cycle) {
            $this->cancel($this->pay($order));
        }
        $this->pay($order);
        $order = $this->deliver($order);

        $approved = $this->refund($order, '50000');

        // Everything paid at delivery given back: the order's own refund path, as for an order paid once
        $this->assertSame('order_refunded', $approved['execution']['mode']);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame('5000.00', $this->commissionReversed($order));
        $this->assertSame('0.00', $this->store->fresh()->debt_balance);
        $this->assertSame('0.00', app(\App\Services\Admin\Orders\RefundRequestService::class)->balance($order->fresh())['refundable']->toString());
    }

    /**
     * A payment cancelled before this fix carries no `refunded_before_delivery` marker: it is told apart by its cancel
     * time against the order's first delivery.
     */
    public function test_a_legacy_payment_refunded_before_delivery_without_the_marker_takes_no_part_either(): void
    {
        $this->travelTo(now()->subHours(2));
        $order = $this->storeOrder();
        foreach (range(1, 2) as $cycle) {
            $this->cancel($this->pay($order));
        }
        $this->pay($order);
        foreach (Payment::where('order_id', $order->id)->where('status', PaymentStatus::REFUNDED->value)->get() as $payment) {
            $payment->forceFill(['payload' => array_diff_key($payment->payload, ['refunded_before_delivery' => true])])->save();
        }
        $this->travelBack();
        $order = $this->deliver($order);

        $approved = $this->refund($order, '25000');

        $this->assertSame('2500.00', $approved['execution']['ledger_reversal']['commission']);
    }

    public function test_a_part_approved_while_the_booking_failed_takes_the_same_share_when_the_retry_books_it(): void
    {
        $order = $this->storeOrder();
        foreach (range(1, 3) as $cycle) {
            $this->cancel($this->pay($order));
        }
        $this->pay($order);
        foreach ([OrderStatus::ACCEPTED, OrderStatus::PREPARING] as $status) {
            if ($order->fresh()->status !== $status) {
                app(OrderStatusService::class)->transition($order->fresh(), $status, null);
            }
        }
        // Delivered while the ledger could not book it (the retry books it later)
        $order->fresh()->forceFill(['status' => OrderStatus::DELIVERED, 'settlement_failed_at' => now(), 'settlement_attempts' => 1])->save();

        $approved = $this->refund($order->fresh(), '5000');
        $this->assertTrue($approved['execution']['ledger_reversal_deferred']);
        $this->assertTrue(app(\App\Services\Finance\SettlementRecoveryService::class)->retry($order->id));

        // 5 000 × 5 000 / 50 000
        $this->assertSame('500.00', OrderRefundRequest::find($approved['id'])->execution['ledger_reversal']['commission']);
        $this->assertSame('4500.00', $this->store->fresh()->debt_balance);
    }
}
