<?php

namespace App\Domain\Pos\Booking;

/**
 * Where the booking came from (choyxonas book by phone; Telegram and Instagram messages; a guest booking in person).
 */
enum BookingSource: string
{
    case PHONE = 'phone';
    case TELEGRAM = 'telegram';
    case INSTAGRAM = 'instagram';
    case WALK_IN = 'walk_in';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PHONE => 'Telefon',
            self::TELEGRAM => 'Telegram',
            self::INSTAGRAM => 'Instagram',
            self::WALK_IN => 'Kelib band qildi',
            self::OTHER => 'Boshqa',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $source) => $source->value, self::cases());
    }
}
