<?php

namespace Tests\Feature\Referral;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Events\OrderStatusUpdated;
use App\Events\PaymentPaid;
use App\Jobs\RetryFailedSettlementsJob;
use App\Jobs\SendTelegramRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SettlementRecoveryService;
use App\Services\Order\OrderStatusService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\NestedTransactionDeadlockTest;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Review finding 3 and review H1: a payment that lands only after delivery (MarkOrderAsPaid) settles the store and may
 * earn the referral bonus; a delivery books the commission, the settlement and the bonus. A grant that fails — here a
 * REAL PostgreSQL deadlock (SQLSTATE 40P01, PostgreSQL's own message "deadlock detected", raised inside the grant's
 * transaction after the grant took its locks and wrote its rows) — must never be lost silently and never undo more
 * than the booking it belongs to:
 *  - late payment: the payment status and the store's settlement are committed first, the bonus is booked in a
 *    transaction of its own; its failure marks the order (settlement_failed_at) and RetryFailedSettlementsJob grants
 *    it later, exactly once;
 *  - delivery: the booking's savepoint (commission, settlement, bonus) is rolled back, the delivery commits with the
 *    marker, and the retry books everything once.
 * Before the fix the deadlock left the caller's whole transaction aborted (Laravel does not roll a nested transaction
 * back to its savepoint on a deadlock): the marker could not be written and the "commit" lost everything.
 */
class ReferralLatePaymentFailureTest extends TestCase
{
    use RefreshDatabase;
    use OrderLifecycleFixtures;
    use ReferralOrders;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([SendTelegramRequest::class]);
        $this->setUpLifecycleStore();
        $this->referrer = $this->makeCustomer();
        $this->customer->forceFill(['referred_by' => $this->referrer->id])->save();

