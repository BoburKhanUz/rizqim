<?php

namespace Tests\Feature\PosBanquetOps;

use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\StaffPay\StaffPayMetrics;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\BanquetSettlement;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\PosShift;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P6-54 (E5): the event day and the final settlement — the tab bound to the event, the billed guests rule (never below
 * the guaranteed), extras, event-day orders, ONE sale receipt at the full total whose prepayment parts are the paid
 * advances in their forms, the remainder at the till, advances over the total refunded, the event settled and its
 * DUE rows gone, the waiters' event pay, and the refusals.
 */
class BanquetSettlementTest extends TestCase
{
    use PosBanquetOpsFixtures;
    use RefreshDatabase;

    private PosShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpBanquetOps();
        $this->shift = $this->openShift('100000');
    }

    /**
     * The deposit in cash and the interim by card, the event started.
     */
    private function paidAndStarted(BanquetEvent $event): BanquetEvent
    {
        [$deposit, $interim] = $this->rows($event)->all();
        $this->payRow($event, $deposit, ['tender' => 'cash'])->assertCreated();
        $this->payRow($event, $interim, ['tender' => 'card', 'card_type' => 'humo', 'transaction_id' => 'RRN-1'])->assertCreated();
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk()->assertJsonPath('data.event.status', 'in_progress');

        return $event->refresh();
    }

    public function test_fewer_guests_than_guaranteed_one_sale_receipt_with_the_advances_as_prepayment(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $order = Order::findOrFail($event->order_id);
        $this->assertSame('pos_hall', $order->channel->value);
        $this->assertSame($this->hallA, (int) $order->hall_id);
        $this->assertSame(200, (int) $order->guests_count);
        // Starting again: the same tab
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk()->assertJsonPath('data.event.order_uuid', $order->uuid);

        $this->pos('PATCH', "/banquets/{$event->uuid}/guests", ['guests_actual' => 180], $this->managerToken)->assertOk()
            ->assertJsonPath('data.settlement.guests.billed', 200)
            ->assertJsonPath('data.settlement.total', '36800000.00');
        $this->eventDayLine($event);

        $preview = $this->pos('GET', "/banquets/{$event->uuid}/settlement")->assertOk()->json('data');
        $this->assertSame('30000000.00', $preview['package_total']);
        $this->assertSame('5000000.00', $preview['hall_total']);
        $this->assertSame('1800000.00', $preview['extras_total']); // cake 800 000 + napkins 5 000 × 200
        $this->assertSame('25760000.00', $preview['advances']['total']);
        $this->assertNull($preview['settlement']);

        $settled = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data');
        $bill = $settled['bill'];
        $this->assertSame('36860000.00', $bill['total']);
        $this->assertSame('36800000.00', $bill['banquet_amount']);
        $this->assertSame(['Banket paketi: Standart', 'Zal ijarasi: Oq zal', 'Tort', 'Salfetka'], array_column($bill['banquet_lines'], 'name'));
        $this->assertSame(['200.000', '1.000', '1.000', '200.000'], array_column($bill['banquet_lines'], 'quantity'));
        $this->assertSame('25760000.00', $bill['paid_amount']);
        $this->assertSame('11100000.00', $bill['due_amount']);
        $this->assertSame('36860000.00', $settled['settlement']['total']);
        $this->assertSame('60000.00', $settled['settlement']['orders']['total']);
        $this->assertSame('11100000.00', $settled['settlement']['due']);
        $this->assertSame('25760000.00', $settled['settlement']['settlement']['applied_amount']);
        // The schedule's unpaid final row went: the rest is paid on this bill
        $this->assertSame(['paid', 'paid'], $this->rows($event)->map(fn (BanquetPayment $row) => $row->status->value)->all());
        $this->assertSame(BanquetEventStatus::IN_PROGRESS, $event->refresh()->status);

        // The rest at the till, in cash
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => '11100000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');

        $event->refresh();
        $this->assertSame(BanquetEventStatus::SETTLED, $event->status);
        $this->assertNotNull($event->settled_at);
        $this->assertSame('36860000.00', $event->total);
        $this->assertSame(BanquetSettlement::SETTLED, BanquetSettlement::where('banquet_event_id', $event->id)->sole()->status);
        $this->assertNotNull(Order::findOrFail($event->order_id)->closed_at);

        // ONE sale receipt at the full total; its prepayment parts are the advances, in their forms
        $billId = OrderBill::where('uuid', $bill['uuid'])->value('id');
        $sale = FiscalReceipt::where('subject_type', OrderBill::FISCAL_SUBJECT)->where('subject_id', $billId)->sole();
        $this->assertSame(FiscalReceiptType::SALE, $sale->type);
        $this->assertSame('36860000.00', Money::of($sale->total)->toString());
        $payments = array_map(fn (array $payment) => [$payment['form'], Money::of($payment['amount'])->toString(), (bool) ($payment['prepayment'] ?? false)], $sale->payments);
        $this->assertSame([['cash', '11040000.00', true], ['card', '14720000.00', true], ['cash', '11100000.00', false]], $payments);
        $prepaid = array_filter($sale->payments, fn (array $payment) => $payment['prepayment'] ?? false);
        $this->assertSame('25760000.00', $this->sumOf(array_column($prepaid, 'amount')));
        $this->assertSame(['Cola', 'Banket paketi: Standart', 'Zal ijarasi: Oq zal', 'Tort', 'Salfetka'], array_map(fn (array $line) => $line['name'], $sale->lines));
        $this->assertSame('10305001003000000', $sale->lines[1]['mxik_code'] ?? $sale->lines[1]['mxik'] ?? null);
        $this->assertSame('10305001004000000', $sale->lines[2]['mxik_code'] ?? $sale->lines[2]['mxik'] ?? null);
        $this->assertSame('10305001005000000', $sale->lines[3]['mxik_code'] ?? $sale->lines[3]['mxik'] ?? null);
        $this->assertSame(1, FiscalReceipt::where('type', 'sale')->count());
        $this->assertSame(2, FiscalReceipt::where('type', 'advance')->count());

        // The X report: the sale with its prepayment part (as a booking deposit's), the drawer holds the cash deposit and
        // the cash rest (11 040 000 + 11 100 000 + the 100 000 opening)
        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame('36860000.00', $x['sales']['received']);
        $this->assertSame('22240000.00', $x['cash']['expected']);

        // Settled: settle again is the same, nothing new
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->assertJsonPath('data.bill.uuid', $bill['uuid']);
        $this->assertSame(1, FiscalReceipt::where('type', 'sale')->count());
    }

    public function test_more_guests_than_guaranteed_are_billed_with_the_per_person_extras(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $this->pos('PATCH', "/banquets/{$event->uuid}/guests", ['guests_actual' => 260], $this->managerToken)->assertOk();

        $preview = $this->pos('GET', "/banquets/{$event->uuid}/settlement")->assertOk()->json('data');
        $this->assertSame(260, $preview['guests']['billed']);
        $this->assertSame('39000000.00', $preview['package_total']);
        $this->assertSame('2100000.00', $preview['extras_total']); // 800 000 + 5 000 × 260
        $this->assertSame('46100000.00', $preview['total']);
        $this->assertSame('20340000.00', $preview['due']);

        $bill = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame('46100000.00', $bill['total']);
        $this->pay($bill['uuid'], [['tender' => 'card', 'amount' => '20340000', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-2']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);
        $sale = FiscalReceipt::where('type', 'sale')->sole();
        $this->assertSame('46100000.00', Money::of($sale->total)->toString());
        $this->assertSame('260.000', (string) $sale->lines[0]['quantity']);
    }

    public function test_advances_over_the_total_pay_the_bill_and_the_difference_is_refunded(): void
    {
        $event = $this->pricedEvent(100, 100, '2027-01-20', [], null, false); // 15 000 000 + 5 000 000
        foreach ($this->rows($event) as $row) {
            $this->payRow($event, $row, ['tender' => 'cash'])->assertCreated();
        }
        // The guaranteed guests went down after everything was paid
        $event->refresh()->forceFill(['guests_guaranteed' => 80, 'guests_minimum' => 80])->save();
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk();

        $settled = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data');
        $this->assertSame('17000000.00', $settled['bill']['total']);
        $this->assertSame('paid', $settled['bill']['status']);
        $this->assertSame('3000000.00', $settled['settlement']['excess']);
        // The cashier holds no pos.refund: the refund waits for the manager's approval
        $this->assertSame('pending', $settled['settlement']['settlement']['excess_status']);
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);
        $sale = FiscalReceipt::where('type', 'sale')->sole();
        $this->assertSame('17000000.00', Money::of($sale->total)->toString());
        $this->assertSame([true], array_values(array_unique(array_map(fn (array $payment) => (bool) $payment['prepayment'], $sale->payments))));

        $this->pos('POST', "/banquets/{$event->uuid}/settlement/refund-excess")->assertForbidden()->assertJsonPath('data.reason', 'approval_required');
        // The approvals' 60 seconds are read in the app's (UTC) clock: "now" as a UTC instant from here on
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 05:00:00', 'UTC'));
        $preview = $this->pos('POST', "/banquets/{$event->uuid}/settlement/refund-excess", ['approval_token' => $this->approval('pos.refund')])->assertOk()->json('data');
        $this->assertSame('refunded', $preview['settlement']['excess_status']);

        // From the newest advance (the final row, 6 000 000 cash), against its advance receipt, from the drawer
        $refund = BanquetPaymentEntry::where('type', 'refund')->sole();
        $this->assertSame('3000000.00', $refund->amount);
        $this->assertSame($this->shift->id, (int) $refund->shift_id);
        $final = $this->rows($event)->last();
        $this->assertSame('3000000.00', $final->refunded_amount);
        $this->assertSame($final->fiscal_receipt_uuid, $refund->original_receipt_uuid);
        $receipt = FiscalReceipt::where('type', 'refund')->sole();
        $this->assertSame('3000000.00', Money::of($receipt->total)->toString());
        // Advances 20 000 000 = 17 000 000 offset on the sale + 3 000 000 refunded
        $this->assertSame('17000000.00', BanquetSettlement::sole()->applied_amount);
        $x = $this->pos('GET', "/shifts/{$this->shift->uuid}/x-report")->assertOk()->json('data.report');
        $this->assertSame('17100000.00', $x['cash']['expected']);
        // Nothing more to give back
        $this->pos('POST', "/banquets/{$event->uuid}/settlement/refund-excess", ['approval_token' => $this->approval('pos.refund')])->assertOk();
        $this->assertSame(1, BanquetPaymentEntry::where('type', 'refund')->count());
    }

    public function test_the_advances_are_offset_only_on_the_final_bill_and_retrying_recomputes_it(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $order = Order::findOrFail($event->order_id);
        $this->eventDayLine($event);
        app(\App\Domain\Pos\Orders\PosChargesCalculator::class)->recalculate($order->refresh());

        // An event-day bill of its own does not take the advances
        $bill = $this->bill($order->refresh())->assertCreated()->json('data.bills.0');
        $this->pay($bill['uuid'], [['tender' => 'prepayment', 'amount' => '60000']])->assertConflict()->assertJsonPath('data.reason', 'not_settlement_bill');

        // Settle, then more drinks before any money is on the bill: settle again bills it again
        $first = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame('25760000.00', $first['paid_amount']);
        $this->pay($first['uuid'], [['tender' => 'cash', 'amount' => '1000']])->assertOk();
        $this->eventDayLine($event, '1', '20000');
        // The bill carries money now: settle returns it as it is (the till pays it)
        $again = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame($first['uuid'], $again['uuid']);
    }

    public function test_refusals_and_tenant_isolation(): void
    {
        $event = $this->pricedEvent();
        // A hold (no deposit): neither started nor settled
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertConflict()->assertJsonPath('data.reason', 'invalid_transition');
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertConflict()->assertJsonPath('data.reason', 'invalid_transition');
        $this->withBearer($this->cashierToken)->postJson("/api/v1/pos/banquets/{$event->uuid}/settle")->assertStatus(428);
        // Guests and staff are the manager's
        $this->pos('PATCH', "/banquets/{$event->uuid}/guests", ['guests_actual' => 10])->assertForbidden();

        $foreignStore = $this->makeStore('Boshqa');
        $foreign = $this->pricedEvent(100, 100, '2027-01-20', [], $this->makeBranch($foreignStore, 'Boshqa filial'), false);
        $this->pos('GET', "/banquets/{$foreign->uuid}/settlement")->assertNotFound();
        $this->pos('POST', "/banquets/{$foreign->uuid}/settle")->assertNotFound();
    }

    public function test_staff_assigned_to_a_settled_event_get_its_event_rate(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $this->pos('PUT', "/banquets/{$event->uuid}/staff", ['user_ids' => [$this->waiter->id, $this->cashier->id]], $this->managerToken)->assertOk()
            ->assertJsonCount(2, 'data.staff');
        $foreignUser = $this->makeStaff(\App\Enums\UserRole::WAITER, null, $this->makeStore('Boshqa'));
        $this->pos('PUT', "/banquets/{$event->uuid}/staff", ['user_ids' => [$foreignUser->id]], $this->managerToken)->assertUnprocessable();

        $bill = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => $bill['due_amount']]])->assertOk();
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);

        $metrics = app(StaffPayMetrics::class)->collect($this->branch->fresh(), '2027-01-20', '2027-01-20');
        $this->assertSame(1, $metrics['days'][$this->waiter->id]['2027-01-20']['events']['banquet'] ?? null);
        $this->assertSame(1, $metrics['days'][$this->cashier->id]['2027-01-20']['events']['banquet'] ?? null);

        // An event_rate rule counts the `banquet` kind
        $component = \App\Domain\Pos\StaffPay\PayComponents::normalize([['type' => 'event_rate', 'amount' => '200000', 'event_types' => ['banquet']]]);
        $this->assertSame(['banquet'], $component[0]['event_types']);
        $this->assertContains('banquet', \App\Domain\Pos\StaffPay\PayComponents::countedEventTypes(['type' => 'event_rate']));
    }
}
