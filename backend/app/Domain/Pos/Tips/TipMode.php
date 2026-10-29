<?php

namespace App\Domain\Pos\Tips;

/**
 * P6-22: whom a branch's tips go to (pos_tip_settings.mode, pos_tips.mode): `personal` — the order's waiter, or the
 * one picked at payment; `pool` — a shared pot split by the branch's rule (TipPoolDistributor).
 */
enum TipMode: string
{
    case PERSONAL = 'personal';
    case POOL = 'pool';

    public function label(): string
    {
        return match ($this) {
            self::PERSONAL => 'Shaxsiy (ofitsiantga)',
            self::POOL => 'Umumiy qozon',
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
