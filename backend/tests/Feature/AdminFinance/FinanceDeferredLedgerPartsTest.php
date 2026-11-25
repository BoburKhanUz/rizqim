<?php

namespace Tests\Feature\AdminFinance;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PlatformRole;
use App\Enums\StoreTransactionType;
use App\Events\PaymentPaid;
use App\Jobs\RetryFailedSettlementsJob;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\OrderLedgerReversal;
use App\Models\OrderRefundRequest;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\AdminFixtures;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\Feature\Referral\ReferralOrders;
use Tests\TestCase;

/**
 * A delivered order's booking failed (settlement_failed_at) and a part of the ledger booking was still deferred when a
 * refund share was approved: e.g. the late payment's settlement was booked but the commission was not (the booking kept
 * failing), so the approval reversed only the settlement's share and recorded a reversal row. When the deferred part is
 * booked later (RetryFailedSettlementsJob), every refund share approved for the order is applied to it exactly once:
 * the outstanding reversal per part (what the approved shares make of what is booked now, less what the order's
 * reversal rows already took back), never twice and never missed.
 *
 * Everything goes through the real services: the delivery (OrderStatusService), the late payment (MarkOrderAsPaid),
 * the refund requests (HTTP, two members), the retry job. The failure is a real PostgreSQL error: a trigger refuses
 * every commission row while it is installed (as a schema mismatch in a deploy window would), everything else books.
 */
class FinanceDeferredLedgerPartsTest extends TestCase
{
    // Installs and drops a trigger (DDL): runs as the schema owner when the roles are split
    use \Tests\Concerns\UsesOwnerConnection;
    use AdminFixtures;
    use OrderLifecycleFixtures;
    use RefreshDatabase;
    use ReferralOrders;

    private string $requester;

