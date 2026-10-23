<?php

namespace App\Domain\Pos\Contracts;

use App\Models\Order;
use App\Support\Money;

/**
 * Booking deposits (P3-17) as seen by billing (P3-18): what is left to offset on an order opened from a booking, and
 * the act of offsetting it when a bill is paid with PosTender::PREPAYMENT.
 */
interface AdvanceLedger
{
    public function available(Order $order): Money;

    /**
     * Marks `amount` of the order's deposits as applied to the bill; throws when more is asked than is available.
     */
    public function apply(Order $order, Money $amount, string $billUuid): void;

    /**
     * Review P3-35: gives back `amount` of what was offset against the bill (a refund of the bill's prepayment part):
     * the deposit holds it again, to be offset, refunded or retained. Throws when more is asked than the bill took.
     */
    public function reverse(Order $order, Money $amount, string $billUuid): void;
}
