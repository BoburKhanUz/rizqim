<?php

namespace App\Domain\Pos\Contracts;

use App\Support\Money;

/**
 * P6-43: gift certificates as seen by billing: paying a bill with PosTender::VOUCHER takes the amount off the voucher
 * (its money was received, and receipted as an advance, when it was sold), and a refund of that part gives it back to
 * the voucher (never cash). App\Domain\Pos\Vouchers owns the vouchers.
 */
interface VoucherLedger
{
    /**
     * Takes `amount` off the store's voucher with this code for the bill, under the voucher's lock (two tills spending
     * it at once: one wins, the other 409 `voucher_insufficient`). Throws App\Domain\Pos\Vouchers\VoucherException:
     * 404 `voucher_not_found` (unknown, another store's), 409 `voucher_blocked`, `voucher_expired`, `voucher_used`,
     * `voucher_insufficient`, `voucher_already_on_bill` (one redemption per voucher and bill). Call it in the
     * transaction that records the payment.
     *
     * @return array{uuid: string, code: string, form: string, balance_after: string} `form`: the sale's payment form
     *                                                                                 (the advance receipt's)
     */
    public function redeem(int $storeId, string $code, Money $amount, string $billUuid, ?int $branchId, ?int $userId, ?int $deviceId, ?int $shiftId): array;

    /**
     * Gives `amount` of what the bill took back to the voucher (a refund of the bill's voucher part); `refundUuid`
     * makes it idempotent. Throws when more is asked than the bill took from it.
     */
    public function reverse(string $voucherUuid, Money $amount, string $billUuid, string $refundUuid, ?int $userId, ?int $deviceId, ?int $shiftId): void;
}
