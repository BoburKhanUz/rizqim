<?php

namespace App\Domain\Fiscal;

/**
 * P6-31 (owner decision 30.10.2026): how a combo goes on the fiscal receipt (stores.fiscal_settings.combo_fiscal_mode).
 *
 *  - `single` (default): ONE line, the combo product's own name, MXIK and package code, quantity and price; the parts
 *    are printed under it without prices on the guest's receipt only;
 *  - `split`: one line per part, each with its dish's own MXIK, whose amounts are shares of the combo line by the parts'
 *    list prices (ComboFiscalSplit: largest remainder in tiyin, ties to the earlier part), so they add up exactly to
 *    the combo line (after its discount share; the service charge stays its own line). For when the accountant asks.
 */
enum ComboFiscalMode: string
{
    case SINGLE = 'single';
    case SPLIT = 'split';

    public function label(): string
    {
        return match ($this) {
            self::SINGLE => 'Bitta qator (kombo MXIK)',
            self::SPLIT => "Tarkib bo'yicha qatorlar",
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $mode) => $mode->value, self::cases());
    }
}
