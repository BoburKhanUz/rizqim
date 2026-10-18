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
}
