<?php

namespace Tests\Feature\PosStaffPay;

use App\Domain\Pos\Shifts\CashMovementType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CashMovement;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\StaffPayStatement;
use App\Models\StaffWorkSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-23 "Hisob-kitob": a November statement computed from real records — bills attributed to the waiter (or to who
 * made the bill), refunds taken off in the bill's proportions, work sessions merged with till shifts, banquet events,
 * tips (only with the tips part), wage advances; the business-day edges (a bill paid at 02:15 on 1 December is
 * November's; the next business day is not); a person's own rule over their role's; warnings; and the life of a
 * statement: exists/overlaps, adjust (kept on recompute), close, pay from the till (a wage cash movement on the
 * shift) or another way, paid, reopen refused, idempotent payment, audit.
 */
class StaffPayStatementsTest extends TestCase
{
    use RefreshDatabase;
    use StaffPayFixtures;

    protected User $waiter2;

    protected User $cook;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-12-02 10:00:00', 'UTC')); // 15:00 in Tashkent
        $this->setUpStaffPay();
        $this->waiter2 = $this->makeStaff(UserRole::WAITER, $this->branch);
        $this->cook = $this->makeStaff(UserRole::COOK, $this->branch);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rule(array $subject, array $components, string $from = '2026-11-01'): void
    {
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', $subject + ['branch_id' => $this->branch->id, 'effective_from' => $from, 'components' => $components])->assertCreated();
    }

    private function november(): void
    {
        $this->rule(['role' => 'waiter'], [['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'sales_percent', 'percent' => '5'], ['type' => 'service_charge_percent', 'percent' => '50']]);
        $this->rule(['user_id' => $this->waiter2->id], [['type' => 'guarantee', 'amount' => '150000', 'per' => 'shift', 'mode' => 'max', 'percent' => '10', 'base' => 'sales'], ['type' => 'event_rate', 'amount' => '200000', 'event_types' => ['ziyofat']]]);
        $this->rule(['role' => 'cook'], [['type' => 'hourly_rate', 'amount' => '15000']]);

        // 10 Nov: the cashier's till shift (09:00–21:00 local), the waiter's bill and its half refunded, a bill without a waiter
        $s1 = $this->closedShift('2026-11-10', $this->cashier, '2026-11-10 04:00', '2026-11-10 16:00');
        $a = $this->paidBill($this->waiter, $s1, '1000000', '100000', '90000');
        $this->paidBill(null, $s1, '200000');
        PosRefund::create([
            'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'order_id' => $a->order_id, 'bill_id' => $a->id,
            'payment_id' => \App\Models\Payment::create(['order_id' => $a->order_id, 'provider' => 'cash', 'amount' => '990000', 'status' => 'refunded', 'paid_at' => now()])->id,
            'shift_id' => $s1->id, 'amount' => '495000', 'method' => 'cash', 'reason' => 'Mijoz qaytardi',
        ]);
        CashMovement::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'shift_id' => $s1->id, 'type' => CashMovementType::WAGE->value, 'category' => 'advance', 'amount' => '50000', 'employee_id' => $this->waiter->id]);
        CashMovement::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'shift_id' => $s1->id, 'type' => CashMovementType::WAGE->value, 'category' => 'bonus', 'amount' => '70000', 'employee_id' => $this->waiter->id]);
        DB::table('pos_tips')->insert(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'business_date' => '2026-11-10', 'kind' => 'tip', 'method' => 'cash', 'mode' => 'personal', 'amount' => '30000', 'staff_id' => $this->waiter->id, 'created_at' => now(), 'updated_at' => now()]);

        // The cook: two overlapping sessions on 10 Nov (counted once: 9 h), one left open on 12 Nov (not counted)
        foreach ([['2026-11-10 03:00', '2026-11-10 11:00'], ['2026-11-10 10:00', '2026-11-10 12:00'], ['2026-11-12 03:00', null]] as [$in, $out]) {
            StaffWorkSession::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'user_id' => $this->cook->id, 'business_date' => substr($in, 0, 10), 'clock_in_at' => Carbon::parse($in, 'UTC'), 'clock_out_at' => $out !== null ? Carbon::parse($out, 'UTC') : null, 'abandoned_at' => $out === null ? Carbon::parse('2026-11-13 03:00', 'UTC') : null]);
        }

        // 20 Nov: waiter2's banquet, its bill
        $s4 = $this->closedShift('2026-11-20', $this->cashier, '2026-11-20 05:00', '2026-11-20 20:00');
        $e = $this->paidBill($this->waiter2, $s4, '3000000');
        Booking::create(['store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'starts_at' => Carbon::parse('2026-11-20 14:00', 'UTC'), 'ends_at' => Carbon::parse('2026-11-20 18:00', 'UTC'), 'event_type' => 'ziyofat', 'contact_name' => 'Aziz', 'status' => 'completed', 'order_id' => $e->order_id]);

        // 30 Nov: a bill paid at 02:15 on 1 December (local) in the 30 November shift — November's
        $s2 = $this->closedShift('2026-11-30', $this->cashier, '2026-11-30 05:00', '2026-11-30 21:30');
        $this->paidBill($this->waiter, $s2, '400000', '0', '40000', paidAt: '2026-11-30 21:15');
        // 1 December's business day: not November's
        $s3 = $this->closedShift('2026-12-01', $this->cashier, '2026-12-01 05:00', '2026-12-01 10:00');
        $this->paidBill($this->waiter, $s3, '5000000');
    }

    private function lineOf(array $statement, User $user): array
    {
        return collect($statement['lines'])->firstWhere('user_id', $user->id) ?? $this->fail("No line for {$user->name}");
    }

    public function test_a_period_statement_from_bills_refunds_work_events_tips_and_advances(): void
    {
        $this->november();

        $statement = $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-14'])
            ->assertCreated()
            ->assertJsonPath('data.period', 'month')
            ->assertJsonPath('data.period_from', '2026-11-01')
            ->assertJsonPath('data.period_to', '2026-11-30')
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        $this->assertCount(3, $statement['lines']);

        // Waiter: 2 shifts × 100 000; 5% × (900 000 − 450 000 + 400 000); 50% × (90 000 − 45 000 + 40 000)
        $waiter = $this->lineOf($statement, $this->waiter);
        $this->assertSame(['shift_rate' => '200000.00', 'sales_percent' => '42500.00', 'service_charge_percent' => '42500.00'], array_column($waiter['components'], 'amount', 'type'));
        $this->assertSame('285000.00', $waiter['gross']);
        $this->assertSame('50000.00', $waiter['advances']); // the advance, not the bonus
        $this->assertSame('235000.00', $waiter['payable']);
        $this->assertSame(2, $waiter['metrics']['shifts']);
        $this->assertSame('850000.00', $waiter['metrics']['sales']);
        $this->assertSame('85000.00', $waiter['metrics']['service_charge']);
        $this->assertSame('30000.00', $waiter['metrics']['tips']); // shown, not paid: no tips part
        $this->assertFalse($waiter['metrics']['own_rule']);

        // Waiter 2's own rule, not the role's: max(150 000, 10% × 3 000 000) + one banquet
        $waiter2 = $this->lineOf($statement, $this->waiter2);
        $this->assertSame(['guarantee' => '300000.00', 'event_rate' => '200000.00'], array_column($waiter2['components'], 'amount', 'type'));
        $this->assertSame('500000.00', $waiter2['payable']);
        $this->assertTrue($waiter2['metrics']['own_rule']);
        $this->assertSame(1, $waiter2['metrics']['events']);

        // The cook: 9 hours × 15 000; the open session is a warning
        $cook = $this->lineOf($statement, $this->cook);
        $this->assertSame('135000.00', $cook['gross']);
        $this->assertSame(540, $cook['metrics']['minutes']);
        $this->assertSame(1, $cook['metrics']['uncounted_sessions']);

        // The cashier worked (a till shift, a bill made without a waiter) but has no rule
        $warnings = collect($statement['warnings']);
        $this->assertTrue($warnings->contains(fn ($w) => $w['type'] === 'no_rule' && $w['user_id'] === $this->cashier->id));
        $this->assertTrue($warnings->contains(fn ($w) => $w['type'] === 'open_sessions' && $w['user_id'] === $this->cook->id && $w['count'] === 1));

        $this->assertSame('870000.00', $statement['payable_total']);
        $this->assertSame('920000.00', $statement['gross_total']);
        $this->assertTrue(AuditLog::where('action', 'staff_pay.statement_created')->exists());
    }

    public function test_the_tips_part_pays_the_persons_tips(): void
    {
        $this->rule(['user_id' => $this->waiter->id], [['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'tips']]);
        $shift = $this->closedShift('2026-11-10', $this->cashier, '2026-11-10 04:00', '2026-11-10 16:00');
        $this->paidBill($this->waiter, $shift, '100000');
        $insert = fn (string $mode, string $amount, ?int $staff, string $kind = 'tip') => DB::table('pos_tips')->insert(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'business_date' => '2026-11-10', 'kind' => $kind, 'method' => 'cash', 'mode' => $mode, 'amount' => $amount, 'staff_id' => $staff, 'created_at' => now(), 'updated_at' => now()]);
        $insert('personal', '30000', $this->waiter->id);
        $insert('personal', '-10000', $this->waiter->id, 'correction');
        $insert('pool', '90000', null);
        $distribution = DB::table('pos_tip_distributions')->insertGetId(['uuid' => (string) Str::uuid(), 'store_id' => $this->store->id, 'branch_id' => $this->branch->id, 'business_date' => '2026-11-10', 'rule' => 'equal', 'pool_amount' => '90000', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pos_tip_distribution_lines')->insert(['distribution_id' => $distribution, 'staff_id' => $this->waiter->id, 'role' => 'waiter', 'basis' => 1, 'amount' => '45000', 'created_at' => now(), 'updated_at' => now()]);

        $line = $this->lineOf($this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-10'])->assertCreated()->json('data'), $this->waiter);

        // 20 000 personal (corrected) + 45 000 pool share
        $this->assertSame(['shift_rate' => '100000.00', 'tips' => '65000.00'], array_column($line['components'], 'amount', 'type'));
    }

    public function test_work_time_follows_the_business_day(): void
    {
        $this->rule(['role' => 'cook'], [['type' => 'hourly_rate', 'amount' => '10000'], ['type' => 'shift_rate', 'amount' => '1000']]);
        // 23:30 on 30 Nov to 03:00 on 1 Dec (local): 30 November's; 04:30–06:00 on 1 Dec: before the 05:00 day start, still 30 Nov
        $this->as($this->owner, 'POST', '/owner/staff-pay/work-sessions', ['branch_id' => $this->branch->id, 'user_id' => $this->cook->id, 'clock_in_at' => '2026-11-30T23:30:00+05:00', 'clock_out_at' => '2026-12-01T03:00:00+05:00', 'reason' => 'Unutdi'])
            ->assertCreated()->assertJsonPath('data.business_date', '2026-11-30')->assertJsonPath('data.minutes', 210);
        $this->as($this->owner, 'POST', '/owner/staff-pay/work-sessions', ['branch_id' => $this->branch->id, 'user_id' => $this->cook->id, 'clock_in_at' => '2026-12-01T04:30:00+05:00', 'clock_out_at' => '2026-12-01T06:00:00+05:00', 'reason' => 'Unutdi'])
            ->assertCreated()->assertJsonPath('data.business_date', '2026-11-30');
        // 05:00 on 1 Dec: December's
        $this->as($this->owner, 'POST', '/owner/staff-pay/work-sessions', ['branch_id' => $this->branch->id, 'user_id' => $this->cook->id, 'clock_in_at' => '2026-12-01T06:30:00+05:00', 'clock_out_at' => '2026-12-01T08:30:00+05:00', 'reason' => 'Unutdi'])
            ->assertCreated()->assertJsonPath('data.business_date', '2026-12-01');

        $line = $this->lineOf($this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-30'])->assertCreated()->json('data'), $this->cook);

        $this->assertSame(300, $line['metrics']['minutes']); // 3 h 30 + 1 h 30
        $this->assertSame(1, $line['metrics']['shifts']);
        $this->assertSame('51000.00', $line['gross']); // 5 h × 10 000 + 1 shift × 1 000
    }

    public function test_the_life_of_a_statement(): void
    {
        $this->november();
        $uuid = $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-01'])->assertCreated()->json('data.uuid');
        $base = "/owner/staff-pay/statements/{$uuid}";

        // One per period; a changed period that overlaps is refused
        $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-30'])->assertStatus(409)->assertJsonPath('data.reason', 'statement_exists');
        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'period' => 'week'])->assertOk()->assertJsonPath('data.period', 'week');
        $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-25'])->assertStatus(409)->assertJsonPath('data.reason', 'statement_overlaps');
        $this->assertTrue(AuditLog::where('action', 'staff_pay.settings_changed')->exists());

        // A fine with its reason, kept when recomputed
        $waiterLine = collect($this->as($this->owner, 'GET', $base)->assertOk()->json('data.lines'))->firstWhere('user_id', $this->waiter->id);
        $this->as($this->owner, 'PUT', "{$base}/lines/{$waiterLine['uuid']}", ['adjustment' => '-35000'])->assertStatus(422)->assertJsonPath('data.reason', 'adjustment_reason_required');
        $this->as($this->owner, 'PUT', "{$base}/lines/{$waiterLine['uuid']}", ['adjustment' => '-35000', 'reason' => 'Idish sindi'])->assertOk();
        $recomputed = $this->as($this->owner, 'POST', "{$base}/recompute")->assertOk()->json('data');
        $this->assertSame('200000.00', $this->lineOf($recomputed, $this->waiter)['payable']);
        $this->assertSame('835000.00', $recomputed['payable_total']);

        // Paying a draft is refused; close freezes it
        $this->as($this->owner, 'POST', "{$base}/lines/{$waiterLine['uuid']}/pay", ['method' => 'other'], (string) Str::uuid())->assertStatus(409)->assertJsonPath('data.reason', 'statement_not_closed');
        $closed = $this->as($this->owner, 'POST', "{$base}/close")->assertOk()->assertJsonPath('data.status', 'closed')->json('data');
        $this->as($this->owner, 'POST', "{$base}/recompute")->assertStatus(409)->assertJsonPath('data.reason', 'statement_not_draft');
        $this->as($this->owner, 'PUT', "{$base}/lines/{$waiterLine['uuid']}", ['adjustment' => '0'])->assertStatus(409);
        $this->assertSame([], $closed['open_shifts']);

        $this->as($this->owner, 'POST', "{$base}/lines/{$waiterLine['uuid']}/pay", ['method' => 'other'])->assertStatus(428);

        // Pay the waiter from the open till: a wage movement (payroll) on that shift, shown in its X report
        $shift = $this->openShift('1000000');
        $open = $this->as($this->owner, 'GET', $base)->json('data.open_shifts');
        $this->assertSame($shift->uuid, $open[0]['uuid']);
        $key = (string) Str::uuid();
        $paid = $this->as($this->owner, 'POST', "{$base}/lines/{$waiterLine['uuid']}/pay", ['method' => 'cash_drawer', 'shift_uuid' => $shift->uuid, 'note' => 'Noyabr'], $key)->assertOk()->json('data');
        $this->assertTrue($this->lineOf($paid, $this->waiter)['paid']);
        $this->as($this->owner, 'POST', "{$base}/lines/{$waiterLine['uuid']}/pay", ['method' => 'cash_drawer', 'shift_uuid' => $shift->uuid, 'note' => 'Noyabr'], $key)->assertOk();
        $this->as($this->owner, 'POST', "{$base}/lines/{$waiterLine['uuid']}/pay", ['method' => 'other'], (string) Str::uuid())->assertStatus(409)->assertJsonPath('data.reason', 'line_already_paid');

        $movement = CashMovement::where('shift_id', $shift->id)->where('type', CashMovementType::WAGE->value)->sole();
        $this->assertSame('payroll', $movement->category);
        $this->assertSame('200000.00', (string) $movement->amount);
        $this->assertSame($this->waiter->id, (int) $movement->employee_id);
        $this->assertStringContainsString('Ish haqi 01.11–30.11.2026: Noyabr', (string) $movement->note);
        $this->assertSame('200000.00', $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->assertOk()->json('data.report.cash.wages'));
        // The payment is not an advance of the next statement
        $this->assertNotContains('payroll', \App\Domain\Pos\StaffPay\StaffPayMetrics::ADVANCE_CATEGORIES);

        // Not more than the drawer holds
        $cookLine = $this->lineOf($paid, $this->cook);
        $shift->forceFill(['opening_cash' => '0'])->save();
        CashMovement::where('shift_id', $shift->id)->where('type', 'opening')->update(['amount' => '0']);
        $this->as($this->owner, 'POST', "{$base}/lines/{$cookLine['uuid']}/pay", ['method' => 'cash_drawer', 'shift_uuid' => $shift->uuid], (string) Str::uuid())->assertStatus(409)->assertJsonPath('data.reason', 'not_enough_cash');

        // A line already paid blocks reopening; paying the others another way makes it paid
        $this->as($this->owner, 'POST', "{$base}/reopen", ['reason' => 'Xato'])->assertStatus(409)->assertJsonPath('data.reason', 'statement_has_payments');
        $this->as($this->owner, 'POST', "{$base}/lines/{$cookLine['uuid']}/pay", ['method' => 'other', 'note' => "Kartaga o'tkazildi"], (string) Str::uuid())->assertOk()->assertJsonPath('data.status', 'closed');
        $final = $this->as($this->owner, 'POST', "{$base}/lines/{$this->lineOf($paid, $this->waiter2)['uuid']}/pay", ['method' => 'other'], (string) Str::uuid())->assertOk()->json('data');
        $this->assertSame('paid', $final['status']);
        $this->assertSame('835000.00', $final['paid_total']);
        $this->assertSame(3, AuditLog::where('action', 'staff_pay.line_paid')->count());
        $this->as($this->owner, 'DELETE', $base)->assertStatus(409)->assertJsonPath('data.reason', 'statement_not_draft');

        $this->as($this->owner, 'GET', '/owner/staff-pay/statements?branch_id=' . $this->branch->id)->assertOk()->assertJsonPath('data.0.uuid', $uuid)->assertJsonPath('data.0.lines_count', 3);
    }

    public function test_reopen_and_delete_a_draft(): void
    {
        $this->november();
        $uuid = $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-01'])->json('data.uuid');
        $this->as($this->owner, 'POST', "/owner/staff-pay/statements/{$uuid}/reopen", ['reason' => 'x'])->assertStatus(409)->assertJsonPath('data.reason', 'statement_not_closed');
        $this->as($this->owner, 'POST', "/owner/staff-pay/statements/{$uuid}/close")->assertOk();
        $this->as($this->owner, 'POST', "/owner/staff-pay/statements/{$uuid}/reopen", [])->assertStatus(422);
        $this->as($this->owner, 'POST', "/owner/staff-pay/statements/{$uuid}/reopen", ['reason' => 'Qoida xato edi'])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->as($this->owner, 'DELETE', "/owner/staff-pay/statements/{$uuid}")->assertOk();
        $this->assertSame(0, StaffPayStatement::count());
        $this->assertTrue(AuditLog::where('action', 'staff_pay.statement_reopened')->exists());
        $this->assertTrue(AuditLog::where('action', 'staff_pay.statement_deleted')->exists());
    }

    public function test_statements_respect_permissions_branches_and_the_module(): void
    {
        $this->november();
        $uuid = $this->as($this->owner, 'POST', '/owner/staff-pay/statements', ['branch_id' => $this->branch->id, 'date' => '2026-11-01'])->json('data.uuid');

        // The manager sees (staff_pay.view), cannot change
        $this->as($this->manager, 'GET', "/owner/staff-pay/statements/{$uuid}")->assertOk();
        $this->as($this->manager, 'POST', "/owner/staff-pay/statements/{$uuid}/close")->assertForbidden();
        // A waiter sees nothing
        $this->as($this->waiter, 'GET', '/owner/staff-pay/statements')->assertForbidden();
        // Another store: not found
        $other = $this->makeStaff(UserRole::OWNER, null, $this->makeStore('Boshqa'));
        $this->as($other, 'GET', "/owner/staff-pay/statements/{$uuid}")->assertNotFound();
        // A PIN session never reaches the owner's side
        $this->withBearer($this->pinSession($this->till, $this->manager, self::MANAGER_PIN))->getJson("/api/v1/owner/staff-pay/statements/{$uuid}")->assertForbidden();

        // Without the module (choyxona): 403 module_disabled, the statement included
        $this->profile(\App\Domain\Pos\Setup\FormatProfile::CHOYXONA);
        $this->as($this->owner, 'GET', "/owner/staff-pay/statements/{$uuid}")->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }
}
