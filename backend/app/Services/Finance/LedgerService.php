<?php

namespace App\Services\Finance;

use App\Models\PayoutRequest;
use App\Models\Store;
use App\Models\StoreTopupRequest;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Enums\PaymentStatus;
use App\Enums\StoreStatus;
use App\Enums\StoreTransactionType;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class LedgerService
{
    // Before owner decision 3's addition a referral reward's cost was charged to the store: a COMMISSION row of the
    // referred order on its debt, told apart by this note. Those rows stay as they are (no rewrite); every reader still
    // tells them apart from commission. New bonuses are StoreTransactionType::REFERRAL_BONUS rows the platform pays.
    public const REFERRAL_NOTE = 'Referral cashback reward cost';

    /** The note of a platform-paid referral bonus row (StoreTransactionType::REFERRAL_BONUS). */
    public const REFERRAL_BONUS_NOTE = 'Referral bonus paid by the platform';

    /**
     * P4-21: the subscription part of the debt (docs/BILLING.md, "Qarzning ikki qismi"): payments pay the oldest
     * invoices first, and the credit limit counts only the commission debt (Store::exceedsCreditLimit).
     */
    public function __construct(
        protected \App\Services\Finance\Billing\SubscriptionDebt $subscriptionDebt,
        // P4-23: platform-collected money pays the store's debt first (docs/BILLING.md "Netting")
        protected \App\Services\Finance\Netting\NettingService $netting,
    ) {}

    /**
     * Deduct commission from the Store Balance (Store owes Platform)
     */
    public function deductCommission(Store $store, Money|string|int|float $amount, \App\Models\Order $order, ?string $paymentId = null, ?int $performedBy = null): ?StoreTransaction
    {
        $amount = Money::of($amount);

        return DB::transaction(function () use ($store, $amount, $order, $paymentId, $performedBy) {
            // Lock the store row for update to prevent race conditions
            $lockedStore = Store::where('id', $store->id)->lock(Store::ledgerLock())->firstOrFail();

            // Idempotency check: Have we already deducted commission for this order?
            if ($this->hasCommission($order)) {
                throw new Exception("Commission already deducted for order: {$order->id}");
            }

            // Taken from the wallet first as far as it goes; the rest becomes debt.
            $walletBalance = Money::of($lockedStore->wallet_balance);
            $amountToDeductFromWallet = $walletBalance->isPositive() ? Money::min($amount, $walletBalance) : Money::zero();
            $amountToAddToDebt = $amount->minus($amountToDeductFromWallet);

            // 1. Deduct from wallet if any
            if ($amountToDeductFromWallet->isPositive()) {
                $walletAfter = $walletBalance->minus($amountToDeductFromWallet);

                StoreWalletTransaction::create([
                    'store_id' => $lockedStore->id,
                    'type' => StoreTransactionType::COMMISSION->value,
                    'amount' => $amountToDeductFromWallet->negated()->toString(), // negative shows deduction
                    'balance_before' => $walletBalance->toString(),
                    'balance_after' => $walletAfter->toString(),
                    'reference_id' => $order->uuid,
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'performed_by' => $performedBy,
                    'notes' => "Commission deduction from wallet for order #{$order->short_code}",
                ]);

                $lockedStore->forceFill(['wallet_balance' => $walletAfter->toString()])->save();
            }

            // 2. Add to debt balance if any
            $transaction = null;
            if ($amountToAddToDebt->isPositive()) {
                $balanceBefore = Money::of($lockedStore->debt_balance);
                // debt_balance is positive for debt. Commission INCREASES debt.
                $balanceAfter = $balanceBefore->plus($amountToAddToDebt);

                $transaction = StoreTransaction::create([
                    'store_id' => $lockedStore->id,
                    'type' => StoreTransactionType::COMMISSION->value,
                    'amount' => $amountToAddToDebt->toString(), // positive means commission added to debt
                    'balance_before' => $balanceBefore->toString(),
                    'balance_after' => $balanceAfter->toString(),
                    'reference_id' => $order->uuid,
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'performed_by' => $performedBy,
                    'notes' => "Commission deduction for order #{$order->short_code}",
                ]);

                $lockedStore->forceFill(['debt_balance' => $balanceAfter->toString()])->save();

                // Block an active store once its debt goes over the credit limit; other states (e.g. a finance hold) are kept.
                if ($this->exceedsCreditLimit($lockedStore, $balanceAfter) && $this->hasStatus($lockedStore, StoreStatus::ACTIVE)) {
                    $lockedStore->forceFill([
                        'status' => StoreStatus::PAYMENT_REQUIRED->value,
                    ])->save();
                    event(new \App\Events\StoreBalanceLow($lockedStore));
                }
            }

            return $transaction;
        });
    }

    /**
     * Add funds to the Store Wallet (Platform owes Store)
     */
    public function addSettlement(Store $store, Money|string|int|float $amount, \App\Models\Order $order, ?string $paymentId = null, ?int $performedBy = null): StoreWalletTransaction
    {
        $amount = Money::of($amount);

        return DB::transaction(function () use ($store, $amount, $order, $paymentId, $performedBy) {
            $lockedStore = Store::where('id', $store->id)->lock(Store::ledgerLock())->firstOrFail();

            $exists = StoreWalletTransaction::where('store_id', $store->id)
                ->where('order_id', $order->id)
                ->where('type', StoreTransactionType::SETTLEMENT->value)
                ->exists();

            if ($exists) {
                throw new Exception("Settlement already added for order: {$order->id}");
            }

            $balanceBefore = Money::of($lockedStore->wallet_balance);
            $balanceAfter = $balanceBefore->plus($amount);

            $transaction = StoreWalletTransaction::create([
                'store_id' => $store->id,
                'type' => StoreTransactionType::SETTLEMENT->value,
                'amount' => $amount->toString(),
                'balance_before' => $balanceBefore->toString(),
                'balance_after' => $balanceAfter->toString(),
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'payment_id' => $paymentId,
                'performed_by' => $performedBy,
                'notes' => "Settlement added for order #{$order->short_code}",
            ]);

            $lockedStore->forceFill(['wallet_balance' => $balanceAfter->toString()])->save();

            return $transaction;
        });
    }

    /**
     * Reduce the store's debt by a payment and unblock it once the debt is back within its credit limit (at most abs(credit_limit)).
     */
    public function reduceDebt(Store $store, Money|string|int|float $amount, string $notes, ?string $referenceId = null, ?int $performedBy = null): StoreTransaction
    {
        $amount = Money::of($amount);

        return DB::transaction(function () use ($store, $amount, $notes, $referenceId, $performedBy) {
            $lockedStore = Store::where('id', $store->id)->lock(Store::ledgerLock())->firstOrFail();

            $balanceBefore = Money::of($lockedStore->debt_balance);
            $balanceAfter = $balanceBefore->minus($amount); // reduce debt

            $transaction = StoreTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::TOPUP->value,
                'amount' => $amount->negated()->toString(),
                'balance_before' => $balanceBefore->toString(),
                'balance_after' => $balanceAfter->toString(),
                'notes' => $notes,
                'reference_id' => $referenceId,
                'performed_by' => $performedBy,
            ]);

            $lockedStore->forceFill(['debt_balance' => $balanceAfter->toString()])->save();
            // P4-21: the oldest subscription invoices are paid first (an invoice paid in full unlocks at once)
            $this->subscriptionDebt->paid($lockedStore, $amount, now());

            if ($this->hasStatus($lockedStore, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($lockedStore, $balanceAfter)) {
                $lockedStore->forceFill(['status' => StoreStatus::ACTIVE->value])->save();
            }

            return $transaction;
        });
    }

    /**
     * Reduce the store's debt by a paid online top-up. Callers must hold the lock on the top-up row
     * and only call this on its pending -> paid transition.
     */
    public function creditTopup(StoreTopupRequest $topup, string $providerLabel): StoreTransaction
    {
        return DB::transaction(function () use ($topup, $providerLabel) {
            $store = Store::where('id', $topup->store_id)->lock(Store::ledgerLock())->firstOrFail();

            $existing = StoreTransaction::where('store_id', $store->id)
                ->where('type', StoreTransactionType::TOPUP->value)
                ->where('reference_id', $topup->uuid)
                ->first();

            return $existing ?? $this->reduceDebt($store, Money::of($topup->amount), "Online top-up via {$providerLabel}", $topup->uuid);
        });
    }

    /**
     * Amount the owner may still request: wallet balance minus pending payouts minus the reserve.
     * Callers must hold the lock on the store row.
     */
    public function availableForPayout(Store $store): Money
    {
        $pending = Money::of(PayoutRequest::where('store_id', $store->id)
            ->where('status', 'pending')
            ->sum('amount'));

        return Money::max(Money::zero(), Money::of($store->wallet_balance)->minus($pending)->minus($this->minPayoutReserve($store)));
    }

    /**
     * Create a pending payout request (API and owner panel). Throws \DomainException with a user-facing
     * message when the card is invalid or the wallet minus pending payouts minus the reserve cannot cover it.
     */
    public function requestPayout(Store $store, Money|string|int|float $amount, string $cardNumber, ?string $notes = null): PayoutRequest
    {
        if (!preg_match('/^\d{16}$/', $cardNumber)) {
            throw new \DomainException('Karta raqami 16 ta raqamdan iborat bo\'lishi kerak.');
        }

        $amount = Money::of($amount);

        return DB::transaction(function () use ($store, $amount, $cardNumber, $notes) {
            // Lock the store so parallel requests cannot each pass the check and together exceed the wallet.
            $lockedStore = Store::where('id', $store->id)->lock(Store::ledgerLock())->firstOrFail();
            $minReserve = $this->minPayoutReserve($lockedStore);

            if (Money::of($lockedStore->wallet_balance)->isLessThan($minReserve)) {
                throw new \DomainException('Mablag\' yechish uchun hamyonda kamida ' . number_format($minReserve->toFloat()) . ' UZS qoldiq bo\'lishi shart.');
            }

            $available = $this->availableForPayout($lockedStore);
            if ($amount->isGreaterThan($available)) {
                throw new \DomainException("Yechib olingandan so'ng hamyonda kamida " . number_format($minReserve->toFloat()) . " UZS qolishi shart. Kutilayotgan so'rovlarni hisobga olganda siz ko'pi bilan " . number_format($available->toFloat()) . " UZS yechishingiz mumkin.");
            }

            return PayoutRequest::create([
                'store_id' => $lockedStore->id,
                'amount' => $amount->toString(),
                'card_number' => $cardNumber,
                'notes' => $notes,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Everything delivery books for an order: the commission, the platform-collected money to the store, and the
     * referrer's bonus when the order qualifies (grantReferralReward). Idempotent, part by part, so a booking that
     * failed can simply be run again (SettlementRecoveryService); callers run it in a transaction (savepoint) of its
     * own, so a failure books nothing at all (a deadlock too: RevisionPostgresConnection rolls it back to the savepoint).
     * The production path (SettlementRecoveryService::book) runs its parts itself, with the refunded parts' catch-up
     * between the settlement and the bonus (review L1); this keeps the whole sequence for direct callers.
     */
    public function processOrderDelivered(\App\Models\Order $order, ?int $performedBy = null): void
    {
        $this->bookCommissionAndSettlement($order, $performedBy);

        // 3. Referral reward; a failure fails the whole booking, which is then retried like any other
        $this->grantReferralReward($order, $performedBy);
    }

    /**
     * The first two parts of processOrderDelivered: the commission and the platform-collected money to the store,
     * each at most once. Returns which of them it booked now (false: booked before, or nothing to book). Review L1:
     * SettlementRecoveryService::book runs the parts refunded meanwhile between these and the referral bonus, so an
     * order whose parts reached everything paid never earns the bonus only to have it taken back at once.
     *
     * @return array{commission: bool, settlement: bool}
     */
    public function bookCommissionAndSettlement(\App\Models\Order $order, ?int $performedBy = null): array
    {
        $store = $order->store;
        // Charge the snapshot taken at order time as-is (0 is valid); the rate is only a fallback for an in-memory order without the attribute.
        $commissionAmount = isset($order->commission_amount)
            ? Money::of($order->commission_amount)
            : Money::of($order->total_price)->percent($this->commissionRateOf($order, $store)); // the same resolved rate as checkout

        // 1. Always deduct commission for every order to log the commission transaction
        $commission = false;
        if ($commissionAmount->isPositive() && !$this->hasCommission($order)) {
            $this->deductCommission($store, $commissionAmount, $order, null, $performedBy);
            $commission = true;
        }

        // 2. Settle full amount if collected by the platform
        $settlement = $this->settlePlatformOrder($order, $performedBy);

        return ['commission' => $commission, 'settlement' => $settlement];
    }

    /** The referral bonus (ReferralService::BONUS): ball to the referrer, paid by the platform out of its commission income. */
    public const REFERRAL_REWARD = \App\Services\Auth\ReferralService::BONUS;

    /** The smallest order total that earns the referral bonus (ReferralService::MIN_ORDER). */
    public const REFERRAL_MIN_ORDER = \App\Services\Auth\ReferralService::MIN_ORDER;

    /** The note of a clawed-back referral bonus begins with this (referralClawbackNote). */
    public const REFERRAL_CLAWBACK_NOTE = 'Referral bonus taken back';

    /**
     * The referrer's bonus for a referred customer's delivered order that qualifies (ReferralService::
     * qualifiesForReferralBonus: at least 50 000, a payment the platform verified, a commission that covers the bonus):
     * 5 000 ball to the referrer, paid by the platform out of its commission income (owner decision 3's addition): a
     * REFERRAL_BONUS row on the order's store ledger records it (the platform's cost, `amount`) without moving the
     * store's debt or wallet. An order that does not qualify neither earns it nor uses the referral up.
     *
     * Exactly once per referred customer net of clawbacks (referralRewardedNet): once a bonus stands, no other order
     * earns one; a full refund of the order that earned it takes it back (clawBackReferralReward) and the referral is
     * open again for a later order. An order whose bonus was taken back, or that is no longer paid, never earns it.
     * Rule B: at most ReferralService::MONTHLY_CAP_PER_REFERRER per referrer per month; at the cap nothing is granted
     * and the referral stays open.
     *
     * Runs at delivery (processOrderDelivered) and when a delivered order's payment lands only afterwards
     * (MarkOrderAsPaid, through SettlementRecoveryService::bookAfterPayment); a bonus that booking lost is granted by
     * SweepMissedReferralBonusesJob (grantMissedReferralBonus, the same rules). Race-free across stores, retries and concurrent
     * deliveries: the store row, then the customer's and the referrer's users rows (one statement in id order,
     * lockCustomerAndReferrer; every ledger path takes the store before any users row) are locked before anything is read, so a second delivery of the same
     * customer, and a delivery of another customer of the same referrer, wait and then see the first one's bonus.
     * Review H1: the store row is taken FOR NO KEY UPDATE (Store::ledgerLock), like on every ledger path: a keshbek
     * checkout holds the customer's users row and then inserts its order, whose foreign key check (FOR KEY SHARE on
     * the store) waited on a FOR UPDATE here while this waited on the users row (40P01).
     * Returns whether it granted now. The caller holds the order's lock inside its transaction.
     */
    public function grantReferralReward(\App\Models\Order $order, ?int $performedBy = null): bool
    {
        return $this->grantReward($order, $performedBy, null);
    }

    /**
     * Owner decision C, review M1 / L1+L2 (SweepMissedReferralBonusesJob): the referral bonus of a delivered order whose
     * payment landed after its delivery and whose bonus booking was lost (MarkOrderAsPaid's second transaction never
     * ran). Only the bonus: never the commission or the settlement (the caller checked both are booked, under the
     * order's lock it holds). Every rule of grantReferralReward applies now, under the same locks (store, then the
     * customer and the referrer), and the decision the lost booking would have taken applies too: decided as of the
     * payment (its paid_at), the referral must not have stood then and the referrer's monthly cap of the payment's
     * month must have had room then (grants and clawbacks booked up to the payment). So an order refused at the
     * payment — the cap, a referral already rewarded — stays refused when a later clawback frees a slot, reopens the
     * referral or a new month begins: a refused order earns nothing, the referral stays open for a LATER order.
     * Review (refused duplicate): "the payment" is the one that paid the order (wherePaidTheOrder), the earliest if
     * there are several; a refused Paylov payment (money to give back, paid on its row only) never is. An order with
     * none is refused.
     * Returns whether it granted now; a failure is thrown (the sweep logs it, nothing is marked).
     */
    public function grantMissedReferralBonus(\App\Models\Order $order): bool
    {
        // The order's own payments, whichever store a caller is bound to (the sweep runs unbound, in the scheduler)
        $paidAt = self::wherePaidTheOrder(\App\Models\Payment::query()->withoutGlobalScope(\App\Support\Tenancy\StoreScope::class)
            ->where('order_id', $order->id))->min('paid_at');
        if ($paidAt === null) {
            return false;
        }

        return $this->grantReward($order, null, \Illuminate\Support\Carbon::parse($paidAt));
    }

    /**
     * Narrows a payments query to the payments that paid their order: status paid, and not money a provider took for
     * an order that could not take it. Only Paylov records such money on a payment row (PaylovPayments::settle /
     * mismatch: paid on the row, `payload.paylov.refused` set — order_already_paid, order_not_payable,
     * amount_mismatch, a refusal at perform — its refund requested); it stays paid until the refund completes,
     * possibly never. Click and Payme refuse such money before any row turns paid (Click's already_paid / cancelled,
     * Payme's accountError), so a paid Click / Payme row always paid its order. The same notion as
     * ReferralService::paymentVerifiedByPlatform's (a confirmed Paylov payment with no refusal).
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<\App\Models\Payment>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function wherePaidTheOrder($query, string $table = 'payments')
    {
        return $query->where("{$table}.status", PaymentStatus::PAID->value)
            ->whereNull("{$table}.payload->paylov->refused");
    }

    /**
     * grantReferralReward, and with $decidedAt also the rules as they stood at that moment (grantMissedReferralBonus).
     */
    private function grantReward(\App\Models\Order $order, ?int $performedBy, ?\Carbon\CarbonInterface $decidedAt): bool
    {
        if (!$order->user_id || $order->status !== \App\Enums\OrderStatus::DELIVERED || $order->payment_status !== PaymentStatus::PAID
            || !\App\Services\Auth\ReferralService::qualifiesForReferralBonus($order)) {
            return false;
        }

        return DB::transaction(function () use ($order, $performedBy, $decidedAt) {
            $lockedStore = Store::where('id', $order->store_id)->lock(Store::ledgerLock())->firstOrFail();
            [$user, $referrer] = $this->lockCustomerAndReferrer((int) $order->user_id);
            if (!$user || !$referrer) {
                return false;
            }

            // A refunded order (its bonus taken back, or parts that reached everything paid) never earns it again
            if (StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::REFERRAL_BONUS->value)->where('amount', '<', 0)->exists()
                || \App\Models\OrderLedgerReversal::where('order_id', $order->id)->where('is_full', true)->exists()) {
                return false;
            }

            // M4: the customer's orders, and the referrer's other customers' orders, at every store. Stage 3: as the
            // login role, because inside the delivery's tenant transaction row-level security hides the other stores'
            // rows whatever the scope says (the reward would be paid again at every store). Only a boolean and a
            // number leave the callback, nothing of another store's order or payment. With $decidedAt (a lost
            // booking decided now): the same two questions as they stood then, too
            [$rewarded, $thisMonth, $then] = \App\Support\Tenancy\TenantRls::asApp(fn () => [
                $this->referralRewardedNet((int) $user->id),
                \App\Services\Auth\ReferralService::monthlyBonusesOf((int) $referrer->id, now()),
                $decidedAt === null ? null : [
                    $this->referralRewardedNet((int) $user->id, $decidedAt),
                    \App\Services\Auth\ReferralService::monthlyBonusesOf((int) $referrer->id, $decidedAt, $decidedAt),
                ],
            ]);
            if ($rewarded || $thisMonth >= \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER) {
                return false;
            }
            if ($then !== null && ($then[0] || $then[1] >= \App\Services\Auth\ReferralService::MONTHLY_CAP_PER_REFERRER)) {
                return false;
            }

            // 1. Award the cashback points to the referrer (a negative balance left by a clawback is paid off first)
            $referrer->increment('balance', self::REFERRAL_REWARD);

            // 2. Owner decision 3 (addition): the platform pays it out of its commission income, the store is never
            // charged. The row on the order's store ledger is the exactly-once marker and the platform's cost record:
            // the store's debt and wallet do not move (balance_before = balance_after), `amount` is what was paid
            $balance = Money::of($lockedStore->debt_balance)->toString();
            StoreTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::REFERRAL_BONUS->value,
                'amount' => Money::of(self::REFERRAL_REWARD)->toString(),
                'balance_before' => $balance,
                'balance_after' => $balance,
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => self::referralBonusNote((int) $user->id),
            ]);

            return true;
        });
    }

    /**
     * Review finding 1: the bonus the order earned goes back when the order is fully refunded (reverseDelivery, in the
     * reversal's transaction): the referrer's balance −5 000 and a compensating REFERRAL_BONUS row (amount −5 000, no
     * effect on the store's balances, the note naming the referred customer and the order), so the monthly report nets
     * it out in the month it happens. Exactly once: only while the order's bonus rows still add up to more than 0,
     * under the locks the grant takes (store, then customer and referrer). The referrer's balance may go below zero (keshbek
     * checkout needs the whole total on the balance, so a debt pays for nothing; the next bonus pays it off). The
     * referral is then open again for the customer. A legacy bonus the store paid (a commission row with the referral
     * note) is not taken back: RIZQIM never paid it. Returns whether it took the bonus back now. Review M2: also the
     * invite merge (User::mergeInviteInto), when the account the invite row merges into earned that row the bonus with
     * its own order: the same row and balance change, $notes saying why (referralMergeClawbackNote).
     */
    public function clawBackReferralReward(\App\Models\Order $order, ?int $performedBy = null, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($order, $performedBy, $notes) {
            $lockedStore = Store::where('id', $order->store_id)->lock(Store::ledgerLock())->firstOrFail();
            $bonusRows = fn () => StoreTransaction::where('store_id', $lockedStore->id)->where('order_id', $order->id)->where('type', StoreTransactionType::REFERRAL_BONUS->value);
            if (!$order->user_id || !$bonusRows()->exists()) {
                return false;
            }

            [, $referrer] = $this->lockCustomerAndReferrer((int) $order->user_id);
            // Read under the locks: a concurrent clawback of the same order committed first is seen here
            if (!Money::of((string) $bonusRows()->sum('amount'))->isPositive()) {
                return false;
            }
            if (!$referrer) {
                // The referrer's account is gone with the points: nothing to take back from
                Log::warning("Referral bonus of refunded order {$order->uuid} not taken back: the referrer no longer exists");

                return false;
            }

            $referrer->decrement('balance', self::REFERRAL_REWARD);
            $balance = Money::of($lockedStore->debt_balance)->toString();
            StoreTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::REFERRAL_BONUS->value,
                'amount' => Money::of(self::REFERRAL_REWARD)->negated()->toString(),
                'balance_before' => $balance,
                'balance_after' => $balance,
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => $notes ?? self::referralClawbackNote((int) $order->user_id, (string) $order->short_code),
            ]);

            return true;
        });
    }

    /**
     * Locks the referred customer's and the referrer's users rows, after the store row the caller locked, both in one
     * statement in id order: two customers who referred each other (referred_by can be set on an existing account by
     * a later invite link) can then never deadlock a grant against a grant or a clawback. Returns [customer, referrer];
     * the referrer is null when the customer has none (any more).
     *
     * @return array{0: \App\Models\User|null, 1: \App\Models\User|null}
     */
    private function lockCustomerAndReferrer(int $customerId): array
    {
        $referrerId = \App\Models\User::whereKey($customerId)->value('referred_by');
        $locked = \App\Models\User::whereKey(array_values(array_filter([$customerId, $referrerId !== null ? (int) $referrerId : null])))
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $customer = $locked->get($customerId);
        // Read again under the lock: the referrer is the one locked, or none
        $referrer = $customer !== null && $customer->referred_by !== null && (int) $customer->referred_by === (int) $referrerId
            ? $locked->get((int) $referrerId) : null;

        return [$customer, $referrer];
    }

    /**
     * The note of a taken-back bonus ("Referral bonus taken back for referred user #12: order #AB12 refunded").
     */
    public static function referralClawbackNote(int $referredUserId, string $shortCode): string
    {
        return self::REFERRAL_CLAWBACK_NOTE . " for referred user #{$referredUserId}: order #{$shortCode} refunded";
    }

    /**
     * Review M2: the note of a bonus taken back at an invite merge ("Referral bonus taken back for referred user #12:
     * order #AB12, its referrer's invite row merged into this customer"); it begins as referralClawbackNote.
     */
    public static function referralMergeClawbackNote(int $referredUserId, string $shortCode): string
    {
        return self::REFERRAL_CLAWBACK_NOTE . " for referred user #{$referredUserId}: order #{$shortCode}" . self::REFERRAL_MERGE_CLAWBACK_SUFFIX;
    }

    /** How referralMergeClawbackNote ends (no refund note does) */
    private const REFERRAL_MERGE_CLAWBACK_SUFFIX = ", its referrer's invite row merged into this customer";

    /** Whether a referral_bonus row's note is referralMergeClawbackNote's (taken back at an invite merge, not a refund) */
    public static function isReferralMergeClawbackNote(?string $notes): bool
    {
        return $notes !== null && str_starts_with($notes, self::REFERRAL_CLAWBACK_NOTE . ' ') && str_ends_with($notes, self::REFERRAL_MERGE_CLAWBACK_SUFFIX);
    }

    /** The note of a legacy, store-charged referral reward ("Referral cashback reward cost for referred user #12"). */
    public static function referralNote(int $referredUserId): string
    {
        return self::REFERRAL_NOTE . " for referred user #{$referredUserId}";
    }

    /** The note of a platform-paid referral bonus ("Referral bonus paid by the platform for referred user #12"). */
    public static function referralBonusNote(int $referredUserId): string
    {
        return self::REFERRAL_BONUS_NOTE . " for referred user #{$referredUserId}";
    }

    /**
     * Narrows a store_transactions query to the referral rewards of this referred customer, in both shapes: the
     * platform-paid REFERRAL_BONUS row, and the legacy store-charged COMMISSION row with the referral note.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<StoreTransaction>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function whereReferralRewardOf($query, int $referredUserId, string $table = 'store_transactions')
    {
        return $query->where(fn ($rows) => $rows
            ->where(fn ($row) => $row->where("{$table}.type", StoreTransactionType::REFERRAL_BONUS->value)->where("{$table}.notes", self::referralBonusNote($referredUserId)))
            ->orWhere(fn ($row) => $row->where("{$table}.type", StoreTransactionType::COMMISSION->value)->where("{$table}.notes", self::referralNote($referredUserId))));
    }

    /**
     * Narrows a store_transactions query to every referral reward, in both shapes (see whereReferralRewardOf).
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder<StoreTransaction>|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function whereAnyReferralReward($query, string $table = 'store_transactions')
    {
        return $query->where(fn ($rows) => $rows
            ->where("{$table}.type", StoreTransactionType::REFERRAL_BONUS->value)
            ->orWhere(fn ($row) => $row->where("{$table}.type", StoreTransactionType::COMMISSION->value)->where("{$table}.notes", 'like', self::REFERRAL_NOTE . '%')));
    }

    /**
     * Whether the order's commission was deducted (wallet and/or debt); the referral reward's cost is not commission.
     */
    public function hasCommission(\App\Models\Order $order): bool
    {
        return StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)->exists()
            || StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::COMMISSION->value)
                ->where(fn ($query) => $query->whereNull('notes')->orWhere('notes', 'not like', self::REFERRAL_NOTE . '%'))
                ->exists();
    }

    /**
     * Whether the referred customer's referral bonus stands: the platform-paid bonus rows on the customer's orders at
     * every store (soft-deleted orders too) add up to more than 0 — a bonus taken back on a full refund cancels its
     * grant, and the referral is open again —, or a legacy bonus the store paid exists (never taken back). Callers
     * inside a store-bound transaction run it as the login role (grantReferralReward). With $asOf: as it stood then,
     * from the rows booked up to that moment (grantMissedReferralBonus).
     */
    public function referralRewardedNet(int $customerId, ?\Carbon\CarbonInterface $asOf = null): bool
    {
        // The customer's orders at every store, soft-deleted ones too: a bonus is the platform's, once per customer
        $customerOrders = fn () => \App\Models\Order::query()->withoutGlobalScope(\App\Support\Tenancy\StoreScope::class)->withTrashed()
            ->select('id')->where('user_id', $customerId);
        // Their bonus rows at every store (the caller elevates past RLS inside a tenant transaction)
        $rows = fn () => StoreTransaction::query()->withoutGlobalScope(\App\Support\Tenancy\StoreScope::class)->whereIn('order_id', $customerOrders())
            ->when($asOf !== null, fn ($query) => $query->where('created_at', '<=', $asOf));

        return Money::of((string) $rows()->where('type', StoreTransactionType::REFERRAL_BONUS->value)->sum('amount'))->isPositive()
            || $rows()->where('type', StoreTransactionType::COMMISSION->value)->where('notes', self::referralNote($customerId))->exists();
    }

    /**
     * Credit a paid, platform-collected order to its store exactly once: at delivery, or when the payment only
     * arrives after delivery. Returns whether it credited now. P4-23: whatever the provider, the money pays the
     * store's debt first and only the rest reaches the wallet (owner decision 28.10.2026).
     */
    public function settlePlatformOrder(\App\Models\Order $order, ?int $performedBy = null): bool
    {
        $total = Money::of($order->total_price);

        if ($order->payment_owner !== 'PLATFORM' || !$total->isPositive()) {
            return false;
        }

        if ($order->payment_status !== PaymentStatus::PAID) {
            // Never credit the wallet for money the platform has not received (e.g. an unpaid online order marked delivered).
            Log::warning("Settlement skipped for unpaid platform order {$order->uuid}");
            return false;
        }

        return DB::transaction(function () use ($order, $total, $performedBy) {
            $lockedStore = Store::where('id', $order->store_id)->lock(Store::ledgerLock())->firstOrFail();

            if ($this->hasSettlement($order)) {
                return false;
            }

            // P4-23 (netting): keshbek, Click, Payme alike — the store's debt first (oldest invoices, then the commission
            // debt, a row each), the rest to the wallet
            $this->settleWithNetting($lockedStore, $total, $order, $performedBy);

            return true;
        });
    }

    /**
     * Whether the order's money has already been credited to its store (wallet settlement or keshbek debt payoff).
     */
    public function hasSettlement(\App\Models\Order $order): bool
    {
        return StoreWalletTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::SETTLEMENT->value)->exists()
            || StoreTransaction::where('order_id', $order->id)->where('type', StoreTransactionType::TOPUP->value)->exists();
    }

    /**
     * Undo, exactly once, what delivery booked for a now refunded order: the commission goes back where it was taken
     * from (wallet and/or debt), and a platform-collected order's money comes back from the store: a debt payoff
     * (keshbek, and since P4-23 Click and Payme too) onto the debt again — reopening the subscription invoices it had
     * paid —, the wallet settlement out of the wallet, and whatever the wallet no longer holds added to the debt
     * instead of overdrawing it. Review finding 1: the referral bonus the order earned is taken back (clawBackReferralReward). P4-23: after parts reversed
     * through reverseDeliveredOrderShare only the rest is reversed. Callers hold the order lock; StoreBalanceLow, if
     * the debt now blocks the store, fires after commit.
     *
     * @return array{commission: Money, settlement: Money, added_to_debt: Money}
     */
    public function reverseDeliveredOrder(\App\Models\Order $order, ?int $performedBy = null): array
    {
        return $this->reverseDelivery($order, null, $performedBy);
    }

    /**
     * P4-23: a part of a delivered order was given back through a refund request: reverse the same share of what
     * delivery booked (commission, settlement, debt payoff), pro-rata — $refunded is what has been given back of the
     * order in all, this part included, of $paid. Each request at most once; parts that add up to everything paid
     * reverse exactly what a full refund would, and the order's refund afterwards only the rest.
     *
     * @return array{commission: Money, settlement: Money, added_to_debt: Money}
     */
    public function reverseDeliveredOrderShare(\App\Models\Order $order, int $refundRequestId, Money $refunded, Money $paid, ?int $performedBy = null): array
    {
        return $this->reverseDelivery($order, ['request' => $refundRequestId, 'refunded' => $refunded, 'paid' => $paid, 'catch_up' => false], $performedBy);
    }

    /**
     * A part of the booking was deferred when refund requests were approved (settlement_failed_at: e.g. the late
     * payment's settlement was booked but the commission was not) and is booked now: reverse, per part of the booking,
     * what is still outstanding of this request's cumulative share — its target ($refunded of $paid of what is booked
     * now) less what the order's reversal rows already took back. Unlike reverseDeliveredOrderShare it runs for a
     * request that already has its reversal row and after a full one: what that row reversed is counted, so nothing is
     * reversed twice and nothing booked later is missed; nothing outstanding, nothing booked and no row. The row it
     * adds names the request in `components.catch_up_for_request` when the request already has its own row (one row
     * per request, unique), and is `is_full` only when the order has no full reversal yet (one per order). Callers hold
     * the order lock (SettlementRecoveryService::reverseDeferredParts).
     *
     * @return array{commission: Money, settlement: Money, added_to_debt: Money}
     */
    public function reverseOutstandingShare(\App\Models\Order $order, int $refundRequestId, Money $refunded, Money $paid, ?int $performedBy = null): array
    {
        return $this->reverseDelivery($order, ['request' => $refundRequestId, 'refunded' => $refunded, 'paid' => $paid, 'catch_up' => true], $performedBy);
    }

    /**
     * @param  array{request: int, refunded: Money, paid: Money, catch_up: bool}|null  $share  null: the whole order
     * @return array{commission: Money, settlement: Money, added_to_debt: Money}
     */
    private function reverseDelivery(\App\Models\Order $order, ?array $share, ?int $performedBy): array
    {
        return DB::transaction(function () use ($order, $share, $performedBy) {
            $store = Store::where('id', $order->store_id)->lock(Store::ledgerLock())->firstOrFail();
            $nothing = ['commission' => Money::zero(), 'settlement' => Money::zero(), 'added_to_debt' => Money::zero()];
            $debtRows = fn () => StoreTransaction::where('store_id', $store->id)->where('order_id', $order->id);
            $walletRows = fn () => StoreWalletTransaction::where('store_id', $store->id)->where('order_id', $order->id);

            $reversals = \App\Models\OrderLedgerReversal::where('order_id', $order->id)->lockForUpdate()->get();
            $hasFull = $reversals->contains(fn ($reversal) => $reversal->is_full);
            $hasOwnRow = $share !== null && $reversals->contains(fn ($reversal) => (int) $reversal->order_refund_request_id === $share['request']);
            // A catch-up (reverseOutstandingShare) takes only the difference to its target, whatever was reversed before
            if (!($share['catch_up'] ?? false) && ($hasFull || $hasOwnRow)) {
                return $nothing;
            }
            // Reversed before P4-23 (all at once, with no record): nothing is left to reverse
            if ($reversals->isEmpty() && ($debtRows()->where('type', StoreTransactionType::REFUND->value)->exists()
                || $walletRows()->where('type', StoreTransactionType::REFUND->value)->exists())) {
                return $nothing;
            }

            // What delivery booked
            $booked = [
                'commission_wallet' => Money::of($walletRows()->where('type', StoreTransactionType::COMMISSION->value)->sum('amount'))->negated(),
                'commission_debt' => Money::of($debtRows()->where('type', StoreTransactionType::COMMISSION->value)
                    ->where(fn ($query) => $query->whereNull('notes')->orWhere('notes', 'not like', self::REFERRAL_NOTE . '%'))
                    ->sum('amount')),
                'settlement_wallet' => Money::of($walletRows()->where('type', StoreTransactionType::SETTLEMENT->value)->sum('amount')),
                // A payoff row without an invoice (keshbek before P4-23, or the commission part) is commission debt
                'payoff_commission' => Money::of($debtRows()->where('type', StoreTransactionType::TOPUP->value)->whereNull('subscription_invoice_id')->sum('amount'))->negated(),
            ];
            $bookedInvoices = [];
            foreach ($debtRows()->where('type', StoreTransactionType::TOPUP->value)->whereNotNull('subscription_invoice_id')
                ->groupBy('subscription_invoice_id')->selectRaw('subscription_invoice_id, SUM(amount) AS total')->toBase()->get() as $row) {
                $bookedInvoices[(int) $row->subscription_invoice_id] = Money::of((string) $row->total)->negated();
            }

            // What earlier reversals (parts) took back
            $already = array_map(fn () => Money::zero(), $booked);
            $alreadyInvoices = [];
            foreach ($reversals as $reversal) {
                foreach (array_keys($booked) as $key) {
                    $already[$key] = $already[$key]->plus((string) ($reversal->components[$key] ?? '0'));
                }
                foreach ((array) ($reversal->components['payoff_invoices'] ?? []) as $invoiceId => $amount) {
                    $alreadyInvoices[(int) $invoiceId] = ($alreadyInvoices[(int) $invoiceId] ?? Money::zero())->plus((string) $amount);
                }
            }

            // This reversal's target: everything (a full refund, or parts that reached what was paid), else the share
            $isFull = $share === null || !$share['paid']->isPositive() || $share['refunded']->isGreaterThanOrEqualTo($share['paid']);
            $target = fn (Money $amount) => $isFull ? $amount : self::prorate($amount, $share['refunded'], $share['paid']);
            $delta = [];
            foreach ($booked as $key => $amount) {
                $delta[$key] = Money::max(Money::zero(), $target($amount)->minus($already[$key]));
            }
            $deltaInvoices = [];
            foreach ($bookedInvoices as $invoiceId => $amount) {
                $take = Money::max(Money::zero(), $target($amount)->minus($alreadyInvoices[$invoiceId] ?? Money::zero()));
                if ($take->isPositive()) {
                    $deltaInvoices[$invoiceId] = $take;
                }
            }

            $debtBefore = Money::of($store->debt_balance);
            $debt = $debtBefore;
            $wallet = Money::of($store->wallet_balance);
            $label = $isFull ? "refunded order #{$order->short_code}" : "part of order #{$order->short_code} refunded";

            if ($delta['commission_wallet']->isPositive()) {
                $wallet = $this->bookRefund($store, $order, StoreWalletTransaction::class, $wallet, $delta['commission_wallet'], $performedBy, "Commission returned to the wallet for {$label}");
            }
            if ($delta['commission_debt']->isPositive()) {
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $delta['commission_debt']->negated(), $performedBy, "Commission reversed for {$label}");
            }
            if ($delta['payoff_commission']->isPositive()) {
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $delta['payoff_commission'], $performedBy, "Commission debt payoff reversed for {$label}");
            }
            $invoiceNumbers = $deltaInvoices !== [] ? \App\Models\SubscriptionInvoice::whereKey(array_keys($deltaInvoices))->pluck('number', 'id') : collect();
            foreach ($deltaInvoices as $invoiceId => $amount) {
                $number = $invoiceNumbers[$invoiceId] ?? "#{$invoiceId}";
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $amount, $performedBy, "Subscription invoice {$number} payoff reversed for {$label}", $invoiceId);
            }

            $fromWallet = Money::min($delta['settlement_wallet'], Money::max(Money::zero(), $wallet));
            $shortfall = $delta['settlement_wallet']->minus($fromWallet);
            if ($fromWallet->isPositive()) {
                $wallet = $this->bookRefund($store, $order, StoreWalletTransaction::class, $wallet, $fromWallet->negated(), $performedBy, "Settlement taken back for {$label}");
            }
            if ($shortfall->isPositive()) {
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $shortfall, $performedBy, "Settlement the wallet no longer covered, added to the debt, for {$label}");
            }

            // Same rules as a commission (blocks an active store at the limit) and a top-up (unblocks under it)
            $store->forceFill(['debt_balance' => $debt->toString(), 'wallet_balance' => $wallet->toString()])->save();
            // P4-23: the invoices this money had paid are unpaid again (a void one stays void: its share is commission debt)
            $reopened = [];
            foreach ($deltaInvoices as $invoiceId => $amount) {
                $again = $this->subscriptionDebt->reopen($store, $invoiceId, $amount, now());
                if ($again->isPositive()) {
                    $reopened[$invoiceId] = $again->toString();
                }
            }
            // P4-21: a debt that fell below its subscription part pays the oldest invoices from the credit
            $this->subscriptionDebt->settleFromCredit($store, now());
            $update = [];
            $blocked = $debt->isGreaterThan($debtBefore) && $this->hasStatus($store, StoreStatus::ACTIVE) && $this->exceedsCreditLimit($store, $debt);
            if ($blocked) {
                $update['status'] = StoreStatus::PAYMENT_REQUIRED->value;
            } elseif ($debt->isLessThan($debtBefore) && $this->hasStatus($store, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($store, $debt)) {
                $update['status'] = StoreStatus::ACTIVE->value;
            }
            if ($update !== []) {
                $store->forceFill($update)->save();
            }

            if ($blocked) {
                DB::afterCommit(fn () => event(new \App\Events\StoreBalanceLow($store)));
            }

            $reversedSomething = collect($delta)->contains(fn (Money $amount) => $amount->isPositive()) || $deltaInvoices !== [];
            if ($reversedSomething) {
                \App\Models\OrderLedgerReversal::create([
                    'order_id' => $order->id,
                    'store_id' => $store->id,
                    // A catch-up of a request that has its own row names it in the components (one row per request)
                    'order_refund_request_id' => $hasOwnRow ? null : ($share['request'] ?? null),
                    'is_full' => $isFull && !$hasFull,
                    'refunded_total' => $share !== null ? $share['refunded']->toString() : null,
                    'paid_total' => $share !== null ? $share['paid']->toString() : null,
                    'components' => array_map(fn (Money $amount) => $amount->toString(), $delta) + [
                        'payoff_invoices' => array_map(fn (Money $amount) => $amount->toString(), $deltaInvoices),
                        'added_to_debt' => $shortfall->toString(),
                        'invoices_reopened' => $reopened,
                    ] + ($hasOwnRow ? ['catch_up_for_request' => $share['request']] : []),
                    'performed_by' => $performedBy,
                ]);
            }

            // Review finding 1: the order is fully refunded (its own refund, or parts that reached everything paid): the
            // referral bonus it earned goes back in this same transaction, once (store → customer → referrer locks)
            if ($isFull) {
                $this->clawBackReferralReward($order, $performedBy);
            }

            return [
                'commission' => $delta['commission_wallet']->plus($delta['commission_debt']),
                'settlement' => $delta['settlement_wallet']->plus($delta['payoff_commission'])->plus(Money::sum(...array_values($deltaInvoices))),
                'added_to_debt' => $shortfall,
            ];
        });
    }

    /**
     * $amount × $refunded / $paid, rounded half-up to the tiyin once.
     */
    private static function prorate(Money $amount, Money $refunded, Money $paid): Money
    {
        return Money::of(\Brick\Math\BigDecimal::of($amount->toString())
            ->multipliedBy($refunded->toString())
            ->dividedBy($paid->toString(), Money::SCALE, \Brick\Math\RoundingMode::HalfUp));
    }

    /**
     * One REFUND row of the debt (StoreTransaction) or wallet (StoreWalletTransaction) ledger; returns the new balance.
     *
     * @param class-string<StoreTransaction|StoreWalletTransaction> $ledger
     */
    private function bookRefund(Store $store, \App\Models\Order $order, string $ledger, Money $before, Money $change, ?int $performedBy, string $notes, ?int $invoiceId = null): Money
    {
        $after = $before->plus($change);

        $ledger::create(($invoiceId !== null ? ['subscription_invoice_id' => $invoiceId] : []) + [
            'store_id' => $store->id,
            'type' => StoreTransactionType::REFUND->value,
            'amount' => $change->toString(),
            'balance_before' => $before->toString(),
            'balance_after' => $after->toString(),
            'reference_id' => $order->uuid,
            'order_id' => $order->id,
            'performed_by' => $performedBy,
            'notes' => $notes,
        ]);

        return $after;
    }

    /**
     * P4-23: a platform-collected order's money: the debt first (NettingService, one row per invoice and one for the
     * commission debt, each carrying the order), the rest to the wallet as the order's settlement.
     */
    private function settleWithNetting(Store $lockedStore, Money $settlementAmount, \App\Models\Order $order, ?int $performedBy): void
    {
        $provider = match ((string) $order->payment_method) {
            'keshbek' => 'Keshbek',
            'click' => 'Click',
            'payme' => 'Payme',
            'uzum' => 'Uzum',
            default => ucfirst((string) $order->payment_method),
        };

        $netting = $this->netting->payDebt(
            $lockedStore,
            $settlementAmount,
            "{$provider} payment for order #{$order->short_code}",
            ['order_id' => $order->id, 'reference_id' => $order->uuid],
            $performedBy,
        );

        $remainder = $netting->remainder();
        if ($remainder->isPositive()) {
            $this->addSettlement($lockedStore, $remainder, $order, null, $performedBy);
        }
    }

    // --- P1-23: subscription invoices (docs/BILLING.md) ---

    /**
     * Post a subscription invoice to the store's debt ledger exactly once: a SUBSCRIPTION row (positive amount, debt
     * up), never taken from the wallet, so the owner sees and settles it like the rest of the debt.
     *
     * P4-21 (owner decision 28.10.2026): it joins the subscription part of the debt and never blocks the store (the
     * credit limit counts only the commission debt); unpaid, it goes through the dunning routine instead. Credit the
     * store already has pays it at once.
     */
    public function chargeSubscription(\App\Models\SubscriptionInvoice $invoice, ?int $performedBy = null): StoreTransaction
    {
        $amount = Money::of($invoice->amount);

        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException("Subscription invoice {$invoice->number} has nothing to charge.");
        }

        return DB::transaction(function () use ($invoice, $amount, $performedBy) {
            // A deleted store's last months are billed too (as syncStatuses() sees its invoices), never a failing run
            $store = Store::withTrashed()->where('id', $invoice->store_id)->lock(Store::ledgerLock())->firstOrFail();

            $existing = $this->subscriptionRows($invoice)->where('amount', '>', 0)->first();
            if ($existing) {
                return $existing;
            }

            $before = Money::of($store->debt_balance);
            $after = $before->plus($amount);

            $transaction = StoreTransaction::create([
                'store_id' => $store->id,
                'type' => StoreTransactionType::SUBSCRIPTION->value,
                'amount' => $amount->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'reference_id' => $invoice->uuid,
                'performed_by' => $performedBy,
                'notes' => "Subscription invoice {$invoice->number} for {$invoice->period}",
            ]);

            $store->forceFill(['debt_balance' => $after->toString()])->save();
            $this->subscriptionDebt->charged($store, now());

            return $transaction;
        });
    }

    /**
     * Undo, exactly once, a voided invoice's charge: a negative SUBSCRIPTION row (debt down), unblocking the store
     * once its debt is back within the credit limit, like a top-up. Null when the invoice was never charged.
     * P4-21: the caller marks the invoice void first, so what was unpaid of it leaves the subscription part; what had
     * been paid of it becomes credit, which pays the store's other invoices.
     */
    public function reverseSubscriptionCharge(\App\Models\SubscriptionInvoice $invoice, ?int $performedBy = null, ?string $reason = null): ?StoreTransaction
    {
        return DB::transaction(function () use ($invoice, $performedBy, $reason) {
            $store = Store::withTrashed()->where('id', $invoice->store_id)->lock(Store::ledgerLock())->firstOrFail();

            $reversal = $this->subscriptionRows($invoice)->where('amount', '<', 0)->first();
            $charge = $this->subscriptionRows($invoice)->where('amount', '>', 0)->first();
            if ($reversal || !$charge) {
                return $reversal;
            }

            $amount = Money::of($charge->amount);
            $before = Money::of($store->debt_balance);
            $after = $before->minus($amount);

            $transaction = StoreTransaction::create([
                'store_id' => $store->id,
                'type' => StoreTransactionType::SUBSCRIPTION->value,
                'amount' => $amount->negated()->toString(),
                'balance_before' => $before->toString(),
                'balance_after' => $after->toString(),
                'reference_id' => $invoice->uuid,
                'performed_by' => $performedBy,
                'notes' => "Subscription invoice {$invoice->number} voided" . ($reason !== null && $reason !== '' ? ": {$reason}" : ''),
            ]);

            $store->forceFill(['debt_balance' => $after->toString()])->save();
            $this->subscriptionDebt->voided($store, now());
            if ($this->hasStatus($store, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($store, $after)) {
                $store->forceFill(['status' => StoreStatus::ACTIVE->value])->save();
            }

            return $transaction;
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<StoreTransaction>
     */
    private function subscriptionRows(\App\Models\SubscriptionInvoice $invoice): \Illuminate\Database\Eloquent\Builder
    {
        return StoreTransaction::where('store_id', $invoice->store_id)
            ->where('type', StoreTransactionType::SUBSCRIPTION->value)
            ->where('reference_id', $invoice->uuid);
    }

    // --- end P1-23 ---

    /**
     * P4-21: the rate of an order without a commission snapshot (only an in-memory order): its channel's rate.
     *
     * @return numeric-string
     */
    private function commissionRateOf(\App\Models\Order $order, Store $store): string
    {
        $policy = app(\App\Services\Finance\Billing\CommissionPolicy::class);

        return $policy->rateFor($store, $policy->channelOfOrder($order, $store));
    }

    private function minPayoutReserve(Store $store): Money
    {
        return Money::of($store->min_payout_reserve ?? 100000);
    }

    /**
     * Debt (positive balance) over abs(credit_limit) blocks the store; one at the limit does not. The same rule
     * CreateOrderAction uses to refuse orders (Store::exceedsCreditLimit).
     */
    private function exceedsCreditLimit(Store $store, Money $debt): bool
    {
        return $store->exceedsCreditLimit($debt);
    }

    private function hasStatus(Store $store, StoreStatus $status): bool
    {
        return ($store->status->value ?? $store->status) === $status->value;
    }
}
