<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Shifts\CashMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Broadcast\BillChangedBroadcast;
use App\Events\Broadcast\OrderStatusChangedBroadcast;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * P3-18: paying bills: cash with change, card (type and transaction id required), QR, a booking deposit, nasiya with a
 * manager's approval, mixed and partial payments; the sale receipt (issued: the bill is paid and the order closed;
 * pending or failed: "fiskal kutmoqda" and a retry), the close rules, shift, version, idempotency and isolation.
 */
class PosPaymentsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
    }

    public function test_cash_with_change_pays_the_bill_issues_the_receipt_and_closes_the_order(): void
    {
        Event::fake([BillChangedBroadcast::class, OrderStatusChangedBroadcast::class]);
        $shift = $this->openShift();
        $order = $this->posOrder([['Osh', '1.5', '120000'], ['Choy', '2', '10000']], ['service_charge_amount' => '13000']);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $response = $this->pay($bill, [['tender' => 'cash', 'amount' => '143000', 'tendered_amount' => '150000']])->assertOk();

        $response->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.bill.fiscal.status', 'issued')
            ->assertJsonPath('data.bill.payments.0.change_amount', '7000.00')
            ->assertJsonPath('data.order.status', 'delivered')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.order.fiscal_pending', false);

        $order->refresh();
        $this->assertSame(OrderStatus::DELIVERED, $order->status);
        $this->assertNotNull($order->closed_at);
        $this->assertSame($shift->id, $order->shift_id);

        $payment = Payment::where('bill_id', '!=', null)->firstOrFail();
        $this->assertSame(['cash', '143000.00', '150000.00', '7000.00', $shift->id, $this->till['device']->id, $this->cashier->id], [
            $payment->provider, $payment->amount, $payment->tendered_amount, $payment->change_amount, $payment->shift_id, $payment->device_id, $payment->received_by,
        ]);
        $this->assertSame('143000.00', CashMovement::where('type', CashMovementType::SALE->value)->value('amount'));

        $document = $this->fiscal->document("pos-bill:{$bill}:sale");
        $this->assertSame(FiscalReceiptType::SALE, $document->type);
        $this->assertSame(['Osh', 'Choy', 'service_charge'], array_map(fn ($line) => $line->name, $document->lines));
        $this->assertSame('1.500', $document->lines[0]->quantity->toString());
        $this->assertSame('order_bill', $document->subjectType);
        $this->assertSame(FiscalPaymentForm::CASH, $document->payments[0]->form);
        $this->assertSame('143000.00', $document->payments[0]->amount->toString());

        Event::assertDispatched(OrderStatusChangedBroadcast::class, fn ($event) => $event->uuid === $order->uuid && $event->status === 'delivered');
        Event::assertDispatched(BillChangedBroadcast::class, fn ($event) => $event->billUuid === $bill && $event->change === 'fiscal');
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.order_closed', 'model_id' => $order->id]);
    }

    public function test_a_mixed_payment_needs_the_card_type_and_transaction_id(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '200000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'card', 'amount' => '150000', 'transaction_id' => '004512']])->assertStatus(422)->assertJsonPath('data.reason', 'card_type_required');
        $this->pay($bill, [['tender' => 'card', 'amount' => '150000', 'card_type' => 'humo']])->assertStatus(422)->assertJsonPath('data.reason', 'transaction_id_required');

        $this->pay($bill, [
            ['tender' => 'card', 'amount' => '150000', 'card_type' => 'Humo', 'transaction_id' => '004512'],
            ['tender' => 'qr', 'amount' => '20000', 'reference' => 'click'],
            ['tender' => 'cash', 'amount' => '30000'],
        ])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        $payments = $this->fiscal->document("pos-bill:{$bill}:sale")->payments;
        $this->assertSame([FiscalPaymentForm::CARD, FiscalPaymentForm::QR, FiscalPaymentForm::CASH], array_map(fn ($payment) => $payment->form, $payments));
        $this->assertSame(['humo', '004512'], [$payments[0]->cardType, $payments[0]->transactionId]);
        $this->assertSame(1, CashMovement::where('type', 'sale')->count());
    }

    public function test_payments_may_come_in_parts_but_never_exceed_the_bill(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'card', 'amount' => '60000', 'card_type' => 'uzcard', 'transaction_id' => '1']])->assertOk()
            ->assertJsonPath('data.bill.status', 'open')
            ->assertJsonPath('data.bill.due_amount', '40000.00');
        $this->assertSame(OrderStatus::ACCEPTED, $order->refresh()->status);

        $this->pay($bill, [['tender' => 'cash', 'amount' => '50000']])->assertStatus(422)->assertJsonPath('data.reason', 'overpayment')->assertJsonPath('data.due', '40000.00');
        $this->pay($bill, [['tender' => 'cash', 'amount' => '40000', 'tendered_amount' => '30000']])->assertStatus(422)->assertJsonPath('data.reason', 'tendered_too_small');

        $this->pay($bill, [['tender' => 'cash', 'amount' => '40000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(OrderStatus::DELIVERED, $order->refresh()->status);

        $this->assertCount(2, $this->fiscal->document("pos-bill:{$bill}:sale")->payments);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '1']])->assertStatus(409);
    }

    public function test_a_booking_deposit_is_offset_and_flagged_as_prepayment_on_the_receipt(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '300000']]);
        $this->advances->deposits[$order->id] = '200000';
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'prepayment', 'amount' => '250000']])->assertStatus(422)->assertJsonPath('data.reason', 'advance_insufficient');

        $this->pay($bill, [['tender' => 'prepayment', 'amount' => '200000'], ['tender' => 'cash', 'amount' => '100000']])->assertOk()
            ->assertJsonPath('data.bill.status', 'paid');

        $this->assertSame([['order_id' => $order->id, 'amount' => '200000.00', 'bill_uuid' => $bill]], $this->advances->applied);
        $payments = $this->fiscal->document("pos-bill:{$bill}:sale")->payments;
        $this->assertSame([true, false], array_map(fn ($payment) => $payment->prepayment, $payments));
        $this->assertSame('300000.00', $this->fiscal->document("pos-bill:{$bill}:sale")->total->toString());
        $this->assertSame('100000.00', CashMovement::where('type', 'sale')->value('amount'));
    }

    public function test_nasiya_needs_a_manager_and_the_sale_receipt_is_still_full(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '850000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $account = '9b2f3c1e-7a44-4c55-9a11-2d3e4f5a6b7c';

        $tenders = [['tender' => 'cash', 'amount' => '350000'], ['tender' => 'account', 'amount' => '500000', 'account_uuid' => $account]];
        $this->pay($bill, $tenders)->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'customer_accounts.manage');
        $this->assertSame(0, Payment::count());

        $this->pay($bill, $tenders, ['approval_token' => $this->approval('customer_accounts.manage')])->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.order.status', 'delivered');

        $this->assertSame([['account' => $account, 'amount' => '500000.00', 'bill_uuid' => $bill, 'approved_by' => $this->manager->id]], $this->accounts->charges);
        $document = $this->fiscal->document("pos-bill:{$bill}:sale");
        $this->assertSame('850000.00', $document->total->toString());
        $this->assertSame([false, true], array_map(fn ($payment) => $payment->prepayment, $document->payments));
        $this->assertEquals(['account_uuid' => $account, 'approved_by' => $this->manager->id], Payment::where('provider', 'account')->value('payload'));

        // A refused account (limit, blocked) takes nothing
        $other = $this->posOrder([['Osh', '1', '1000']]);
        $otherBill = $this->bill($other)->json('data.bills.0.uuid');
        $this->posAs($this->manager, 'POST', "/bills/{$otherBill}/payments", ['tenders' => [['tender' => 'account', 'amount' => '1000', 'account_uuid' => FakeAccountLedger::BLOCKED]], 'shift_uuid' => \App\Models\PosShift::value('uuid')])
            ->assertStatus(422)->assertJsonPath('data.reason', 'credit_limit_exceeded');
        $this->assertSame(0, Payment::where('bill_id', \App\Models\OrderBill::where('uuid', $otherBill)->value('id'))->count());
    }

    public function test_a_pending_receipt_keeps_the_order_open_until_it_is_issued(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->fiscal->mode = 'pending';

        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']])->assertOk()
            ->assertJsonPath('data.bill.status', 'open')
            ->assertJsonPath('data.bill.fiscal.status', 'pending')
            ->assertJsonPath('data.order.fiscal_pending', true)
            ->assertJsonPath('data.order.status', 'accepted');

        $this->pay($bill, [['tender' => 'cash', 'amount' => '1']])->assertStatus(409)->assertJsonPath('data.reason', 'bill_awaiting_fiscal');
        $this->pos('POST', "/orders/{$order->uuid}/close")->assertStatus(409)->assertJsonPath('data.reason', 'order_not_settled');

        $this->fiscal->mode = 'issued';
        $this->pos('POST', "/bills/{$bill}/fiscal-retry")->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.order.status', 'delivered');
        $this->assertCount(1, array_unique($this->fiscal->uuids)); // the same receipt, asked again
        $this->pos('POST', "/orders/{$order->uuid}/close")->assertOk()->assertJsonPath('data.order.status', 'delivered');
    }

    public function test_a_failed_receipt_is_kept_and_retried(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->fiscal->mode = 'throw';

        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']])->assertOk()
            ->assertJsonPath('data.bill.fiscal.status', 'failed')
            ->assertJsonPath('data.bill.fiscal.error', 'Provayder javob bermadi.')
            ->assertJsonPath('data.order.fiscal_pending', true);
        $this->assertSame(1, Payment::count()); // the money is kept

        $this->fiscal->mode = 'issued';
        $this->pos('POST', "/orders/{$order->uuid}/close")->assertOk()->assertJsonPath('data.order.status', 'delivered');
    }

    public function test_a_line_without_mxik_refuses_the_payment_before_money_is_taken(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Kalyan MXIKSIZ', '1', '50000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'cash', 'amount' => '50000']])->assertStatus(422)->assertJsonPath('data.reason', 'mxik_missing');
        $this->assertSame(0, Payment::count());
    }

    public function test_split_bills_close_the_order_only_when_the_last_one_is_paid(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']], ['service_charge_amount' => '10000']);
        $bills = $this->bill($order, 'equal', ['parts' => 2])->json('data.bills');

        $this->pay($bills[0]['uuid'], [['tender' => 'cash', 'amount' => '55000']])->assertOk()
            ->assertJsonPath('data.bill.status', 'paid')
            ->assertJsonPath('data.order.status', 'accepted');
        $this->pay($bills[1]['uuid'], [['tender' => 'qr', 'amount' => '55000']])->assertOk()
            ->assertJsonPath('data.order.status', 'delivered')
            ->assertJsonPath('data.order.paid_amount', '110000.00');

        // Each split bill has its own sale receipt
        $this->assertSame('55000.00', $this->fiscal->document("pos-bill:{$bills[0]['uuid']}:sale")->total->toString());
        $this->assertSame('55000.00', $this->fiscal->document("pos-bill:{$bills[1]['uuid']}:sale")->total->toString());
    }

    public function test_a_round_after_part_of_the_bill_is_paid_is_billed_on_its_own(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bills = $this->bill($order, 'equal', ['parts' => 2])->json('data.bills');
        $this->pay($bills[0]['uuid'], [['tender' => 'cash', 'amount' => '50000']])->assertOk();

        $this->line($order, 'Choy', '1', '5000');
        $order->forceFill(['subtotal' => '105000', 'total_price' => '105000', 'version' => $order->version + 1])->save();

        // The unpaid bill no longer matches the order: bill again; the paid one stays
        $this->pay($bills[1]['uuid'], [['tender' => 'cash', 'amount' => '50000']])->assertStatus(409)->assertJsonPath('data.reason', 'order_changed');
        $again = $this->bill($order)->assertCreated()->json('data');
        $this->assertSame(['paid', 'open'], array_column($again['bills'], 'status'));
        $this->assertSame('55000.00', $again['bills'][1]['total']);

        $this->pay($again['bills'][1]['uuid'], [['tender' => 'cash', 'amount' => '55000']])->assertOk()->assertJsonPath('data.order.status', 'delivered');
    }

    public function test_a_payment_needs_an_open_shift_and_pos_take_payment(): void
    {
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']])->assertStatus(409)->assertJsonPath('data.reason', 'shift_not_open');

        $this->openShift();
        $waiterToken = $this->pinSession($this->till, $this->waiter);
        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']], [], $waiterToken)->assertForbidden();
        $this->assertSame(0, Payment::count());
    }

    public function test_a_repeated_payment_is_replayed_and_a_stale_version_refused(): void
    {
        $this->openShift();
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $this->pay($bill, [['tender' => 'cash', 'amount' => '40000']], ['version' => 1])->assertStatus(409)->assertJsonPath('data.reason', 'version_conflict');

        $data = ['tenders' => [['tender' => 'cash', 'amount' => '40000']]];
        $first = $this->pos('POST', "/bills/{$bill}/payments", $data, null, 'pay-1')->assertOk();
        $this->pos('POST', "/bills/{$bill}/payments", $data, null, 'pay-1')->assertOk()->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame(1, Payment::count());
        $this->assertSame($first->json('data.bill.payments.0.uuid'), Payment::value('uuid'));
    }

    public function test_another_branchs_bill_cannot_be_paid(): void
    {
        $order = $this->posOrder([['Osh', '1', '100000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');

        $otherCashier = $this->makeStaff(\App\Enums\UserRole::CASHIER, $this->otherBranch);
        $otherTill = $this->pairTerminal($this->owner, $this->otherBranch);
        $otherToken = $this->pinSession($otherTill, $otherCashier);
        $this->openShift('0', $otherTill['device'], $otherCashier);

        $this->pay($bill, [['tender' => 'cash', 'amount' => '100000']], [], $otherToken)->assertNotFound();
        $this->assertSame(PaymentStatus::PENDING, Order::find($order->id)->payment_status);
    }
}
