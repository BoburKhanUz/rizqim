<?php

namespace Tests\Feature\PosBilling;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CashMovement;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Review P3-35 (review A #1, #5, #8): a change that would take away what a bill being paid carries is refused, so the
 * order never gets stuck; a payment or refund re-reads the shift under its row lock (a shift closed meanwhile takes no
 * money, the drawer never goes below zero); the deposit and nasiya parts of a bill are refunded too (back to the
 * deposit, off the debt), so an order paid with them can still be cancelled.
 */
class PosBillingReviewFixesTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPosBilling();
    }

    public function test_voiding_a_line_on_a_paid_bill_is_refused_and_the_order_can_still_be_settled(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000'], ['Choy', '2', '10000']]);
        $bills = $this->bill($order, 'equal', ['parts' => 2])->assertCreated()->json('data.bills');
        $this->pay($bills[0]['uuid'], [['tender' => 'cash', 'amount' => $bills[0]['total']]])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        $choy = OrderItem::where('order_id', $order->id)->where('product_name', 'Choy')->firstOrFail();
        $version = (int) $order->refresh()->version;
        $this->posAs($this->manager, 'POST', "/orders/{$order->uuid}/items/{$choy->id}/void", ['version' => $version, 'reason' => 'Xato'])
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'paid_bill_affected');

        // Nothing changed: the line, the version, the second bill is still payable and the order closes
        $this->assertNull($choy->refresh()->voided_at);
        $this->assertSame($version, (int) $order->refresh()->version);
        $this->pay($bills[1]['uuid'], [['tender' => 'cash', 'amount' => $bills[1]['total']]])->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.order.status', 'delivered');
    }

    public function test_a_shift_closed_while_a_payment_waits_takes_no_money(): void
    {
        $shift = $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        // The Z closes the shift between the payment's first look at it and its lock
        $this->closeShiftAfterFirstRead($shift);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']])
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'shift_closed');
        $this->assertSame(0, Payment::count());
        $this->assertSame(0, CashMovement::where('type', 'sale')->count());
    }

    public function test_a_refund_rechecks_the_shift_and_the_drawer_under_the_shifts_lock(): void
    {
        $shift = $this->openShift('0');
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $this->pay($this->bill($order)->json('data.bills.0.uuid'), [['tender' => 'cash', 'amount' => '100000']])->assertOk();
        $payment = Payment::sole();

        $this->closeShiftAfterFirstRead($shift);
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '1000', 'reason' => 'Xato', 'shift_uuid' => $shift->uuid])
            ->assertStatus(409)
            ->assertJsonPath('data.reason', 'shift_closed');
        $this->assertSame(0, PosRefund::count());
    }

    public function test_the_deposit_and_nasiya_parts_are_refunded_and_the_order_can_then_be_cancelled(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000'], ['Choy', '2', '10000']]);
        $this->advances->deposits[$order->id] = '30000';
        $account = '9b2f3c1e-7a44-4c55-9a11-2d3e4f5a6b7c';
        $bills = $this->bill($order, 'equal', ['parts' => 2])->assertCreated()->json('data.bills');
        $this->pay($bills[0]['uuid'], [
            ['tender' => 'prepayment', 'amount' => '30000'],
            ['tender' => 'account', 'amount' => '25000', 'account_uuid' => $account],
        ], ['approval_token' => $this->approval('customer_accounts.manage')])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        // The guests leave before the second bill: refund the paid one first, then cancel
        $cancel = fn () => $this->posAs($this->manager, 'POST', "/orders/{$order->uuid}/cancel", ['version' => (int) $order->refresh()->version, 'reason' => 'Mehmonlar ketdi']);
        $cancel()->assertStatus(409)->assertJsonPath('data.reason', 'has_payments');

        $drawer = CashMovement::count();
        foreach (Payment::orderBy('id')->get() as $payment) {
            $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => $payment->amount, 'reason' => 'Mehmonlar ketdi', 'shift_uuid' => PosShift::value('uuid')])
                ->assertCreated()
                ->assertJsonPath('data.method', $payment->provider)
                ->assertJsonPath('data.fiscal.status', 'issued');
            $this->assertSame(PaymentStatus::REFUNDED, $payment->refresh()->status);
        }
        $this->assertSame($drawer, CashMovement::count()); // neither part moves cash

        // The deposit holds its money again, the debt is off
        $this->assertSame('30000.00', $this->advances->available($order)->toString());
        $this->assertSame([['account' => $account, 'amount' => '25000.00', 'bill_uuid' => $bills[0]['uuid'], 'reference' => PosRefund::where('method', 'account')->value('uuid'), 'approved_by' => $this->manager->id]], $this->accounts->reversals);

        // Each REFUND receipt names the sale and shows the part as the sale did
        $refunds = PosRefund::orderBy('id')->get();
        $prepayment = $this->fiscal->document("pos-refund:{$refunds[0]->uuid}");
        $credit = $this->fiscal->document("pos-refund:{$refunds[1]->uuid}");
        $this->assertTrue($prepayment->payments[0]->prepayment);
        $this->assertFalse($prepayment->payments[0]->credit);
        $this->assertTrue($credit->payments[0]->credit);
        $this->assertSame(\App\Models\OrderBill::where('uuid', $bills[0]['uuid'])->value('fiscal_receipt_uuid'), $credit->originalReceiptUuid);

        $cancel()->assertOk();
        $this->assertSame(OrderStatus::CANCELED, $order->refresh()->status);
    }

    /**
     * Closes the shift in the database right after it is first read (as a Z on another terminal would), so the
     * action's own read under the row lock finds it closed.
     */
    private function closeShiftAfterFirstRead(PosShift $shift): void
    {
        $closed = false;
        PosShift::retrieved(function (PosShift $read) use ($shift, &$closed) {
            if (!$closed && (int) $read->id === (int) $shift->id) {
                $closed = true;
                DB::table('pos_shifts')->where('id', $shift->id)->update(['status' => 'closed', 'closed_at' => now()]);
            }
        });
    }
}