    private string $approver;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        config(['orders.settlement_retry_max_attempts' => 12]);
        $this->setUpLifecycleStore();
        $this->requester = $this->confirmedSession($this->makeMember(PlatformRole::OPERATOR));
        $this->approver = $this->confirmedSession($this->makeMember(PlatformRole::FINANCE));
    }

    private function failCommissions(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION test_refuse_commission() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'commission ledger unavailable (test)'; END $$;
            CREATE TRIGGER test_refuse_commission_wallet BEFORE INSERT ON store_wallet_transactions
                FOR EACH ROW WHEN (NEW.type = 'commission') EXECUTE FUNCTION test_refuse_commission();
            CREATE TRIGGER test_refuse_commission_debt BEFORE INSERT ON store_transactions
                FOR EACH ROW WHEN (NEW.type = 'commission') EXECUTE FUNCTION test_refuse_commission();
            SQL);
    }

    private function repairCommissions(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER test_refuse_commission_wallet ON store_wallet_transactions;
            DROP TRIGGER test_refuse_commission_debt ON store_transactions;
            DROP FUNCTION test_refuse_commission();
            SQL);
    }

    /**
     * 100 000, 10 000 commission, paid to RIZQIM's Click only after delivery. The commission cannot be booked at
     * delivery nor when the payment lands; the late payment's settlement is booked (all 100 000 to the empty wallet).
     */
    private function platformOrderWithDeferredCommission(): Order
    {
        $this->failCommissions();
        $order = $this->deliveredOrder('platform_click_unpaid', ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertSame(['0.00', '0.00'], $this->balances());

        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($payment));

        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertSame(2, (int) $order->settlement_attempts);
        $this->assertSame(['0.00', '100000.00'], $this->balances());
        $this->assertSame('0.00', $this->netCommission($order));

        return $order;
    }

    /** The same order paid into the store's own Click account: no settlement, only the commission (deferred). */
    private function storeOrderWithDeferredCommission(): Order
    {
        $this->failCommissions();
        $order = $this->deliveredOrder('store_click', ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertSame(['0.00', '0.00'], $this->balances());

        return $order;
    }

    /** @return array<string, mixed> the approved request */
    private function refund(Order $order, string $amount): array
    {
        $request = $this->adminCall('POST', "/orders/{$order->uuid}/refund-requests", $this->requester, ['amount' => $amount, 'reason' => 'Qisman qaytarish'])
            ->assertCreated()->json('data');

        return $this->adminCall('POST', "/refund-requests/{$request['id']}/approve", $this->approver, ['reason' => 'Tasdiqlandi'])->assertOk()->json('data');
    }

    private function adminCall(string $method, string $uri, string $token, array $data = []): \Illuminate\Testing\TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->json($method, '/api/v1/admin' . $uri, $data);
    }

    private function retry(): void
    {
        app()->call([new RetryFailedSettlementsJob, 'handle']);
    }

    /** @return array{0: string, 1: string} [debt, wallet] */
    private function balances(): array
    {
        $store = $this->store->fresh();

        return [(string) $store->debt_balance, (string) $store->wallet_balance];
    }

    /** Commission booked on the order (wallet and debt) less every commission reversal: what the store is charged. */
    private function netCommission(Order $order): string
    {
        $wallet = StoreWalletTransaction::where('order_id', $order->id);
        $debt = StoreTransaction::where('order_id', $order->id);
        $booked = Money::of((string) (clone $wallet)->where('type', StoreTransactionType::COMMISSION->value)->sum('amount'))->negated()
            ->plus((string) (clone $debt)->where('type', StoreTransactionType::COMMISSION->value)->sum('amount'));
        $returned = Money::of((string) (clone $wallet)->where('type', StoreTransactionType::REFUND->value)->where('notes', 'like', 'Commission returned%')->sum('amount'))
            ->plus(Money::of((string) (clone $debt)->where('type', StoreTransactionType::REFUND->value)->where('notes', 'like', 'Commission reversed%')->sum('amount'))->negated());

        return $booked->minus($returned)->toString();
    }

    /** Commission rows booked for the order (each booking is one row per ledger it touched). */
    private function commissionRows(Order $order): int
    {
        return StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->count()
            + StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->count();
    }

    /**
     * The order's reversal rows: [request id, is_full, commission taken back, settlement taken back].
     *
     * @return list<array{0: int|null, 1: bool, 2: string, 3: string}>
     */
    private function reversals(Order $order): array
    {
        return OrderLedgerReversal::where('order_id', $order->id)->orderBy('id')->get()->map(fn (OrderLedgerReversal $row) => [
            $row->order_refund_request_id !== null ? (int) $row->order_refund_request_id : ($row->components['catch_up_for_request'] ?? null),
            (bool) $row->is_full,
            Money::of((string) ($row->components['commission_wallet'] ?? '0'))->plus((string) ($row->components['commission_debt'] ?? '0'))->toString(),
            Money::of((string) ($row->components['settlement_wallet'] ?? '0'))->plus((string) ($row->components['payoff_commission'] ?? '0'))->toString(),
        ])->all();
    }

    private function ledgerRows(): int
    {
        return StoreTransaction::count() + StoreWalletTransaction::count() + OrderLedgerReversal::count();
    }

    /**
     * The reviewer's scenario: half refunded while the commission was deferred and the settlement booked. The store
     * keeps 50 000 of the money and pays 5 000 of the commission: 45 000 in the wallet, never 40 000.
     */
    public function test_a_part_refunded_while_only_the_settlement_was_booked_gives_back_its_commission_share_on_retry(): void
    {
        $order = $this->platformOrderWithDeferredCommission();

        $approved = $this->refund($order, '50000');
        $this->assertTrue($approved['execution']['ledger_reversal_deferred']);
        $this->assertSame(['commission' => '0.00', 'settlement' => '50000.00', 'added_to_debt' => '0.00'], $approved['execution']['ledger_reversal']);
        $this->assertSame(['0.00', '50000.00'], $this->balances());
        $this->assertSame([[(int) $approved['id'], false, '0.00', '50000.00']], $this->reversals($order));

        $this->repairCommissions();
        $this->retry();

        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame('5000.00', $this->netCommission($order));
        $this->assertSame(['0.00', '45000.00'], $this->balances());
        $this->assertSame([[(int) $approved['id'], false, '0.00', '50000.00'], [(int) $approved['id'], false, '5000.00', '0.00']], $this->reversals($order));
        // The request says what it took back in all, and what of it only the retry could
        $execution = OrderRefundRequest::find($approved['id'])->execution;
        $this->assertSame(['commission' => '5000.00', 'settlement' => '50000.00', 'added_to_debt' => '0.00'], $execution['ledger_reversal']);
        // Review L2: when and why the rest was caught up (no more `ledger_reversal_on_retry: true` for every catch-up)
        $this->assertNotNull($execution['ledger_reversal_caught_up_at'] ?? null);
        $this->assertSame('retry', $execution['ledger_reversal_catch_up_reason'] ?? null);
        $this->assertArrayNotHasKey('ledger_reversal_on_retry', $execution);

        // Retried again (the job, the late payment's event replayed): nothing moves
        $rows = $this->ledgerRows();
        $this->retry();
        event(new PaymentPaid(Payment::where('order_id', $order->id)->sole()));
        $this->assertFalse(app(\App\Services\Finance\SettlementRecoveryService::class)->retry($order->id));
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(['0.00', '45000.00'], $this->balances());
        $this->assertSame(1, $this->commissionRows($order));

        // A later part takes only its own share of both (75 000 back in all: 7 500 of the commission, 75 000 of the money)
        $later = $this->refund($order->fresh(), '25000');
        $this->assertSame(['commission' => '2500.00', 'settlement' => '25000.00', 'added_to_debt' => '0.00'], $later['execution']['ledger_reversal']);
        $this->assertSame('2500.00', $this->netCommission($order));
        $this->assertSame(['0.00', '22500.00'], $this->balances());
    }

    public function test_two_parts_refunded_while_the_commission_was_deferred_each_give_back_their_share_once(): void
    {
        $order = $this->platformOrderWithDeferredCommission();

        $first = $this->refund($order, '20000');
        $second = $this->refund($order->fresh(), '30000');
        $this->assertSame(['0.00', '50000.00'], $this->balances());

        $this->repairCommissions();
        $this->retry();
        $this->retry();

        // 20 000 → 2 000, 30 000 → 3 000 of the commission: 5 000 charged, 45 000 left
        $this->assertSame('5000.00', $this->netCommission($order));
        $this->assertSame(['0.00', '45000.00'], $this->balances());
        $this->assertSame([
            [(int) $first['id'], false, '0.00', '20000.00'],
            [(int) $second['id'], false, '0.00', '30000.00'],
            [(int) $first['id'], false, '2000.00', '0.00'],
            [(int) $second['id'], false, '3000.00', '0.00'],
        ], $this->reversals($order));
        $this->assertSame('2000.00', OrderRefundRequest::find($first['id'])->execution['ledger_reversal']['commission']);
        $this->assertSame('3000.00', OrderRefundRequest::find($second['id'])->execution['ledger_reversal']['commission']);
        $this->assertSame(1, $this->commissionRows($order));
    }

    /**
     * Parts that add up to everything paid, approved while the commission was deferred: the retry books the
     * commission and gives all of it back, so the store is charged nothing and holds nothing of the order.
     */
    public function test_parts_adding_up_to_everything_paid_before_the_retry_charge_no_commission(): void
    {
        $order = $this->platformOrderWithDeferredCommission();

        $first = $this->refund($order, '50000');
        $second = $this->refund($order->fresh(), '50000');
        $this->assertSame(['0.00', '0.00'], $this->balances());
        $this->assertSame([[(int) $first['id'], false, '0.00', '50000.00'], [(int) $second['id'], true, '0.00', '50000.00']], $this->reversals($order));

        $this->repairCommissions();
        $this->retry();
        $rows = $this->ledgerRows();
        $this->retry();
        $this->assertSame($rows, $this->ledgerRows());

        $this->assertSame('0.00', $this->netCommission($order));
        $this->assertSame(['0.00', '0.00'], $this->balances());
        $this->assertSame(OrderStatus::DELIVERED, $order->fresh()->status);
        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame([
            [(int) $first['id'], false, '0.00', '50000.00'],
            [(int) $second['id'], true, '0.00', '50000.00'],
            [(int) $first['id'], false, '5000.00', '0.00'],
            [(int) $second['id'], false, '5000.00', '0.00'],
        ], $this->reversals($order));
        $this->assertSame(1, OrderLedgerReversal::where('order_id', $order->id)->where('is_full', true)->count());
    }

    /** The whole order refunded at once before the retry: its own refund path; the retry books nothing. */
    public function test_the_whole_order_refunded_before_the_retry_books_nothing_later(): void
    {
        $order = $this->platformOrderWithDeferredCommission();

        $approved = $this->refund($order, '100000');
        $this->assertSame('order_refunded', $approved['execution']['mode']);
        $this->assertSame(OrderStatus::REFUNDED, $order->fresh()->status);
        $this->assertSame(['0.00', '0.00'], $this->balances());

        $this->repairCommissions();
        $rows = $this->ledgerRows();
        $this->retry();
        $this->retry();

        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(0, $this->commissionRows($order));
        $this->assertSame('0.00', $this->netCommission($order));
        $this->assertSame(['0.00', '0.00'], $this->balances());
        $this->assertNull($order->fresh()->settlement_failed_at);
    }

    /** A store-owned order (only the commission is booked): a part approved before the retry is taken off it once. */
    public function test_a_store_owned_order_refunded_in_part_before_the_retry_is_charged_its_share(): void
    {
        $order = $this->storeOrderWithDeferredCommission();

        $approved = $this->refund($order, '50000');
        $this->assertTrue($approved['execution']['ledger_reversal_deferred']);
        $this->assertSame([], $this->reversals($order));

        $this->repairCommissions();
        $this->retry();
        $this->retry();

        // 10 000 on the debt (the wallet was empty), 5 000 of it given back
        $this->assertSame('5000.00', $this->netCommission($order));
        $this->assertSame(['5000.00', '0.00'], $this->balances());
        $this->assertSame([[(int) $approved['id'], false, '5000.00', '0.00']], $this->reversals($order));
    }

    public function test_a_store_owned_order_refunded_in_parts_to_the_whole_before_the_retry_is_charged_nothing(): void
    {
        $order = $this->storeOrderWithDeferredCommission();

        $first = $this->refund($order, '50000');
        $second = $this->refund($order->fresh(), '50000');

        $this->repairCommissions();
        $this->retry();
        $this->retry();

        $this->assertSame('0.00', $this->netCommission($order));
        $this->assertSame(['0.00', '0.00'], $this->balances());
        $this->assertSame([[(int) $first['id'], false, '5000.00', '0.00'], [(int) $second['id'], true, '5000.00', '0.00']], $this->reversals($order));
    }

    /** The normal path: booked at delivery, a part refunded after it reverses its share at once (unchanged). */
    public function test_a_part_refunded_after_a_normal_booking_is_unchanged(): void
    {
        $order = $this->deliveredOrder('platform_click', ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->assertNull($order->settlement_failed_at);
        $this->assertSame(['0.00', '90000.00'], $this->balances());

        $approved = $this->refund($order, '50000');

        $this->assertSame(['commission' => '5000.00', 'settlement' => '50000.00', 'added_to_debt' => '0.00'], $approved['execution']['ledger_reversal']);
        $this->assertFalse($approved['execution']['ledger_reversal_deferred']);
        $this->assertSame('5000.00', $this->netCommission($order));
        $this->assertSame(['0.00', '45000.00'], $this->balances());
        $this->assertSame([[(int) $approved['id'], false, '5000.00', '50000.00']], $this->reversals($order));
    }

    /**
     * With a referral: a part refunded does not stop the bonus (ReferralService::qualifiesForReferralBonus reads the
     * order's total), so the retry grants it once with the commission net of the part.
     */
    public function test_a_referred_order_refunded_in_part_before_the_retry_earns_the_bonus_once(): void
    {
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
        $order = $this->platformOrderWithDeferredCommission();
        $this->assertSame([], $this->rewards());

        $this->refund($order, '50000');
        $this->repairCommissions();
        $this->retry();
        $this->retry();

        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertSame('5000.00', $this->netCommission($order));
        $this->assertSame(['0.00', '45000.00'], $this->balances());
    }

    /**
     * With a referral: parts that reached everything paid before the retry leave no bonus (LedgerService::
     * grantReferralReward: an order with a full reversal never earns it) and no commission.
     */
    public function test_a_referred_order_refunded_in_parts_to_the_whole_before_the_retry_earns_no_bonus(): void
    {
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
        $order = $this->platformOrderWithDeferredCommission();

        $this->refund($order, '50000');
        $this->refund($order->fresh(), '50000');
        $this->repairCommissions();
        $this->retry();
        $this->retry();

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertSame('0.00', $this->netCommission($order));
        $this->assertSame(['0.00', '0.00'], $this->balances());
    }
    /** Commission and settlement rows refused, then allowed again (nothing at all is booked while it is installed). */
    private function deliveredWithNothingBooked(string $how): Order
    {
        $this->failCommissions();
        $order = $this->deliveredOrder($how, ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertSame(['0.00', '0.00'], $this->balances());

        return $order;
    }

    /**
     * An approval written before P4-23 (6ce13ffb, 28.10.2026): the part was given back to the customer but the ledger
     * was never touched, and the request says nothing about it (no `execution.ledger_reversal`), no reversal row.
     */
    private function legacyApprovedRequest(Order $order, string $amount): OrderRefundRequest
    {
        $payment = Payment::where('order_id', $order->id)->sole();

        return OrderRefundRequest::forceCreate([
            'order_id' => $order->id,
            'store_id' => $order->store_id,
            'amount' => $amount,
            'status' => OrderRefundRequest::APPROVED,
            'reason' => 'Eski qaytarish',
            'decided_at' => now()->subMonth(),
            'execution' => [
                'mode' => 'manual',
                'order_status_before' => OrderStatus::DELIVERED->value,
                'order_status_after' => OrderStatus::DELIVERED->value,
                'payment_method' => $order->payment_method,
                'automatic_amount' => '0.00',
                'manual_amount' => $amount,
                'allocations' => [['payment_id' => $payment->id, 'provider' => $payment->provider, 'amount' => $amount, 'kind' => 'manual']],
            ],
        ]);
    }

    private function replayPaymentPaid(Order $order): void
    {
        event(new PaymentPaid(Payment::where('order_id', $order->id)->sole()->fresh(), true));
    }

    /** Review M1 (a): a fully booked order with a legacy approval: a replayed PaymentPaid moves nothing. */
    public function test_a_legacy_approval_on_a_fully_booked_order_is_not_reversed_by_a_replayed_payment(): void
    {
        $order = $this->deliveredOrder('platform_click', ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->assertSame(['0.00', '90000.00'], $this->balances());
        $legacy = $this->legacyApprovedRequest($order, '50000');

        $rows = $this->ledgerRows();
        $this->replayPaymentPaid($order);
        app(\App\Services\Finance\SettlementRecoveryService::class)->bookAfterPayment($order->id);

        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(['0.00', '90000.00'], $this->balances());
        $this->assertSame('10000.00', $this->netCommission($order));
        $this->assertArrayNotHasKey('ledger_reversal', $legacy->fresh()->execution);
    }

    /**
     * Review M1, the legacy rule: even when the catch-up runs (a retry of a failed booking), a request without
     * `execution.ledger_reversal` and without a reversal row is never reversed by it (it predates the mechanism).
     */
    public function test_a_legacy_approval_is_not_reversed_by_the_retry_of_a_failed_booking(): void
    {
        $order = $this->deliveredWithNothingBooked('platform_click');
        $this->legacyApprovedRequest($order, '50000');

        $this->repairCommissions();
        $this->retry();

        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame([], $this->reversals($order));
        $this->assertSame('10000.00', $this->netCommission($order));
        $this->assertSame(['0.00', '90000.00'], $this->balances());
    }

    /**
     * Review M1 (b): a normal booked order with a part reversed at its approval: a replayed PaymentPaid moves nothing.
     */
    public function test_a_replayed_payment_on_a_booked_order_moves_nothing(): void
    {
        $order = $this->deliveredOrder('platform_click', ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->refund($order, '50000');
        $this->assertSame(['0.00', '45000.00'], $this->balances());

        $rows = $this->ledgerRows();
        $this->replayPaymentPaid($order);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(['0.00', '45000.00'], $this->balances());

    }

    /**
     * Review M1 (b): before c69e3bef a part approved while only the settlement was booked reversed only the settlement,
     * the retry booked the whole commission and skipped the request, and finance gave the 5 000 back by hand (an
     * adjustment carries no order_id). A replayed PaymentPaid must not give it back a second time.
     */
    public function test_a_replayed_payment_on_an_order_corrected_by_hand_moves_nothing(): void
    {
        $order = $this->platformOrderWithDeferredCommission();
        $this->refund($order, '50000');
        $this->repairCommissions();
        app(\App\Services\Finance\LedgerService::class)->processOrderDelivered($order->fresh());
        $order->forceFill(['settlement_failed_at' => null, 'settlement_error' => null, 'settlement_attempts' => 0])->save();
        $this->assertSame(['0.00', '40000.00'], $this->balances());
        $store = $this->store->fresh();
        StoreWalletTransaction::create([
            'store_id' => $store->id, 'type' => StoreTransactionType::ADJUSTMENT->value, 'amount' => '5000.00',
            'balance_before' => (string) $store->wallet_balance, 'balance_after' => '45000.00',
            'notes' => 'Manual correction: commission share of a refunded part',
        ]);
        $store->forceFill(['wallet_balance' => '45000.00'])->save();

        $rows = $this->ledgerRows();
        $this->replayPaymentPaid($order);
        $this->assertSame($rows, $this->ledgerRows());
        $this->assertSame(['0.00', '45000.00'], $this->balances());
    }

    /**
     * Review M1 (c): the H1 state (the late payment's settlement lost without a marker, before the fix) with a part
     * approved meanwhile (it reversed its commission share): the replayed payment books the settlement and reverses
     * the part's share of it at once. The store keeps 50 000 less 5 000 commission.
     */
    public function test_a_replay_that_heals_a_lost_late_settlement_applies_the_parts_approved_meanwhile(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid', ['total_price' => 100000, 'commission_amount' => 10000]);
        $this->assertSame(['10000.00', '0.00'], $this->balances());
        Payment::where('order_id', $order->id)->update(['status' => PaymentStatus::PAID->value, 'paid_at' => now()]);
        $order->forceFill(['payment_status' => PaymentStatus::PAID->value])->save();

        $approved = $this->refund($order->fresh(), '50000');
        $this->assertSame(['commission' => '5000.00', 'settlement' => '0.00', 'added_to_debt' => '0.00'], $approved['execution']['ledger_reversal']);
        $this->assertSame(['5000.00', '0.00'], $this->balances());

        $this->replayPaymentPaid($order);

        [$debt, $wallet] = $this->balances();
        $this->assertSame('45000.00', Money::of($wallet)->minus($debt)->toString());
        $this->assertSame('5000.00', $this->netCommission($order));
        $this->assertSame([[(int) $approved['id'], false, '5000.00', '0.00'], [(int) $approved['id'], false, '0.00', '50000.00']], $this->reversals($order));
        $execution = OrderRefundRequest::find($approved['id'])->execution;
        $this->assertSame(['commission' => '5000.00', 'settlement' => '50000.00', 'added_to_debt' => '0.00'], $execution['ledger_reversal']);
        $this->assertSame('replay', $execution['ledger_reversal_catch_up_reason']);

        $rows = $this->ledgerRows();
        $this->replayPaymentPaid($order);
        $this->retry();
        $this->assertSame($rows, $this->ledgerRows());
    }

    /**
     * The same lost settlement booked by hand (`orders:retry-settlements --confirm-unbooked`, the order carries no
     * marker): the part approved meanwhile gives back its share once, and the request records the reason `manual`.
     */
    public function test_the_command_healing_a_lost_late_settlement_records_the_catch_up_reason_manual(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid', ['total_price' => 100000, 'commission_amount' => 10000]);
        Payment::where('order_id', $order->id)->update(['status' => PaymentStatus::PAID->value, 'paid_at' => now()]);
        $order->forceFill(['payment_status' => PaymentStatus::PAID->value])->save();
        $approved = $this->refund($order->fresh(), '50000');
        $this->assertSame(['5000.00', '0.00'], $this->balances());

        $this->artisan('orders:retry-settlements', ['orders' => [(string) $order->id], '--confirm-unbooked' => true])
            ->expectsOutputToContain('booked')->assertSuccessful();

        [$debt, $wallet] = $this->balances();
        $this->assertSame('45000.00', Money::of($wallet)->minus($debt)->toString());
        $execution = OrderRefundRequest::find($approved['id'])->execution;
        $this->assertSame(['commission' => '5000.00', 'settlement' => '50000.00', 'added_to_debt' => '0.00'], $execution['ledger_reversal']);
        $this->assertSame('manual', $execution['ledger_reversal_catch_up_reason']);
    }

    /**
     * Review L1: nothing was booked when the parts were approved and they reach everything paid: the retry books the
     * commission and the settlement, reverses the parts (a full reversal) and only then looks at the referral bonus,
     * which a fully refunded order never earns: no bonus row at all (no +5 000 / −5 000 pair).
     */
    public function test_parts_reaching_the_whole_while_nothing_was_booked_leave_no_bonus_rows_at_all(): void
    {
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();
        $order = $this->deliveredWithNothingBooked('platform_click');

        $this->refund($order, '50000');
        $this->refund($order->fresh(), '50000');
        $this->assertSame([], $this->reversals($order));

        $this->repairCommissions();
        $this->retry();
        $this->retry();

        $this->assertSame([], $this->rewards());
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertSame('0.00', $this->netCommission($order));
        $this->assertSame(['0.00', '0.00'], $this->balances());
        $this->assertSame(1, OrderLedgerReversal::where('order_id', $order->id)->where('is_full', true)->count());
    }
}
