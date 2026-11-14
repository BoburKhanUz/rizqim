<?php

namespace App\Domain\Pos\Banquet;

/**
 * The part of the day an event takes (P6-51): the three slots with the store's times (banquet_settings.slots) or a
 * custom start and end.
 */
enum BanquetSlot: string
{
    case MORNING = 'morning';
    case DAY = 'day';
    case EVENING = 'evening';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::MORNING => 'Ertalab',
            self::DAY => 'Kunduzi',
            self::EVENING => 'Kechqurun',
            self::CUSTOM => 'Boshqa vaqt',
        };
    }

    /**
     * The default local times [start, end] ("HH:MM"; an end at or before the start is the next day).
     *
     * @return array{0: string, 1: string}
     */
    public function defaultTimes(): array
    {
        return match ($this) {
            self::MORNING => ['06:00', '11:00'],
            self::DAY => ['12:00', '16:00'],
            self::EVENING, self::CUSTOM => ['18:00', '23:00'],
        };
    }

    /**
     * @return list<self>
     */
    public static function named(): array
    {
        return [self::MORNING, self::DAY, self::EVENING];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $slot) => $slot->value, self::cases());
    }
}
