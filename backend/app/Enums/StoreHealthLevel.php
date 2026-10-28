<?php

namespace App\Enums;

/**
 * P4-22: a store's health (Ovqatxonalar → sog'liq bahosi): good, needs attention, churn risk.
 */
enum StoreHealthLevel: string
{
    case GOOD = 'good';
    case ATTENTION = 'attention';
    case CHURN_RISK = 'churn_risk';

    public function label(): string
    {
        return match ($this) {
            self::GOOD => 'Yaxshi',
            self::ATTENTION => "E'tibor kerak",
            self::CHURN_RISK => 'Ketish xavfi',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::GOOD => 0,
            self::ATTENTION => 1,
            self::CHURN_RISK => 2,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $level) => $level->value, self::cases());
    }
}
