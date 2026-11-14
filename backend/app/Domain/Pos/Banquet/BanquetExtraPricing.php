<?php

namespace App\Domain\Pos\Banquet;

/**
 * How an extra of the catalogue is priced (P6-52): once per event (tort, dekor, an extra hour) or per billed guest.
 */
enum BanquetExtraPricing: string
{
    case PER_EVENT = 'per_event';
    case PER_PERSON = 'per_person';

    public function label(): string
    {
        return match ($this) {
            self::PER_EVENT => 'Tadbirga',
            self::PER_PERSON => 'Har mehmonga',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $pricing) => $pricing->value, self::cases());
    }
}
