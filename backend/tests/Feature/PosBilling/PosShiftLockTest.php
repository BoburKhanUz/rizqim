<?php

namespace Tests\Feature\PosBilling;

use App\Models\PosShift;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * P6-19: the money writes lock the shift's row FOR NO KEY UPDATE, not FOR UPDATE. A payment locks the shift, then the
 * order; the kitchen display bumping the last line of the same pay-first order locks the order, then its update's
 * foreign-key check takes FOR KEY SHARE on the shift: with FOR UPDATE the two deadlocked under load (the e2e "osh
 * markazi tushligi", two tills and a cook at once). NO KEY UPDATE still makes the money writers wait for each other.
 */
class PosShiftLockTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosBilling();
    }

    /**
     * @return list<string> the statements that locked the shifts table while $run ran
     */
    private function shiftLocks(callable $run): array
    {
        $locks = [];
        DB::listen(function (QueryExecuted $query) use (&$locks) {
            if (str_contains($query->sql, '"pos_shifts"') && str_contains($query->sql, ' for ')) {
                $locks[] = $query->sql;
            }
        });
        $run();

        return $locks;
    }

    public function test_a_payment_locks_the_shift_without_blocking_its_orders_foreign_keys(): void
    {
        $shift = $this->openShift();
        $order = $this->posOrder([['Osh', '1', '120000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $locks = $this->shiftLocks(fn () => $this->pay($bill, [['tender' => 'cash', 'amount' => '120000']])->assertOk());
        $this->assertNotEmpty($locks);
        foreach ($locks as $sql) {
            $this->assertStringEndsWith('for no key update', $sql);
        }

        $this->assertSame((int) $shift->id, (int) PosShift::lockForMoney((int) $shift->id)->id);
    }
}
