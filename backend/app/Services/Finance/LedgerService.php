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
    // A referral reward's cost is booked as a COMMISSION row of the referred order; its note tells it apart.
    private const REFERRAL_NOTE = 'Referral cashback reward cost';

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
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

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
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

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
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

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
            $store = Store::where('id', $topup->store_id)->lockForUpdate()->firstOrFail();

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
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();
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
     * referrer's reward for a referred customer's first delivered order. Idempotent, part by part, so a booking that
     * failed can simply be run again (SettlementRecoveryService); callers run it in a transaction (savepoint) of its
     * own, so a failure books nothing at all.
     */
    public function processOrderDelivered(\App\Models\Order $order, ?int $performedBy = null): void
    {
        $store = $order->store;
        // Charge the snapshot taken at order time as-is (0 is valid); the rate is only a fallback for an in-memory order without the attribute.
        $commissionAmount = isset($order->commission_amount)
            ? Money::of($order->commission_amount)
            : Money::of($order->total_price)->percent($this->commissionRateOf($order, $store)); // the same resolved rate as checkout

        // 1. Always deduct commission for every order to log the commission transaction
        if ($commissionAmount->isPositive() && !$this->hasCommission($order)) {
            $this->deductCommission($store, $commissionAmount, $order, null, $performedBy);
        }

        // 2. Settle full amount if collected by the platform
        $this->settlePlatformOrder($order, $performedBy);

        // 3. Referral reward; a failure fails the whole booking, which is then retried like any other
        $user = $order->user;
        if (!$user || !$user->referred_by || Money::of($order->total_price)->isLessThan(50000)
            || $this->hasReferralReward($order) || !$this->isFirstDelivery($order)) {
            return;
        }

        $referrer = \App\Models\User::find($user->referred_by);
        if (!$referrer) {
            return;
        }

        // Store row first, then the user, like every other ledger path; both or neither
        DB::transaction(function () use ($store, $order, $user, $referrer, $performedBy) {
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

            // 1. Award 5000 cashback points to referrer
            $referrer->increment('balance', 5000);

            // 2. Charge 5000 to store's debit/balance
            $balanceBefore = Money::of($lockedStore->debt_balance);
            $balanceAfter = $balanceBefore->plus(5000);

            StoreTransaction::create([
                'store_id' => $store->id,
                'type' => StoreTransactionType::COMMISSION->value,
                'amount' => Money::of(5000)->toString(),
                'balance_before' => $balanceBefore->toString(),
                'balance_after' => $balanceAfter->toString(),
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => self::REFERRAL_NOTE . " for referred user #{$user->id}",
            ]);

            $lockedStore->forceFill(['debt_balance' => $balanceAfter->toString()])->save();
        });
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

    private function hasReferralReward(\App\Models\Order $order): bool
    {
        return StoreTransaction::where('order_id', $order->id)
            ->where('type', StoreTransactionType::COMMISSION->value)
            ->where('notes', 'like', self::REFERRAL_NOTE . '%')
            ->exists();
    }

    /**
     * Whether no other order of the customer was delivered before this one (a refunded one was delivered too, and
     * one without a recorded delivery counts as earlier). Decided by the order of the delivered status history rows,
     * so a booking retried after the customer's next order was delivered still sees this one as the first.
     */
    private function isFirstDelivery(\App\Models\Order $order): bool
    {
        $delivered = \App\Enums\OrderStatus::DELIVERED->value;
        $deliveredAs = \App\Models\OrderStatusHistory::where('order_id', $order->id)->where('status', $delivered)->min('id');

        return !\App\Models\Order::where('user_id', $order->user_id)
            ->whereKeyNot($order->id)
            ->whereIn('status', [$delivered, \App\Enums\OrderStatus::REFUNDED->value])
            ->when($deliveredAs !== null, fn ($query) => $query->whereDoesntHave(
                'statusHistories',
                fn ($history) => $history->where('status', $delivered)->where('id', '>', $deliveredAs)
            ))
            ->exists();
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
            $lockedStore = Store::where('id', $order->store_id)->lockForUpdate()->firstOrFail();

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
     * instead of overdrawing it. The referral reward stays (see processOrderDelivered). P4-23: after parts reversed
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
        return $this->reverseDelivery($order, ['request' => $refundRequestId, 'refunded' => $refunded, 'paid' => $paid], $performedBy);
    }

    /**
     * @param  array{request: int, refunded: Money, paid: Money}|null  $share  null: the whole order
     * @return array{commission: Money, settlement: Money, added_to_debt: Money}
     */
    private function reverseDelivery(\App\Models\Order $order, ?array $share, ?int $performedBy): array
    {
        return DB::transaction(function () use ($order, $share, $performedBy) {
            $store = Store::where('id', $order->store_id)->lockForUpdate()->firstOrFail();
            $nothing = ['commission' => Money::zero(), 'settlement' => Money::zero(), 'added_to_debt' => Money::zero()];
            $debtRows = fn () => StoreTransaction::where('store_id', $store->id)->where('order_id', $order->id);
            $walletRows = fn () => StoreWalletTransaction::where('store_id', $store->id)->where('order_id', $order->id);

            $reversals = \App\Models\OrderLedgerReversal::where('order_id', $order->id)->lockForUpdate()->get();
            if ($reversals->contains(fn ($reversal) => $reversal->is_full)
                || ($share !== null && $reversals->contains(fn ($reversal) => (int) $reversal->order_refund_request_id === $share['request']))) {
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
                    'order_refund_request_id' => $share['request'] ?? null,
                    'is_full' => $isFull,
                    'refunded_total' => $share !== null ? $share['refunded']->toString() : null,
                    'paid_total' => $share !== null ? $share['paid']->toString() : null,
                    'components' => array_map(fn (Money $amount) => $amount->toString(), $delta) + [
                        'payoff_invoices' => array_map(fn (Money $amount) => $amount->toString(), $deltaInvoices),
                        'added_to_debt' => $shortfall->toString(),
                        'invoices_reopened' => $reopened,
                    ],
                    'performed_by' => $performedBy,
                ]);
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
            $store = Store::withTrashed()->where('id', $invoice->store_id)->lockForUpdate()->firstOrFail();

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
            $store = Store::withTrashed()->where('id', $invoice->store_id)->lockForUpdate()->firstOrFail();

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