        // While $fail is set the grant fails: 'deadlock' = a real 40P01 from PostgreSQL inside the grant's own
        // transaction, after the real grant ran; 'php' = an exception thrown in PHP. Every other booking works
        $this->app->singleton(LedgerService::class, fn ($app) => new class($app->make(\App\Services\Finance\Billing\SubscriptionDebt::class), $app->make(\App\Services\Finance\Netting\NettingService::class)) extends LedgerService {
            public static ?string $fail = null;

            public function grantReferralReward(\App\Models\Order $order, ?int $performedBy = null): bool
            {
                if (self::$fail === 'php') {
                    throw new \RuntimeException('SQLSTATE[40P01]: Deadlock detected: 7 ERROR:  deadlock detected');
                }
                if (self::$fail === 'deadlock') {
                    return DB::transaction(function () use ($order, $performedBy) {
                        $granted = parent::grantReferralReward($order, $performedBy);
                        NestedTransactionDeadlockTest::raiseDeadlock();

                        return $granted;
                    });
                }

                return parent::grantReferralReward($order, $performedBy);
            }
        });
    }

    protected function tearDown(): void
    {
        $this->failGrant(null);

        parent::tearDown();
    }

    private function failGrant(?string $how): void
    {
        $class = get_class(app(LedgerService::class));
        $class::$fail = $how;
    }

    /** The order's ledger: [commission rows, settlement rows, bonus rows, debt, wallet] */
    private function ledgerOf(Order $order): array
    {
        return [
            StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->count(),
            StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->count()
                + StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::TOPUP->value)->count(),
            count($this->rewards()),
            (string) $this->store->fresh()->debt_balance,
            (string) $this->store->fresh()->wallet_balance,
        ];
    }

    private function payLate(Order $order): Payment
    {
        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($payment));

        return $payment;
    }

    private function retryTwiceAndReplay(Payment $payment): void
    {
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        event(new PaymentPaid($payment->fresh())); // a replayed event too
    }

    private function assertGrantedOnce(Order $order): void
    {
        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $order->refresh();
        $this->assertNull($order->settlement_failed_at);
        $this->assertNull($order->settlement_error);
        // Nothing double-booked: one commission, one settlement (netting: the 5 000 commission debt, 45 000 to the wallet)
        $this->assertSame([1, 2, 1, '0.00', '45000.00'], $this->ledgerOf($order));
    }

    public function test_a_failing_grant_never_undoes_a_late_payment_and_is_granted_once_on_retry(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        $this->assertNull($order->settlement_failed_at);
        // Delivered unpaid: the 5 000 commission on the debt, nothing settled
        $this->assertSame([1, 0, 0, '5000.00', '0.00'], $this->ledgerOf($order));

        $this->failGrant('php');
        $payment = $this->payLate($order);

        // Paid and settled; the bonus is owed, marked for retry
        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame([1, 2, 0, '0.00', '45000.00'], $this->ledgerOf($order));
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertStringContainsString('deadlock detected', (string) $order->settlement_error);

        $this->failGrant(null);
        $this->retryTwiceAndReplay($payment);
        $this->assertGrantedOnce($order);
    }

    public function test_a_real_deadlock_in_the_grant_of_a_late_payment_keeps_payment_and_settlement_and_is_retried(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');

        $this->failGrant('deadlock');
        $payment = $this->payLate($order);

        // The transaction is usable (not left aborted) and kept the payment and the settlement; the grant's rows are gone
        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame([1, 2, 0, '0.00', '45000.00'], $this->ledgerOf($order));
        $this->assertSame(0.0, $this->referrerBalance());
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertSame(1, (int) $order->settlement_attempts);
        $this->assertStringContainsString('deadlock detected', (string) $order->settlement_error);

        // A retry that deadlocks again stays marked and counts the attempt
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        $this->assertSame(2, (int) $order->fresh()->settlement_attempts);
        $this->assertSame([], $this->rewards());

        $this->failGrant(null);
        $this->retryTwiceAndReplay($payment);
        $this->assertGrantedOnce($order);
    }

    public function test_a_real_deadlock_in_the_grant_at_delivery_rolls_back_only_the_booking(): void
    {
        Event::fake([OrderStatusUpdated::class]);
        $this->failGrant('deadlock');
        $order = $this->deliveredOrder('platform_click');

        // Delivered (and announced, after its commit); the booking is rolled back whole and marked for the retry
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertStringContainsString('deadlock detected', (string) $order->settlement_error);
        $this->assertSame([0, 0, 0, '0.00', '0.00'], $this->ledgerOf($order));
        $this->assertSame(0.0, $this->referrerBalance());
        Event::assertDispatchedTimes(OrderStatusUpdated::class, 1);
        $this->assertSame(1, \App\Models\OrderStatusHistory::where('order_id', $order->id)->where('status', OrderStatus::DELIVERED->value)->count());

        $this->failGrant(null);
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertNull($order->fresh()->settlement_failed_at);
        $this->assertSame([1, 2, 1, '0.00', '45000.00'], $this->ledgerOf($order));
    }

    /**
     * Review L5: a late payment whose booking succeeds clears a marker left by an earlier failed booking, as the retry
     * does, in the same savepoint as the booking (so a marker is never cleared for a booking that did not commit).
     */
    public function test_a_late_payment_booking_that_succeeds_clears_an_earlier_failure_marker(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        // Marked as an earlier failed booking (or a failed bonus of a payment) leaves it
        $order->forceFill(['settlement_failed_at' => now()->subMinutes(5), 'settlement_error' => 'earlier', 'settlement_attempts' => 1])->save();

        $this->payLate($order);

        $this->assertGrantedOnce($order);
        $this->assertSame(0, (int) $order->fresh()->settlement_attempts);
    }

    /**
     * Review H1, defensively: a booking that finds its caller's transaction already aborted (nothing in it can commit
     * any more) fails loudly instead of returning "marked for retry" — the marker could not be written either.
     */
    public function test_a_booking_inside_an_aborted_transaction_fails_loudly(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        $order->forceFill(['payment_status' => PaymentStatus::PAID->value])->save();
        $order = Order::findOrFail($order->id);
        $order->loadMissing('store');

        DB::beginTransaction();
        try {
            try {
                DB::select('select 1 / 0');
            } catch (QueryException) {
                // the transaction is aborted now
            }

            $thrown = null;
            try {
                app(SettlementRecoveryService::class)->book($order);
            } catch (\PDOException $e) {
                $thrown = $e; // QueryException, or the PDOException of the SAVEPOINT statement itself
            }
        } finally {
            DB::rollBack();
        }

        $this->assertNotNull($thrown, 'book() returned instead of failing loudly');
        $this->assertSame('25P02', (string) $thrown->getCode());
        $this->assertNull($order->fresh()->settlement_failed_at);
    }

    /**
     * MarkOrderAsPaid: whatever escapes the bonus booking (its own top-level transaction, after the payment and the
     * settlement committed) still leaves the order marked, so the retry grants it.
     */
    public function test_a_bonus_booking_that_throws_after_the_settlement_committed_still_marks_the_order(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        $this->app->singleton(SettlementRecoveryService::class, fn ($app) => new class($app->make(LedgerService::class), $app->make(\App\Services\Notifications\SuperadminAlertService::class)) extends SettlementRecoveryService {
            public static bool $throw = true;

            public function book(Order $order, ?int $performedBy = null, string $catchUpReason = self::CATCH_UP_RETRY): bool
            {
                if (self::$throw) {
                    throw new \RuntimeException('the booking blew up');
                }

                return parent::book($order, $performedBy, $catchUpReason);
            }
        });
        $this->app->forgetInstance(\App\Listeners\MarkOrderAsPaid::class);

        $payment = $this->payLate($order);

        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame([1, 2, 0, '0.00', '45000.00'], $this->ledgerOf($order));
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertStringContainsString('the booking blew up', (string) $order->settlement_error);

        $class = get_class(app(SettlementRecoveryService::class));
        $class::$throw = false;
        $this->retryTwiceAndReplay($payment);
        $this->assertGrantedOnce($order);
    }
}
