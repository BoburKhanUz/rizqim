<?php

namespace App\Enums;

/**
 * P4-22: what kind of food place a store application is (the public form's `format`).
 */
enum StoreFormat: string
{
    case RESTAURANT = 'restaurant';
    case CAFE = 'cafe';
    case FAST_FOOD = 'fast_food';
    case CANTEEN = 'canteen';
    case COFFEE_SHOP = 'coffee_shop';
    case BAKERY = 'bakery';
    case TEAHOUSE = 'teahouse';
    case CATERING = 'catering';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::RESTAURANT => 'Restoran',
            self::CAFE => 'Kafe',
            self::FAST_FOOD => 'Fast food',
            self::CANTEEN => 'Oshxona',
            self::COFFEE_SHOP => 'Qahvaxona',
            self::BAKERY => 'Novvoyxona va qandolat',
            self::TEAHOUSE => 'Choyxona',
            self::CATERING => "To'yxona va banket",
            self::OTHER => 'Boshqa',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $format) => $format->value, self::cases());
    }
}
