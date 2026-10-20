<?php

namespace App\Enums;

/**
 * How a product variant is sold (product_variants.unit): by the piece (portion, bottle) or by weight/volume, whose
 * price is per kilogram or litre and whose quantity may be a fraction in steps of quantity_step (docs/MENU.md).
 */
enum MenuUnit: string
{
    case PIECE = 'piece';
    case KG = 'kg';
    case LITRE = 'l';

    public function label(): string
    {
        return match ($this) {
            self::PIECE => 'dona',
            self::KG => 'kg',
            self::LITRE => 'l',
        };
    }

    /**
     * Whether quantities may be fractional (0.5 kg); pieces are always whole.
     */
    public function isMeasured(): bool
    {
        return $this !== self::PIECE;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $unit) => $unit->value, self::cases());
    }
}
