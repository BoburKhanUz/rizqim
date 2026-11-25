<?php

namespace App\Enums;

enum StoreTransactionType: string
{
    case COMMISSION = 'commission'; // Order commission deduction (balance down)
    case TOPUP = 'topup';           // Store pays platform (balance up)
    case SETTLEMENT = 'settlement'; // Platform collects online payment on behalf of store (wallet up)
    case PAYOUT = 'payout';         // Platform pays store (wallet down)
    case REFUND = 'refund';         // Order refund
    case ADJUSTMENT = 'adjustment'; // Manual fix
    case SUBSCRIPTION = 'subscription'; // P1-23: monthly tariff invoice (debt up); its void books a negative row (debt down)
    // Owner decision 3 (addition): a referral bonus RIZQIM paid out of its own commission income. It never moves the
    // store's money (balance_before = balance_after); `amount` is what the platform paid the referrer. It is the
    // exactly-once marker of the bonus (LedgerService::grantReferralReward), never shown to the store as a charge
    case REFERRAL_BONUS = 'referral_bonus';
}
