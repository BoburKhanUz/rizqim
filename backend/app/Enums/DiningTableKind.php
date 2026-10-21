<?php

namespace App\Enums;

/**
 * What a place on the floor map is (dining_tables.kind, P3-12): a plain table, a room ("xona", "kabinka": a long
 * session with a room fee and often a minimum spend) or a tapchan.
 */
enum DiningTableKind: string
{
    case TABLE = 'table';
    case ROOM = 'room';
    case TAPCHAN = 'tapchan';

    public function label(): string
    {
        return match ($this) {
            self::TABLE => 'Stol',
            self::ROOM => 'Xona',
            self::TAPCHAN => 'Tapchan',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $kind) => $kind->value, self::cases());
    }
}
