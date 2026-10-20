<?php

namespace Tests\Unit;

use App\Support\Money;
use Brick\Math\Exception\NumberFormatException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_values_are_normalized_to_two_decimals_half_up(): void
    {
        $this->assertSame('12.50', Money::of('12.5')->toString());
        $this->assertSame('100000.00', Money::of(100000)->toString());
        $this->assertSame('1.01', Money::of('1.005')->toString());
        $this->assertSame('-1.01', Money::of('-1.005')->toString());
        $this->assertSame('1.01', Money::of(1.005)->toString());
        $this->assertSame('0.30', Money::of(0.1 + 0.2)->toString());
        $this->assertSame('0.00', Money::of(null)->toString());
        $this->assertSame('0.00', Money::of('')->toString());
        $this->assertSame('1000.00', Money::of(' 1e3 ')->toString());
    }

    public function test_arithmetic_is_exact(): void
    {
        $this->assertSame('100000.00', Money::of('131072.02')->minus('31072.02')->toString());
        $this->assertSame('0.30', Money::of('0.10')->plus('0.20')->toString());
        $this->assertSame('59999.97', Money::of('19999.99')->times(3)->toString());
        $this->assertSame('3.60', Money::sum(1, '2.5', null, 0.1)->toString());
        $this->assertSame('3.33', Money::min(5, '3.333', 4)->toString());
        $this->assertSame('5.00', Money::max('-1', 5, '4.99')->toString());
        $this->assertSame('-12.35', Money::of('12.35')->negated()->toString());
        $this->assertSame('12.35', Money::of('-12.35')->abs()->toString());
    }

    public function test_percentages_round_once_half_up_to_the_requested_scale(): void
    {
        $this->assertSame('4166.63', Money::of(33333)->percent('12.5')->toString());
        $this->assertSame('10000.00', Money::of('100000.00')->percent(10)->toString());
        // 249.50 * 1% = 2.495: one rounding to whole UZS gives 2, not 3 (2.495 -> 2.50 -> 3)
        $this->assertSame('2.00', Money::of('249.50')->percent(1, 0)->toString());
        $this->assertSame('5.00', Money::of('4.50')->roundTo(0)->toString());

        $this->expectException(InvalidArgumentException::class);
        Money::of(1)->percent(10, 3);
    }

    public function test_comparisons(): void
    {
        $limit = Money::of('-100000.00')->abs();

        $this->assertTrue(Money::of('131072.02')->minus('31072.02')->isGreaterThanOrEqualTo($limit));
        $this->assertTrue(Money::of('50000')->equals('50000.00'));
        $this->assertTrue(Money::of('0.004')->isZero());
        $this->assertTrue(Money::of('-0.01')->isNegative());
        $this->assertTrue(Money::of('0.01')->isPositive());
        $this->assertSame(-1, Money::of(1)->compareTo('1.01'));
        $this->assertTrue(Money::of(1)->isLessThan(2));
        $this->assertFalse(Money::of(2)->isLessThanOrEqualTo('1.99'));
    }

    public function test_tiyin_conversion(): void
    {
        $this->assertSame('50000.00', Money::fromTiyin(5000000)->toString());
        $this->assertSame('123.45', Money::fromTiyin('12345')->toString());
        $this->assertSame(5000000, Money::of('50000.00')->toTiyin());
        $this->assertSame(100056, Money::of('1000.555')->toTiyin());
        // A fraction of a tiyin rounds half-up, as the amount checks always did
        $this->assertSame('50000.00', Money::fromTiyin('5000000.4')->toString());
        $this->assertSame('50000.01', Money::fromTiyin(5000000.5)->toString());
    }

    public function test_output_forms(): void
    {
        $this->assertSame('150000.50', (string) Money::of('150000.5'));
        $this->assertSame('{"amount":150000.5}', json_encode(['amount' => Money::of('150000.5')]));
        $this->assertSame(150000.5, Money::of('150000.5')->toFloat());
    }

    public function test_invalid_amounts_are_rejected(): void
    {
        try {
            Money::of(INF);
            $this->fail('INF is not an amount.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(NumberFormatException::class);
        Money::of('12 000');
    }
}
