<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipService;
use App\Models\CashMovement;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\PosTipDistribution;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Review P6 wave 2 (S6, S8): a tip's correction never moves the drawer's expected cash, and never makes the tip more
 * than was taken (the corrected total stays between 0 and the original amount, whatever the method); it locks the
 * splits covering the tip before checking they are open (a close at the same moment waits). A waiter's own view of
 * the tips shows only their own share: not who else holds a tip, nor who recorded it, nor the others' corrections.
 */
class PosTipCorrectionGuardsTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTips();
    }

    /**
     * @return array{0: PosShift, 1: PosTip}
     */
    private function tipped(): array
    {
        $shift = $this->openShift('100000');
        [, $bill] = $this->billed();
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '160000', 'tip_amount' => '10000']])->assertOk();

        return [$shift, PosTip::firstOrFail()];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function correct(PosTip $tip, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->pos('POST', "/tips/{$tip->uuid}/corrections", $data + ['mode' => 'personal', 'staff_id' => $this->waiter->id, 'reason' => 'xato', 'approval_token' => $this->approval('tips.manage')]);
    }

    /**
     * The X report's expected cash now (a request each time).
     *
     * @phpstan-impure
     */
    private function expectedCash(PosShift $shift): mixed
    {
        return $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report.cash.expected');
    }

    public function test_a_correction_never_moves_the_drawer_and_never_goes_over_the_tip_taken(): void
    {
        [$shift, $tip] = $this->tipped();
        $this->assertSame('253000.00', $this->expectedCash($shift));

        $this->correct($tip, ['amount' => '0'])->assertOk()->assertJsonPath('data.amount', '0.00');
        $this->assertSame('253000.00', $this->expectedCash($shift));
        $this->assertSame(0, CashMovement::whereNotIn('type', ['sale', 'opening'])->count());

        $this->correct($tip, ['amount' => '10000.01'])->assertStatus(422)->assertJsonPath('data.reason', 'tip_correction_too_high')
            ->assertJsonPath('data.original_amount', '10000.00');
        $this->correct($tip, ['amount' => '9999999999'])->assertStatus(422)->assertJsonPath('data.reason', 'tip_correction_too_high');
        $this->correct($tip, ['amount' => '10000'])->assertOk()->assertJsonPath('data.amount', '10000.00');
        $this->assertSame('253000.00', $this->expectedCash($shift));
        $this->assertSame('10000.00', Money::of(app(TipService::class)->amountOf($tip))->toString());
    }

    public function test_a_correction_locks_the_splits_covering_the_tip_before_checking_them(): void
    {
        $this->tipSettings(TipMode::POOL);
        [$shift, $tip] = $this->tipped();
        $this->owner('POST', '/owner/pos/tips/distributions', ['branch_id' => $this->branch->id, 'business_date' => $shift->business_date->toDateString()])->assertOk();

        $locks = [];
        DB::listen(function ($query) use (&$locks) {
            if (str_contains($query->sql, 'pos_tip_distributions') && str_contains(strtolower($query->sql), 'for update')) {
                $locks[] = $query->sql;
            }
        });
        $this->correct($tip, ['mode' => 'pool', 'amount' => '8000'])->assertOk();
        $this->assertNotEmpty($locks);

        PosTipDistribution::query()->update(['status' => 'closed']);
        $this->correct($tip, ['mode' => 'pool', 'amount' => '7000'])->assertConflict()->assertJsonPath('data.reason', 'tips_period_closed');
    }

    public function test_a_waiters_own_view_shows_only_their_share(): void
    {
        [$shift, $tip] = $this->tipped();
        // 4 000 of the waiter's tip went to the second waiter, with a reason (a split: two signed rows)
        foreach ([[$this->waiter->id, '-4000'], [$this->waiter2->id, '4000']] as [$staffId, $amount]) {
            PosTip::create(['store_id' => $tip->store_id, 'branch_id' => $tip->branch_id, 'order_id' => $tip->order_id, 'bill_id' => $tip->bill_id, 'payment_id' => $tip->payment_id, 'shift_id' => $tip->shift_id, 'business_date' => $tip->business_date->toDateString(), 'kind' => 'correction', 'method' => 'cash', 'mode' => 'personal', 'amount' => $amount, 'staff_id' => $staffId, 'corrects_id' => $tip->id, 'reason' => 'Kassir bilan kelishildi', 'recorded_by' => $this->manager->id, 'approved_by' => $this->manager->id]);
        }
        $this->assertSame(['6000.00', '4000.00'], collect(app(TipService::class)->holders($tip))->sortByDesc(fn ($h) => $h['amount']->toTiyin())->map(fn ($h) => $h['amount']->toString())->values()->all());

        $token = $this->pinSession($this->till, $this->waiter);
        $row = $this->pos('GET', "/tips?shift_uuid={$shift->uuid}", [], $token)->assertOk()->assertJsonPath('data.own_only', true)->json('data.tips.0');
        $this->assertSame('6000.00', $row['amount']);
        $this->assertSame('6000.00', $row['original_amount']);
        $this->assertSame(['id' => $this->waiter->id, 'name' => $this->waiter->name], $row['staff']);
        $this->assertNull($row['recorded_by']);
        $this->assertSame([[$this->waiter->id, '6000.00']], array_map(fn ($h) => [$h['staff']['id'], $h['amount']], $row['holders']));
        $this->assertNotContains($this->waiter2->id, array_column($row['corrections'], 'staff_id'));
        foreach ($row['corrections'] as $correction) {
            $this->assertSame($this->waiter->id, $correction['staff_id']);
            $this->assertNull($correction['by']);
            $this->assertNull($correction['approved_by']);
        }
        $this->assertStringNotContainsString($this->waiter2->name, json_encode($row));

        // The second waiter sees their 4 000 the same way
        $token2 = $this->pinSession($this->till, $this->waiter2, '4832');
        $row2 = $this->pos('GET', "/tips?shift_uuid={$shift->uuid}", [], $token2)->assertOk()->json('data.tips.0');
        $this->assertSame(['4000.00', '4000.00', $this->waiter2->id], [$row2['amount'], $row2['original_amount'], $row2['staff']['id']]);
        $this->assertStringNotContainsString($this->waiter->name, json_encode($row2));
    }
}
