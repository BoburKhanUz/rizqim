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
     * Throws when the account is unknown, blocked or over its limit. P6-44: a corporate account's charge names its
     * member by the talon code or phone (`memberCode`, `memberPhone`) and is refused over the member's daily limit or
     * meals per day and the account's monthly limit.
     */
    public function charge(string $accountUuid, Money $amount, string $billUuid, int $approvedByUserId, ?string $memberCode = null, ?string $memberPhone = null): void;

    /**
     * P6-44: whether charging this account at the till needs customer_accounts.manage or a manager's approval (nasiya:
     * always; a corporate account: when its `charge_approval` is on).
     */
    public function approvalRequired(string $accountUuid, int $storeId): bool;

    /**
     * Review P3-35: takes `amount` of the bill's nasiya charge off the debt again (a refund of the bill's credit
     * part), as an adjustment; `reference` makes it idempotent. Throws when the bill was not charged that much or the
     * debt left is smaller (the guest already repaid it).
     */
    public function reverse(string $accountUuid, Money $amount, string $billUuid, string $reference, int $approvedByUserId): void;
}
