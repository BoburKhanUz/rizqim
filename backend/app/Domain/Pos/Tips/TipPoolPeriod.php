<?php

namespace App\Domain\Pos\Tips;

/**
 * P6-22: what one split of the pool covers: a business day of the branch (Asia/Tashkent, from the branch's day start)
 * or one till shift.
 */
enum TipPoolPeriod: string
{
    case DAY = 'day';
    case SHIFT = 'shift';

    public function label(): string
    {
        return match ($this) {
            self::DAY => 'Biznes kun',
            self::SHIFT => 'Smena',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $period) => $period->value, self::cases());
    }
}
