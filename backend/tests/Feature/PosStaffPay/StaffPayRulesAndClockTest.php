<?php

namespace Tests\Feature\PosStaffPay;

use App\Domain\Pos\Setup\FormatProfile;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\StaffPayRule;
use App\Models\StaffWorkSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * P6-23 "Ish haqi qoidalari" and the work time: the rules page (options, staff, versions), adding / changing /
 * deleting a rule with its checks and audit, the pay period; the till's clock-in/out from a PIN session (idempotent,
 * 409s, a forgotten session set aside, a normal sign-in refused), the bootstrap section, the module gate; a manager's
 * sessions in the panel (reason, overlaps, 24 hours, future) and who may do what.
 */
class StaffPayRulesAndClockTest extends TestCase
{
    use RefreshDatabase;
    use StaffPayFixtures;

    protected string $waiterToken;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-14 06:00:00', 'UTC')); // 11:00 in Tashkent
        $this->setUpStaffPay();
        $this->waiterToken = $this->pinSession($this->till, $this->waiter);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rules_page_and_rule_versions(): void
    {
        $page = $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertOk()
            ->assertJsonPath('data.period', 'month')
            ->assertJsonPath('data.rules', [])
            ->json('data');
        $this->assertSame(['shift_rate', 'hourly_rate', 'sales_percent', 'service_charge_percent', 'guarantee', 'event_rate', 'tips'], array_column($page['component_types'], 'value'));
        $this->assertContains('hostess', array_column($page['roles'], 'value'));
        $this->assertNotContains('owner', array_column($page['roles'], 'value'));
        $this->assertContains($this->waiter->id, array_column($page['staff'], 'id'));
        $this->assertNotContains($this->owner->id, array_column($page['staff'], 'id'));

        $body = ['branch_id' => $this->branch->id, 'role' => 'waiter', 'effective_from' => '2026-11-01', 'components' => [['type' => 'shift_rate', 'amount' => '100000'], ['type' => 'guarantee', 'amount' => '120000', 'per' => 'shift', 'mode' => 'plus', 'percent' => '5']]];
        $rule = $this->as($this->owner, 'POST', '/owner/staff-pay/rules', $body)->assertCreated()
            ->assertJsonPath('data.role', 'waiter')
            ->assertJsonPath('data.role_label', 'Ofitsiant')
            ->assertJsonPath('data.components.1.label', 'Garant + foiz')
            ->assertJsonPath('data.components.1.base', 'sales')
            ->json('data');
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', $body)->assertStatus(422)->assertJsonValidationErrors('effective_from');

        // Checks: one subject, a real staff member of the branch, a valid role and components
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['user_id' => $this->waiter->id] + $body)->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', array_merge($body, ['role' => 'owner']))->assertStatus(422)->assertJsonValidationErrors('role');
        $foreign = $this->makeStaff(UserRole::WAITER, $this->otherBranch);
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', array_merge(array_diff_key($body, ['role' => 1]), ['user_id' => $foreign->id]))->assertStatus(422)->assertJsonValidationErrors('user_id');
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', array_merge($body, ['effective_from' => '2026-12-01', 'components' => [['type' => 'sales_percent', 'percent' => '150']]]))->assertStatus(422)->assertJsonValidationErrors('components.0.percent');

        // A person's own version; an empty list stops pay from its date
        $this->as($this->owner, 'POST', '/owner/staff-pay/rules', ['branch_id' => $this->branch->id, 'user_id' => $this->waiter->id, 'effective_from' => '2026-12-01', 'components' => []])->assertCreated()->assertJsonPath('data.user_name', $this->waiter->name);

