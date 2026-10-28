<?php

namespace App\Services\Admin\Marketing;

use Carbon\CarbonInterface;

/**
 * P4-31: how Marketing shows a moment: ISO 8601 in Asia/Tashkent (the storage stays UTC).
 */
final class MarketingTime
{
    public const TIMEZONE = 'Asia/Tashkent';

    public static function iso(?CarbonInterface $at): ?string
    {
        return $at?->copy()->setTimezone(self::TIMEZONE)->toIso8601String();
    }
}
