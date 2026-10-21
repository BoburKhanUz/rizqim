<?php

namespace Tests\Unit;

use App\Domain\Pos\Billing\Allocation;
use PHPUnit\Framework\TestCase;

/**
 * P3-18: the exact splitting behind bills: equal parts with the remainder on the first, largest-remainder proportions
 * with ties to the earlier part, and the two-way table whose rows and columns both add up.
 */
class PosBillingAllocationTest extends TestCase
{
    public function test_equal_parts_put_the_remainder_on_the_first(): void
    {
        $this->assertSame([4, 2, 2, 2], Allocation::equal(10, 4));
        $this->assertSame([3334, 3333, 3333], Allocation::equal(10000, 3));
        $this->assertSame([0, 0], Allocation::equal(0, 2));
        $this->assertSame([7], Allocation::equal(7, 1));
    }

    public function test_proportional_shares_add_up_and_ties_go_to_the_earlier_part(): void
    {
        $this->assertSame([3, 7], Allocation::proportional(10, [1, 2])); // 3.33 / 6.67: the larger fraction gets the unit
        $this->assertSame([2, 1, 1], Allocation::proportional(4, [1, 1, 1]));
        $this->assertSame([5, 5], Allocation::proportional(10, [0, 0]));
        $this->assertSame([0, 10], Allocation::proportional(10, [0, 3]));
        $this->assertSame([1038462, 1961538], Allocation::proportional(3000000, [4275000, 8075000]));
        $this->assertSame([PHP_INT_MAX >> 12, 0], Allocation::proportional(PHP_INT_MAX >> 12, [999999999999, 0])); // no overflow
    }

    public function test_the_table_meets_every_row_and_column_total(): void
    {
        mt_srand(18);
        for ($run = 0; $run < 200; $run++) {
            $columns = array_map(fn () => mt_rand(0, 5_000_000), range(1, mt_rand(1, 8)));
            $parts = mt_rand(1, 12);
            $rows = Allocation::equal(array_sum($columns), $parts);

            $cells = Allocation::matrix($rows, $columns);

            foreach ($cells as $r => $row) {
                $this->assertSame($rows[$r], array_sum($row));
                foreach ($row as $cell) {
                    $this->assertGreaterThanOrEqual(0, $cell);
                }
            }
            foreach ($columns as $c => $total) {
                $this->assertSame($total, array_sum(array_column($cells, $c)));
            }
        }
    }

    public function test_bad_input_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Allocation::matrix([5], [4]);
    }
}
