<?php

namespace App\Domain\Pos\Hall;

use App\Enums\DayType;
use App\Models\Branch;
use App\Domain\Pos\Setup\PosSettings;
use App\Models\RoomTariffCalendar;
use App\Support\StoreTimezone;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * The business day a POS order belongs to and its day type, for the room tariffs (P3-12, R3).
 *
 * Business date: the store-local date of the moment, minus one day before the branch's day start
 * (branches.business_day_starts_at, P3-13; 05:00 when absent): a room opened at 01:30 on Saturday night is Friday's.
 * Day type: `holiday` when the date is in the branch's holiday list (branches.pos_settings.holidays, P3-26: the one
 * list), `weekend` when its ISO weekday is one of the branch's weekend days (Saturday and
 * Sunday unless configured), else `weekday`.
 */
class BusinessCalendar
{
    public const DEFAULT_DAY_START = '05:00';

    /** ISO weekdays (1 = Monday). */
    public const DEFAULT_WEEKEND_DAYS = [6, 7];

    public function businessDate(Branch $branch, DateTimeInterface $at): string
    {
        $timezone = StoreTimezone::of($branch->store);
        [$hour, $minute] = $this->dayStart($branch);

        $local = CarbonImmutable::instance($at)->setTimezone(new DateTimeZone($timezone));
        $start = $local->setTime($hour, $minute);

        return ($local->lessThan($start) ? $local->subDay() : $local)->format('Y-m-d');
    }

    public function dayType(Branch $branch, string $date): DayType
    {
        $calendar = RoomTariffCalendar::where('branch_id', $branch->id)->first();

        if (in_array($date, $this->holidays($branch, $calendar), true)) {
            return DayType::HOLIDAY;
        }

        $weekday = (int) CarbonImmutable::createFromFormat('!Y-m-d', $date)?->isoWeekday();

        return in_array($weekday, $this->weekendDays($calendar), true) ? DayType::WEEKEND : DayType::WEEKDAY;
    }

    /**
     * @return list<int>
     */
    public function weekendDays(?RoomTariffCalendar $calendar): array
    {
        $days = $calendar?->weekend_days;

        return is_array($days) ? array_map('intval', $days) : self::DEFAULT_WEEKEND_DAYS;
    }

    /**
     * The branch's holidays (P3-26: one list, branches.pos_settings.holidays; the tariff calendar keeps the weekend
     * days only, its old list was merged in by migration). $calendar is not read for them any more.
     *
     * @return list<string>
     */
    public function holidays(Branch $branch, ?RoomTariffCalendar $calendar = null): array
    {
        $settings = $branch->getAttribute('pos_settings');
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }

        return PosSettings::resolve(is_array($settings) ? $settings : null)['holidays'];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dayStart(Branch $branch): array
    {
        $value = $branch->getAttribute('business_day_starts_at');
        $value = is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $match) === 1 ? $match : null;

        if ($value === null) {
            preg_match('/^(\d{1,2}):(\d{2})/', self::DEFAULT_DAY_START, $value);
        }

        return [(int) $value[1], (int) $value[2]];
    }
}
