<?php

namespace App\Domain\Pos\Takeaway;

/**
 * P6-34 (B8): what a packaging rule counts. `order`: its quantity once per order (a bag per takeaway order); `item`: its
 * quantity per portion of the dishes it names (one category or one product: a soup bowl per soup, a lid per cup).
 */
enum PackagingBasis: string
{
    case ORDER = 'order';
    case ITEM = 'item';

    public function label(): string
    {
        return match ($this) {
            self::ORDER => 'Buyurtmaga',
            self::ITEM => 'Taomga',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $basis) => $basis->value, self::cases());
    }
}
