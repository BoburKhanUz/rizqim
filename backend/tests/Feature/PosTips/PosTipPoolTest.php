<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolDistributor;
use App\Domain\Pos\Tips\TipPoolPeriod;
use App\Domain\Pos\Tips\TipPoolRule;
use App\Enums\UserRole;
use App\Models\PosShift;
use App\Models\PosTipDistributionLine;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-22: the tip pool's split per business day or shift by each rule — equal, hours, sales, role weights — among the
 * roles taking part (or the manager's own list), to the so'm with the remainder rule (the lines add up to the pool),
 * stored and recomputed until closed, refused when stale or closed.
 */
class PosTipPoolTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTips();
    }

    /**
     * @param  array<int, int|string>  $bases
     * @return array<int, string>
     */
    private function split(string $pool, array $bases): array
    {
        return array_map(fn (Money $share) => $share->toString(), TipPoolDistributor::split(Money::of($pool), array_map(fn ($basis) => BigInteger::of($basis), $bases)));
    }

    public function test_the_split_rounds_to_the_soum_and_always_adds_up(): void
    {
        // 100 001 so'm in three: 33 333 each, the 2 so'm left to the largest remainders (equal: the lower ids first)
        $this->assertSame([1 => '33334.00', 2 => '33334.00', 3 => '33333.00'], $this->split('100001', [1 => 1, 2 => 1, 3 => 1]));
        // By minutes 120 / 60: 6 666.67 / 3 333.33 → 6 667 / 3 333
        $this->assertSame([7 => '6667.00', 9 => '3333.00'], $this->split('10000', [7 => 120, 9 => 60]));
        // A tiyin pool: the whole so'm shared, the tiyin to the largest share
        $this->assertSame([1 => '50.50', 2 => '50.00'], $this->split('100.50', [1 => 1, 2 => 1]));
        $this->assertSame([1 => '3.00', 2 => '7.25'], $this->split('10.25', [1 => 3, 2 => 7]));
        // A tie of remainders goes to the larger basis
        $this->assertSame([1 => '0.00', 2 => '2.00'], $this->split('2', [1 => 1, 2 => 3]));
        $this->assertSame([1 => '1.00', 2 => '2.00'], $this->split('3', [1 => 3, 2 => 5]));
        // Nothing in the pool, nobody to share it with
        $this->assertSame([1 => '0.00'], $this->split('0', [1 => 1]));
        $this->assertSame([], $this->split('100', []));

        foreach ([['999999.99', [1 => 7, 2 => 11, 3 => 13, 4 => 1]], ['12345', [5 => 17, 6 => 17, 7 => 17]], ['1', [1 => 1, 2 => 1, 3 => 1]]] as [$pool, $bases]) {
            $this->assertSame(Money::of($pool)->toString(), Money::sum(...array_values($this->split($pool, $bases)))->toString());
        }
    }

    private function poolTips(array $amounts, ?PosShift $shift = null): void
    {
        foreach ($amounts as $amount) {
            [, $bill] = $this->billed([['Osh', '1', '100000']]);
            $this->pay($bill, [['tender' => 'cash', 'amount' => '100000', 'tip_amount' => $amount]], $shift !== null ? ['shift_uuid' => $shift->uuid] : [])->assertOk();
        }
    }

    public function test_equal_among_the_waiters_who_worked_from_their_orders_and_the_cashier_by_role(): void
    {
        $shift = $this->openShift();
        $this->tipSettings(TipMode::POOL, TipPoolRule::EQUAL, ['waiter']);
        [, $bill] = $this->billed([['Osh', '1', '100000']], $this->waiter2);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000', 'tip_amount' => '10001']])->assertOk();
        $this->poolTips(['20000']); // served by the first waiter

        $date = $shift->business_date->toDateString();
        $response = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->assertOk();
        $response->assertJsonPath('data.rule', 'equal')->assertJsonPath('data.pool_amount', '30001.00')->assertJsonPath('data.tips_count', 2)
            ->assertJsonPath('data.distributed', '30001.00')->assertJsonPath('data.status', 'open')->assertJsonCount(2, 'data.lines');
        $lines = collect($response->json('data.lines'))->pluck('amount', 'staff_id')->all();
        $this->assertSame([$this->waiter->id => '15001.00', $this->waiter2->id => '15000.00'], $lines);

        // Cashiers too (they opened the shift and took the payments)
        $this->tipSettings(TipMode::POOL, TipPoolRule::EQUAL, ['waiter', 'cashier']);
        $lines = collect($this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->json('data.lines'));
        $this->assertSame([$this->cashier->id, $this->waiter->id, $this->waiter2->id], $lines->pluck('staff_id')->sort()->values()->all());
        $this->assertSame('30001.00', Money::sum(...$lines->pluck('amount')->all())->toString());
        $this->assertSame(3, PosTipDistributionLine::count()); // recomputing replaced the lines
    }

    public function test_hours_from_clock_in_sessions_sales_from_paid_bills_and_role_weights(): void
    {
        $shift = $this->openShift();
        $date = $shift->business_date->toDateString();
        $this->tipSettings(TipMode::POOL, TipPoolRule::HOURS, ['waiter']);
        $this->poolTips(['9000']);
        [, $bill] = $this->billed([['Osh', '1', '300000']], $this->waiter2);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '300000']])->assertOk(); // sales, no tip

        // P6-23 sessions: 4 h and 2 h
        foreach ([[$this->waiter, 240], [$this->waiter2, 120]] as [$user, $minutes]) {
            DB::table('staff_work_sessions')->insert([
                'uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'user_id' => $user->id,
                'business_date' => $date, 'clock_in_at' => $shift->opened_at, 'clock_out_at' => $shift->opened_at->copy()->addMinutes($minutes),
                'source' => 'manual', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $hours = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->assertOk();
        $this->assertSame([$this->waiter->id => ['240.00', '6000.00'], $this->waiter2->id => ['120.00', '3000.00']], collect($hours->json('data.lines'))->mapWithKeys(fn ($line) => [$line['staff_id'] => [$line['basis'], $line['amount']]])->all());

        // Sales: 100 000 and 300 000 → 2 250 / 6 750
        $this->tipSettings(TipMode::POOL, TipPoolRule::SALES, ['waiter']);
        $sales = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->assertOk()->assertJsonPath('data.rule', 'sales');
        $this->assertSame([$this->waiter->id => ['100000.00', '2250.00'], $this->waiter2->id => ['300000.00', '6750.00']], collect($sales->json('data.lines'))->mapWithKeys(fn ($line) => [$line['staff_id'] => [$line['basis'], $line['amount']]])->all());

        // Role weights: a cook weighing 2 beside the waiters at 1 (the manager's own list)
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->tipSettings(TipMode::POOL, TipPoolRule::ROLE_WEIGHTS, ['waiter', 'cook'], ['cook' => '2', 'waiter' => '1']);
        $weights = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date, 'participants' => [['staff_id' => $this->waiter->id], ['staff_id' => $this->waiter2->id], ['staff_id' => $cook->id]]])->assertOk();
        $this->assertSame([$this->waiter->id => '2250.00', $this->waiter2->id => '2250.00', $cook->id => '4500.00'], collect($weights->json('data.lines'))->pluck('amount', 'staff_id')->all());
        $weights->assertJsonPath('data.manual_participants', true);
    }

    public function test_nobody_with_a_basis_falls_back_to_equal_and_a_split_per_shift(): void
    {
        $shift = $this->openShift();
        $date = $shift->business_date->toDateString();
        $this->tipSettings(TipMode::POOL, TipPoolRule::HOURS, ['waiter'], [], TipPoolPeriod::SHIFT);
        $this->poolTips(['5000']);

        $response = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date, 'shift_uuid' => $shift->uuid, 'participants' => [['staff_id' => $this->waiter->id, 'minutes' => 0], ['staff_id' => $this->waiter2->id, 'minutes' => 0]]])
            ->assertOk()->assertJsonPath('data.rule', 'equal')->assertJsonPath('data.shift_uuid', $shift->uuid);
        $this->assertSame(['2500.00', '2500.00'], collect($response->json('data.lines'))->pluck('amount')->all());

        $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => '2020-01-01', 'shift_uuid' => $shift->uuid])
            ->assertStatus(422)->assertJsonValidationErrors('shift_uuid');
        $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date, 'shift_uuid' => $shift->uuid, 'participants' => [['staff_id' => $this->owner->id + 999]]])
            ->assertStatus(422)->assertJsonPath('data.reason', 'staff_not_found');
    }

    public function test_closing_freezes_the_split_and_a_stale_one_must_be_computed_again(): void
    {
        $shift = $this->openShift();
        $date = $shift->business_date->toDateString();
        $this->tipSettings(TipMode::POOL);
        $this->poolTips(['6000']);
        $uuid = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->json('data.uuid');

        $this->poolTips(['4000']);
        $this->owner('POST', "/owner/pos/tips/distributions/{$uuid}/close")->assertConflict()->assertJsonPath('data.reason', 'tips_distribution_stale')->assertJsonPath('data.pool_amount', '10000.00');

        $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->assertOk()->assertJsonPath('data.uuid', $uuid)->assertJsonPath('data.pool_amount', '10000.00');
        $this->owner('POST', "/owner/pos/tips/distributions/{$uuid}/close")->assertOk()->assertJsonPath('data.closed_by.id', $this->owner->id);
        $this->owner('POST', "/owner/pos/tips/distributions/{$uuid}/close")->assertConflict()->assertJsonPath('data.reason', 'tips_distribution_closed');
        $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date])->assertConflict()->assertJsonPath('data.reason', 'tips_distribution_closed');

        // The report: the day, its closed split and each person's share
        $report = $this->owner('GET', "/owner/pos/tips?branch_id={$this->branch->id}&from={$date}&to={$date}")->assertOk();
        $report->assertJsonPath('data.totals.amount', '10000.00')->assertJsonPath('data.by_day.0.pool', '10000.00')
            ->assertJsonPath('data.by_day.0.distributions.0.status', 'closed')->assertJsonPath('data.by_day.0.undistributed', '0.00')
            ->assertJsonPath('data.undistributed', '0.00');
        $this->assertSame('10000.00', Money::sum(...collect($report->json('data.by_staff'))->pluck('pool_share_closed')->all())->toString());
        $this->owner('GET', "/owner/pos/tips/distributions?branch_id={$this->branch->id}&from={$date}&to={$date}")->assertOk()->assertJsonCount(1, 'data.distributions');
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.tips_distribution_closed']);
    }
}
