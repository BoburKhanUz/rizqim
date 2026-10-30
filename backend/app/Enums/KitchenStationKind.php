<?php

namespace App\Enums;

/**
 * What a kitchen station is (kitchen_stations.kind): the choyxona's osh/qozon, mangal (grill), salat va bar, choy,
 * or an ordinary kitchen. Clients pick the KDS layout from it (the mangal station counts skewers).
 *
 * P6-33 (KDS v2, docs/POS_SPEC.md "KDS v2"): the universal stations of a fast food or a coffee shop — `grill`,
 * `fryer` (fritur), `drinks`, `barista` — and `expo`, the assembly screen: an order cooked on several stations is
 * put together there and its bump makes the number ready (module `kds_expo`). An expo station gets no dishes of its
 * own (its routes are refused); its time target is the assembly time.
 */
enum KitchenStationKind: string
{
    case OSH = 'osh';
    case MANGAL = 'mangal';
    case SALAD_BAR = 'salad_bar';
    case TEA = 'tea';
    case KITCHEN = 'kitchen';
    case OTHER = 'other';
    // P6-33
    case GRILL = 'grill';
    case FRYER = 'fryer';
    case DRINKS = 'drinks';
    case BARISTA = 'barista';
    case EXPO = 'expo';

    public function label(): string
    {
        return match ($this) {
            self::OSH => 'Osh va qozon',
            self::MANGAL => 'Mangal',
            self::SALAD_BAR => 'Salat va bar',
            self::TEA => 'Choy',
            self::KITCHEN => 'Oshxona',
            self::OTHER => 'Boshqa',
            self::GRILL => 'Grill',
            self::FRYER => 'Fritur',
            self::DRINKS => 'Ichimliklar',
            self::BARISTA => 'Barista',
            self::EXPO => "Yig'ish",
        };
    }

    /**
     * P6-33: the time target (seconds) a new station of this kind starts with from a format template; null: none
     * (the choyxona's stations keep none, the owner sets one per station if wanted).
     */
    public function defaultTargetSeconds(): ?int
    {
        return match ($this) {
            self::GRILL => 300,
            self::FRYER => 240,
            self::DRINKS => 90,
            self::BARISTA => 180,
            self::EXPO => 120,
            default => null,
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
