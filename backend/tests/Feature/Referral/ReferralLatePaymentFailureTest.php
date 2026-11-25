<?php

namespace Tests\Feature\Referral;

use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Events\PaymentPaid;
use App\Jobs\RetryFailedSettlementsJob;
use App\Jobs\SendTelegramRequest;
use App\Models\Payment;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Services\Finance\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\OrderLifecycleFixtures;
use Tests\TestCase;

/**
 * Review finding 3: a payment that lands only after delivery (MarkOrderAsPaid) settles the store and may earn the
 * referral bonus. A failing grant (a deadlock, a lock timeout) must never undo the payment status or the store's
 * settlement: it runs in a savepoint of its own (SettlementRecoveryService::book), and a failure leaves the order
 * marked (settlement_failed_at) for RetryFailedSettlementsJob, which grants it later, exactly once.
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

        // The grant fails while $fail is set (as a deadlock or a lock timeout would), every other booking works
        $this->app->singleton(LedgerService::class, fn ($app) => new class($app->make(\App\Services\Finance\Billing\SubscriptionDebt::class), $app->make(\App\Services\Finance\Netting\NettingService::class)) extends LedgerService {
            public static bool $fail = false;

            public function grantReferralReward(\App\Models\Order $order, ?int $performedBy = null): bool
            {
                if (self::$fail) {
                    throw new \RuntimeException('SQLSTATE[40P01]: Deadlock detected');
                }

                return parent::grantReferralReward($order, $performedBy);
            }
        });
    }

    private function failGrant(bool $fail): void
    {
        $class = get_class(app(LedgerService::class));
        $class::$fail = $fail;
    }

    public function test_a_failing_grant_never_undoes_a_late_payment_and_is_granted_once_on_retry(): void
    {
        $order = $this->deliveredOrder('platform_click_unpaid');
        $this->assertNull($order->settlement_failed_at);
        // Delivered unpaid: the 5 000 commission on the debt, nothing settled
        $this->assertSame(['5000.00', '0.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);

        $this->failGrant(true);
        $payment = Payment::where('order_id', $order->id)->sole();
        $payment->update(['status' => PaymentStatus::PAID, 'paid_at' => now()]);
        event(new PaymentPaid($payment));

        // Paid and settled (netting: the 5 000 commission debt, 45 000 to the wallet); the bonus is owed, marked for retry
        $order->refresh();
        $this->assertSame(PaymentStatus::PAID, $order->payment_status);
        $this->assertSame(['0.00', '45000.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
        $this->assertSame([], $this->rewards());
        $this->assertNotNull($order->settlement_failed_at);
        $this->assertStringContainsString('Deadlock', (string) $order->settlement_error);

        $this->failGrant(false);
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        app()->call([new RetryFailedSettlementsJob, 'handle']);
        event(new PaymentPaid($payment->fresh())); // a replayed event too

        $this->assertSame([['order_id' => $order->id, 'store_id' => $this->store->id, 'amount' => '5000.00']], $this->rewards());
        $this->assertSame(5000.0, $this->referrerBalance());
        $this->assertNull($order->fresh()->settlement_failed_at);
        // Nothing double-booked: one commission, one settlement
        $this->assertSame(1, StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->count());
        $this->assertSame(1, StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->count());
        $this->assertSame(['0.00', '45000.00'], [(string) $this->store->fresh()->debt_balance, (string) $this->store->fresh()->wallet_balance]);
    }
}
