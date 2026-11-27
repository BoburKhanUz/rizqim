<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StoreTransactionType;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\ReferralService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\SettlementRecoveryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Owner decision C: MarkOrderAsPaid books a delivered order's late payment in two transactions — the payment status
 * and the store's settlement, then the referral bonus (SettlementRecoveryService::bookAfterPayment). A process that
 * dies between them leaves no marker (settlement_failed_at is written only by a booking that ran and failed), so
 * RetryFailedSettlementsJob never sees the order, and a provider does not replay a payment it got an answer for: the
 * bonus would never be granted. Hourly, this grants such a bonus, and only the bonus. Not queued: it runs inside the
 * scheduler, like RetryFailedSettlementsJob.
 *
 * Review M1: only that gap. A candidate's payment landed AFTER its delivery (payments.paid_at later than the first
 * `delivered` row of order_status_histories; an order without one is none) and its order carries no referral bonus
 * row of its own. An order paid before delivery had its bonus decided at delivery, a refusal included (the monthly
 * cap, a referral already rewarded): a refused order earns nothing, the referral stays open for a LATER order, so
 * such an order is never a candidate. A late payment's own second transaction may have run and refused too (it writes
 * nothing then): LedgerService::grantMissedReferralBonus decides as of the payment, so that refusal is repeated, never
 * overturned by a clawback, a reopened referral or a new month.
 *
 * Review L1+L2: per candidate, in a transaction of its own, the order's row lock first (the order → store → users
 * order of every ledger path), then under it: still delivered, paid and unmarked, its commission booked and, for a
 * platform-collected order, its settlement booked; only then LedgerService::grantMissedReferralBonus (the store's and
 * the users' locks, every rule of the grant). It never books a commission or a settlement (an order missing one is
 * left to RetryFailedSettlementsJob or, unmarked, `orders:retry-settlements --confirm-unbooked` after finance checked
 * its manual adjustments, and logged here). A grant that fails (a lock timeout, a deadlock) is logged and nothing
 * else: no settlement marker, no "settlement not booked" alert; the next run tries again. Idempotent: an order granted
 * (or refused under the locks) is granted nothing.
 *
 * Candidates: first the orders paid after delivery between WINDOW_DAYS and AFTER_MINUTES ago (latePaidOrderIds:
 * payments alone, through payments_paid_at_index, the newest LATE_PAYMENTS_PER_RUN payments; the listener is given
 * AFTER_MINUTES to book it itself), then of those (candidates(), by primary key) the delivered, still paid, unmarked
 * orders of referred customers with no referral bonus row of their own, whose customer's referral is still open
 * (no net bonus on any of their orders, no legacy store-paid one). Review L3: the shapes the grant refuses for good
 * are left out where SQL says it cheaply: below the minimum, a commission below the bonus, cash and keshbek, a store's
 * own account other than a Paylov payment confirmed on read-back, a platform order with a keshbek share, an order a
 * full reversal refunded. What is left that can be refused for good (the cap or the referral as of the payment) is
 * decided by the grant, and the newest come first, so a run never sits behind older refused ones: a real candidate is
 * reached unless more than LATE_PAYMENTS_PER_RUN newer late payments (cash and keshbek aside), or MAX_PER_RUN newer
 * late-paid, unbonused orders of open referrals the grant refuses, fall within WINDOW_DAYS. One customer's orders: the oldest of the batch first, at most one grant per run.
 */
class SweepMissedReferralBonusesJob
{
    use Dispatchable;

    /** How far back a payment is looked for (bounded: the query never reads every delivered order) */
    public const WINDOW_DAYS = 7;

    /** A payment paid less than this ago is left to MarkOrderAsPaid itself */
    public const AFTER_MINUTES = SettlementRecoveryService::UNBOOKED_AFTER_MINUTES;

    /** The most candidates one run looks at (the newest first) */
    public const MAX_PER_RUN = 200;

    /** The most late payments one run reads (the newest first), before the orders' own conditions */
    public const LATE_PAYMENTS_PER_RUN = 1000;

