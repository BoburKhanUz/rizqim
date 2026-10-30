<?php

namespace Tests\Feature\PosStaffPay;

use App\Models\CashMovement;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Review P6 wave 2 (S1/C1): money given from the till is taken off the pay statement. Tips paid out from the drawer
 * (a `wage` movement of category `tips`) are taken off a line whose rule pays the `tips` part (`tips_paid`, apart
 * from the advances), and never off a line without it (that money was the guest's tip, not a wage). `payroll` is
 * written only by paying a statement line; a manual one is refused, and a legacy `payroll` movement not linked to a
 * statement line counts as an advance.
 */
class StaffPayTillDeductionsTest extends TestCase
{
    use RefreshDatabase;
    use StaffPayFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-02 10:00:00', 'UTC'));
        $this->setUpStaffPay();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cashTip(PosShift $shift, int $staffId, string $amount): void
    {
        DB::table('pos_tips')->insert(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'shift_id' => $shift->id, 'business_date' => $shift->business_date->toDateString(), 'kind' => 'tip', 'method' => 'cash', 'mode' => 'personal', 'amount' => $amount, 'staff_id' => $staffId, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function wage(PosShift $shift, string $category, int $employeeId, string $amount): int
    {
        return (int) DB::table('cash_movements')->insertGetId(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'shift_id' => $shift->id, 'type' => 'wage', 'category' => $category, 'amount' => $amount, 'employee_id' => $employeeId, 'user_id' => $this->cashier->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @return array<int, array<string, mixed>> user id => line
     */
    private function statement(string $date): array
    {
        $statement = $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => $date])->assertCreated()->json('data');

        return collect($statement['lines'])->keyBy('user_id')->all();
    }

    public function test_tips_paid_from_the_till_are_taken_off_a_line_with_the_tips_part_only(): void
    {
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['user_id' => $this->waiter->id, 'branch_id' => $this->branch->id, 'effective_from' => '2026-11-01', 'components' => [['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'tips']]])->assertCreated();
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['role' => 'cashier', 'branch_id' => $this->branch->id, 'effective_from' => '2026-11-01', 'components' => [['type' => 'shift_rate', 'amount' => '100000']]])->assertCreated();
        $shift = $this->closedShift('2026-11-10', $this->cashier, '2026-11-10 04:00', '2026-11-10 16:00');
        $this->paidBill($this->waiter, $shift, '100000');
        // A 30 000 cash tip to the waiter, handed to them from the drawer the same evening ("Choy pulini berish")
        $this->cashTip($shift, $this->waiter->id, '30000');
        $this->wage($shift, 'tips', $this->waiter->id, '30000');
        // A guest's 15 000 tip handed to the cashier, whose rule has no tips part: her wage is not touched
        $this->cashTip($shift, $this->cashier->id, '15000');
        $this->wage($shift, 'tips', $this->cashier->id, '15000');
        $this->wage($shift, 'advance', $this->cashier->id, '20000');

        $lines = $this->statement('2026-11-10');
        $waiter = $lines[$this->waiter->id];
        $this->assertSame(['130000.00', '0.00', '30000.00', '100000.00'], [$waiter['gross'], $waiter['advances'], $waiter['tips_paid'], $waiter['payable']]);
        $cashier = $lines[$this->cashier->id];
        $this->assertSame(['100000.00', '20000.00', '0.00', '80000.00'], [$cashier['gross'], $cashier['advances'], $cashier['tips_paid'], $cashier['payable']]);

        // A manual adjustment keeps the tips paid out off the payable
        $statementUuid = \App\Models\StaffPayStatement::query()->value('uuid');
        $adjusted = $this->as($this->owner, 'PUT', "/owner/staff-pay/statements/{$statementUuid}/lines/{$waiter['uuid']}", ['adjustment' => '5000', 'reason' => 'Mukofot'])->assertOk()->json('data.lines');
        $this->assertSame('105000.00', collect($adjusted)->firstWhere('user_id', $this->waiter->id)['payable']);
    }

    public function test_payroll_is_refused_by_hand_and_a_legacy_unlinked_payroll_is_an_advance(): void
    {
        $shift = $this->openShift('500000');
        $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'wage', 'category' => 'payroll', 'amount' => '50000', 'employee_id' => $this->cashier->id])
            ->assertStatus(422)->assertJsonPath('data.reason', 'movement_category_reserved');
        $this->assertSame(0, CashMovement::where('category', 'payroll')->count());

        // Legacy: a payroll movement written by hand before the rule, linked to no statement line
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['role' => 'cashier', 'branch_id' => $this->branch->id, 'effective_from' => '2026-11-01', 'components' => [['type' => 'shift_rate', 'amount' => '100000']]])->assertCreated();
        $closed = $this->closedShift('2026-11-10', $this->cashier, '2026-11-10 04:00', '2026-11-10 16:00');
        $this->wage($closed, 'payroll', $this->cashier->id, '50000');
        $this->assertSame('50000.00', $this->statement('2026-11-10')[$this->cashier->id]['advances']);
    }

    public function test_a_statement_payment_from_the_till_is_not_an_advance_of_the_next_period(): void
    {
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['role' => 'cashier', 'branch_id' => $this->branch->id, 'effective_from' => '2026-11-01', 'components' => [['type' => 'shift_rate', 'amount' => '100000']]])->assertCreated();
        $this->closedShift('2026-11-10', $this->cashier, '2026-11-10 04:00', '2026-11-10 16:00');
        $lines = $this->statement('2026-11-10');
        $uuid = \App\Models\StaffPayStatement::query()->value('uuid');
        $this->as($this->owner, 'POST', "/owner/staff-pay/statements/{$uuid}/close")->assertOk();

        // Paid on 2 December from the open till: a payroll movement linked to the line
        $shift = $this->openShift('500000');
        $this->as($this->owner, 'POST', "/owner/staff-pay/statements/{$uuid}/lines/{$lines[$this->cashier->id]['uuid']}/pay", ['method' => 'cash_drawer', 'shift_uuid' => $shift->uuid], (string) Str::uuid())->assertOk();
        $this->assertSame('payroll', CashMovement::where('shift_id', $shift->id)->where('type', 'wage')->value('category'));

        $this->assertSame('0.00', $this->statement('2026-12-02')[$this->cashier->id]['advances']);
    }
}
