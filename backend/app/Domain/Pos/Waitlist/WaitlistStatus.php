<?php

namespace App\Domain\Pos\Waitlist;

/**
 * A waitlist entry's life (P6-24): waiting → seated (a table was given, maybe with its order) | canceled (the guest
 * left or changed their mind) | no_show (called, never came). Only a waiting entry changes.
 */
enum WaitlistStatus: string
{
    case WAITING = 'waiting';
    case SEATED = 'seated';
    case CANCELED = 'canceled';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::WAITING => 'Kutmoqda',
            self::SEATED => "Joylashtirildi",
            self::CANCELED => 'Bekor qilindi',
            self::NO_SHOW => 'Kelmadi',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::WAITING;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
