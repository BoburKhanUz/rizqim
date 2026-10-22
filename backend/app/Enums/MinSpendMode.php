<?php

namespace App\Enums;

/**
 * How a room's minimum spend ("obyazalovka", R4) is set (dining_tables.min_spend_mode, P3-12): once for the room, or
 * per guest (× guests_count).
 */
enum MinSpendMode: string
{
    case PER_TABLE = 'per_table';
    case PER_PERSON = 'per_person';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $mode) => $mode->value, self::cases());
    }
}
