<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A quantity of a menu item: pieces (1, 2) or a weight/volume (0.5 kg, 1.25 l), exact to three decimals, the scale
 * of every quantity column (order_items.quantity, product_variants.stock, menu_batches). Never goes through floats
 * for arithmetic; JSON shows a whole quantity as an int (2, as before weights existed) and a fraction as a number.
 */
final class Quantity implements JsonSerializable, Stringable
{
    public const SCALE = 3;

    private function __construct(
        private readonly BigDecimal $amount
    ) {}

    /**
     * Strings, ints and BigNumbers are read exactly; a float through its shortest decimal form (0.1 is 0.1). More
     * than three decimals is refused rather than silently rounded: a quantity is what the customer asked for.
     */
    public static function of(self|BigNumber|int|float|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        try {
            return new self(self::decimal($value)->toScale(self::SCALE));
        } catch (MathException $e) {
            throw new InvalidArgumentException('A quantity has at most ' . self::SCALE . ' decimals.', 0, $e);
        }
    }

    /**
     * Like of(), but null for anything that is not a finite number with at most three decimals.
     */
    public static function tryOf(mixed $value): ?self
    {
        if (!is_int($value) && !is_float($value) && !is_string($value) && !$value instanceof BigNumber && !$value instanceof self) {
            return null;
        }
        if (is_string($value) && !preg_match('/^\s*-?\d+(\.\d+)?\s*$/', $value)) {
            return null;
        }

        try {
            return self::of($value);
        } catch (InvalidArgumentException|MathException) {
            return null;
        }
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    public function plus(self|BigNumber|int|float|string $other): self
    {
        return new self($this->amount->plus(self::of($other)->amount));
    }

    public function minus(self|BigNumber|int|float|string $other): self
    {
        return new self($this->amount->minus(self::of($other)->amount));
    }

    /**
     * Multiplied by a factor (e.g. a batch consumption of 0.7 per portion), rounded half-up to three decimals.
     */
    public function times(self|BigNumber|int|float|string $factor): self
    {
        return new self($this->amount->multipliedBy(self::of($factor)->amount)->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public function compareTo(self|BigNumber|int|float|string $other): int
    {
        return $this->amount->compareTo(self::of($other)->amount);
    }

    public function isGreaterThan(self|BigNumber|int|float|string $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isLessThan(self|BigNumber|int|float|string $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isWhole(): bool
    {
        return $this->amount->getFractionalPart() === str_repeat('0', self::SCALE);
    }

    /**
     * Whether this is a whole number of $step (0.3 kg with a 0.1 step: yes; 0.25: no). A zero or missing step
     * accepts anything.
     */
    public function isMultipleOf(self|BigNumber|int|float|string|null $step): bool
    {
        if ($step === null) {
            return true;
        }

        $step = self::of($step);
        if (!$step->isPositive()) {
            return true;
        }

        return $this->amount->remainder($step->amount)->isZero();
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->amount;
    }

    /**
     * "1.500": the form the DECIMAL(…, 3) columns take and return.
     */
    public function toString(): string
    {
        return $this->amount->toString();
    }

    /**
     * For display: "2", "1.5", "0.25" (no trailing zeros).
     */
    public function format(): string
    {
        $text = rtrim(rtrim($this->amount->toString(), '0'), '.');

        return $text === '' || $text === '-0' ? '0' : $text;
    }

    /**
     * An int when whole (what API clients always got), else a float; for JSON and display only.
     */
    public function toNumber(): int|float
    {
        return $this->isWhole() ? $this->amount->toBigInteger()->toInt() : $this->amount->toFloat();
    }

    /**
     * A DB value as the API shows it: 2 for "2.000", 1.5 for "1.500"; null stays null.
     */
    public static function normalize(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::of(is_numeric($value) ? $value : (string) $value)->toNumber();
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): int|float
    {
        return $this->toNumber();
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
                throw new InvalidArgumentException('A quantity must be a finite number.');
            }

            // The shortest form that reads back as the same float: 0.1 is "0.1", not 0.1000000000000000055…
            $value = (string) $value;
            if (str_contains($value, 'E')) {
                $value = sprintf('%.15F', (float) $value);
            }
        }

        return BigDecimal::of(is_string($value) ? trim($value) : $value);
    }
}
