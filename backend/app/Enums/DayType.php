<?php

namespace App\Enums;

/**
 * The kind of business day a room tariff is for (room_tariffs.day_type, P3-12, R3): a weekday, a weekend day (the
 * branch's weekend days) or a holiday (the branch's holiday list). A holiday without its own tariff is priced as a
 * weekend, a weekend without one as a weekday (fallback()).
 */
enum DayType: string
{
    case WEEKDAY = 'weekday';
    case WEEKEND = 'weekend';
    case HOLIDAY = 'holiday';

    public function label(): string
    {
        return match ($this) {
            self::WEEKDAY => 'Hafta ichi',
            self::WEEKEND => 'Dam olish kuni',
            self::HOLIDAY => 'Bayram',
        };
    }

    /**
     * The day types whose tariff prices this day, in order.
     *
     * @return list<self>
     */
    public function fallback(): array
    {
        return match ($this) {
            self::HOLIDAY => [self::HOLIDAY, self::WEEKEND, self::WEEKDAY],
            self::WEEKEND => [self::WEEKEND, self::WEEKDAY],
            self::WEEKDAY => [self::WEEKDAY],
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
