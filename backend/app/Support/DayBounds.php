<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Wave M3: a `from` / `to` filter on a timestamp column. A plain date (YYYY-MM-DD) is that calendar day in the given
 * time zone (the store's for an owner, the platform's for the team), `to` taking the whole day; a full datetime keeps
 * its exact meaning (its offset, else the app's UTC). The bounds go to the database in UTC as `col >= from` and
 * `col < next day` (or `col <= the exact moment`), the shape the month partitions of a journal prune on.
 */
final class DayBounds
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function apply(Builder $query, string $column, mixed $from, mixed $to, string $timezone): Builder
    {
        if (filled($from)) {
            $query->where($column, '>=', self::format(self::start((string) $from, $timezone)));
        }
        if (filled($to)) {
            $to = (string) $to;
            self::isDate($to)
                ? $query->where($column, '<', self::format(self::start($to, $timezone)->addDay()))
                : $query->where($column, '<=', self::format(CarbonImmutable::parse($to)));
        }

        return $query;
    }

    private static function start(string $value, string $timezone): CarbonImmutable
    {
        return self::isDate($value) ? CarbonImmutable::parse($value, $timezone)->startOfDay() : CarbonImmutable::parse($value);
    }

    /**
     * A day without a time of day (2026-11-10, also any other date-only form the `date` rule lets through).
     */
    private static function isDate(string $value): bool
    {
        return !str_contains($value, ':') && !str_contains($value, 'T') && !str_starts_with(trim($value), '@');
    }

    private static function format(CarbonImmutable $at): string
    {
        return $at->utc()->format('Y-m-d H:i:s.u');
    }
}
