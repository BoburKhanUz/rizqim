<?php

namespace App\Services\Finance;

use App\Models\Store;
use App\Models\StoreTransaction;
use App\Models\StoreWalletTransaction;
use App\Enums\StoreTransactionType;
use Illuminate\Support\Facades\DB;
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

                $lockedStore->update(['wallet_balance' => $walletAfter]);
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

                $lockedStore->update(['balance' => $balanceAfter]);

                // Check if credit limit exceeded (using absolute value of credit_limit)
                $limit = abs($lockedStore->credit_limit);
                if ($balanceAfter >= $limit) {
                    $lockedStore->update([
                        'status' => \App\Enums\StoreStatus::PAYMENT_REQUIRED->value,
                    ]);
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

            $lockedStore->update(['wallet_balance' => $balanceAfter]);

            return $transaction;
        });
    }

    /**
     * Process all financial logic when an order is delivered
     */
    public function processOrderDelivered(\App\Models\Order $order, ?int $performedBy = null): void
    {
        $store = $order->store;
        $commissionRate = $store->commission_rate ?? 10;
        $commissionAmount = $order->total_price * ($commissionRate / 100);

        // 1. Always deduct commission for every order to log the commission transaction
        if ($commissionAmount > 0) {
            $this->deductCommission($store, $commissionAmount, $order, null, $performedBy);
        }

        // 2. Settle full amount if collected by the platform
        if ($order->payment_owner === 'PLATFORM') {
            $settlementAmount = $order->total_price;
            if ($settlementAmount > 0) {
                if ($order->payment_method === 'keshbek') {
                    // Cashback payment logic: Pay off debt first, remainder goes to wallet
                    DB::transaction(function () use ($store, $settlementAmount, $order, $performedBy) {
                        $lockedStore = Store::where('id', $store->id)->lockForUpdate()->firstOrFail();
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
                            
                            $limit = abs($lockedStore->credit_limit);
                            if ($lockedStore->status === \App\Enums\StoreStatus::PAYMENT_REQUIRED->value && ($balanceAfter < $limit || $balanceAfter <= -100000)) {
                                $updateData['status'] = \App\Enums\StoreStatus::ACTIVE->value;
                            }
                            $lockedStore->update($updateData);

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

                                $lockedStore->update(['wallet_balance' => $walletAfter]);
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

                            $lockedStore->update(['wallet_balance' => $walletAfter]);
                        }
                    });
                } else {
                    $this->addSettlement($store, $settlementAmount, $order, null, $performedBy);
                }
            }
        }

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

                            $lockedStore->update(['balance' => $balanceAfter]);
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to process referral reward for order {$order->uuid}: " . $e->getMessage());
        }
    }
}
