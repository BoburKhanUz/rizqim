<?php

namespace App\Domain\Pos\Vouchers;

use App\Domain\Pos\Billing\PosActionException;

/**
 * A voucher rule refused the action (unknown code, another store's, blocked, expired, not enough on it, used on the
 * bill already, a promo code that does not apply). An Uzbek message for the cashier and a stable `reason` code.
 */
class VoucherException extends PosActionException
{
    public static function notFound(): self
    {
        return new self('voucher_not_found', 'Sertifikat topilmadi.', 404);
    }
}
