<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Shifts\ShiftStatus;
use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolDistributor;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\PosApproval;
use App\Models\PosShift;
use App\Models\PosTip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review P6 wave 2 (S2, S7): paying tips out of the till ("Choy pulini berish": a `wage` movement of category `tips`)
 * needs tips.manage or a manager's approval for it, used once; it never goes over the cash tips the shift took less
 * what was paid out of them, nor over what the person is owed in cash on the shift's business day (their personal
 * cash tips, corrections included, plus their share of a closed pool split, less the tips already paid to them).
 */
class PosTipPayoutTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTips();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function payout(PosShift $shift, int $to, string $amount, array $extra = [], ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'wage', 'category' => 'tips', 'amount' => $amount, 'employee_id' => $to] + $extra, $token);
    }

    private function cashTip(string $amount, ?int $waiter = null, string $tender = 'cash'): void
    {
        [, $bill] = $this->billed(waiter: $waiter !== null ? \App\Models\User::findOrFail($waiter) : null);
        $tenderRow = $tender === 'cash'
            ? ['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => (string) (143000 + (int) $amount), 'tip_amount' => $amount]
            : ['tender' => 'card', 'amount' => '143000', 'card_type' => 'uzcard', 'transaction_id' => (string) Str::uuid(), 'tip_amount' => $amount];
        $this->pay($bill, [$tenderRow])->assertOk();
    }

    public function test_a_tip_payout_needs_tips_manage_or_a_managers_approval_used_once(): void
    {
        $shift = $this->openShift('100000');
        $this->cashTip('10000');

        // The cashier holds pos.cash_in_out, not tips.manage
        $this->payout($shift, $this->waiter->id, '4000')->assertForbidden()
            ->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'tips.manage');
        // ... an approval for another permission does not do
        $this->payout($shift, $this->waiter->id, '4000', ['approval_token' => $this->approval('pos.cash_in_out')])->assertForbidden()
            ->assertJsonPath('data.reason', 'approval_wrong_permission');

        $token = $this->approval('tips.manage');
        $this->payout($shift, $this->waiter->id, '4000', ['approval_token' => $token])->assertCreated()
            ->assertJsonPath('data.movement.approved_by', $this->manager->id);
        $this->assertNotNull(PosApproval::where('permission', 'tips.manage')->value('used_at'));
        $this->payout($shift, $this->waiter->id, '4000', ['approval_token' => $token])->assertForbidden()->assertJsonPath('data.reason', 'approval_used');

        // A manager on the till pays out alone
        $managerToken = $this->pinSession($this->till, $this->manager);
        $this->payout($shift, $this->waiter->id, '4000', [], $managerToken)->assertCreated()->assertJsonPath('data.movement.approved_by', null);
        $this->assertSame(2, CashMovement::where('category', 'tips')->count());
    }

    public function test_a_tip_payout_stays_within_the_shifts_cash_tips_and_what_the_person_is_owed(): void
    {
        $shift = $this->openShift('500000');
        $this->cashTip('10000');                                   // the waiter's, in this drawer
        $this->cashTip('6000', $this->waiter2->id);                 // the second waiter's
        $this->cashTip('8000', tender: 'card');                     // a card tip: never in the drawer
        $approved = fn (array $extra = []) => $extra + ['approval_token' => $this->approval('tips.manage')];

        $this->payout($shift, $this->waiter->id, '12000', $approved())->assertConflict()
            ->assertJsonPath('data.reason', 'tip_payout_exceeds_owed')->assertJsonPath('data.owed', '10000.00');
        $this->payout($shift, $this->cashier->id, '1000', $approved())->assertConflict()
            ->assertJsonPath('data.reason', 'tip_payout_exceeds_owed')->assertJsonPath('data.owed', '0.00');
        $this->payout($shift, $this->waiter->id, '10000', $approved())->assertCreated();
        $this->payout($shift, $this->waiter->id, '1', $approved())->assertConflict()->assertJsonPath('data.owed', '0.00');

        // A manager moves the second waiter's tip to the first: owed to the first now
        $tip = PosTip::where('staff_id', $this->waiter2->id)->firstOrFail();
        $this->pos('POST', "/tips/{$tip->uuid}/corrections", ['mode' => 'personal', 'staff_id' => $this->waiter->id, 'reason' => 'Bir stol', 'approval_token' => $this->approval('tips.manage')])->assertOk();
        $this->payout($shift, $this->waiter2->id, '6000', $approved())->assertConflict()->assertJsonPath('data.owed', '0.00');
        $this->payout($shift, $this->waiter->id, '6000', $approved())->assertCreated();

        // The waiter's cash tips taken on another till the same day are owed too, but this drawer holds only its own
        $other = PosShift::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'device_id' => $this->till['device']->id, 'business_date' => $shift->business_date, 'status' => ShiftStatus::CLOSED->value, 'opened_by' => $this->cashier->id, 'opened_at' => now()->subHours(5), 'closed_by' => $this->cashier->id, 'closed_at' => now()->subHours(4), 'opening_cash' => '0', 'z_number' => 1]);
        DB::table('pos_tips')->insert(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'shift_id' => $other->id, 'business_date' => $shift->business_date->toDateString(), 'kind' => 'tip', 'method' => 'cash', 'mode' => 'personal', 'amount' => '20000', 'staff_id' => $this->waiter->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->cashTip('3000');
        $this->payout($shift, $this->waiter->id, '5000', $approved())->assertConflict()
            ->assertJsonPath('data.reason', 'tip_payout_exceeds_tips')->assertJsonPath('data.available', '3000.00');
        $this->payout($shift, $this->waiter->id, '3000', $approved())->assertCreated();
        $this->assertSame('19000.00', $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report.cash.tip_payouts'));
    }

    public function test_pool_tips_are_owed_in_cash_once_the_split_is_closed(): void
    {
        $this->tipSettings(TipMode::POOL);
        $shift = $this->openShift('100000');
        $this->cashTip('10000');
        $approved = fn () => ['approval_token' => $this->approval('tips.manage')];

        $this->payout($shift, $this->waiter->id, '5000', $approved())->assertConflict()->assertJsonPath('data.owed', '0.00');

        $distributor = app(TipPoolDistributor::class);
        $split = $distributor->compute(Branch::findOrFail($this->branch->id), $shift->business_date->toDateString(), null, [['staff_id' => $this->waiter->id], ['staff_id' => $this->waiter2->id]], $this->owner);
        $this->payout($shift, $this->waiter->id, '5000', $approved())->assertConflict()->assertJsonPath('data.owed', '0.00');
        $distributor->close($split, $this->owner);

        $this->payout($shift, $this->waiter->id, '5000', $approved())->assertCreated();
        $this->payout($shift, $this->waiter->id, '1', $approved())->assertConflict()->assertJsonPath('data.reason', 'tip_payout_exceeds_owed')->assertJsonPath('data.owed', '0.00');
        $this->payout($shift, $this->waiter2->id, '5000', $approved())->assertCreated();
    }
}
