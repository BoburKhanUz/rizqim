<?php

namespace App\Enums;

/**
 * What a menu item is on a receipt (products.kind, P3-11): a dish (taom, ichimlik) or a service the store sells
 * like a dish (cooking from the guest's own food, kazan rent, a set menu service). Both need an MXIK code before a
 * fiscal store may sell them; the kind tells the panel and the receipt which MXIK group (10202001 or 10202002) fits.
 */
enum ProductKind: string
{
    case DISH = 'dish';
    case SERVICE = 'service';

    public function label(): string
    {
        return match ($this) {
            self::DISH => 'Taom',
            self::SERVICE => 'Xizmat',
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
