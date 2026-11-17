<?php

namespace App\Domain\Pos\Shifts;

use App\Domain\Pos\Setup\PosSettings;
use App\Models\Branch;
use App\Models\Store;
use App\Support\StoreTimezone;

/**
 * The business day a moment belongs to (POS_SPEC 2.3): the store's local date, minus a day before the branch's day
 * start (branches.business_day_starts_at, 05:00 by default): a room paid at 02:00 on Friday is Thursday's sale.
 * Shifts, reports and queue numbers follow it. Same rule as GET /pos/bootstrap (PosSettings::businessDate).
 */
final class BusinessDay
{
    public static function of(Branch $branch, ?\DateTimeInterface $at = null): string
    {
        $store = $branch->relationLoaded('store') ? $branch->store : Store::find($branch->store_id);

        return PosSettings::businessDate(
            $at ?? now(),
            StoreTimezone::of($store),
            PosSettings::timeOf($branch->getAttribute('business_day_starts_at')),
        );
    }

    /**
     * Wave M2: the moment the business day after the one $at belongs to starts (the branch's day start, local time):
     * what follows the business day (today's queue numbers, the till's closed orders) changes then by the clock alone.
     */
    public static function nextStart(Branch $branch, ?\DateTimeInterface $at = null): \Carbon\CarbonImmutable
    {
        $store = $branch->relationLoaded('store') ? $branch->store : Store::find($branch->store_id);
        $timezone = new \DateTimeZone(StoreTimezone::of($store));
        [$hour, $minute] = array_map('intval', explode(':', PosSettings::timeOf($branch->getAttribute('business_day_starts_at'))));
        $today = self::of($branch, $at);

        $day = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $today, $timezone);

        return $day ? $day->addDay()->setTime($hour, $minute) : \Carbon\CarbonImmutable::instance($at ?? now())->addDay();
    }
}
