<?php

namespace App\Domain\Marketplace;

use App\Models\Store;
use App\Support\StoreTimezone;
use Carbon\CarbonInterface;

/**
 * A store's "open now", computed at read time from the raw schedule a document keeps (the is_open switch, open_time,
 * close_time, the time zone), so a cached card is never open or closed by the clock of its build. The rule is the
 * one of Store::getIsOpenAttribute (and of the old feed query): no hours or equal hours = always, overnight hours wrap.
 */
final class StoreSchedule
{
    /**
     * The raw schedule of a loaded store, as the documents keep it.
     *
     * @return array{flag: bool, open: string|null, close: string|null, tz: string}
     */
    public static function of(Store $store): array
    {
        return [
            'flag' => (bool) $store->getRawOriginal('is_open'),
            'open' => self::time($store->getRawOriginal('open_time')),
            'close' => self::time($store->getRawOriginal('close_time')),
            'tz' => StoreTimezone::of($store),
        ];
    }

    /**
     * @param  array{flag: bool, open: string|null, close: string|null, tz: string}  $schedule
     */
    public static function isOpen(array $schedule, ?CarbonInterface $at = null): bool
    {
        if (!$schedule['flag']) {
            return false;
        }

        $open = $schedule['open'];
        $close = $schedule['close'];
        if (!$open || !$close || $open === $close) {
            return true;
        }

        $now = ($at ?? now())->copy()->setTimezone($schedule['tz'])->format('H:i:s');

        return $close > $open
            ? !($now < $open || $now > $close)
            : !($now < $open && $now > $close);
    }

    private static function time(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
