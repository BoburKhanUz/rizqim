<?php

namespace App\Domain\Pos\Booking;

/**
 * What the booking is for (research report §3): helps the kitchen and the owner see the load ahead.
 */
enum BookingEventType: string
{
    case GAP = 'gap';
    case ZIYOFAT = 'ziyofat';
    case BIRTHDAY = 'tugilgan_kun';
    case NAHOR_OSHI = 'nahor_oshi';
    case CORPORATE = 'korporativ';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::GAP => 'Gap',
            self::ZIYOFAT => 'Ziyofat',
            self::BIRTHDAY => "Tug'ilgan kun",
            self::NAHOR_OSHI => 'Nahor oshi',
            self::CORPORATE => 'Korporativ',
            self::OTHER => 'Boshqa',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
