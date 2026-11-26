<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\StoreTransaction;
use App\Models\User;
use App\Services\Auth\ReferralService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SettlementRecoveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Owner decision C: MarkOrderAsPaid books a delivered order's late payment in two transactions — the payment status
 * and the store's settlement, then the referral bonus (SettlementRecoveryService::bookAfterPayment). A process that
 * dies between them leaves no marker (settlement_failed_at is written only by a booking that ran and failed), so
 * RetryFailedSettlementsJob never sees the order, and a provider does not replay a payment it got an answer for: the
 * bonus would never be granted. Hourly, this books such orders through the same path (bookAfterPayment, under the
 * order's lock; LedgerService::grantReferralReward under the store's and the users' locks), so every rule applies
 * there: exactly once per referred customer net of clawbacks, the monthly cap, the commission that must cover the
 * bonus, a refunded order never. Idempotent: an order already granted (or refused under the locks) is granted nothing.
 * Not queued: it runs inside the scheduler, like RetryFailedSettlementsJob.
 *
 * Candidates (candidates(), one indexed query, at most MAX_PER_RUN): delivered, still paid, unmarked orders of
 * referred customers with a payment paid between WINDOW_DAYS and AFTER_MINUTES ago (payments_paid_at_index; the
 * listener is given AFTER_MINUTES to book it itself), with no referral bonus row of their own and whose customer's
 * referral is still open (no net bonus on any of their orders, no legacy store-paid one). The SQL only narrows: the
 * decision is the real one — ReferralService::qualifiesForReferralBonus here, then the grant under its locks. An
 * order whose referrer is at the monthly cap now is skipped before any lock is taken (the grant would refuse it); an
 * order whose commission or settlement is not booked is left alone (logged): booking those is for
 * RetryFailedSettlementsJob or, unmarked, `orders:retry-settlements --confirm-unbooked` after finance checked its
 * manual adjustments — never for this sweep. One customer's orders: the oldest first, at most one grant per run.
 */
class SweepMissedReferralBonusesJob
{
    use Dispatchable;

    /** How far back a payment is looked for (bounded: the query never reads every delivered order) */
    public const WINDOW_DAYS = 7;

    /** A payment paid less than this ago is left to MarkOrderAsPaid itself */
    public const AFTER_MINUTES = SettlementRecoveryService::UNBOOKED_AFTER_MINUTES;

    /** The most candidates one run looks at (the oldest first); the next run goes on */
    public const MAX_PER_RUN = 200;

    /**
     * @return Builder<Order>
     */
    public static function candidates(): Builder
    {
        $bonus = StoreTransactionType::REFERRAL_BONUS->value;
        $customerOrders = fn ($query) => $query->select('o2.id')->from('orders as o2')->whereColumn('o2.user_id', 'orders.user_id');

        return Order::query()
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->where('orders.payment_status', PaymentStatus::PAID->value)
            ->whereNull('orders.settlement_failed_at')
            ->where('orders.total_price', '>=', ReferralService::MIN_ORDER)
            ->where('orders.commission_amount', '>=', ReferralService::BONUS)
            ->whereNotIn('orders.payment_method', ['cash', 'keshbek'])
            // Only these can be verified by the platform (ReferralService::paymentVerifiedByPlatform decides)
            ->where(fn ($query) => $query->where('orders.payment_owner', 'PLATFORM')->orWhere('orders.payment_method', 'paylov'))
            ->whereIn('orders.user_id', User::query()->select('id')->whereNotNull('referred_by'))
            ->whereIn('orders.id', fn ($query) => $query->select('payments.order_id')->from('payments')
                ->where('payments.status', PaymentStatus::PAID->value)
                ->where('payments.paid_at', '>=', now()->subDays(self::WINDOW_DAYS))
                ->where('payments.paid_at', '<=', now()->subMinutes(self::AFTER_MINUTES)))
            // Not granted nor taken back for this order
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_transactions as own')
                ->whereColumn('own.order_id', 'orders.id')->where('own.type', $bonus))
            // The customer's referral still open (LedgerService::referralRewardedNet, decided again under the locks)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_transactions as net')
                ->where('net.type', $bonus)->whereIn('net.order_id', $customerOrders)
                ->havingRaw('SUM(net.amount) > 0'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_transactions as legacy')
                ->where('legacy.type', StoreTransactionType::COMMISSION->value)->whereIn('legacy.order_id', $customerOrders)
                ->whereRaw("legacy.notes = ? || orders.user_id", [LedgerService::REFERRAL_NOTE . ' for referred user #']))
            ->orderBy('orders.id')
            ->limit(self::MAX_PER_RUN);
    }

    public function handle(SettlementRecoveryService $settlements, LedgerService $ledger): void
    {
        $granted = [];
        $customers = [];
        foreach (self::candidates()->get() as $order) {
            if (isset($customers[(int) $order->user_id])) {
                continue;
            }
            try {
                if (!ReferralService::qualifiesForReferralBonus($order)) {
                    continue;
                }
                $referrerId = User::whereKey($order->user_id)->value('referred_by');
                if ($referrerId === null || ReferralService::monthlyBonusesOf((int) $referrerId, now()) >= ReferralService::MONTHLY_CAP_PER_REFERRER) {
                    continue;
                }
                if (!$ledger->hasCommission($order) || ($order->payment_owner === 'PLATFORM' && !$ledger->hasSettlement($order))) {
                    Log::warning("Missed referral bonus sweep: order {$order->uuid} is not booked (commission or settlement missing); left to the settlement retry and monitoring", ['order_id' => $order->id]);

                    continue;
                }
                $customers[(int) $order->user_id] = true;

                $settlements->bookAfterPayment((int) $order->id, SettlementRecoveryService::CATCH_UP_LATE_PAYMENT);
                if (StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFERRAL_BONUS->value)->where('amount', '>', 0)->exists()) {
                    $granted[] = $order->uuid;
                    Log::info("Missed referral bonus of order {$order->uuid} booked by the sweep", ['order_id' => $order->id, 'referrer_id' => (int) $referrerId]);
                }
            } catch (Throwable $e) {
                // e.g. a lock timeout: the next run tries again; one order never holds up the others
                Log::error("Missed referral bonus sweep of order {$order->id} could not run: {$e->getMessage()}");
            }
        }

        if ($granted !== []) {
            Log::warning('Missed referral bonus sweep granted ' . count($granted) . ' bonus(es) a late payment had left unbooked', ['orders' => $granted]);
        }
    }
}
