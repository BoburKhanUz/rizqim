<?php

namespace App\Domain\Pos\Kitchen;

/**
 * Which orders go through a branch's expo (assembly) station (branches.pos_settings.expo_scope, owner decision
 * 03.11.2026, docs/POS_SPEC.md "KDS v2"): `all` (the default) every order a customer or courier waits for, one station
 * or more; `multi_station` only an order cooked on two or more stations (the rule of P6-33 before the decision).
 */
enum ExpoScope: string
{
    case ALL = 'all';
    case MULTI_STATION = 'multi_station';

    public const DEFAULT = self::ALL;

    /**
     * The fewest kitchen station groups an order needs to go through the expo.
     */
    public function minStations(): int
    {
        return $this === self::ALL ? 1 : 2;
    }

    public function label(): string
    {
        return match ($this) {
            self::ALL => 'Har bir buyurtma',
            self::MULTI_STATION => "Faqat 2 va undan ko'p sexli buyurtma",
        };
    }
}
