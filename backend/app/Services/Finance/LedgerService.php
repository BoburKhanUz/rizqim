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

            $updateData = ['debt_balance' => $balanceAfter->toString()];

            if ($this->hasStatus($lockedStore, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($lockedStore, $balanceAfter)) {
                $updateData['status'] = StoreStatus::ACTIVE->value;
            }

            $lockedStore->forceFill($updateData)->save();

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
            : Money::of($order->total_price)->percent($store->commission_rate ?? 10);

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
     * arrives after delivery. Returns whether it credited now.
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

            if ($order->payment_method === 'keshbek') {
                // Cashback payment logic: Pay off debt first, remainder goes to wallet
                $this->settleKeshbek($lockedStore, $total, $order, $performedBy);
            } else {
                $this->addSettlement($lockedStore, $total, $order, null, $performedBy);
            }

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
     * from (wallet and/or debt), and a platform-collected order's money comes back from the store: a keshbek debt
     * payoff onto the debt again, the wallet settlement out of the wallet, and whatever the wallet no longer holds
     * added to the debt instead of overdrawing it. The referral reward stays (see processOrderDelivered).
     * Callers hold the order lock; StoreBalanceLow, if the debt now blocks the store, fires after commit.
     *
     * @return array{commission: Money, settlement: Money, added_to_debt: Money}
     */
    public function reverseDeliveredOrder(\App\Models\Order $order, ?int $performedBy = null): array
    {
        return DB::transaction(function () use ($order, $performedBy) {
            $store = Store::where('id', $order->store_id)->lockForUpdate()->firstOrFail();
            $debtRows = fn () => StoreTransaction::where('store_id', $store->id)->where('order_id', $order->id);
            $walletRows = fn () => StoreWalletTransaction::where('store_id', $store->id)->where('order_id', $order->id);

            if ($debtRows()->where('type', StoreTransactionType::REFUND->value)->exists()
                || $walletRows()->where('type', StoreTransactionType::REFUND->value)->exists()) {
                return ['commission' => Money::zero(), 'settlement' => Money::zero(), 'added_to_debt' => Money::zero()];
            }

            $commissionFromWallet = Money::of($walletRows()->where('type', StoreTransactionType::COMMISSION->value)->sum('amount'))->negated();
            $commissionFromDebt = Money::of($debtRows()->where('type', StoreTransactionType::COMMISSION->value)
                ->where(fn ($query) => $query->whereNull('notes')->orWhere('notes', 'not like', self::REFERRAL_NOTE . '%'))
                ->sum('amount'));
            $settledToWallet = Money::of($walletRows()->where('type', StoreTransactionType::SETTLEMENT->value)->sum('amount'));
            $paidOffDebt = Money::of($debtRows()->where('type', StoreTransactionType::TOPUP->value)->sum('amount'))->negated();

            $debtBefore = Money::of($store->debt_balance);
            $debt = $debtBefore;
            $wallet = Money::of($store->wallet_balance);
            $label = "refunded order #{$order->short_code}";

            if ($commissionFromWallet->isPositive()) {
                $wallet = $this->bookRefund($store, $order, StoreWalletTransaction::class, $wallet, $commissionFromWallet, $performedBy, "Commission returned to the wallet for {$label}");
            }
            if ($commissionFromDebt->isPositive()) {
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $commissionFromDebt->negated(), $performedBy, "Commission reversed for {$label}");
            }
            if ($paidOffDebt->isPositive()) {
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $paidOffDebt, $performedBy, "Keshbek debt payoff reversed for {$label}");
            }

            $fromWallet = Money::min($settledToWallet, Money::max(Money::zero(), $wallet));
            $shortfall = $settledToWallet->minus($fromWallet);
            if ($fromWallet->isPositive()) {
                $wallet = $this->bookRefund($store, $order, StoreWalletTransaction::class, $wallet, $fromWallet->negated(), $performedBy, "Settlement taken back for {$label}");
            }
            if ($shortfall->isPositive()) {
                $debt = $this->bookRefund($store, $order, StoreTransaction::class, $debt, $shortfall, $performedBy, "Settlement the wallet no longer covered, added to the debt, for {$label}");
            }

            // Same rules as a commission (blocks an active store at the limit) and a top-up (unblocks under it)
            $update = ['debt_balance' => $debt->toString(), 'wallet_balance' => $wallet->toString()];
            $blocked = $debt->isGreaterThan($debtBefore) && $this->hasStatus($store, StoreStatus::ACTIVE) && $this->exceedsCreditLimit($store, $debt);
            if ($blocked) {
                $update['status'] = StoreStatus::PAYMENT_REQUIRED->value;
            } elseif ($debt->isLessThan($debtBefore) && $this->hasStatus($store, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($store, $debt)) {
                $update['status'] = StoreStatus::ACTIVE->value;
            }
            $store->forceFill($update)->save();

            if ($blocked) {
                DB::afterCommit(fn () => event(new \App\Events\StoreBalanceLow($store)));
            }

            return [
                'commission' => $commissionFromWallet->plus($commissionFromDebt),
                'settlement' => $settledToWallet->plus($paidOffDebt),
                'added_to_debt' => $shortfall,
            ];
        });
    }

    /**
     * One REFUND row of the debt (StoreTransaction) or wallet (StoreWalletTransaction) ledger; returns the new balance.
     *
     * @param class-string<StoreTransaction|StoreWalletTransaction> $ledger
     */
    private function bookRefund(Store $store, \App\Models\Order $order, string $ledger, Money $before, Money $change, ?int $performedBy, string $notes): Money
    {
        $after = $before->plus($change);

        $ledger::create([
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

    private function settleKeshbek(Store $lockedStore, Money $settlementAmount, \App\Models\Order $order, ?int $performedBy): void
    {
        $debt = Money::of($lockedStore->debt_balance);

        if ($debt->isPositive()) {
            $amountToPayOff = Money::min($settlementAmount, $debt);
            $remainingToWallet = $settlementAmount->minus($amountToPayOff);

            // Reduce debt
            $balanceBefore = $debt;
            $balanceAfter = $balanceBefore->minus($amountToPayOff);

            StoreTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::TOPUP->value,
                'amount' => $amountToPayOff->negated()->toString(),
                'balance_before' => $balanceBefore->toString(),
                'balance_after' => $balanceAfter->toString(),
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => "Keshbek payment covering restaurant debt for order #{$order->short_code}",
            ]);

            $updateData = ['debt_balance' => $balanceAfter->toString()];

            if ($this->hasStatus($lockedStore, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($lockedStore, $balanceAfter)) {
                $updateData['status'] = StoreStatus::ACTIVE->value;
            }
            $lockedStore->forceFill($updateData)->save();

            // Add remaining to wallet if any
            if ($remainingToWallet->isPositive()) {
                $walletBefore = Money::of($lockedStore->wallet_balance);
                $walletAfter = $walletBefore->plus($remainingToWallet);

                StoreWalletTransaction::create([
                    'store_id' => $lockedStore->id,
                    'type' => StoreTransactionType::SETTLEMENT->value,
                    'amount' => $remainingToWallet->toString(),
                    'balance_before' => $walletBefore->toString(),
                    'balance_after' => $walletAfter->toString(),
                    'reference_id' => $order->uuid,
                    'order_id' => $order->id,
                    'performed_by' => $performedBy,
                    'notes' => "Keshbek payment remainder settlement for order #{$order->short_code}",
                ]);

                $lockedStore->forceFill(['wallet_balance' => $walletAfter->toString()])->save();
            }
        } else {
            // No debt, goes directly to wallet
            $walletBefore = Money::of($lockedStore->wallet_balance);
            $walletAfter = $walletBefore->plus($settlementAmount);

            StoreWalletTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::SETTLEMENT->value,
                'amount' => $settlementAmount->toString(),
                'balance_before' => $walletBefore->toString(),
                'balance_after' => $walletAfter->toString(),
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => "Keshbek payment settlement for order #{$order->short_code}",
            ]);

            $lockedStore->forceFill(['wallet_balance' => $walletAfter->toString()])->save();
        }
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
