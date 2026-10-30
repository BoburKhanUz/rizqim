<?php

namespace App\Domain\Pos\Labels;

/**
 * P6-32 (B7): which items of an order get a cup sticker (branch_label_settings.scope): every item, or only the dishes
 * of the categories the owner picked (the drinks category of a coffee shop).
 */
enum LabelScope: string
{
    case ALL = 'all';
    case CATEGORIES = 'categories';

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Hamma taomlarga',
            self::CATEGORIES => 'Tanlangan kategoriyalarga (masalan, ichimliklar)',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $scope) => $scope->value, self::cases());
    }
}
