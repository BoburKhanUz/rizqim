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

    /**
     * Review P3-35: takes `amount` of the bill's nasiya charge off the debt again (a refund of the bill's credit
     * part), as an adjustment; `reference` makes it idempotent. Throws when the bill was not charged that much or the
     * debt left is smaller (the guest already repaid it).
     */
    public function reverse(string $accountUuid, Money $amount, string $billUuid, string $reference, int $approvedByUserId): void;
}
