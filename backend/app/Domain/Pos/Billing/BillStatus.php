<?php

namespace App\Domain\Pos\Billing;

/**
 * order_bills.status: `open` when made, `printed` once handed to the guest (the order is locked from then on), `paid`
 * when its payments cover it AND its sale receipt is issued, `void` when the order was billed again or cancelled.
 */
enum BillStatus: string
{
    case OPEN = 'open';
    case PRINTED = 'printed';
    case PAID = 'paid';
    case VOID = 'void';

    public function isPayable(): bool
    {
        return $this === self::OPEN || $this === self::PRINTED;
    }
}
