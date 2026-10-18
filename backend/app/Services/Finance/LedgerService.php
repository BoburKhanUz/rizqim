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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class LedgerService
{
    /**
     * Deduct commission from the Store Balance (Store owes Platform)
     */
    public function deductCommission(Store $store, float $amount, \App\Models\Order $order, ?string $paymentId = null, ?int $performedBy = null): ?StoreTransaction
    {
        return DB::transaction(function () use ($store, $amount, $order, $paymentId, $performedBy) {
            // Lock the store row for update to prevent race conditions
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

            // Idempotency check: Have we already deducted commission for this order?
            $existsMain = StoreTransaction::where('store_id', $store->id)
                ->where('order_id', $order->id)
                ->where('type', StoreTransactionType::COMMISSION->value)
                ->exists();

            $existsWallet = StoreWalletTransaction::where('store_id', $store->id)
                ->where('order_id', $order->id)
                ->where('type', StoreTransactionType::COMMISSION->value)
                ->exists();

            if ($existsMain || $existsWallet) {
                throw new Exception("Commission already deducted for order: {$order->id}");
            }

            $walletBalance = $lockedStore->wallet_balance;
            $amountToDeductFromWallet = 0.0;
            $amountToAddToDebt = 0.0;

            if ($walletBalance > 0) {
                if ($amount <= $walletBalance) {
                    $amountToDeductFromWallet = $amount;
                    $amountToAddToDebt = 0.0;
                } else {
                    $amountToDeductFromWallet = $walletBalance;
                    $amountToAddToDebt = $amount - $walletBalance;
                }
            } else {
                $amountToAddToDebt = $amount;
            }

            // 1. Deduct from wallet if any
            if ($amountToDeductFromWallet > 0) {
                $walletBefore = $lockedStore->wallet_balance;
                $walletAfter = $walletBefore - $amountToDeductFromWallet;

                StoreWalletTransaction::create([
                    'store_id' => $lockedStore->id,
                    'type' => StoreTransactionType::COMMISSION->value,
                    'amount' => -$amountToDeductFromWallet, // negative shows deduction
                    'balance_before' => $walletBefore,
                    'balance_after' => $walletAfter,
                    'reference_id' => $order->uuid,
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'performed_by' => $performedBy,
                    'notes' => "Commission deduction from wallet for order #{$order->short_code}",
                ]);

                $lockedStore->forceFill(['wallet_balance' => $walletAfter])->save();
            }

            // 2. Add to debt balance if any
            $transaction = null;
            if ($amountToAddToDebt > 0) {
                $balanceBefore = $lockedStore->balance;
                // balance is positive for debt. Commission INCREASES debt.
                $balanceAfter = $balanceBefore + $amountToAddToDebt;

                $transaction = StoreTransaction::create([
                    'store_id' => $lockedStore->id,
                    'type' => StoreTransactionType::COMMISSION->value,
                    'amount' => $amountToAddToDebt, // positive means commission added to debt
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reference_id' => $order->uuid,
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'performed_by' => $performedBy,
                    'notes' => "Commission deduction for order #{$order->short_code}",
                ]);

                $lockedStore->forceFill(['balance' => $balanceAfter])->save();

                // Block an active store once its debt reaches the credit limit; other states (e.g. a finance hold) are kept.
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
    public function addSettlement(Store $store, float $amount, \App\Models\Order $order, ?string $paymentId = null, ?int $performedBy = null): StoreWalletTransaction
    {
        return DB::transaction(function () use ($store, $amount, $order, $paymentId, $performedBy) {
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

            $exists = StoreWalletTransaction::where('store_id', $store->id)
                ->where('order_id', $order->id)
                ->where('type', StoreTransactionType::SETTLEMENT->value)
                ->exists();

            if ($exists) {
                throw new Exception("Settlement already added for order: {$order->id}");
            }

            $balanceBefore = $lockedStore->wallet_balance;
            $balanceAfter = $balanceBefore + $amount;

            $transaction = StoreWalletTransaction::create([
                'store_id' => $store->id,
                'type' => StoreTransactionType::SETTLEMENT->value,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'payment_id' => $paymentId,
                'performed_by' => $performedBy,
                'notes' => "Settlement added for order #{$order->short_code}",
            ]);

            $lockedStore->forceFill(['wallet_balance' => $balanceAfter])->save();

            return $transaction;
        });
    }

    /**
     * Reduce the store's debt by a payment and unblock it once the debt is back under its credit limit.
     */
    public function reduceDebt(Store $store, float $amount, string $notes, ?string $referenceId = null, ?int $performedBy = null): StoreTransaction
    {
        return DB::transaction(function () use ($store, $amount, $notes, $referenceId, $performedBy) {
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();

            $balanceBefore = (float) $lockedStore->balance;
            $balanceAfter = $balanceBefore - $amount; // reduce debt

            $transaction = StoreTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::TOPUP->value,
                'amount' => -$amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'notes' => $notes,
                'reference_id' => $referenceId,
                'performed_by' => $performedBy,
            ]);

            $updateData = ['balance' => $balanceAfter];

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

            return $existing ?? $this->reduceDebt($store, (float) $topup->amount, "Online top-up via {$providerLabel}", $topup->uuid);
        });
    }

    /**
     * Amount the owner may still request: wallet balance minus pending payouts minus the reserve.
     * Callers must hold the lock on the store row.
     */
    public function availableForPayout(Store $store): float
    {
        $pending = (float) PayoutRequest::where('store_id', $store->id)
            ->where('status', 'pending')
            ->sum('amount');

        $minReserve = (float) ($store->min_payout_reserve ?? 100000.00);

        return max(0.0, round((float) $store->wallet_balance - $pending - $minReserve, 2));
    }

    /**
     * Create a pending payout request (API and owner panel). Throws \DomainException with a user-facing
     * message when the card is invalid or the wallet minus pending payouts minus the reserve cannot cover it.
     */
    public function requestPayout(Store $store, float $amount, string $cardNumber, ?string $notes = null): PayoutRequest
    {
        if (!preg_match('/^\d{16}$/', $cardNumber)) {
            throw new \DomainException('Karta raqami 16 ta raqamdan iborat bo\'lishi kerak.');
        }

        return DB::transaction(function () use ($store, $amount, $cardNumber, $notes) {
            // Lock the store so parallel requests cannot each pass the check and together exceed the wallet.
            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();
            $minReserve = (float) ($lockedStore->min_payout_reserve ?? 100000.00);

            if ((float) $lockedStore->wallet_balance < $minReserve) {
                throw new \DomainException('Mablag\' yechish uchun hamyonda kamida ' . number_format($minReserve) . ' UZS qoldiq bo\'lishi shart.');
            }

            $available = $this->availableForPayout($lockedStore);
            if ($amount > $available) {
                throw new \DomainException("Yechib olingandan so'ng hamyonda kamida " . number_format($minReserve) . " UZS qolishi shart. Kutilayotgan so'rovlarni hisobga olganda siz ko'pi bilan " . number_format($available) . " UZS yechishingiz mumkin.");
            }

            return PayoutRequest::create([
                'store_id' => $lockedStore->id,
                'amount' => $amount,
                'card_number' => $cardNumber,
                'notes' => $notes,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Process all financial logic when an order is delivered
     */
    public function processOrderDelivered(\App\Models\Order $order, ?int $performedBy = null): void
    {
        $store = $order->store;
        $commissionRate = $store->commission_rate ?? 10;
        // Charge the snapshot taken at order time as-is (0 is valid); the rate is only a fallback for an in-memory order without the attribute.
        $commissionAmount = $order->commission_amount !== null
            ? (float) $order->commission_amount
            : round($order->total_price * $commissionRate / 100, 2);

        // 1. Always deduct commission for every order to log the commission transaction
        if ($commissionAmount > 0) {
            $this->deductCommission($store, $commissionAmount, $order, null, $performedBy);
        }

        // 2. Settle full amount if collected by the platform
        $this->settlePlatformOrder($order, $performedBy);

        // --- Referral & Cashback Processing ---
        try {
            $user = $order->user;
            if ($user && $user->referred_by && $order->total_price >= 50000) {
                // Check if this is the customer's first completed/delivered order
                $completedOrdersCount = \App\Models\Order::where('user_id', $user->id)
                    ->where('status', \App\Enums\OrderStatus::DELIVERED->value)
                    ->count();

                if ($completedOrdersCount === 1) {
                    $referrer = \App\Models\User::find($user->referred_by);
                    if ($referrer) {
                        // 1. Award 5000 cashback points to referrer
                        $referrer->increment('balance', 5000);

                        // 2. Charge 5000 to store's debit/balance
                        DB::transaction(function () use ($store, $order, $user, $performedBy) {
                            $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();
                            $balanceBefore = $lockedStore->balance;
                            $balanceAfter = $balanceBefore + 5000;

                            StoreTransaction::create([
                                'store_id' => $store->id,
                                'type' => StoreTransactionType::COMMISSION->value,
                                'amount' => 5000,
                                'balance_before' => $balanceBefore,
                                'balance_after' => $balanceAfter,
                                'reference_id' => $order->uuid,
                                'order_id' => $order->id,
                                'performed_by' => $performedBy,
                                'notes' => "Referral cashback reward cost for referred user #{$user->id}",
                            ]);

                            $lockedStore->forceFill(['balance' => $balanceAfter])->save();
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to process referral reward for order {$order->uuid}: " . $e->getMessage());
        }
    }

    /**
     * Credit a paid, platform-collected order to its store exactly once: at delivery, or when the payment only
     * arrives after delivery. Returns whether it credited now.
     */
    public function settlePlatformOrder(\App\Models\Order $order, ?int $performedBy = null): bool
    {
        if ($order->payment_owner !== 'PLATFORM' || (float) $order->total_price <= 0) {
            return false;
        }

        if ($order->payment_status !== PaymentStatus::PAID) {
            // Never credit the wallet for money the platform has not received (e.g. an unpaid online order marked delivered).
            Log::warning("Settlement skipped for unpaid platform order {$order->uuid}");
            return false;
        }

        return DB::transaction(function () use ($order, $performedBy) {
            $lockedStore = Store::where('id', $order->store_id)->lockForUpdate()->firstOrFail();

            if ($this->hasSettlement($order)) {
                return false;
            }

            if ($order->payment_method === 'keshbek') {
                // Cashback payment logic: Pay off debt first, remainder goes to wallet
                $this->settleKeshbek($lockedStore, (float) $order->total_price, $order, $performedBy);
            } else {
                $this->addSettlement($lockedStore, (float) $order->total_price, $order, null, $performedBy);
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

    private function settleKeshbek(Store $lockedStore, float $settlementAmount, \App\Models\Order $order, ?int $performedBy): void
    {
        $debt = $lockedStore->balance;

        if ($debt > 0) {
            if ($settlementAmount <= $debt) {
                $amountToPayOff = $settlementAmount;
                $remainingToWallet = 0.0;
            } else {
                $amountToPayOff = $debt;
                $remainingToWallet = $settlementAmount - $debt;
            }

            // Reduce debt
            $balanceBefore = $lockedStore->balance;
            $balanceAfter = $balanceBefore - $amountToPayOff;

            StoreTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::TOPUP->value,
                'amount' => -$amountToPayOff,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => "Keshbek payment covering restaurant debt for order #{$order->short_code}",
            ]);

            $updateData = ['balance' => $balanceAfter];

            if ($this->hasStatus($lockedStore, StoreStatus::PAYMENT_REQUIRED) && !$this->exceedsCreditLimit($lockedStore, $balanceAfter)) {
                $updateData['status'] = StoreStatus::ACTIVE->value;
            }
            $lockedStore->forceFill($updateData)->save();

            // Add remaining to wallet if any
            if ($remainingToWallet > 0) {
                $walletBefore = $lockedStore->wallet_balance;
                $walletAfter = $walletBefore + $remainingToWallet;

                StoreWalletTransaction::create([
                    'store_id' => $lockedStore->id,
                    'type' => StoreTransactionType::SETTLEMENT->value,
                    'amount' => $remainingToWallet,
                    'balance_before' => $walletBefore,
                    'balance_after' => $walletAfter,
                    'reference_id' => $order->uuid,
                    'order_id' => $order->id,
                    'performed_by' => $performedBy,
                    'notes' => "Keshbek payment remainder settlement for order #{$order->short_code}",
                ]);

                $lockedStore->forceFill(['wallet_balance' => $walletAfter])->save();
            }
        } else {
            // No debt, goes directly to wallet
            $walletBefore = $lockedStore->wallet_balance;
            $walletAfter = $walletBefore + $settlementAmount;

            StoreWalletTransaction::create([
                'store_id' => $lockedStore->id,
                'type' => StoreTransactionType::SETTLEMENT->value,
                'amount' => $settlementAmount,
                'balance_before' => $walletBefore,
                'balance_after' => $walletAfter,
                'reference_id' => $order->uuid,
                'order_id' => $order->id,
                'performed_by' => $performedBy,
                'notes' => "Keshbek payment settlement for order #{$order->short_code}",
            ]);

            $lockedStore->forceFill(['wallet_balance' => $walletAfter])->save();
        }
    }

    /**
     * Debt (positive balance) at or above abs(credit_limit) blocks the store — the same rule CreateOrderAction uses to refuse orders.
     */
    private function exceedsCreditLimit(Store $store, float $debt): bool
    {
        return $debt >= abs((float) $store->credit_limit);
    }

    private function hasStatus(Store $store, StoreStatus $status): bool
    {
        return ($store->status->value ?? $store->status) === $status->value;
    }
}