        $this->as($this->owner, 'PUT', "/owner/staff-pay/rules/{$rule['uuid']}", ['components' => [['type' => 'hourly_rate', 'amount' => '25000']], 'note' => 'Yangi stavka'])->assertOk()
            ->assertJsonPath('data.components.0.type', 'hourly_rate')->assertJsonPath('data.note', 'Yangi stavka');
        $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertJsonCount(2, 'data.rules');
        $this->as($this->owner, 'DELETE', "/owner/staff-pay/rules/{$rule['uuid']}")->assertOk();
        $this->assertSame(1, StaffPayRule::count());
        foreach (['staff_pay.rule_created', 'staff_pay.rule_changed', 'staff_pay.rule_deleted'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), $action);
        }

        // The period
        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'period' => 'fortnight'])->assertStatus(422);
        $this->as($this->owner, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'period' => 'half_month'])->assertOk()->assertJsonPath('data.period_label', 'Yarim oylik');
        $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertJsonPath('data.period', 'half_month');
    }

    public function test_who_may_read_and_change_rules(): void
    {
        $this->as($this->manager, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertOk();
        $this->as($this->manager, 'POST', '/owner/staff-pay/rules', ['branch_id' => $this->branch->id, 'role' => 'cook', 'effective_from' => '2026-11-01', 'components' => []])->assertForbidden();
        $this->as($this->manager, 'PUT', '/owner/staff-pay/settings', ['branch_id' => $this->branch->id, 'period' => 'day'])->assertForbidden();
        $this->as($this->cashier, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertForbidden();

        // Asosiy+: a kafe has no staff_pay by default; the owner may switch it on
        $this->profile(FormatProfile::KAFE);
        $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->profile(FormatProfile::KAFE, ['staff_pay' => true]);
        $this->as($this->owner, 'GET', '/owner/staff-pay/rules?branch_id=' . $this->branch->id)->assertOk();
    }

    public function test_clock_in_and_out_from_the_pin_session(): void
    {
        $this->pos('GET', '/clock', [], $this->waiterToken)->assertOk()->assertJsonPath('data.session', null)->assertJsonPath('data.business_date', '2026-11-14');
        $this->pos('GET', '/bootstrap', [], $this->waiterToken)->assertOk()->assertJsonPath('data.staff_pay', ['clock' => true, 'session' => null]);

        $key = (string) Str::uuid();
        $in = $this->pos('POST', '/clock/in', [], $this->waiterToken, $key)->assertCreated()->assertJsonPath('data.session.open', true)->json('data.session');
        $this->pos('POST', '/clock/in', [], $this->waiterToken, $key)->assertCreated()->assertJsonPath('data.session.uuid', $in['uuid']);
        $this->pos('POST', '/clock/in', [], $this->waiterToken)->assertStatus(409)->assertJsonPath('data.reason', 'already_clocked_in');
        $this->assertSame(1, StaffWorkSession::count());
        $this->pos('GET', '/bootstrap', [], $this->waiterToken)->assertJsonPath('data.staff_pay.session.uuid', $in['uuid']);

        Carbon::setTestNow(Carbon::parse('2026-11-14 14:30:00', 'UTC'));
        $this->pos('POST', '/clock/out', [], $this->waiterToken)->assertOk()->assertJsonPath('data.session', null)->assertJsonPath('data.today_minutes', 510);
        $this->pos('POST', '/clock/out', [], $this->waiterToken)->assertStatus(409)->assertJsonPath('data.reason', 'not_clocked_in');
        $this->assertTrue(AuditLog::where('action', 'staff_pay.clock_in')->exists());
        $this->assertTrue(AuditLog::where('action', 'staff_pay.clock_out')->exists());

        // Without a key: 428
        $this->withBearer($this->waiterToken)->postJson('/api/v1/pos/clock/in')->assertStatus(428);
        // A normal sign-in (no terminal) cannot clock in
        $this->posAs($this->waiter, 'POST', '/clock/in')->assertForbidden()->assertJsonPath('data.reason', 'terminal_required');
    }

    public function test_a_forgotten_session_is_set_aside_on_the_next_day(): void
    {
        $this->pos('POST', '/clock/in', [], $this->waiterToken)->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-11-15 05:00:00', 'UTC')); // 10:00 the next business day
        $this->waiterToken = $this->pinSession($this->till, $this->waiter);
        $this->pos('POST', '/clock/in', [], $this->waiterToken)->assertCreated()->assertJsonPath('data.session.business_date', '2026-11-15');

        $old = StaffWorkSession::where('business_date', '2026-11-14')->sole();
        $this->assertNotNull($old->abandoned_at);
        $this->assertNull($old->clock_out_at);

        // The manager gives it its end (a reason), and it counts
        $this->as($this->owner, 'PUT', "/owner/staff-pay/work-sessions/{$old->uuid}", ['clock_in_at' => $old->clock_in_at->toIso8601String(), 'clock_out_at' => '2026-11-14T20:00:00+05:00'])->assertStatus(422);
        $this->as($this->owner, 'PUT', "/owner/staff-pay/work-sessions/{$old->uuid}", ['clock_in_at' => $old->clock_in_at->toIso8601String(), 'clock_out_at' => '2026-11-14T20:00:00+05:00', 'reason' => 'Ketishni unutdi'])->assertOk()
            ->assertJsonPath('data.abandoned', false)->assertJsonPath('data.minutes', 540);
        $this->as($this->owner, 'GET', '/owner/staff-pay/work-sessions?branch_id=' . $this->branch->id . '&from=2026-11-14&to=2026-11-15')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_manual_sessions_are_checked(): void
    {
        $add = fn (array $body) => $this->as($this->owner, 'POST', '/owner/staff-pay/work-sessions', $body + ['branch_id' => $this->branch->id, 'user_id' => $this->waiter->id, 'reason' => 'Qo\'lda']);

        $session = $add(['clock_in_at' => '2026-11-13T09:00:00+05:00', 'clock_out_at' => '2026-11-13T18:00:00+05:00'])->assertCreated()->assertJsonPath('data.source', 'manual')->json('data');
        $add(['clock_in_at' => '2026-11-13T17:00:00+05:00', 'clock_out_at' => '2026-11-13T20:00:00+05:00'])->assertStatus(422)->assertJsonValidationErrors('clock_in_at'); // overlaps
        $add(['clock_in_at' => '2026-11-11T09:00:00+05:00', 'clock_out_at' => '2026-11-12T10:00:00+05:00'])->assertStatus(422)->assertJsonValidationErrors('clock_out_at'); // > 24 h
        $add(['clock_in_at' => '2026-11-13T21:00:00+05:00', 'clock_out_at' => '2026-11-13T20:00:00+05:00'])->assertStatus(422)->assertJsonValidationErrors('clock_out_at');
        $add(['clock_in_at' => '2026-11-15T09:00:00+05:00'])->assertStatus(422)->assertJsonValidationErrors('clock_in_at'); // future
        $this->as($this->owner, 'POST', '/owner/staff-pay/work-sessions', ['branch_id' => $this->branch->id, 'user_id' => $this->owner->id, 'clock_in_at' => '2026-11-13T09:00:00+05:00', 'reason' => 'x'])->assertStatus(422)->assertJsonValidationErrors('user_id');

        $this->as($this->manager, 'GET', '/owner/staff-pay/work-sessions?branch_id=' . $this->branch->id)->assertOk()->assertJsonCount(1, 'data');
        $this->as($this->manager, 'DELETE', "/owner/staff-pay/work-sessions/{$session['uuid']}", ['reason' => 'x'])->assertForbidden();
        $this->as($this->owner, 'DELETE', "/owner/staff-pay/work-sessions/{$session['uuid']}", [])->assertStatus(422);
        $this->as($this->owner, 'DELETE', "/owner/staff-pay/work-sessions/{$session['uuid']}", ['reason' => 'Xato kiritilgan'])->assertOk();
        $this->assertSame(0, StaffWorkSession::count());
        $this->assertSame(['staff_pay.session_added', 'staff_pay.session_removed'], AuditLog::whereIn('action', ['staff_pay.session_added', 'staff_pay.session_removed'])->orderBy('id')->pluck('action')->all());
    }

    public function test_the_clock_needs_the_module(): void
    {
        $this->profile(FormatProfile::CHOYXONA);
        $this->pos('GET', '/bootstrap', [], $this->waiterToken)->assertOk()->assertJsonPath('data.staff_pay', null);
        $this->pos('POST', '/clock/in', [], $this->waiterToken)->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
    }
}
