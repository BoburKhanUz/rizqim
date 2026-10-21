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
}
