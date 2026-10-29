<?php

namespace App\Domain\Pos\StaffPay;

/**
 * P6-23: what a guarantee is guaranteed for: each shift worked (compared day by day), or the whole pay period.
 */
enum GuaranteePer: string
{
    case SHIFT = 'shift';
    case PERIOD = 'period';

    public function label(): string
    {
        return match ($this) {
            self::SHIFT => 'Har smena uchun',
            self::PERIOD => 'Butun davr uchun',
        };
    }
}
