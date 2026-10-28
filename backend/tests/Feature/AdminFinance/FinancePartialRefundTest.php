<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Enums\SubscriptionInvoiceStatus;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Order\OrderStatusService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P4-23 (wave 1 leftover): a delivered order refunded in parts through refund requests takes back the same share of
 * the store's commission and settlement (pro-rata, LedgerService::reverseDeliveredOrderShare), never twice; parts that
 * add up to everything paid reverse exactly what a full refund does; the order's refund afterwards reverses the rest.
 */
class FinancePartialRefundTest extends TestCase
{
    use FinanceFixtures;
    use RefreshDatabase;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->v2Store('Qisman');
    }

    /**
     * Click, 100 000 paid to RIZQIM, 15 000 commission: delivery books the commission as debt (the wallet was empty),
     * the settlement pays it and 85 000 reaches the wallet.
     */
    private function deliveredClickOrder(): Order
    {
        $order = $this->platformOrder($this->store, 'click', '100000.00', '15000.00');
        $this->ledger()->processOrderDelivered($order);
        $this->assertSame(['0.00', '85000.00'], [$this->store->fresh()->debt_balance, $this->store->fresh()->wallet_balance]);

        return $order->refresh();
    }

    /**
     * @return array<string, mixed> the approved request
     */
    private function refund(Order $order, string $amount, string $requester, string $approver, array $headers = []): array
    {
        $request = $this->financeJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => $amount, 'reason' => 'Qisman qaytarish'])
            ->assertCreated()->json('data');

        return $this->financeJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiqlandi'], $headers)->assertOk()->json('data');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function members(): array
    {
        return [$this->confirmedSession($this->makeMember(PlatformRole::OPERATOR)), $this->confirmedSession($this->makeMember(PlatformRole::FINANCE))];
    }

    private function commissionReversed(Order $order): Money
    {
        return Money::of(StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFUND->value)->where('notes', 'like', 'Commission reversed%')->sum('amount'))->negated();
    }

    public function test_two_parts_summing_to_the_whole_reverse_exactly_what_a_full_refund_does(): void
    {
        $order = $this->deliveredClickOrder();
        [$requester, $approver] = $this->members();

        // A third (with tiyin): 15 000 × 33 333,33 / 100 000 = 4 999,9995 → 5 000,00; 85 000 × the same → 28 333,33
        $first = $this->refund($order, '33333.33', $requester, $approver);
        $this->assertSame(['commission' => '5000.00', 'settlement' => '33333.33', 'added_to_debt' => '0.00'], $first['execution']['ledger_reversal']);
        $this->assertSame('5000.00', $this->commissionReversed($order)->toString());
        $store = $this->store->fresh();
        $this->assertSame(['0.00', '56666.67'], [$store->debt_balance, $store->wallet_balance]);

        // The rest: everything paid is given back now, so exactly the rest of every part is reversed
        $second = $this->refund($order, '66666.67', $requester, $approver);
        $this->assertSame(['commission' => '10000.00', 'settlement' => '66666.67', 'added_to_debt' => '0.00'], $second['execution']['ledger_reversal']);
        $this->assertSame('15000.00', $this->commissionReversed($order)->toString());
        $store = $this->store->fresh();
        $this->assertSame(['0.00', '0.00'], [$store->debt_balance, $store->wallet_balance]);
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertSame(2, OrderLedgerReversal::where('order_id', $order->id)->count());
        $this->assertTrue(OrderLedgerReversal::where('order_id', $order->id)->latest('id')->first()->is_full);

        // The order's own refund path afterwards: nothing is left to reverse
        $rows = StoreTransaction::count() + StoreWalletTransaction::count();
        app(OrderStatusService::class)->refund($order->fresh(), null, 'Tizim');
        $this->assertSame($rows, StoreTransaction::count() + StoreWalletTransaction::count());
        $this->assertSame(['0.00', '0.00'], [$this->store->fresh()->debt_balance, $this->store->fresh()->wallet_balance]);
    }

    public function test_the_same_part_is_never_reversed_twice(): void
    {
        $order = $this->deliveredClickOrder();
        [$requester, $approver] = $this->members();
        $request = $this->financeJson('POST', "/orders/{$order->uuid}/refund-requests", $requester, ['amount' => '20000', 'reason' => 'Qisman'])->assertCreated()->json('data');
        $approve = fn () => $this->financeJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiq'], ['Idempotency-Key' => 'part-1'])->assertOk();

        $approve();
        $this->assertSame(['0.00', '68000.00'], [$this->store->fresh()->debt_balance, $this->store->fresh()->wallet_balance]);
        $rows = StoreTransaction::count() + StoreWalletTransaction::count();

        // The same key, the same request without a key, and the ledger asked again for the same request: nothing moves
        $approve()->assertHeader('Idempotent-Replayed', 'true');
        $this->financeJson('POST', "/refund-requests/{$request['id']}/approve", $approver, ['reason' => 'Tasdiq'])->assertOk();
        $again = $this->ledger()->reverseDeliveredOrderShare($order->fresh(), (int) $request['id'], Money::of('20000'), Money::of('100000'));
        $this->assertTrue(collect($again)->every->isZero());

        $this->assertSame($rows, StoreTransaction::count() + StoreWalletTransaction::count());
        $this->assertSame('68000.00', $this->store->fresh()->wallet_balance);
        $this->assertSame(1, OrderLedgerReversal::where('order_id', $order->id)->count());
    }

    public function test_a_full_refund_after_a_part_reverses_only_the_rest(): void
    {
        $order = $this->deliveredClickOrder();
        [$requester, $approver] = $this->members();
        $this->refund($order, '40000', $requester, $approver);
        $this->assertSame('51000.00', $this->store->fresh()->wallet_balance);

        $reversed = app(\App\Services\Finance\LedgerService::class)->reverseDeliveredOrder($order->fresh());

        $this->assertSame('9000.00', $reversed['commission']->toString());
        $this->assertSame('60000.00', $reversed['settlement']->toString());
        $this->assertSame('15000.00', $this->commissionReversed($order)->toString());
        $this->assertSame(['0.00', '0.00'], [$this->store->fresh()->debt_balance, $this->store->fresh()->wallet_balance]);
    }

    public function test_a_part_of_an_order_that_paid_an_invoice_reopens_its_share_of_the_invoice(): void
    {
        $this->store = $this->storeWithTwoInvoices('Qisman obuna');
        $order = $this->platformOrder($this->store, 'payme', '100000.00');
        $this->ledger()->settlePlatformOrder($order);
        [$september] = $this->invoicesOf($this->store);
        $this->assertSame('100000.00', $september->paid_amount);
        [$requester, $approver] = $this->members();

        $this->refund($order, '25000', $requester, $approver);

        $september->refresh();
        $this->assertSame('75000.00', $september->paid_amount);
        $this->assertSame(SubscriptionInvoiceStatus::ISSUED, $september->status);
        $this->assertSame(['223000.00', '223000.00'], [$this->store->fresh()->debt_balance, $this->store->fresh()->subscription_debt]);
    }

    /**
     * Review W2: a part approved while delivery's booking had failed found nothing on the ledger to reverse; the retry
     * then booked the whole order. The successful retry now reverses every such part (cumulative, each once).
     */
    public function test_parts_approved_while_the_booking_failed_are_reversed_when_the_retry_books_it(): void
    {
        $order = $this->platformOrder($this->store, 'click', '100000.00', '15000.00');
        $order->forceFill(['settlement_failed_at' => now(), 'settlement_attempts' => 1])->save();
        [$requester, $approver] = $this->members();

        $first = $this->refund($order->refresh(), '20000', $requester, $approver);
        $this->refund($order->refresh(), '30000', $requester, $approver);
        $this->assertSame(0, StoreTransaction::count() + StoreWalletTransaction::count());
        $this->assertTrue($first['execution']['ledger_reversal_deferred']);

        $recovery = app(\App\Services\Finance\SettlementRecoveryService::class);
        $this->assertTrue($recovery->retry($order->id));

        // Half went back to the customer: the store keeps half of 100 000 − 15 000
        $store = $this->store->fresh();
        $this->assertSame('42500.00', Money::of($store->wallet_balance)->minus($store->debt_balance)->toString());
        $this->assertSame('7500.00', $this->commissionReversed($order)->toString());
        $this->assertSame(2, OrderLedgerReversal::where('order_id', $order->id)->count());
        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame('3000.00', \App\Models\OrderRefundRequest::find($first['id'])->execution['ledger_reversal']['commission']);

        // Asking again changes nothing; a later part still reverses only its own share
        $rows = StoreTransaction::count() + StoreWalletTransaction::count();
        $this->assertFalse($recovery->retry($order->id));
        $this->assertSame($rows, StoreTransaction::count() + StoreWalletTransaction::count());
        $this->refund($order->refresh(), '10000', $requester, $approver);
        $store = $this->store->fresh();
        $this->assertSame('34000.00', Money::of($store->wallet_balance)->minus($store->debt_balance)->toString());
    }

    public function test_a_part_approved_after_the_retry_booked_the_order_is_reversed_at_once(): void
    {
        $order = $this->platformOrder($this->store, 'click', '100000.00', '15000.00');
        $order->forceFill(['settlement_failed_at' => now(), 'settlement_attempts' => 1])->save();
        $this->assertTrue(app(\App\Services\Finance\SettlementRecoveryService::class)->retry($order->id));
        [$requester, $approver] = $this->members();

        $approved = $this->refund($order->refresh(), '50000', $requester, $approver);

        $this->assertSame('7500.00', $approved['execution']['ledger_reversal']['commission']);
        $store = $this->store->fresh();
        $this->assertSame('42500.00', Money::of($store->wallet_balance)->minus($store->debt_balance)->toString());
    }

    public function test_a_cancelled_order_refund_touches_no_ledger(): void
    {
        $order = $this->platformOrder($this->store, 'click', '50000.00');
        $order->update(['status' => OrderStatus::CANCELED]);
        [$requester, $approver] = $this->members();

        $approved = $this->refund($order, '10000', $requester, $approver);

        $this->assertNull($approved['execution']['ledger_reversal']);
        $this->assertSame(0, OrderLedgerReversal::count());
    }
}
