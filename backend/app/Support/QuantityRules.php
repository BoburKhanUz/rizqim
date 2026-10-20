<?php

namespace App\Support;

use Brick\Math\RoundingMode;

/**
 * Rules of the quantity fields of a variant sold by weight or volume (docs/MENU.md): an order line must be at least
 * `min_quantity` AND a multiple of `quantity_step` (OrderLineBuilder::checkQuantity). A minimum that is not itself a
 * multiple of the step (0.3 kg in steps of 0.5) is refused by the menu forms (owner API, store panel), so the least
 * quantity a customer can pick is always one the server takes. The clients start at the first multiple of the step at
 * or above the minimum anyway, for variants saved before this rule.
 */
final class QuantityRules
{
    // The step the server uses when a measured variant has none (ProductVariant::quantityStep)
    public const DEFAULT_STEP = '0.1';

    /**
     * The Uzbek reason the minimum does not fit the step, or null when it does (or a field is empty or not a number:
     * the field's own rules report that).
     */
    public static function minimumProblem(mixed $minimum, mixed $step): ?string
    {
        if ($minimum === null || $minimum === '') {
            return null;
        }
        $min = Quantity::tryOf($minimum);
        $stepQuantity = Quantity::tryOf($step === null || $step === '' ? self::DEFAULT_STEP : $step);
        if ($min === null || $stepQuantity === null || !$min->isPositive() || !$stepQuantity->isPositive()) {
            return null;
        }
        if ($min->isMultipleOf($stepQuantity)) {
            return null;
        }

        $steps = $min->toBigDecimal()->dividedBy($stepQuantity->toBigDecimal(), 0, RoundingMode::CEILING);
        $suggested = Quantity::of($steps->multipliedBy($stepQuantity->toBigDecimal()));

        return "Eng kam miqdor ({$min->format()}) qadamga ({$stepQuantity->format()}) karrali bo'lishi kerak, "
            . "aks holda bu taomni buyurtma qilib bo'lmaydi. Masalan: {$suggested->format()}.";
    }
}
