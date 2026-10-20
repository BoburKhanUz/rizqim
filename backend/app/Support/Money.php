<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of UZS, exact to the tiyin: two decimals, the scale of every money column. Arithmetic never goes through
 * floats; whatever needs rounding (more precise input, multiplication, percentages) is rounded half-up.
 */
final class Money implements JsonSerializable, Stringable
{
    public const SCALE = 2;

    private function __construct(
        private readonly BigDecimal $amount
    ) {}

    /**
     * DB strings, request input and ints are read exactly; a float is read as its 15-significant-digit decimal, which
     * gives every DECIMAL(12,2) value back unchanged and drops binary noise (0.1 + 0.2 is 0.30). Null and '' are zero.
     */
    public static function of(self|BigNumber|int|float|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return new self(self::decimal($value)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    /**
     * Payme amounts are in tiyin (UZS * 100); a fraction of a tiyin is rounded half-up.
     */
    public static function fromTiyin(int|float|string $tiyin): self
    {
        return new self(self::decimal($tiyin)->withPointMovedLeft(self::SCALE)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public static function sum(self|BigNumber|int|float|string|null ...$values): self
    {
        return array_reduce($values, fn (self $sum, $value) => $sum->plus($value), self::zero());
    }

    public static function min(self|BigNumber|int|float|string|null $first, self|BigNumber|int|float|string|null ...$others): self
    {
        return array_reduce($others, fn (self $min, $value) => $min->isGreaterThan($value) ? self::of($value) : $min, self::of($first));
    }

    public static function max(self|BigNumber|int|float|string|null $first, self|BigNumber|int|float|string|null ...$others): self
    {
        return array_reduce($others, fn (self $max, $value) => $max->isLessThan($value) ? self::of($value) : $max, self::of($first));
    }

    public function plus(self|BigNumber|int|float|string|null $other): self
    {
        return new self($this->amount->plus(self::of($other)->amount));
    }

    public function minus(self|BigNumber|int|float|string|null $other): self
    {
        return new self($this->amount->minus(self::of($other)->amount));
    }

    /**
     * E.g. unit price × quantity.
     */
    public function times(BigNumber|int|float|string $factor): self
    {
        return new self($this->amount->multipliedBy(self::decimal($factor))->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    /**
     * $rate percent of this amount, computed exactly and rounded once, half-up, to $scale decimals (2 = tiyin, 0 = whole UZS).
     */
    public function percent(BigNumber|int|float|string $rate, int $scale = self::SCALE): self
    {
        if ($scale < 0 || $scale > self::SCALE) {
            throw new InvalidArgumentException('Money is kept to ' . self::SCALE . ' decimals at most.');
        }

        return new self($this->amount->multipliedBy(self::decimal($rate))->withPointMovedLeft(2)
            ->toScale($scale, RoundingMode::HalfUp)->toScale(self::SCALE));
    }

    /**
     * Rounded half-up to $scale decimals (0 = whole UZS).
     */
    public function roundTo(int $scale): self
    {
        if ($scale < 0 || $scale > self::SCALE) {
            throw new InvalidArgumentException('Money is kept to ' . self::SCALE . ' decimals at most.');
        }

        return new self($this->amount->toScale($scale, RoundingMode::HalfUp)->toScale(self::SCALE));
    }

    public function negated(): self
    {
        return new self($this->amount->negated());
    }

    public function abs(): self
    {
        return new self($this->amount->abs());
    }

    public function compareTo(self|BigNumber|int|float|string|null $other): int
    {
        return $this->amount->compareTo(self::of($other)->amount);
    }

    public function equals(self|BigNumber|int|float|string|null $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isGreaterThan(self|BigNumber|int|float|string|null $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isGreaterThanOrEqualTo(self|BigNumber|int|float|string|null $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function isLessThan(self|BigNumber|int|float|string|null $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isLessThanOrEqualTo(self|BigNumber|int|float|string|null $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function toTiyin(): int
    {
        return $this->amount->withPointMovedRight(self::SCALE)->toInt();
    }

    /**
     * "1234.50": the form DECIMAL columns take and return.
     */
    public function toString(): string
    {
        return $this->amount->toString();
    }

    /**
     * For display and JSON numbers only, never for further arithmetic.
     */
    public function toFloat(): float
    {
        return $this->amount->toFloat();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): float
    {
        return $this->toFloat();
    }

    private static function decimal(self|BigNumber|int|float|string|null $value): BigDecimal
    {
        if ($value instanceof self) {
            return $value->amount;
        }

        if ($value === null || $value === '') {
            return BigDecimal::zero();
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('A money amount must be a finite number.');
            }

            $value = sprintf('%.15H', $value);
        }

        return BigDecimal::of(is_string($value) ? trim($value) : $value);
    }
}
