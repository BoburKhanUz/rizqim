<?php

namespace App\Domain\Pos\Booking;

/**
 * A deposit: `taken` (an ADVANCE receipt was issued; part of it may already be offset, see applied_amount),
 * `applied` (fully offset against the order's bills), `refunded` (what was left went back to the guest) or `retained`
 * (what was left was kept after a cancellation or no-show, with a manager's approval).
 */
enum BookingPaymentStatus: string
{
    case TAKEN = 'taken';
    case APPLIED = 'applied';
    case REFUNDED = 'refunded';
    case RETAINED = 'retained';

    public function label(): string
    {
        return match ($this) {
            self::TAKEN => 'Olingan',
            self::APPLIED => 'Hisobdan ayirilgan',
            self::REFUNDED => 'Qaytarilgan',
            self::RETAINED => 'Ushlab qolingan',
        };
    }
}
