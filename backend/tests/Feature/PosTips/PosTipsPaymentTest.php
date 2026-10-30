<?php

namespace Tests\Feature\PosTips;

use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\TerminalFlow\ShiftCardTotals;
use App\Models\CashMovement;
use App\Models\Payment;
use App\Models\PosTip;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-22 (C2): tips taken with the payment — on top of a cash, card or QR tender, or the change left — are never a
 * payment and never on the receipt (what the guest handed over = receipt total + tips), go to the order's waiter or
 * the one picked (personal) or the pool, sit on their own X/Z lines (cash in the drawer, paid out as a `tips` wage),
 * come inside the card terminal's transaction, need the tips module online, and a refund never touches them.
 */
class PosTipsPaymentTest extends TestCase
{
    use PosTipsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTips();
    }

    public function test_a_cash_tip_on_top_is_not_on_the_receipt_and_goes_to_the_orders_waiter(): void
    {
        $shift = $this->openShift('100000');
        [$order, $bill] = $this->billed();

        $response = $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '160000', 'tip_amount' => '10000']])->assertOk();
        $response->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.bill.total', '143000.00')
            ->assertJsonPath('data.bill.paid_amount', '143000.00')
            ->assertJsonPath('data.bill.tips_amount', '10000.00')
            ->assertJsonPath('data.bill.tips.0.amount', '10000.00')
            ->assertJsonPath('data.bill.tips.0.method', 'cash')
            ->assertJsonPath('data.bill.tips.0.mode', 'personal')
            ->assertJsonPath('data.bill.tips.0.staff.id', $this->waiter->id)
            ->assertJsonPath('data.bill.payments.0.amount', '143000.00')
            ->assertJsonPath('data.bill.payments.0.change_amount', '7000.00')
            ->assertJsonPath('data.bill.payments.0.tip_amount', '10000.00');

        // The receipt is the bill: its payments add up to the bill total, the tip is not on it
        $document = $this->fiscal->document("pos-bill:{$bill}:sale");
        $receiptTotal = Money::sum(...array_map(fn ($payment) => $payment->amount, $document->payments));
        $this->assertSame('143000.00', $receiptTotal->toString());
        $this->assertNotContains('tip', array_map(fn ($line) => $line->name, $document->lines));

        // What the guest handed over = receipt total + tips
        $payment = Payment::firstOrFail();
        $handed = Money::of($payment->tendered_amount)->minus($payment->change_amount);
        $this->assertSame($receiptTotal->plus('10000')->toString(), $handed->toString());
        $this->assertSame('143000.00', CashMovement::where('type', 'sale')->value('amount'));

        $tip = PosTip::firstOrFail();
        $this->assertSame([TipMode::PERSONAL, $this->waiter->id, $shift->id, $payment->id, 'tender'], [$tip->mode, $tip->staff_id, $tip->shift_id, $tip->payment_id, $tip->source->value]);
        $this->assertSame($shift->business_date->toDateString(), $tip->business_date->toDateString());

        // X: tips on their own lines, not in the takings; the cash tip in the drawer
        $report = $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame('143000.00', $report['sales']['received']);
        $this->assertSame('143000.00', collect($report['by_tender'])->firstWhere('tender', 'cash')['amount']);
        $this->assertSame(['count' => 1, 'amount' => '10000.00', 'personal' => '10000.00', 'pool' => '0.00', 'unassigned' => '0.00'], array_intersect_key($report['tips'], array_flip(['count', 'amount', 'personal', 'pool', 'unassigned'])));
        $this->assertSame([['staff_id' => $this->waiter->id, 'name' => $this->waiter->name, 'amount' => '10000.00']], $report['tips']['by_staff']);
        $this->assertSame('10000.00', $report['cash']['tips']);
        $this->assertSame('253000.00', $report['cash']['expected']); // 100 000 + 143 000 + 10 000

        // Paid out to the waiter: a wage of category `tips`, not a wage (a manager's approval for tips.manage)
        $this->pos('POST', "/shifts/{$shift->uuid}/cash-movements", ['type' => 'wage', 'category' => 'tips', 'amount' => '10000', 'employee_id' => $this->waiter->id, 'approval_token' => $this->approval('tips.manage')])->assertCreated();
        $cash = $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report.cash');
        $this->assertSame(['10000.00', '0.00', '243000.00'], [$cash['tip_payouts'], $cash['wages'], $cash['expected']]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.payment', 'model_id' => $order->id]);
    }

    public function test_the_change_left_as_a_tip_and_a_waiter_picked_at_payment(): void
    {
        $this->openShift();
        [, $bill] = $this->billed();

        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '150000', 'tip_from_change' => true]], ['tip_staff_id' => $this->waiter2->id, 'tip_approval_token' => $this->approval('tips.manage')])
            ->assertOk()
            ->assertJsonPath('data.bill.payments.0.change_amount', '0.00')
            ->assertJsonPath('data.bill.tips_amount', '7000.00')
            ->assertJsonPath('data.bill.tips.0.source', 'change')
            ->assertJsonPath('data.bill.tips.0.staff.id', $this->waiter2->id);

        // Part of the change
        [, $second] = $this->billed();
        $this->pay($second, [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '150000', 'tip_from_change' => true, 'tip_amount' => '5000']])
            ->assertOk()->assertJsonPath('data.bill.payments.0.change_amount', '2000.00')->assertJsonPath('data.bill.tips_amount', '5000.00');
    }

    public function test_a_card_tip_is_inside_the_terminal_transaction_and_a_qr_tip_too(): void
    {
        $shift = $this->openShift();
        [, $bill] = $this->billed([['Osh', '1', '200000']]);

        $this->pay($bill, [
            ['tender' => 'card', 'amount' => '150000', 'card_type' => 'humo', 'transaction_id' => 'RRN1', 'tip_amount' => '15000'],
            ['tender' => 'qr', 'amount' => '50000', 'tip_amount' => '5000'],
        ])->assertOk()->assertJsonPath('data.bill.status', 'paid')->assertJsonPath('data.bill.tips_amount', '20000.00');

        $cards = app(ShiftCardTotals::class)->of($shift->refresh());
        $this->assertSame('165000.00', $cards['total']->toString());
        $this->assertSame('165000.00', $cards['transactions'][0]['amount']);
        $this->assertSame('15000.00', $cards['breakdown']['tips']);

        $tips = $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report.tips');
        $this->assertSame(['cash' => '0.00', 'card' => '15000.00', 'qr' => '5000.00'], collect($tips['by_method'])->pluck('amount', 'method')->all());
        $this->assertSame('0.00', $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report.cash.tips'));
    }

    public function test_pool_mode_tips_go_to_the_pot_and_a_personal_tip_without_a_waiter_waits_unassigned(): void
    {
        $shift = $this->openShift();
        $this->tipSettings(TipMode::POOL);
        [, $bill] = $this->billed();
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => '8000']], ['tip_staff_id' => $this->waiter2->id])->assertOk();
        $this->assertSame([TipMode::POOL, null], [PosTip::firstOrFail()->mode, PosTip::firstOrFail()->staff_id]);

        $this->tipSettings(TipMode::PERSONAL);
        $order = $this->posOrder([['Choy', '1', '10000']]);
        $second = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($second, [['tender' => 'cash', 'amount' => '10000', 'tip_amount' => '2000']])->assertOk();

        $tips = $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report.tips');
        $this->assertSame(['10000.00', '8000.00', '2000.00', '2000.00'], [$tips['amount'], $tips['pool'], $tips['personal'], $tips['unassigned']]);
    }

    public function test_tips_need_the_module_a_tipping_tender_and_a_staff_member_of_the_branch(): void
    {
        $this->openShift();
        [, $bill] = $this->billed();

        $this->pay($bill, [['tender' => 'prepayment', 'amount' => '1000', 'tip_amount' => '1000']])->assertStatus(422)->assertJsonPath('data.reason', 'tip_tender_invalid');
        $this->pay($bill, [['tender' => 'card', 'amount' => '143000', 'card_type' => 'humo', 'transaction_id' => 'X', 'tip_from_change' => true]])->assertStatus(422)->assertJsonPath('data.reason', 'tip_from_change_invalid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_from_change' => true]])->assertStatus(422)->assertJsonPath('data.reason', 'tip_from_change_invalid');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '145000', 'tip_amount' => '5000']])->assertStatus(422)->assertJsonPath('data.reason', 'tendered_too_small');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => '5000']], ['tip_staff_id' => $this->otherBranchStaff()->id])->assertStatus(422)->assertJsonPath('data.reason', 'tip_staff_not_found');

        // A choyxona has no tips module: refused before any money is taken; the same payment without a tip goes
        $this->profile($this->branch, 'choyxona');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => '5000']])->assertForbidden()->assertJsonPath('data.reason', 'module_disabled');
        $this->assertSame(0, Payment::count());
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000']])->assertOk()->assertJsonPath('data.bill.tips_amount', '0.00');
        $this->assertSame(0, PosTip::count());
    }

    public function test_a_refund_never_touches_the_tips(): void
    {
        $shift = $this->openShift();
        [, $bill] = $this->billed();
        $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => '10000']])->assertOk();
        $payment = Payment::firstOrFail();

        $this->pos('POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '143000', 'reason' => 'Mijoz qaytardi', 'approval_token' => $this->approval('pos.refund')])->assertCreated();

        $this->assertSame(1, PosTip::count());
        $this->assertSame('10000.00', PosTip::firstOrFail()->amount);
        $report = $this->pos('GET', "/shifts/{$shift->uuid}/x-report")->json('data.report');
        $this->assertSame(['10000.00', '10000.00', '143000.00'], [$report['tips']['amount'], $report['cash']['tips'], $report['refunds']['amount']]);
        $this->assertSame('110000.00', $report['cash']['expected']); // 100 000 + 143 000 − 143 000 + 10 000
    }

    public function test_a_tip_taken_offline_is_kept_whatever_the_module_says_now(): void
    {
        $shift = $this->openShift();
        [$order, $bill] = $this->billed();
        $this->profile($this->branch, 'choyxona');

        $result = app(\App\Domain\Pos\Billing\PaymentService::class)->pay(
            $this->cashier,
            \App\Models\OrderBill::where('uuid', $bill)->firstOrFail(),
            [['tender' => 'cash', 'amount' => '143000', 'tip_amount' => '3000']],
            null,
            $shift->uuid,
            null,
            $this->till['device'],
            null,
            now(),
            $this->otherBranchStaff()->id, // not of the branch: dropped, the order's waiter takes it
        );

        $this->assertSame('paid', $result->status->value);
        $tip = PosTip::firstOrFail();
        $this->assertSame(['3000.00', $this->waiter->id], [$tip->amount, $tip->staff_id]);
        $this->assertSame($order->id, $tip->order_id);
    }

    private function otherBranchStaff(): \App\Models\User
    {
        return $this->makeStaff(\App\Enums\UserRole::WAITER, $this->otherBranch);
    }
}
