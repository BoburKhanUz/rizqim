<?php

namespace Tests\Feature\PosBilling;

use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Enums\PaymentStatus;
use App\Models\CashMovement;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\PosShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P3-18: refunds within the shift: pos.refund or a manager's approval, a reason, cash out of the drawer, a REFUND
 * receipt naming the sale receipt with its lines scaled, never more than the payment, never another shift's payment
 * or a deposit / nasiya part.
 */
class PosRefundsTest extends TestCase
{
    use PosBillingFixtures;
    use RefreshDatabase;

    private PosShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPosBilling();
        $this->shift = $this->openShift('100000');
    }

    /**
     * @param  list<array<string, mixed>>  $tenders
     * @return array{0: Order, 1: string}
     */
    private function paidOrder(array $tenders = [['tender' => 'cash', 'amount' => '130000']]): array
    {
        $order = $this->posOrder([['Osh', '1.5', '120000'], ['Choy', '2', '10000']]);
        $bill = $this->bill($order)->json('data.bills.0.uuid');
        $this->pay($bill, $tenders)->assertOk()->assertJsonPath('data.bill.status', 'paid');

        return [$order, $bill];
    }

    public function test_a_cashier_refunds_cash_with_a_managers_approval(): void
    {
        [$order, $bill] = $this->paidOrder();
        $payment = Payment::firstOrFail();
        $data = ['payment_uuid' => $payment->uuid, 'amount' => '13000', 'reason' => 'Choy sovuq edi'];

        $this->pos('POST', '/refunds', $data)->assertForbidden()->assertJsonPath('data.reason', 'approval_required')->assertJsonPath('data.permission', 'pos.refund');

        $response = $this->pos('POST', '/refunds', $data + ['approval_token' => $this->approval('pos.refund')])->assertCreated();
        $response->assertJsonPath('data.amount', '13000.00')
            ->assertJsonPath('data.method', 'cash')
            ->assertJsonPath('data.approved_by', $this->manager->id)
            ->assertJsonPath('data.fiscal.status', 'issued');

        $refund = PosRefund::firstOrFail();
        $this->assertSame('13000.00', CashMovement::where('type', 'refund')->value('amount'));
        $document = $this->fiscal->document("pos-refund:{$refund->uuid}");
        $this->assertSame(FiscalReceiptType::REFUND, $document->type);
        $this->assertSame(OrderBill::where('uuid', $bill)->value('fiscal_receipt_uuid'), $document->originalReceiptUuid);
        $this->assertSame(['12000.00', '1000.00'], array_map(fn ($line) => $line->amount->toString(), $document->lines));
        $this->assertSame(['0.150', '0.200'], array_map(fn ($line) => $line->quantity->toString(), $document->lines));
        $this->assertSame(FiscalPaymentForm::CASH, $document->payments[0]->form);
        $this->assertDatabaseHas('audit_logs', ['action' => 'pos.refund', 'user_id' => $this->cashier->id, 'model_id' => $order->id]);
        $this->assertSame(PaymentStatus::PAID, $payment->refresh()->status);
    }

    public function test_a_manager_refunds_a_card_payment_up_to_what_is_left(): void
    {
        [$order] = $this->paidOrder([['tender' => 'card', 'amount' => '130000', 'card_type' => 'visa', 'transaction_id' => 'T9']]);
        $payment = Payment::firstOrFail();
        $refund = fn (string $amount) => $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => $amount, 'reason' => 'Mijoz qaytardi', 'shift_uuid' => $this->shift->uuid]);

        $refund('100000')->assertCreated();
        $refund('40000')->assertStatus(422)->assertJsonPath('data.reason', 'refund_exceeds_payment')->assertJsonPath('data.left', '30000.00');
        $refund('30000')->assertCreated();

        $this->assertSame(PaymentStatus::REFUNDED, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::REFUNDED, Order::find($order->id)->payment_status);
        $this->assertSame(0, CashMovement::where('type', 'refund')->count()); // a card refund leaves the drawer alone
        $this->assertSame('T9', $this->fiscal->document('pos-refund:' . PosRefund::orderBy('id')->value('uuid'))->payments[0]->transactionId);
    }

    public function test_only_this_shifts_payments_are_refunded(): void
    {
        $this->advances->deposits = [];
        [$order] = $this->paidOrder();
        $payment = Payment::firstOrFail();

        // Not more cash than the drawer holds
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '130000', 'reason' => 'Xato', 'shift_uuid' => $this->shift->uuid])->assertCreated();
        $second = $this->posOrder([['Osh', '1', '500000']]);
        $this->advances->deposits[$second->id] = '500000';
        $bill = $this->bill($second)->json('data.bills.0.uuid');
        $this->pay($bill, [['tender' => 'prepayment', 'amount' => '500000']])->assertOk();
        $prepayment = Payment::where('provider', 'prepayment')->firstOrFail();
        // Review P3-35: a deposit part goes back to the deposit (no cash leaves the drawer)
        $cashOut = \App\Models\CashMovement::where('type', 'refund')->count();
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $prepayment->uuid, 'amount' => '1000', 'reason' => 'Xato', 'shift_uuid' => $this->shift->uuid])
            ->assertCreated()->assertJsonPath('data.method', 'prepayment');
        $this->assertSame($cashOut, \App\Models\CashMovement::where('type', 'refund')->count());
        $this->assertSame('-1000.00', end($this->advances->applied)['amount']);

        $third = $this->posOrder([['Osh', '1', '300000']]);
        $this->pay($this->bill($third)->json('data.bills.0.uuid'), [['tender' => 'qr', 'amount' => '300000']])->assertOk();
        $cashPaid = $this->posOrder([['Osh', '1', '20000']]);
        $this->pay($this->bill($cashPaid)->json('data.bills.0.uuid'), [['tender' => 'cash', 'amount' => '20000']])->assertOk();
        $cash = Payment::where('order_id', $cashPaid->id)->firstOrFail();
        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $cash->uuid, 'amount' => '20000', 'reason' => 'Xato', 'shift_uuid' => $this->shift->uuid])->assertCreated();

        // Another shift's payment: not refunded here
        $this->pos('POST', "/shifts/{$this->shift->uuid}/close", ['counted_breakdown' => [], 'force' => true, 'reason' => 'Test', 'approval_token' => $this->approval('orders.cancel')])->assertOk();
        $this->openShift('0');
        $qr = Payment::where('provider', 'qr')->firstOrFail();
        $this->pos('POST', '/refunds', ['payment_uuid' => $qr->uuid, 'amount' => '1000', 'reason' => 'Xato', 'approval_token' => $this->approval('pos.refund')])
            ->assertStatus(409)->assertJsonPath('data.reason', 'refund_outside_shift');
    }

    public function test_cash_beyond_the_drawer_is_refused_and_isolation_holds(): void
    {
        [$order] = $this->paidOrder();
        $payment = Payment::firstOrFail();
        $this->posAs($this->manager, 'POST', "/shifts/{$this->shift->uuid}/cash-movements", ['type' => 'cash_out', 'category' => 'collection', 'amount' => '200000'])->assertCreated();

        $this->posAs($this->manager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '50000', 'reason' => 'Xato', 'shift_uuid' => $this->shift->uuid])
            ->assertStatus(409)->assertJsonPath('data.reason', 'not_enough_cash');

        $otherManager = $this->makeStaff(\App\Enums\UserRole::MANAGER, $this->otherBranch);
        $this->posAs($otherManager, 'POST', '/refunds', ['payment_uuid' => $payment->uuid, 'amount' => '1000', 'reason' => 'Xato'])->assertNotFound();
        $this->assertSame(0, PosRefund::count());
    }
}
