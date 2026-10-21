<?php

namespace App\Domain\Pos\Billing;

use Brick\Math\BigInteger;
use InvalidArgumentException;

/**
 * Splitting whole units (tiyin of money, thousandths of a quantity) exactly: every share is a whole unit, the shares
 * always add up to the total, and the same input always gives the same shares (no floats, ties to the earlier bill).
 */
final class Allocation
{
    /**
     * $total split as equally as possible into $parts; the remainder all goes to the first part ("qoldiq tiyin
     * birinchi schyotga"): 10 in 4 parts is [4, 2, 2, 2].
     *
     * @return list<int>
     */
    public static function equal(int $total, int $parts): array
    {
        if ($parts < 1 || $total < 0) {
            throw new InvalidArgumentException('Cannot split a negative total or into fewer than one part.');
        }

        $base = intdiv($total, $parts);
        $shares = array_fill(0, $parts, $base);
        $shares[0] += $total - $base * $parts;

        return $shares;
    }

    /**
     * $total split in proportion to $weights by the largest remainder: each share is the floor of its exact share,
     * and the units left over go one each to the largest fractional parts (ties: the earlier one). All-zero weights
     * split equally (equal()).
     *
     * @param  list<int>  $weights  non-negative
     * @return list<int>
     */
    public static function proportional(int $total, array $weights): array
    {
        if ($total < 0) {
            throw new InvalidArgumentException('Cannot allocate a negative total.');
        }
        if ($weights === []) {
            if ($total !== 0) {
                throw new InvalidArgumentException('Cannot allocate a total to nothing.');
            }

            return [];
        }

        $sum = 0;
        foreach ($weights as $weight) {
            if ($weight < 0) {
                throw new InvalidArgumentException('Weights must not be negative.');
            }
            $sum += $weight;
        }
        if ($sum === 0) {
            return self::equal($total, count($weights));
        }

        $shares = [];
        $fractions = [];
        $given = 0;
        $big = BigInteger::of($total);
        foreach ($weights as $i => $weight) {
            $exact = $big->multipliedBy($weight);
            $shares[$i] = $exact->quotient($sum)->toInt();
            $fractions[$i] = $exact->remainder($sum)->toInt();
            $given += $shares[$i];
        }

        $order = array_keys($weights);
        usort($order, fn (int $a, int $b) => [$fractions[$b], $a] <=> [$fractions[$a], $b]);
        for ($left = $total - $given, $k = 0; $left > 0; $left--, $k++) {
            $shares[$order[$k]]++;
        }

        return array_values($shares);
    }

    /**
     * A table of whole shares whose rows add up to $rowTargets and whose columns add up to $columnTotals (both must
     * have the same sum): each cell starts at the floor of its proportional share (column × row / sum), and the few
     * units left over are placed row by row, column by column (first bill first). Used to split an order equally
     * while every bill stays exact to the tiyin and every line and charge is fully shared out.
     *
     * @param  list<int>  $rowTargets
     * @param  list<int>  $columnTotals
     * @return list<list<int>> [row][column]
     */
    public static function matrix(array $rowTargets, array $columnTotals): array
    {
        $sum = array_sum($rowTargets);
        if ($sum !== array_sum($columnTotals)) {
            throw new InvalidArgumentException('Row and column totals must add up to the same amount.');
        }
        foreach ([...$rowTargets, ...$columnTotals] as $value) {
            if ($value < 0) {
                throw new InvalidArgumentException('Totals must not be negative.');
            }
        }

        $cells = [];
        $rowLeft = [];
        $columnLeft = $columnTotals;
        foreach ($rowTargets as $r => $target) {
            $rowLeft[$r] = $target;
            foreach ($columnTotals as $c => $total) {
                $cell = $sum === 0 ? 0 : BigInteger::of($total)->multipliedBy($target)->quotient($sum)->toInt();
                $cells[$r][$c] = $cell;
                $rowLeft[$r] -= $cell;
                $columnLeft[$c] -= $cell;
            }
        }

        // What the floors left: at most a few units per row and column, placed north-west first
        foreach (array_keys($rowTargets) as $r) {
            foreach (array_keys($columnTotals) as $c) {
                if ($rowLeft[$r] === 0) {
                    break;
                }
                $give = min($rowLeft[$r], $columnLeft[$c]);
                if ($give > 0) {
                    $cells[$r][$c] += $give;
                    $rowLeft[$r] -= $give;
                    $columnLeft[$c] -= $give;
                }
            }
        }

        return array_values(array_map('array_values', $cells));
    }
}
