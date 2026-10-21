<?php

namespace App\Domain\Pos\Hall;

use App\Enums\DayType;
use App\Models\DiningTable;
use App\Models\RoomTariff;
use Illuminate\Support\Collection;

/**
 * The room tariff that prices a place on a business date (P3-12, R3). The most specific tariff wins: the place's own,
 * then its kind in its hall, then its kind in the whole branch. At each level the day type falls back as
 * DayType::fallback() says (a holiday without its own price is a weekend, a weekend a weekday). Among tariffs of the
 * same level and day type valid on the date, the one that started last (then the newest row). None: no room fee.
 */
class RoomTariffResolver
{
    public function resolve(DiningTable $table, string $date, DayType $dayType): ?RoomTariff
    {
        /** @var Collection<int, RoomTariff> $tariffs */
        $tariffs = RoomTariff::query()
            ->where('branch_id', $table->branch_id)
            ->where(fn ($query) => $query->where('dining_table_id', $table->id)
                ->orWhere(fn ($query) => $query->whereNull('dining_table_id')->where('table_kind', $table->kind->value)
                    ->where(fn ($query) => $query->whereNull('hall_id')->orWhere('hall_id', $table->hall_id))))
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date))
            ->get();

        $levels = [
            $tariffs->filter(fn (RoomTariff $tariff) => (int) $tariff->dining_table_id === (int) $table->id),
            $tariffs->filter(fn (RoomTariff $tariff) => $tariff->dining_table_id === null && $tariff->hall_id !== null),
            $tariffs->filter(fn (RoomTariff $tariff) => $tariff->dining_table_id === null && $tariff->hall_id === null),
        ];

        foreach ($levels as $level) {
            foreach ($dayType->fallback() as $type) {
                $match = $level->filter(fn (RoomTariff $tariff) => $tariff->day_type === $type)
                    ->sortByDesc(fn (RoomTariff $tariff) => ($tariff->valid_from?->format('Y-m-d') ?? '0000-00-00') . sprintf('%020d', (int) $tariff->id))
                    ->first();
                if ($match !== null) {
                    return $match;
                }
            }
        }

        return null;
    }
}
