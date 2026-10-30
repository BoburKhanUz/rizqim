<?php

namespace App\Domain\Pos\Booking;

/**
 * P6-26: how a branch's table booking deposit is worked out (branch_booking_rules.deposit_mode): none, one amount
 * for the booking, or an amount per planned guest.
 */
enum BookingDepositMode: string
{
    case OFF = 'off';
    case FLAT = 'flat';
    case PER_GUEST = 'per_guest';

    public function label(): string
    {
        return match ($this) {
            self::OFF => "Bo'nak olinmaydi",
            self::FLAT => "Bron uchun bir xil bo'nak",
            self::PER_GUEST => "Har bir mehmon uchun bo'nak",
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $mode) => $mode->value, self::cases());
    }
}
