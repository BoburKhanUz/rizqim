<?php

namespace App\Domain\Pos\Contracts;

use App\Support\Money;

/**
 * Customer credit (nasiya) as seen by billing (P3-18): closing a bill with PosTender::ACCOUNT charges the account
 * (P3-17 owns accounts, limits and repayments with credit receipts).
 */
interface CustomerAccountLedger
{
    /**
     * Throws when the account is unknown, blocked or over its limit.
     */
    public function charge(string $accountUuid, Money $amount, string $billUuid, int $approvedByUserId): void;
}
