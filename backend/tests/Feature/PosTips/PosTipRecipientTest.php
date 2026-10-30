<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Tips\TipMode;
use App\Enums\UserRole;
use App\Models\Payment;
use App\Models\PosTip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Review P6 wave 2 (S5): whom the cashier may give a personal tip to at payment (`tip_staff_id`). The order's waiter
 * by default; someone else only of the branch's tip roles (TipSettings::roles, waiters by default) and with
 * tips.manage or a manager's approval for it (`tip_approval_token`, used once) — refused before any money is taken.
 * In pool mode the pick does not matter (the tip goes to the pot).
 */
class PosTipRecipientTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTips();
    }

    /** @var list<array<string, string>> */
    private const TENDER = [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '150000', 'tip_amount' => '5000']];

    public function test_the_orders_waiter_takes_the_tip_without_approval(): void
    {
        $this->openShift();
        [, $bill] = $this->billed();
        $this->pay($bill, self::TENDER, ['tip_staff_id' => $this->waiter->id])->assertOk()->assertJsonPath('data.bill.tips.0.staff.id', $this->waiter->id);
        $this->assertNull(PosTip::sole()->approved_by);
    }

    public function test_another_waiter_needs_a_managers_approval_before_any_money_is_taken(): void
    {
        $this->openShift();
        [, $bill] = $this->billed();

        $this->pay($bill, self::TENDER, ['tip_staff_id' => $this->waiter2->id])->assertForbidden()
            ->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'tips.manage');
        $this->assertSame([0, 0], [Payment::count(), PosTip::count()]);

        $this->pay($bill, self::TENDER, ['tip_staff_id' => $this->waiter2->id, 'tip_approval_token' => $this->approval('tips.manage')])->assertOk()
            ->assertJsonPath('data.bill.tips.0.staff.id', $this->waiter2->id);
        $this->assertSame($this->manager->id, (int) PosTip::sole()->approved_by);

        // A till that resends the payment with `approval_token` after the 403 (no nasiya tender needs it)
        [, $resent] = $this->billed();
        $this->pay($resent, self::TENDER, ['tip_staff_id' => $this->waiter2->id, 'approval_token' => $this->approval('tips.manage')])->assertOk()
            ->assertJsonPath('data.bill.tips.0.staff.id', $this->waiter2->id);

        // A manager at the till gives it alone
        [, $second] = $this->billed();
        $this->pay($second, self::TENDER, ['tip_staff_id' => $this->waiter2->id], $this->pinSession($this->till, $this->manager))->assertOk()
            ->assertJsonPath('data.bill.tips.0.staff.id', $this->waiter2->id);
    }

    public function test_only_the_branchs_tip_roles_may_take_a_tip(): void
    {
        $this->openShift();
        [, $bill] = $this->billed();
        $cook = $this->makeStaff(UserRole::COOK, $this->branch);

        // The cashier gives the waiter's tip to herself, or to the cook: neither is of the tip roles (waiters)
        foreach ([$this->cashier, $cook, $this->manager] as $person) {
            $this->pay($bill, self::TENDER, ['tip_staff_id' => $person->id, 'tip_approval_token' => $this->approval('tips.manage')])
                ->assertStatus(422)->assertJsonPath('data.reason', 'tip_staff_not_eligible');
        }
        $this->assertSame(0, Payment::count());

        // The owner puts the cooks among the tip roles
        $this->tipSettings(TipMode::PERSONAL, roles: ['waiter', 'cook']);
        $this->pay($bill, self::TENDER, ['tip_staff_id' => $cook->id, 'tip_approval_token' => $this->approval('tips.manage')])->assertOk()
            ->assertJsonPath('data.bill.tips.0.staff.id', $cook->id);
    }

    public function test_in_pool_mode_the_pick_does_not_matter(): void
    {
        $this->tipSettings(TipMode::POOL);
        $this->openShift();
        [, $bill] = $this->billed();
        $this->pay($bill, self::TENDER, ['tip_staff_id' => $this->cashier->id])->assertOk()->assertJsonPath('data.bill.tips.0.mode', 'pool');
        $this->assertNull(PosTip::sole()->staff_id);
    }
}