    /**
     * Review M1: the orders whose payment landed after their delivery, paid between WINDOW_DAYS and AFTER_MINUTES ago,
     * the newest payments first, at most LATE_PAYMENTS_PER_RUN. A query of its own on payments alone, so that it reads
     * through payments_paid_at_index (partial: status paid) whatever the planner knows of the other tables (a single
     * query let an unanalysed database drive it from orders instead). Cash and keshbek never earn the bonus: left out.
     *
     * @return list<int>
     */
    public static function latePaidOrderIds(): array
    {
        return self::latePayments()->pluck('payments.order_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** latePaidOrderIds' query */
    public static function latePayments(): \Illuminate\Database\Query\Builder
    {
        return DB::table('payments')
            ->where('payments.status', PaymentStatus::PAID->value)
            ->where('payments.paid_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->where('payments.paid_at', '<=', now()->subMinutes(self::AFTER_MINUTES))
            ->whereNotIn('payments.provider', ['cash', 'keshbek'])
            // After the delivery: later than the order's first `delivered` status row (none: not a candidate)
            ->where('payments.paid_at', '>', fn ($delivered) => $delivered->selectRaw('min(h.created_at)')->from('order_status_histories as h')
                ->whereColumn('h.order_id', 'payments.order_id')->where('h.status', OrderStatus::DELIVERED->value))
            ->orderByDesc('payments.paid_at')
            ->limit(self::LATE_PAYMENTS_PER_RUN)
            ->select('payments.order_id');
    }

    /**
     * @param  list<int>|null  $orderIds  latePaidOrderIds() (read now when null)
     * @return Builder<Order>
     */
    public static function candidates(?array $orderIds = null): Builder
    {
        $orderIds ??= self::latePaidOrderIds();
        $bonus = StoreTransactionType::REFERRAL_BONUS->value;
        $customerOrders = fn ($query) => $query->select('o2.id')->from('orders as o2')->whereColumn('o2.user_id', 'orders.user_id');

        return Order::query()
            ->where('orders.status', OrderStatus::DELIVERED->value)
            ->where('orders.payment_status', PaymentStatus::PAID->value)
            ->whereNull('orders.settlement_failed_at')
            ->where('orders.total_price', '>=', ReferralService::MIN_ORDER)
            ->where('orders.commission_amount', '>=', ReferralService::BONUS)
            ->whereNotIn('orders.payment_method', ['cash', 'keshbek'])
            // Only these can be verified by the platform (ReferralService::paymentVerifiedByPlatform decides): the
            // platform's account without a keshbek share, or a store's own Paylov payment confirmed on read-back
            ->where(fn ($query) => $query
                ->where(fn ($platform) => $platform->where('orders.payment_owner', 'PLATFORM')
                    ->whereNotExists(fn ($share) => $share->selectRaw('1')->from('payments as share')
                        ->whereColumn('share.order_id', 'orders.id')->where('share.provider', 'keshbek')))
                ->orWhere(fn ($store) => $store->where('orders.payment_owner', '!=', 'PLATFORM')->where('orders.payment_method', 'paylov')
                    ->whereExists(fn ($paylov) => $paylov->selectRaw('1')->from('payments as paylov')
                        ->whereColumn('paylov.order_id', 'orders.id')->where('paylov.provider', 'paylov')
                        ->whereIn('paylov.status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value])
                        ->where('paylov.payload->paylov->state', \App\Services\Payment\Paylov\PaylovPayments::STATE_CONFIRMED)
                        ->whereNull('paylov.payload->paylov->refused'))))
            ->whereIn('orders.user_id', User::query()->select('id')->whereNotNull('referred_by'))
            // Review M1: paid after the delivery, in the window (latePaidOrderIds)
            ->whereIn('orders.id', $orderIds)
            // Not granted nor taken back for this order
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_transactions as own')
                ->whereColumn('own.order_id', 'orders.id')->where('own.type', $bonus))
            // Not refunded in full by its parts (the grant refuses such an order for good)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('order_ledger_reversals as full_reversal')
                ->whereColumn('full_reversal.order_id', 'orders.id')->where('full_reversal.is_full', true))
            // The customer's referral still open (LedgerService::referralRewardedNet, decided again under the locks)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_transactions as net')
                ->where('net.type', $bonus)->whereIn('net.order_id', $customerOrders)
                ->havingRaw('SUM(net.amount) > 0'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('store_transactions as legacy')
                ->where('legacy.type', StoreTransactionType::COMMISSION->value)->whereIn('legacy.order_id', $customerOrders)
                ->whereRaw("legacy.notes = ? || orders.user_id", [LedgerService::REFERRAL_NOTE . ' for referred user #']))
            ->orderByDesc('orders.id')
            ->limit(self::MAX_PER_RUN);
    }

    public function handle(LedgerService $ledger): void
    {
        $granted = [];
        $customers = [];
        $late = self::latePaidOrderIds();
        if ($late === []) {
            return;
        }
        // The newest batch, then within it one customer's orders the oldest first
        foreach (self::candidates($late)->get(['orders.id', 'orders.uuid', 'orders.user_id'])->sortBy('id') as $candidate) {
            if (isset($customers[(int) $candidate->user_id])) {
                continue;
            }
            try {
                $outcome = DB::transaction(function () use ($candidate, $ledger) {
                    // The order's lock first, as every ledger path (then the store and the users rows, in the grant)
                    $order = Order::whereKey($candidate->id)->lockForUpdate()->first();
                    if (!$order || $order->status !== OrderStatus::DELIVERED || $order->payment_status !== PaymentStatus::PAID || $order->settlement_failed_at !== null) {
                        return 'changed';
                    }
                    if (!$ledger->hasCommission($order) || ($order->payment_owner === 'PLATFORM' && !$ledger->hasSettlement($order))) {
                        return 'unbooked';
                    }

                    return $ledger->grantMissedReferralBonus($order) ? 'granted' : 'refused';
                });
            } catch (Throwable $e) {
                // e.g. a lock timeout: logged only (no marker, no settlement alert); the next run tries again
                Log::error("Missed referral bonus sweep of order {$candidate->uuid} could not run, tried again next hour: {$e->getMessage()}", ['order_id' => $candidate->id]);

                continue;
            }

            if ($outcome === 'unbooked') {
                Log::warning("Missed referral bonus sweep: order {$candidate->uuid} is not booked (commission or settlement missing); left to the settlement retry and monitoring", ['order_id' => $candidate->id]);
            } elseif ($outcome === 'granted') {
                $customers[(int) $candidate->user_id] = true;
                $granted[] = $candidate->uuid;
                Log::info("Missed referral bonus of order {$candidate->uuid} booked by the sweep", ['order_id' => $candidate->id]);
            }
        }

        if ($granted !== []) {
            Log::warning('Missed referral bonus sweep granted ' . count($granted) . ' bonus(es) a late payment had left unbooked', ['orders' => $granted]);
        }
    }
}
