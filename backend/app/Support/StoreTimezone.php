<?php

namespace App\Support;

use App\Domain\Delivery\ZoneResolver;
use App\Models\Store;

/**
 * P2-33: a store's time zone as every date filter, report and opening-hours check reads it: the stored identifier when
 * PHP knows it, otherwise Asia/Tashkent (ZoneResolver::DEFAULT_TIMEZONE). The settings forms validate the field
 * (`timezone:all`), but a row written before that, by hand or by an import must not turn a date filter into a 500.
 */
final class StoreTimezone
{
    public static function of(?Store $store): string
    {
        return self::orDefault($store?->timezone);
    }

    public static function orDefault(mixed $timezone): string
    {
        $timezone = is_string($timezone) ? trim($timezone) : '';

        return $timezone !== '' && self::isValid($timezone) ? $timezone : ZoneResolver::DEFAULT_TIMEZONE;
    }

    public static function isValid(string $timezone): bool
    {
        static $known = null;
        $known ??= array_flip(\DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC));

        return isset($known[$timezone]);
    }
}
