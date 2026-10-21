<?php

namespace App\Enums;

/**
 * What a kitchen station is (kitchen_stations.kind): the choyxona's osh/qozon, mangal (grill), salat va bar, choy,
 * or an ordinary kitchen. Clients pick the KDS layout from it (the mangal station counts skewers).
 */
enum KitchenStationKind: string
{
    case OSH = 'osh';
    case MANGAL = 'mangal';
    case SALAD_BAR = 'salad_bar';
    case TEA = 'tea';
    case KITCHEN = 'kitchen';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::OSH => 'Osh va qozon',
            self::MANGAL => 'Mangal',
            self::SALAD_BAR => 'Salat va bar',
            self::TEA => 'Choy',
            self::KITCHEN => 'Oshxona',
            self::OTHER => 'Boshqa',
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
