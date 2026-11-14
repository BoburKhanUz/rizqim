<?php

namespace Tests\Feature\PosBanquetOps;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\Ops\BanquetAdvances;
use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Models\BanquetEvent;
use App\Models\BanquetSettlement;
use App\Models\FiscalReceipt;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Review P6 wave 5 (H1): settlement and event-day integrity.
 *
 *  - C1: an in-progress event's order is billed and paid only through the settlement (409 `banquet_settle_first`),
 *    ordinary orders untouched; an event already stuck (its order closed by a direct payment before this rule) is
 *    settled by an additional bill on that order carrying only the banquet charge with the advances as prepayment;
 *  - C8: settle decides under the event's and the settlement's row locks and makes its bill inside them;
 *  - C10: lines added after the settlement offset the advances are re-billed onto the same settlement while no sale
 *    receipt exists; after the receipt they are a bill (and a receipt) of their own, which the settlement lists;
 *  - C4 / C12: the event's `paid`, `refunded`, `outstanding` and `guests_billed` as the report and the settlement count;
 *  - C7: the report's `paid` is net of refunds (`received` gross), a billed event unpaid after its date is overdue.
 */
class BanquetSettlementIntegrityTest extends TestCase
{
    use PosBanquetOpsFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBanquetOps();
        $this->openShift('100000');
    }

    private function paidAndStarted(BanquetEvent $event): BanquetEvent
    {
        [$deposit, $interim] = $this->rows($event)->all();
        $this->payRow($event, $deposit, ['tender' => 'cash'])->assertCreated();
        $this->payRow($event, $interim, ['tender' => 'card', 'card_type' => 'humo', 'transaction_id' => 'RRN-1'])->assertCreated();
        $this->pos('POST', "/banquets/{$event->uuid}/start")->assertOk();

        return $event->refresh();
    }

    private function drinks(BanquetEvent $event, string $quantity = '3', string $total = '60000'): Order
    {
        $this->eventDayLine($event, $quantity, $total);
        $order = Order::findOrFail($event->refresh()->order_id);
        app(PosChargesCalculator::class)->recalculate($order);

        return $order->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function card(BanquetEvent $event): array
    {
        $card = $this->pos('GET', "/banquets/{$event->uuid}", [], $this->managerToken)->assertOk()->json('data');

        return $card['event'] ?? $card;
    }

    private function saleReceipts(): int
    {
        return FiscalReceipt::where('type', 'sale')->count();
    }

    /** C1: bill, split, pay and the waiter's print request on the event's order are refused until the settlement */
    public function test_the_event_day_order_is_billed_and_paid_only_through_the_settlement(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $order = $this->drinks($event);

        $this->bill($order)->assertConflict()->assertJsonPath('data.reason', 'banquet_settle_first')->assertJsonPath('data.event_uuid', $event->uuid);
        $this->bill($order, 'equal', ['parts' => 2])->assertConflict()->assertJsonPath('data.reason', 'banquet_settle_first');
        $this->assertSame(0, OrderBill::where('order_id', $order->id)->count());

        // A bill made before the rule (or by any other path) takes no money at the till either
        $legacy = OrderBill::create([
            'store_id' => $order->store_id, 'branch_id' => $order->branch_id, 'order_id' => $order->id, 'number' => 1, 'status' => 'open',
            'split_mode' => 'single', 'subtotal' => '60000', 'total' => '60000', 'basis' => \App\Domain\Pos\Billing\BillableOrder::fingerprint($order),
        ]);
        $this->pay($legacy->uuid, [['tender' => 'cash', 'amount' => '60000']])->assertConflict()->assertJsonPath('data.reason', 'banquet_settle_first');
        $this->assertSame(0, Payment::where('bill_id', $legacy->id)->count());
        $legacy->forceFill(['status' => 'void', 'voided_at' => now()])->save();

        // The settlement bills it all at once; that bill is paid at the till as usual
        $bill = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame('36860000.00', $bill['total']);
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => $bill['due_amount']]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);
        $this->assertSame(1, $this->saleReceipts());

        // An ordinary hall order of the same branch is billed and paid as before
        $other = $this->hallOrderWithLine();
        $plain = $this->bill($other)->assertCreated()->json('data.bills.0');
        $this->pay($plain['uuid'], [['tender' => 'cash', 'amount' => $plain['total']]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
    }

    /**
     * C1 (the scratch scenario): an event whose order was closed by a direct payment before the rule — settle bills the
     * banquet charge on that order (reopened) with the advances as prepayment. This legacy path has two sale receipts:
     * the drinks' and the settlement's (the banquet charge, its prepayment the advances).
     */
    public function test_event_day_bill_paid_directly_strands_the_event_and_its_advances(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $order = $this->drinks($event);

        // The stuck state as it arose before the rule: the drinks billed and paid straight on the event's order
        $event->forceFill(['status' => BanquetEventStatus::CONFIRMED->value])->save();
        $drinks = $this->bill($order)->assertCreated()->json('data.bills.0');
        $this->pay($drinks['uuid'], [['tender' => 'cash', 'amount' => $drinks['total']]])->assertOk();
        $event->forceFill(['status' => BanquetEventStatus::IN_PROGRESS->value])->save();
        $this->assertNotNull($order->refresh()->closed_at, 'order closed by the drinks bill');
        $this->assertSame(1, $this->saleReceipts());

        $settle = $this->pos('POST', "/banquets/{$event->uuid}/settle");
        $this->assertTrue($settle->isOk(), 'settle refused after the event-day bill was paid directly: ' . $settle->getContent());
        $bill = $settle->json('data.bill');
        // Only the banquet charge (the drinks are on their own paid bill), the advances offset on it
        $this->assertSame('36800000.00', $bill['total']);
        $this->assertSame('36800000.00', $bill['banquet_amount']);
        $this->assertSame('25760000.00', $bill['paid_amount']);
        $this->assertSame('11040000.00', $bill['due_amount']);
        $this->assertSame('36860000.00', $settle->json('data.settlement.total'));
        $this->assertNull($order->refresh()->closed_at);

        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => '11040000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $event->refresh();
        $this->assertSame(BanquetEventStatus::SETTLED, $event->status);
        $this->assertNotNull($order->refresh()->closed_at);
        $this->assertSame('25760000.00', BanquetSettlement::sole()->applied_amount);
        $this->assertSame('0.00', Money::max(Money::zero(), BanquetAdvances::total($event)->minus(BanquetSettlement::sole()->applied_amount))->toString());
        // ONE more sale receipt, for the banquet charge
        $this->assertSame(2, $this->saleReceipts());
        $sale = FiscalReceipt::where('type', 'sale')->where('subject_id', OrderBill::where('uuid', $bill['uuid'])->value('id'))->sole();
        $this->assertSame('36800000.00', Money::of($sale->total)->toString());
        $this->assertSame('0.00', $this->card($event)['outstanding']);
    }

    /** C8: the bill is made under the settlement's lock, inside the transaction that decided to make it */
    public function test_settle_decides_and_bills_under_the_settlement_lock(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        // A settlement row exists (an earlier attempt, no bill yet)
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk();
        $settlement = BanquetSettlement::sole();
        $billId = $settlement->bill_id;
        OrderBill::whereKey($billId)->update(['status' => 'void', 'voided_at' => now()]);
        Payment::where('bill_id', $billId)->delete();
        $settlement->forceFill(['applied_amount' => '0', 'applications' => null])->save();

        $base = DB::transactionLevel();
        $log = [];
        DB::listen(function ($query) use (&$log) {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'banquet_settlements') && str_contains($sql, 'for update')) {
                $log[] = ['lock', DB::transactionLevel()];
            }
            if (str_starts_with($sql, 'insert into "order_bills"')) {
                $log[] = ['bill', DB::transactionLevel()];
            }
        });
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk();

        $first = collect($log)->search(fn (array $entry) => $entry[0] === 'lock');
        $bill = collect($log)->search(fn (array $entry) => $entry[0] === 'bill');
        $this->assertNotFalse($first, 'the settlement row is locked');
        $this->assertNotFalse($bill);
        $this->assertLessThan($bill, $first, 'locked before the bill is made');
        // The bill insert runs inside settle's own transaction (and BillService's), not after it committed
        $this->assertGreaterThanOrEqual($base + 2, $log[$bill][1]);
        $this->assertSame(1, OrderBill::where('order_id', $event->refresh()->order_id)->where('status', '!=', 'void')->count());

        // Settling again (a second till) makes no second bill
        $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->assertJsonPath('data.bill.uuid', BanquetSettlement::sole()->bill_uuid);
        $this->assertSame(1, OrderBill::where('order_id', $event->order_id)->where('status', '!=', 'void')->count());
    }

    /**
     * C9 remainder: settle locks as a payment of its bill does (shift → order → event): the event's order before the
     * event and its settlement, the bill last — never the event before the order.
     */
    private function lockSequence(callable $work): array
    {
        $locks = [];
        DB::listen(function ($query) use (&$locks) {
            $sql = strtolower($query->sql);
            if (!preg_match('/for (no key )?update/', $sql)) {
                return;
            }
            foreach (['"pos_shifts"' => 'shift', '"orders"' => 'order', '"banquet_events"' => 'event', '"banquet_settlements"' => 'settlement', '"order_bills"' => 'bill'] as $table => $name) {
                if (str_contains($sql, 'from ' . $table)) {
                    $locks[] = $name;
                }
            }
        });
        $work();

        return $locks;
    }

    public function test_settle_locks_the_order_before_the_event_like_a_payment(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $locks = $this->lockSequence(fn () => $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk());
        $first = fn (string $name) => array_search($name, $locks, true);
        $this->assertNotFalse($first('order'));
        $this->assertLessThan($first('event'), $first('order'), 'settle: order before event — ' . implode(' > ', $locks));
        $this->assertLessThan($first('settlement'), $first('event'));
        // Every event lock of the settle transaction comes after an order lock; the payment of its bill does the same
        $bill = BanquetSettlement::sole()->bill_uuid;
        $locks = $this->lockSequence(fn () => $this->pay((string) $bill, [['tender' => 'cash', 'amount' => '11040000']])->assertOk());
        $this->assertLessThan(array_search('order', $locks, true), array_search('shift', $locks, true));
        $eventLock = array_search('event', $locks, true);
        if ($eventLock !== false) {
            $this->assertLessThan($eventLock, array_search('order', $locks, true), 'payment: order before event — ' . implode(' > ', $locks));
        }
    }

    /** A bank transfer voided as a mistake (H2: `transfer_void`) is not money received */
    public function test_a_voided_transfer_is_not_counted_as_paid(): void
    {
        $event = $this->pricedEvent(200, 150);
        $deposit = $this->rows($event)->first();
        $this->panel($this->owner, 'POST', "/pos/banquets/{$event->uuid}/payments/{$deposit->uuid}/bank-transfer", ['reference' => 'PP-1'])->assertCreated();
        $this->assertSame(['11040000.00', '25760000.00'], [$this->card($event)['paid'], $this->card($event)['outstanding']]);
        $entry = \App\Models\BanquetPaymentEntry::where('banquet_event_id', $event->id)->sole();
        $this->panel($this->owner, 'DELETE', "/pos/banquets/{$event->uuid}/payments/transfers/{$entry->uuid}?reason=Xato+yozildi")->assertOk();
        $this->assertSame('transfer_void', $entry->refresh()->type->value);

        $card = $this->card($event);
        $this->assertSame(['0.00', '0.00', '36800000.00'], [$card['paid'], $card['refunded'], $card['outstanding']]);
        $row = collect($this->panel($this->owner, 'GET', '/owner/pos/banquets/report?from=2027-01-01&to=2027-01-31')->assertOk()->json('data.rows'))->firstWhere('uuid', $event->uuid);
        $this->assertSame(['0.00', '0.00', '36800000.00'], [$row['received'], $row['paid'], $row['outstanding']]);
    }

    /** C10 a: drinks after the advances were offset, no receipt yet — settle again re-bills the same settlement */
    public function test_lines_added_before_the_sale_receipt_are_rebilled_onto_the_same_settlement(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $first = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->assertSame('25760000.00', $first['paid_amount']);
        $this->drinks($event);

        $again = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data');
        $this->assertNotSame($first['uuid'], $again['bill']['uuid']);
        $this->assertSame('void', OrderBill::where('uuid', $first['uuid'])->sole()->status->value);
        $this->assertSame('36860000.00', $again['bill']['total']);
        $this->assertSame('25760000.00', $again['bill']['paid_amount']);
        $this->assertSame('11100000.00', $again['bill']['due_amount']);
        $settlement = BanquetSettlement::sole();
        $this->assertSame($again['bill']['uuid'], $settlement->bill_uuid);
        $this->assertSame('25760000.00', $settlement->applied_amount);
        $this->assertSame(0, Payment::where('bill_id', OrderBill::where('uuid', $first['uuid'])->value('id'))->where('status', 'paid')->count());

        $this->pay($again['bill']['uuid'], [['tender' => 'cash', 'amount' => '11100000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);
        $this->assertSame(1, $this->saleReceipts());
        $this->assertSame('36860000.00', Money::of(FiscalReceipt::where('type', 'sale')->sole()->total)->toString());
    }

    /** C10 b: drinks the settlement bill does not carry once its sale receipt exists are a bill of their own */
    public function test_lines_added_after_the_sale_receipt_become_their_own_bill(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $first = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        $this->drinks($event);
        // The till pays the settlement bill as it is: its receipt is issued, the drinks are still to bill
        $this->pay($first['uuid'], [['tender' => 'cash', 'amount' => '11040000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::IN_PROGRESS, $event->refresh()->status);
        $this->assertSame(1, $this->saleReceipts());

        $again = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data');
        $this->assertNotSame($first['uuid'], $again['bill']['uuid']);
        $this->assertSame('60000.00', $again['bill']['total']);
        $this->assertSame('0.00', $again['bill']['paid_amount']);
        $this->assertSame($first['uuid'], $again['settlement']['settlement']['bill_uuid']);
        $this->assertSame([['uuid' => $again['bill']['uuid'], 'status' => 'open', 'total' => '60000.00', 'paid_amount' => '0.00']], $again['settlement']['settlement']['extra_bills']);
        $this->assertNotNull($again['settlement']['settlement']['extra_bills_notice']);

        $this->pay($again['bill']['uuid'], [['tender' => 'cash', 'amount' => '60000']])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);
        $this->assertSame(2, $this->saleReceipts());
        $card = $this->card($event);
        // The event total follows the add-on (its orders as billed)
        $this->assertSame(['36860000.00', '36860000.00', '0.00'], [$card['total'], $card['paid'], $card['outstanding']]);
    }

    /** C4: a settled, fully paid event shows no remainder; the figures count the final bill and the refunds */
    public function test_settled_event_still_shows_the_remainder_as_outstanding(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $card = $this->card($event);
        $this->assertSame(['36800000.00', '25760000.00', '25760000.00', '0.00', '11040000.00'], [$card['total'], $card['paid_total'], $card['paid'], $card['refunded'], $card['outstanding']]);

        $bill = $this->pos('POST', "/banquets/{$event->uuid}/settle")->assertOk()->json('data.bill');
        // Billed: the advances offset are not counted twice
        $this->assertSame(['25760000.00', '11040000.00'], [$this->card($event)['paid'], $this->card($event)['outstanding']]);
        $this->pay($bill['uuid'], [['tender' => 'cash', 'amount' => $bill['due_amount']]])->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $this->assertSame(BanquetEventStatus::SETTLED, $event->refresh()->status);

        $card = $this->card($event);
        $list = collect($this->pos('GET', '/banquets?from=2027-01-01&to=2027-01-31', [], $this->managerToken)->assertOk()->json('data.events'))->firstWhere('uuid', $event->uuid);
        $this->assertSame('0.00', $card['outstanding'], 'a settled, fully paid event shows a remainder (Qoldiq) on the till and panel');
        $this->assertSame('0.00', $list['outstanding']);
        $this->assertSame(['36800000.00', '36800000.00', '0.00'], [$card['paid'], $list['paid'], $list['refunded']]);
    }

    /** C4: advances over the total refunded — paid is net; a cancelled event owes nothing */
    public function test_refunds_are_netted_and_a_cancelled_event_owes_nothing(): void
    {
        $over = $this->pricedEvent(100, 100, '2027-01-20', [], null, false); // 20 000 000
        foreach ($this->rows($over) as $row) {
            $this->payRow($over, $row, ['tender' => 'cash'])->assertCreated();
        }
        $over->refresh()->forceFill(['guests_guaranteed' => 80, 'guests_minimum' => 80])->save();
        $this->pos('POST', "/banquets/{$over->uuid}/start")->assertOk();
        $this->pos('POST', "/banquets/{$over->uuid}/settle")->assertOk()->assertJsonPath('data.settlement.settlement.excess_status', 'pending');
        // The approvals' 60 seconds are read in the app's (UTC) clock: "now" as a UTC instant from here on
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 05:00:00', 'UTC'));
        $this->pos('POST', "/banquets/{$over->uuid}/settlement/refund-excess", ['approval_token' => $this->approval('pos.refund')])->assertOk()
            ->assertJsonPath('data.settlement.excess_status', 'refunded');
        $card = $this->card($over);
        $this->assertSame(['settled', '17000000.00', '17000000.00', '3000000.00', '0.00'], [$card['status'], $card['total'], $card['paid'], $card['refunded'], $card['outstanding']]);
        $this->assertSame('20000000.00', $card['paid_total']); // the column: money received on the schedule

        $cancelled = $this->pricedEvent(200, 150, '2027-01-21');
        $this->payRow($cancelled, $this->rows($cancelled)->first(), ['tender' => 'card', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-5'])->assertCreated();
        $this->pos('POST', "/banquets/{$cancelled->uuid}/cancellation", ['reason' => "Mijoz to'yni ko'chirdi"], $this->managerToken)->assertOk();
        $card = $this->card($cancelled);
        $this->assertSame(['cancelled', '0.00', '11040000.00', '0.00'], [$card['status'], $card['paid'], $card['refunded'], $card['outstanding']]);
    }

    /** C12: more guests than guaranteed are billed — the card says so as the settlement does */
    public function test_guests_billed_counts_the_guests_who_came(): void
    {
        $event = $this->paidAndStarted($this->pricedEvent(200, 150));
        $this->assertSame(200, $this->card($event)['guests_billed']);
        $this->pos('PATCH', "/banquets/{$event->uuid}/guests", ['guests_actual' => 230], $this->managerToken)->assertOk()
            ->assertJsonPath('data.settlement.guests.billed', 230);
        $this->assertSame(230, $this->card($event)['guests_billed']);
        $list = collect($this->pos('GET', '/banquets?from=2027-01-01&to=2027-01-31', [], $this->managerToken)->assertOk()->json('data.events'))->firstWhere('uuid', $event->uuid);
        $this->assertSame(230, $list['guests_billed']);
    }

    /** C7: the report's paid is net of refunds (received gross); a billed event unpaid after its date is overdue */
    public function test_report_nets_refunds_and_flags_an_unpaid_final_bill_after_the_event(): void
    {
        $cancelled = $this->pricedEvent(200, 150, '2027-01-21');
        $this->payRow($cancelled, $this->rows($cancelled)->first(), ['tender' => 'card', 'card_type' => 'uzcard', 'transaction_id' => 'RRN-5'])->assertCreated();
        $this->pos('POST', "/banquets/{$cancelled->uuid}/cancellation", ['reason' => "Mijoz to'yni ko'chirdi"], $this->managerToken)->assertOk();

        $billed = $this->paidAndStarted($this->pricedEvent(200, 150, '2027-01-20'));
        $this->pos('POST', "/banquets/{$billed->uuid}/settle")->assertOk();

        $report = fn () => $this->panel($this->owner, 'GET', '/owner/pos/banquets/report?from=2027-01-01&to=2027-01-31')->assertOk()->json('data');
        $rows = collect($report()['rows'])->keyBy('uuid');
        $this->assertSame(['11040000.00', '11040000.00', '0.00', '0.00'], [$rows[$cancelled->uuid]['received'], $rows[$cancelled->uuid]['refunded'], $rows[$cancelled->uuid]['paid'], $rows[$cancelled->uuid]['outstanding']]);
        $this->assertSame(['25760000.00', '25760000.00', '11040000.00', '0.00'], [$rows[$billed->uuid]['received'], $rows[$billed->uuid]['paid'], $rows[$billed->uuid]['outstanding'], $rows[$billed->uuid]['overdue']]);
        $this->assertSame(['36800000.00', '25760000.00', '11040000.00'], [$report()['received'], $report()['paid'], $report()['refunded']]);

        // Two days after the event the final bill is still unpaid: overdue
        $this->travelTo(\Illuminate\Support\Carbon::parse('2027-01-22 10:00', 'Asia/Tashkent'));
        $after = $report();
        $row = collect($after['rows'])->firstWhere('uuid', $billed->uuid);
        $this->assertSame('11040000.00', $row['overdue']);
        $this->assertSame(['count' => 1, 'amount' => '11040000.00'], $after['overdue']);

        // The XLSX carries the received column
        $xlsx = $this->panel($this->owner, 'GET', '/owner/pos/banquets/export?from=2027-01-01&to=2027-01-31');
        $xlsx->assertOk();
        $reader = new \OpenSpout\Reader\XLSX\Reader();
        $file = $xlsx->baseResponse;
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $file);
        $reader->open($file->getFile()->getPathname());
        $sheet = iterator_to_array($reader->getSheetIterator(), false)[0];
        $header = null;
        foreach ($sheet->getRowIterator() as $line) {
            $header = $line->toArray();
            break;
        }
        $reader->close();
        $this->assertSame(['Jami', 'Kelib tushgan', "To'langan", 'Qolgan'], array_slice((array) $header, 8, 4));
    }

    private function hallOrderWithLine(): Order
    {
        $order = app(\App\Domain\Pos\Orders\PosOrderService::class)->openOrder($this->branch, \App\Enums\OrderChannel::POS_HALL, null, 0, null, null, $this->cashier, []);
        \App\Models\OrderItem::create([
            'order_id' => $order->id, 'product_variant_id' => $this->drink->id, 'product_name' => 'Cola', 'quantity' => '1',
            'unit' => 'piece', 'unit_price' => '20000', 'total_price' => '20000',
        ]);
        app(PosChargesCalculator::class)->recalculate($order->refresh());

        return $order->refresh();
    }
}
