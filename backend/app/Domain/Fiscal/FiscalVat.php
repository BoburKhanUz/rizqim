<?php

namespace App\Domain\Fiscal;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * VAT is included in menu prices: the VAT of an amount at rate r is amount × r / (100 + r), rounded half-up to the
 * tiyin, line by line (a receipt's VAT total is the sum of its lines). Rates travel as short decimal strings:
 * "12", "6", "0", "7.5".
 */
final class FiscalVat
{
    /**
     * "12.00" → "12", "6.50" → "6.5"; 0–100 with at most two decimals, anything else is refused.
     */
    public static function normalizeRate(string|int|float|BigDecimal|null $rate): string
    {
        if ($rate === null || $rate === '' || (is_string($rate) && !preg_match('/^\s*\d+(\.\d+)?\s*$/', $rate))) {
            throw new InvalidArgumentException('A VAT rate is a number from 0 to 100.');
        }

        $decimal = BigDecimal::of(is_float($rate) ? sprintf('%.2F', $rate) : (is_string($rate) ? trim($rate) : $rate))->strippedOfTrailingZeros();
        if ($decimal->isNegative() || $decimal->isGreaterThan(100) || $decimal->getScale() > 2) {
            throw new InvalidArgumentException('A VAT rate is a number from 0 to 100 with at most two decimals.');
        }

        return $decimal->toString();
    }

    /**
     * The VAT included in $amount at $rate percent.
     */
    public static function included(Money $amount, string $rate): Money
    {
        $rate = BigDecimal::of(self::normalizeRate($rate));
        if ($rate->isZero()) {
            return Money::zero();
        }

        $vat = BigDecimal::of($amount->toString())
            ->multipliedBy($rate)
            ->dividedBy($rate->plus(100), Money::SCALE, RoundingMode::HalfUp);

        return Money::of($vat);
    }
}
