<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Tips\TipDistributionStatus;
use App\Domain\Pos\Tips\TipKind;
use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolRule;
use App\Models\PosTip;
use App\Models\PosTipDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-22: a manager corrects a tip with a reason (tips.manage, or a manager's PIN on the till): a new amount, another
 * waiter, or into the pool — signed rows next to the original, audited; a closed pool split locks the tips it covers.
 * The till lists a shift's tips; a waiter sees only theirs.
 */
class PosTipCorrectionsTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTips();
    }

    private function tipped(string $tip = '10000'): PosTip
    {
        [, $bill] = $this->billed();
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => $tip]])->assertOk();

        return PosTip::where('kind', 'tip')->latest('id')->firstOrFail();
    }

    public function test_the_cashier_needs_a_managers_approval_and_the_correction_moves_the_tip(): void
    {
        $shift = $this->openShift();
        $tip = $this->tipped();
        $correct = fn (array $data) => $this->pos('POST', "/tips/{$tip->uuid}/corrections", $data);

        $correct(['mode' => 'personal', 'staff_id' => $this->waiter2->id, 'reason' => 'Boshqa ofitsiant xizmat qildi'])
            ->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'tips.manage');

        $correct(['mode' => 'personal', 'staff_id' => $this->waiter2->id, 'reason' => 'Boshqa ofitsiant xizmat qildi', 'approval_token' => $this->approval('tips.manage')])
            ->assertOk()
            ->assertJsonPath('data.amount', '10000.00')
            ->assertJsonPath('data.staff.id', $this->waiter2->id)
            ->assertJsonPath('data.corrections.0.amount', '-10000.00')
            ->assertJsonPath('data.corrections.1.amount', '10000.00')
            ->assertJsonPath('data.corrections.1.reason', 'Boshqa ofitsiant xizmat qildi')
            ->assertJsonPath('data.corrections.1.approved_by', $this->manager->id);

        // A new amount for the same person (a typo at the till): one row of the difference; the drawer follows
        $this->posAs($this->manager, 'POST', "/tips/{$tip->uuid}/corrections", ['amount' => '6000', 'mode' => 'personal', 'staff_id' => $this->waiter2->id, 'reason' => "Kassir xato yozdi"])
            ->assertOk()->assertJsonPath('data.amount', '6000.00')->assertJsonPath('data.original_amount', '10000.00');
        $this->assertSame('-4000.00', PosTip::where('kind', TipKind::CORRECTION->value)->latest('id')->value('amount'));

        $report = $this->posAs($this->manager, 'GET', "/shifts/{$shift->uuid}/x-report")->json('data.report');
        $this->assertSame(['6000.00', '6000.00'], [$report['tips']['amount'], $report['cash']['tips']]);
        $this->assertSame([['staff_id' => $this->waiter2->id, 'name' => $this->waiter2->name, 'amount' => '6000.00']], $report['tips']['by_staff']);
        $this->assertSame(['count' => 1, 'amount' => '-4000.00'], $report['tips']['corrections']);

        // Nothing changes / no reason / unknown person
        $this->posAs($this->manager, 'POST', "/tips/{$tip->uuid}/corrections", ['mode' => 'personal', 'staff_id' => $this->waiter2->id, 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'nothing_to_correct');
        $this->posAs($this->manager, 'POST', "/tips/{$tip->uuid}/corrections", ['mode' => 'pool'])->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->posAs($this->manager, 'POST', "/tips/{$tip->uuid}/corrections", ['mode' => 'personal', 'staff_id' => $this->owner->id + 999, 'reason' => 'x'])
            ->assertStatus(422)->assertJsonPath('data.reason', 'staff_not_found');

        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.tip_corrected', 'model_id' => $tip->id]);
    }

    public function test_the_owner_corrects_into_the_pool_and_a_closed_split_locks_the_day(): void
    {
        $this->openShift();
        $tip = $this->tipped('9000');

        $this->owner('POST', "/owner/pos/tips/{$tip->uuid}/corrections", ['mode' => 'pool', 'reason' => "Umumiy qozonga"])
            ->assertOk()->assertJsonPath('data.mode', 'pool')->assertJsonPath('data.staff', null);
        $this->assertSame('9000.00', PosTip::where('mode', TipMode::POOL->value)->sum('amount') . '');

        $this->tipSettings(TipMode::POOL, TipPoolRule::EQUAL, ['waiter']);
        $date = $tip->business_date->toDateString();
        $uuid = $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $date, 'participants' => [['staff_id' => $this->waiter->id], ['staff_id' => $this->waiter2->id]]])
            ->assertOk()->assertJsonPath('data.pool_amount', '9000.00')->json('data.uuid');
        $this->owner('POST', "/owner/pos/tips/distributions/{$uuid}/close")->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertSame(TipDistributionStatus::CLOSED, PosTipDistribution::firstOrFail()->status);

        $this->owner('POST', "/owner/pos/tips/{$tip->uuid}/corrections", ['mode' => 'personal', 'staff_id' => $this->waiter->id, 'reason' => 'Qaytarish'])
            ->assertConflict()->assertJsonPath('data.reason', 'tips_period_closed');
        $this->owner('GET', "/owner/pos/tips/entries?branch_id={$this->branch->id}&from={$date}&to={$date}")->assertOk()->assertJsonPath('data.tips.0.closed', true);

        // A cashier holds no tips.manage for the owner's endpoint; a waiter no reports.view
        $this->owner('POST', "/owner/pos/tips/{$tip->uuid}/corrections", ['mode' => 'pool', 'reason' => 'x'], $this->cashier)->assertForbidden();
    }

    public function test_the_till_lists_the_shifts_tips_and_a_waiter_sees_only_theirs(): void
    {
        $shift = $this->openShift();
        $this->tipped('10000');
        [, $bill] = $this->billed(waiter: $this->waiter2);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => '4000']])->assertOk();

        $this->pos('GET', '/tips')->assertOk()
            ->assertJsonPath('data.shift_uuid', $shift->uuid)
            ->assertJsonPath('data.own_only', false)
            ->assertJsonPath('data.summary.amount', '14000.00')
            ->assertJsonCount(2, 'data.tips');

        $waiterToken = $this->pinSession($this->till, $this->waiter2);
        $this->pos('GET', "/tips?shift_uuid={$shift->uuid}", [], $waiterToken)->assertOk()
            ->assertJsonPath('data.own_only', true)
            ->assertJsonPath('data.summary.amount', '4000.00')
            ->assertJsonCount(1, 'data.tips')
            ->assertJsonPath('data.tips.0.staff.id', $this->waiter2->id);
    }
}
