<?php

namespace App\Domain\Pos\StaffPay;

use Carbon\CarbonImmutable;

/**
 * P6-23: the pay period of a branch (business days, the store's time zone, Asia/Tashkent by default): one business
 * day; an ISO week, Monday to Sunday; half a month, the 1st–15th and the 16th–last day; a calendar month. A statement
 * covers exactly one period (range()).
 */
enum PayPeriod: string
{
    case DAY = 'day';
    case WEEK = 'week';
    case HALF_MONTH = 'half_month';
    case MONTH = 'month';

    public function label(): string
    {
        return match ($this) {
            self::DAY => 'Kunlik',
            self::WEEK => 'Haftalik',
            self::HALF_MONTH => 'Yarim oylik',
            self::MONTH => 'Oylik',
        };
    }

    /**
     * The first and last business dates (YYYY-MM-DD) of the period holding $date.
     *
     * @return array{0: string, 1: string}
     */
    public function range(string $date): array
    {
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

        [$from, $to] = match ($this) {
            self::DAY => [$day, $day],
            self::WEEK => [$day->subDays($day->dayOfWeekIso - 1), $day->subDays($day->dayOfWeekIso - 1)->addDays(6)],
            self::HALF_MONTH => $day->day <= 15
                ? [$day->setDay(1), $day->setDay(15)]
                : [$day->setDay(16), $day->endOfMonth()->startOfDay()],
            self::MONTH => [$day->startOfMonth(), $day->endOfMonth()->startOfDay()],
        };

        return [$from->format('Y-m-d'), $to->format('Y-m-d')];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $period) => $period->value, self::cases());
    }
}
