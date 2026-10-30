<?php

namespace App\Domain\Pos\Orders;

/**
 * P6-25 (B3): which weight a "weight later" dish's kg price is for (products.weight_mode, kept on the sold line as
 * order_items.weight_basis): the fish as chosen, before cooking (`raw`), or as served (`cooked`).
 */
enum WeightBasis: string
{
    case RAW = 'raw';
    case COOKED = 'cooked';

    public function label(): string
    {
        return match ($this) {
            self::RAW => 'xom',
            self::COOKED => 'pishgan',
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
