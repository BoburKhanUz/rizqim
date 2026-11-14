<?php

namespace App\Domain\Pos\Banquet;

/**
 * What a banquet_event_lines row bills (P6-52): the package (per person × the billed guests), the rent of one hall,
 * an extra from the catalogue (per event or per person) or, on the day, the event-day order (P6-54).
 */
enum BanquetLineKind: string
{
    case PACKAGE = 'package';
    case HALL_RENT = 'hall_rent';
    case EXTRA = 'extra';
    case ORDER = 'order';

    public function label(): string
    {
        return match ($this) {
            self::PACKAGE => 'Paket',
            self::HALL_RENT => 'Zal ijarasi',
            self::EXTRA => "Qo'shimcha",
            self::ORDER => 'Tadbir kuni buyurtma',
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
