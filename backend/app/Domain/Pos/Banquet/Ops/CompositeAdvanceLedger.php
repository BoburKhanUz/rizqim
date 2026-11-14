<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Booking\BookingAdvanceLedger;
use App\Models\Order;
use App\Support\Money;

/**
 * P6-54: billing's AdvanceLedger (PosTender::PREPAYMENT) for both kinds of advance: the booking deposits ledger it
 * extends (every order with a booking, and every order that is not an event's), and an event's order (banquet_events.
 * order_id), which offsets the event's schedule payments (BanquetPrepaymentLedger).
 */
final class CompositeAdvanceLedger extends BookingAdvanceLedger
{
    public function __construct(
        private readonly BanquetPrepaymentLedger $banquets,
    ) {}

    public function available(Order $order): Money
    {
        return $this->isBanquet($order) ? $this->banquets->available($order) : parent::available($order);
    }

    public function apply(Order $order, Money $amount, string $billUuid): void
    {
        $this->isBanquet($order) ? $this->banquets->apply($order, $amount, $billUuid) : parent::apply($order, $amount, $billUuid);
    }

    public function reverse(Order $order, Money $amount, string $billUuid): void
    {
        $this->isBanquet($order) ? $this->banquets->reverse($order, $amount, $billUuid) : parent::reverse($order, $amount, $billUuid);
    }

    private function isBanquet(Order $order): bool
    {
        return $order->getAttribute('booking_id') === null && BanquetPrepaymentLedger::eventOf($order) !== null;
    }
}
