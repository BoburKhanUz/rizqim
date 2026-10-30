<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\StaffPay\PosTipsStaffTips;
use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolDistributor;
use App\Domain\Pos\Tips\TipPoolPeriod;
use App\Domain\Pos\Tips\TipPoolRule;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\PosTipDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6 wave 2 review (S3, C2, C5, the first-compute race): who may be in a manual pool list (the pool's roles, of the
 * branch, never the manager who computes it), the audit of the list and the shares, staff pay counting a split only
 * once it is closed, the branch's pool period enforced (a day split and shift splits of one date never both exist),
 * closing a split nobody takes refused, and two first computes at once giving a 409, not a 500.
 */
class PosTipPoolRulesTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTips();
    }

    private function poolTip(string $amount = '10000'): PosShift
    {
        $shift = PosShift::where('branch_id', $this->branch->id)->whereNull('closed_at')->first() ?? $this->openShift();
        [, $bill] = $this->billed([['Osh', '1', '100000']]);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000', 'tip_amount' => $amount]])->assertOk();

        return $shift;
    }

    private function compute(array $data, $user = null)
    {
        return $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id] + $data, $user);
    }

    public function test_a_manual_list_takes_only_the_pool_roles_and_never_the_manager_who_computes_it(): void
    {
        $this->tipSettings(TipMode::POOL, roles: ['waiter']);
        $date = $this->poolTip()->business_date->toDateString();

        // The manager names himself: refused (the owner may put a manager in, when managers are a pool role)
        $this->compute(['business_date' => $date, 'participants' => [['staff_id' => $this->manager->id]]], $this->manager)
            ->assertForbidden()->assertJsonPath('data.reason', 'tips_pool_self');
        // A cook or the cashier is not of the pool's roles (waiters)
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);
        $this->compute(['business_date' => $date, 'participants' => [['staff_id' => $this->waiter->id], ['staff_id' => $cook->id], ['staff_id' => $this->cashier->id]]], $this->manager)
            ->assertStatus(422)->assertJsonPath('data.reason', 'staff_not_in_pool')
            ->assertJsonPath('data.staff_ids', [$this->cashier->id, $cook->id]);
        // Someone of another branch
        $other = $this->makeBranch($this->store, 'Ikkinchi filial');
        $stranger = $this->makeStaff(UserRole::WAITER, $other);
        $this->compute(['business_date' => $date, 'participants' => [['staff_id' => $stranger->id]]], $this->manager)
            ->assertStatus(422)->assertJsonPath('data.reason', 'staff_not_found');
        $this->assertSame(0, PosTipDistribution::count());

        // Managers in the pool: the owner may name one; the manager still not himself
        $this->tipSettings(TipMode::POOL, roles: ['waiter', 'manager']);
        $this->compute(['business_date' => $date, 'participants' => [['staff_id' => $this->manager->id]]], $this->manager)
            ->assertForbidden()->assertJsonPath('data.reason', 'tips_pool_self');
        $split = $this->compute(['business_date' => $date, 'participants' => [['staff_id' => $this->waiter->id], ['staff_id' => $this->manager->id]]])
            ->assertOk()->json('data');
        $shares = [$this->waiter->id => '5000.00', $this->manager->id => '5000.00'];
        ksort($shares);
        $this->assertSame($shares, collect($split['lines'])->pluck('amount', 'staff_id')->all());

        // The audit row says who and how much
        $log = AuditLog::where('action', 'pos.tips_distribution_computed')->latest('id')->firstOrFail();
        $meta = $log->payload_json ?? [];
        $this->assertSame(array_keys($shares), $meta['participants'] ?? null);
        $this->assertEquals($shares, $meta['shares'] ?? null);
        $this->assertTrue($meta['manual'] ?? null);
    }

    public function test_staff_pay_counts_a_pool_split_only_once_it_is_closed(): void
    {
        $this->tipSettings(TipMode::POOL, roles: ['waiter']);
        $date = $this->poolTip()->business_date->toDateString();
        $branch = Branch::findOrFail($this->branch->id);
        $uuid = $this->compute(['business_date' => $date])->assertOk()->json('data.uuid');

        $this->assertArrayNotHasKey($this->waiter->id, app(PosTipsStaffTips::class)->tipsByDay($branch, $date, $date));

        $this->owner('POST', "/owner/pos/tips/distributions/{$uuid}/close")->assertOk();
        $this->assertSame([$date => '10000.00'], app(PosTipsStaffTips::class)->tipsByDay($branch, $date, $date)[$this->waiter->id]);
    }

    public function test_the_pool_period_is_enforced_and_never_counted_twice(): void
    {
        // Day: no shift split
        $this->tipSettings(TipMode::POOL, roles: ['waiter']);
        $shift = $this->poolTip();
        $date = $shift->business_date->toDateString();
        $this->compute(['business_date' => $date, 'shift_uuid' => $shift->uuid])->assertStatus(422)->assertJsonPath('data.reason', 'tips_pool_period_day');
        $this->compute(['business_date' => $date])->assertOk();

        // Shift: the shift is required, and the day already has a day split
        $this->tipSettings(TipMode::POOL, roles: ['waiter'], period: TipPoolPeriod::SHIFT);
        $this->compute(['business_date' => $date])->assertStatus(422)->assertJsonPath('data.reason', 'tips_pool_period_shift');
        $this->compute(['business_date' => $date, 'shift_uuid' => $shift->uuid])->assertConflict()->assertJsonPath('data.reason', 'tips_pool_period_conflict');

        // The other way round: shift splits first, then no day split of that date
        PosTipDistribution::query()->delete();
        $this->compute(['business_date' => $date, 'shift_uuid' => $shift->uuid])->assertOk()->assertJsonPath('data.shift_uuid', $shift->uuid);
        $this->tipSettings(TipMode::POOL, roles: ['waiter']);
        $this->compute(['business_date' => $date])->assertConflict()->assertJsonPath('data.reason', 'tips_pool_period_conflict');

        // The panel reads the period with the splits
        $this->owner('GET', "/owner/pos/tips/distributions?branch_id={$this->branch->id}&from={$date}&to={$date}")->assertOk()
            ->assertJsonPath('data.pool_period', 'day')->assertJsonCount(1, 'data.distributions');

        // The domain refuses too (not only the API)
        $this->expectException(PosActionException::class);
        app(TipPoolDistributor::class)->compute(Branch::findOrFail($this->branch->id), $date, null, null, $this->manager);
    }

    public function test_a_split_nobody_takes_cannot_be_closed(): void
    {
        // The pool's roles are cooks, and no cook worked: a pool of 10 000 with no lines
        $this->tipSettings(TipMode::POOL, roles: ['cook']);
        $date = $this->poolTip()->business_date->toDateString();
        $split = $this->compute(['business_date' => $date])->assertOk()->assertJsonPath('data.pool_amount', '10000.00')->assertJsonPath('data.distributed', '0.00')->json('data');
        $this->assertSame([], $split['lines']);

        $this->owner('POST', "/owner/pos/tips/distributions/{$split['uuid']}/close")->assertConflict()
            ->assertJsonPath('data.reason', 'tips_distribution_unassigned')->assertJsonPath('data.pool_amount', '10000.00');

        // With people it closes
        $this->tipSettings(TipMode::POOL, roles: ['waiter']);
        $this->compute(['business_date' => $date])->assertOk()->assertJsonPath('data.distributed', '10000.00');
        $this->owner('POST', "/owner/pos/tips/distributions/{$split['uuid']}/close")->assertOk();
    }

    public function test_two_first_computes_at_once_give_a_conflict_not_a_server_error(): void
    {
        $this->tipSettings(TipMode::POOL, roles: ['waiter']);
        $date = $this->poolTip()->business_date->toDateString();

        // The other request's row lands between this one's look-up and its insert
        Event::listen('eloquent.creating: ' . PosTipDistribution::class, function (PosTipDistribution $row) {
            DB::table('pos_tip_distributions')->insert([
                'uuid' => (string) Str::uuid(), 'store_id' => $row->store_id, 'branch_id' => $row->branch_id, 'business_date' => $row->business_date,
                'shift_id' => $row->shift_id, 'rule' => 'equal', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->compute(['business_date' => $date])->assertConflict()->assertJsonPath('data.reason', 'tips_distribution_busy');
        $this->assertSame(0, PosTipDistribution::count());
    }
}
