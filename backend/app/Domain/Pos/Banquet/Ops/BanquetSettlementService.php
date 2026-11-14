<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Banquet\BanquetEventChanged;
use App\Domain\Pos\Banquet\BanquetEventService;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetException;
use App\Domain\Pos\Banquet\BanquetLineKind;
use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Banquet\Payments\BanquetEntryType;
use App\Domain\Pos\Banquet\Payments\BanquetFiscalLines;
use App\Domain\Pos\Banquet\Payments\BanquetPaymentService;
use App\Domain\Pos\Billing\BillableOrder;
use App\Domain\Pos\Billing\BillService;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\OrderCloser;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Booking\ApprovalGate;
use App\Domain\Pos\Booking\BookingDepositService;
use App\Domain\Pos\Booking\BookingException;
use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Orders\PosChargesCalculator;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\ShiftStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\BanquetEvent;
use App\Models\BanquetEventLine;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\BanquetSettlement;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * P6-54 (E5): the final settlement of a banquet event — ONE sale receipt at the full event total with the advances as
 * its prepayment part.
 *
 * The rule (document, owner decision to confirm):
 *
 *   billed guests = max(actual, guaranteed, minimum) — never below the guaranteed guests, more when more came
 *   package       = the package line's (frozen) unit price × quantity × billed guests
 *   hall rent     = each hall's rent line as it stands
 *   extras        = per event: price × quantity; per person: price × quantity × billed guests
 *   event-day     = the event's order (its lines less the discount, with the branch's service charge on those lines
 *                   if the branch charges one — the package itself carries no service charge; the place fee is waived)
 *   total         = package + hall rent + extras + event-day
 *
 * settle(): the package, hall rent and extras become the order's `banquet` charge (orders.banquet_amount with its
 * parts, BanquetFiscal); the schedule's unpaid rows go (the rest is paid on this bill); the order is billed as one
 * bill; the paid advances (till payments and bank transfers) are offset on it (PosTender::PREPAYMENT through
 * PaymentService, so the money and the receipt follow the usual path); what is left is paid at the till by any tender
 * on POST /pos/bills/{uuid}/payments. The sale receipt is issued once the bill is covered; the order closes; the event
 * becomes `settled` and its halls are free (OrderStatusUpdated → markSettled). Advances over the total: the bill is
 * paid by them in full and the difference is refunded against the advance receipts, newest first (refundExcess: cash
 * from the open shift's drawer with pos.refund or a manager's approval, card/QR the way they came, a bank transfer
 * marked "to be returned by transfer"); without the approval it stays `pending` for the manager. A split final bill is
 * out of scope.
 *
 * Retrying settle() before any money is on the bill recomputes it (more guests, more drinks); once the bill carries a
 * payment it stays as it is (the till goes on paying it).
 *
 * Review P6 wave 5: only settle() bills the event's order while it is in progress (BanquetBillingGuard, C1), and it
 * decides and bills under the event's and the settlement's row locks (C8). Lines added after only the advances were
 * offset (no other money, no receipt) are billed again onto the same settlement — the offset goes back and is made
 * again on the new bill; once the settlement's receipt exists, settle() bills what came since as a bill (and a receipt)
 * of its own, listed in the settlement's `extra_bills` (C10: one receipt per settlement, a later add-on is its own).
 * An order a direct payment closed before the guard (an event stuck with its advances) is reopened for a settlement
 * bill that carries only the banquet charge — that legacy path has two sale receipts.
 */
final class BanquetSettlementService
{
    public const PREVIEW = 'array{event: array{uuid: string, status: string, version: int, order_uuid: string|null}, guests: array{guaranteed: int, minimum: int, actual: int|null, billed: int}, lines: list<array{uuid: string, kind: string, name: string, unit_price: string, quantity: string, persons: int|null, amount: string}>, package_total: string, hall_total: string, extras_total: string, orders: array{lines: string, discount: string, service_charge: string, other_charges: string, total: string}, total: string, advances: array{total: string, applied: string, rows: list<array{uuid: string, tender: string, amount: string, paid_at: string|null}>}, due: string, excess: string, settlement: array{uuid: string, status: string, bill_uuid: string|null, applied_amount: string, excess_amount: string, excess_status: string, settled_at: string|null, extra_bills: list<array{uuid: string, status: string, total: string, paid_amount: string}>, extra_bills_notice: string|null}|null, warnings: list<' . BanquetWarnings::WARNING . '>}';

    public function __construct(
        private readonly BanquetEventService $events,
        private readonly BanquetEventDay $day,
        private readonly PosChargesCalculator $charges,
        private readonly BillService $bills,
        private readonly AuditLogger $audit,
        private readonly BanquetWarnings $warnings,
    ) {}

    /**
     * What the final bill is (now, or as settled).
     *
     * @return array<string, mixed> PREVIEW
     */
    public function preview(BanquetEvent $event): array
    {
        $event = BanquetEvent::whereKey($event->id)->firstOrFail();
        $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->first();
        $parts = $this->compute($event);
        $order = $event->order_id !== null ? Order::find($event->order_id) : null;
        $advances = BanquetAdvances::of($event);
        $advanceTotal = Money::sum(...array_column($advances, 'amount'));

        return [
            'event' => ['uuid' => (string) $event->uuid, 'status' => $event->status->value, 'version' => (int) $event->version, 'order_uuid' => $order?->uuid],
            'guests' => $parts['guests'],
            'lines' => $parts['lines'],
            'package_total' => $parts['package']->toString(),
            'hall_total' => $parts['hall']->toString(),
            'extras_total' => $parts['extras']->toString(),
            'orders' => $parts['orders'],
            'total' => $parts['total']->toString(),
            'advances' => [
                'total' => $advanceTotal->toString(),
                'applied' => Money::of($settlement->applied_amount ?? '0')->toString(),
                'rows' => array_map(fn (array $row) => ['uuid' => $row['uuid'], 'tender' => $row['tender'], 'amount' => $row['amount']->toString(), 'paid_at' => $row['paid_at']], $advances),
            ],
            'due' => Money::max(Money::zero(), $parts['total']->minus($advanceTotal))->toString(),
            'excess' => Money::max(Money::zero(), $advanceTotal->minus($parts['total']))->toString(),
            'settlement' => $settlement !== null ? self::settlementView($settlement) : null,
            'warnings' => $this->warnings->of($event),
        ];
    }

    /**
     * @return array{guests: array{guaranteed: int, minimum: int, actual: int|null, billed: int}, lines: list<array{uuid: string, kind: string, name: string, unit_price: string, quantity: string, persons: int|null, amount: string}>, package: Money, hall: Money, extras: Money, orders: array{lines: string, discount: string, service_charge: string, other_charges: string, total: string}, total: Money}
     */
    public function compute(BanquetEvent $event): array
    {
        $billed = max((int) ($event->guests_actual ?? 0), (int) $event->guests_guaranteed, (int) $event->guests_minimum);
        $lines = [];
        $sums = ['package' => Money::zero(), 'hall' => Money::zero(), 'extras' => Money::zero()];
        foreach (BanquetEventLine::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get() as $line) {
            if ($line->kind === BanquetLineKind::ORDER) {
                continue; // the event-day order is read from the order itself
            }
            $persons = $line->persons !== null ? $billed : null;
            $amount = $persons !== null
                ? Money::of($line->unit_price)->times($line->quantity)->times($persons)
                : Money::of($line->amount);
            $key = match ($line->kind) {
                BanquetLineKind::PACKAGE => 'package',
                BanquetLineKind::HALL_RENT => 'hall',
                default => 'extras',
            };
            $sums[$key] = $sums[$key]->plus($amount);
            $lines[] = [
                'uuid' => (string) $line->uuid,
                'kind' => $line->kind->value,
                'name' => (string) $line->name,
                'unit_price' => Money::of($line->unit_price)->toString(),
                'quantity' => Quantity::of($line->quantity)->toString(),
                'persons' => $persons,
                'amount' => $amount->toString(),
            ];
        }

        $order = $event->order_id !== null ? Order::find($event->order_id) : null;
        $orderTotal = $order !== null ? Money::max(Money::zero(), Money::of($order->total_price)->minus($order->getAttribute('banquet_amount') ?? '0')) : Money::zero();
        $lineSum = Money::of($order->subtotal ?? '0');
        $discount = Money::of($order->discount ?? '0');
        $service = Money::of($order->service_charge_amount ?? '0');

        return [
            'guests' => ['guaranteed' => (int) $event->guests_guaranteed, 'minimum' => (int) $event->guests_minimum, 'actual' => $event->guests_actual !== null ? (int) $event->guests_actual : null, 'billed' => $billed],
            'lines' => $lines,
            'package' => $sums['package'],
            'hall' => $sums['hall'],
            'extras' => $sums['extras'],
            'orders' => [
                'lines' => $lineSum->toString(),
                'discount' => $discount->toString(),
                'service_charge' => $service->toString(),
                'other_charges' => Money::max(Money::zero(), $orderTotal->minus($lineSum)->plus($discount)->minus($service))->toString(),
                'total' => $orderTotal->toString(),
            ],
            'total' => $sums['package']->plus($sums['hall'])->plus($sums['extras'])->plus($orderTotal),
        ];
    }

    /**
     * @return array{settlement: BanquetSettlement, bill: OrderBill}
     */
    public function settle(BanquetEvent $event, User $actor, ?string $shiftUuid, ?Device $device, ?int $version = null, ?string $approvalToken = null): array
    {
        $event = BanquetEvent::whereKey($event->id)->firstOrFail();
        if ($event->status === BanquetEventStatus::CONFIRMED) {
            // Settled straight from confirmed (nothing ordered on the day): the tab opens now
            $event = $this->day->start($event, $actor, $device?->id, $version);
            $version = null;
        }
        if ($event->status === BanquetEventStatus::SETTLED) {
            $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->firstOrFail();

            return ['settlement' => $settlement, 'bill' => OrderBill::findOrFail($settlement->bill_id)];
        }
        if ($event->status !== BanquetEventStatus::IN_PROGRESS || $event->order_id === null) {
            throw new BanquetException("Tadbir \"{$event->status->label()}\" holatida: yakuniy hisob qilib bo'lmaydi.", 'invalid_transition', 409, ['status' => $event->status->value]);
        }

        // Review P6 wave 5 (C8): decided under the order's, the event's and the settlement's row locks, the bill made
        // inside them, so two tills settling at once make one bill. BanquetBillingGuard (C1): only this bills the event's order.
        [$bill, $billed, $closedOrder] = BanquetBillingGuard::settling(fn () => DB::transaction(function () use ($event, $actor, $version, $device) {
            // One lock order with a payment of its bill (review C9: shift → order → event): the event's order first, then
            // the event and its settlement, then the bill (the order id never changes once the event started)
            $order = Order::whereKey($event->order_id)->lockForUpdate()->firstOrFail();
            $event = $this->events->lockFresh($event, null);
            $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->lockForUpdate()->first();
            if ($event->status === BanquetEventStatus::SETTLED && $settlement?->bill_id !== null) {
                return [OrderBill::findOrFail($settlement->bill_id), false, null]; // another till settled it meanwhile
            }
            if ($event->status !== BanquetEventStatus::IN_PROGRESS || $event->order_id === null || (int) $event->order_id !== (int) $order->id) {
                throw new BanquetException("Tadbir \"{$event->status->label()}\" holatida: yakuniy hisob qilib bo'lmaydi.", 'invalid_transition', 409, ['status' => $event->status->value]);
            }
            $current = $settlement?->bill_id !== null ? OrderBill::whereKey($settlement->bill_id)->lockForUpdate()->first() : null;

            if ($current !== null && $current->status !== BillStatus::VOID) {
                // C10: the settlement's sale receipt exists — what the order got since is a bill (and a receipt) of its own
                if ($current->status === BillStatus::PAID) {
                    if ($order->closed_at !== null) {
                        return [$current, false, $order];
                    }
                    if (!BillableOrder::of($order)->isEmpty()) {
                        $extra = $this->bills->create($actor, $order, 'single', 1, [], (int) $order->getAttribute('version'), $device)[0];
                        $this->audit->record('banquet.settlement_extra_bill', $event, [], [], ['bill' => $extra->uuid, 'total' => Money::of($extra->total)->toString(), 'settlement_bill' => $current->uuid], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);

                        return [$extra, false, null];
                    }
                    $open = OrderBill::where('order_id', $order->id)->whereNotIn('status', [BillStatus::VOID->value, BillStatus::PAID->value])->orderByDesc('number')->first();

                    return [$open ?? $current, false, null];
                }
                $taken = Payment::where('bill_id', $current->id)->where('status', 'paid')->get();
                if ($taken->isNotEmpty()) {
                    // C10: lines added after the advances were offset and before any receipt (only the offset on the
                    // bill): the offset goes back to the advances and the order is billed again; otherwise the till
                    // goes on paying this bill
                    $onlyOffset = $taken->every(fn (Payment $payment) => $payment->provider === PosTender::PREPAYMENT->value);
                    $covered = Money::sum(...$taken->pluck('amount')->all())->isGreaterThanOrEqualTo($current->total);
                    if (!$onlyOffset || $covered || $current->basis === BillableOrder::fingerprint($order)) {
                        return [$current, false, null];
                    }
                    $this->releaseOffset($event, $order, $current, $taken, $actor);
                }
            }

            // Billing (again) is a change of the event: at the version the terminal holds
            $event = $this->events->lockFresh($event, $version);
            // C1: an order a direct payment closed before the settlement (the event stuck with its advances) is opened
            // again for the settlement's bill: it carries only the banquet charge, the advances its prepayment
            $this->reopenForSettlement($event, $order, $actor);
            $order = BillService::lockOpenOrder(Order::findOrFail($event->order_id), null);

            // The event-day order as it stands, without a banquet charge from an earlier attempt
            $order->forceFill(['banquet_amount' => '0.00', 'banquet_lines' => null]);
            $this->charges->recalculate($order);
            $parts = $this->compute($event);
            $banquet = $parts['package']->plus($parts['hall'])->plus($parts['extras']);
            $order->forceFill(['banquet_amount' => $banquet->toString(), 'banquet_lines' => self::receiptLines($parts)]);
            $this->charges->recalculate($order);
            OrderCloser::bumpVersion($order);
            $order->save();

            // The rest is paid on the final bill: the schedule's unpaid rows go
            BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::DUE->value)->delete();

            $before = ['total' => Money::of($event->total)->toString(), 'guests_actual' => $event->guests_actual];
            $event->forceFill([
                'package_total' => $parts['package']->toString(),
                'hall_total' => $parts['hall']->toString(),
                'extras_total' => $parts['extras']->toString(),
                'orders_total' => Money::of($parts['orders']['total'])->toString(),
                'total' => $parts['total']->toString(),
                'version' => (int) $event->version + 1,
            ])->save();

            $advances = BanquetAdvances::total($event);
            $settlement ??= new BanquetSettlement();
            $settlement->forceFill([
                'banquet_event_id' => $event->id,
                'store_id' => $event->store_id,
                'branch_id' => $event->branch_id,
                'order_id' => $order->id,
                'guests_billed' => $parts['guests']['billed'],
                'package_total' => $parts['package']->toString(),
                'hall_total' => $parts['hall']->toString(),
                'extras_total' => $parts['extras']->toString(),
                'orders_total' => $parts['orders']['total'],
                'total' => $parts['total']->toString(),
                'advances_total' => $advances->toString(),
                'excess_amount' => Money::max(Money::zero(), $advances->minus($parts['total']))->toString(),
                'excess_status' => $advances->isGreaterThan($parts['total']) ? 'pending' : 'none',
                'status' => BanquetSettlement::OPEN,
                'created_by' => $settlement->created_by ?? $actor->id,
            ]);
            $settlement->save();

            $this->audit->record('banquet.settlement_billed', $event, $before, ['total' => $parts['total']->toString(), 'guests_billed' => $parts['guests']['billed']], [
                'package' => $parts['package']->toString(),
                'hall' => $parts['hall']->toString(),
                'extras' => $parts['extras']->toString(),
                'orders' => $parts['orders']['total'],
                'advances' => $advances->toString(),
            ], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);

            $bills = $this->bills->create($actor, $order, 'single', 1, [], (int) $order->getAttribute('version'), $device);
            $bill = $bills[0] ?? throw new BanquetException("Yakuniy hisob uchun schyot tuzilmadi.", 'nothing_to_bill', 409);
            $settlement->forceFill(['bill_id' => $bill->id, 'bill_uuid' => $bill->uuid])->save();

            return [$bill, true, null];
        }));
        if ($billed) {
            event(new BanquetEventChanged(BanquetEvent::findOrFail($event->id), 'settlement_billed', false));
        }
        if ($closedOrder !== null) {
            // Paid and closed, the event not marked yet (the listener failed): settle it now
            $this->markSettled($closedOrder);
        }
        $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->firstOrFail();
        if ($bill->id !== $settlement->bill_id) {
            // A later add-on (C10): its own bill, paid at the till as any bill; the advances are on the settlement's
            return ['settlement' => $settlement, 'bill' => $bill->refresh()];
        }

        /** @var OrderBill $bill */
        $bill = OrderBill::findOrFail($settlement->bill_id);
        $settlement->refresh();

        // The advances: offset once on the final bill (PaymentService: the shift, the audit, the receipt when covered)
        $hasPrepayment = Payment::where('bill_id', $bill->id)->where('provider', PosTender::PREPAYMENT->value)->where('status', 'paid')->exists();
        if (!$hasPrepayment && $bill->status->isPayable()) {
            $order = Order::findOrFail($bill->order_id);
            $paid = Money::sum(...Payment::where('bill_id', $bill->id)->where('status', 'paid')->pluck('amount')->all());
            $offset = Money::min(app(AdvanceLedger::class)->available($order), Money::of($bill->total)->minus($paid));
            if ($offset->isPositive()) {
                try {
                    $bill = app(PaymentService::class)->pay($actor, $bill, [['tender' => PosTender::PREPAYMENT->value, 'amount' => $offset->toString()]], null, $shiftUuid, null, $device);
                } catch (PosActionException $e) {
                    // Another till offset them on this bill a moment earlier (C8): once is enough
                    if (!in_array($e->reason, ['tender_once_per_bill', 'bill_paid', 'bill_awaiting_fiscal'], true)) {
                        throw $e;
                    }
                }
            }
        }

        $settlement->refresh();
        if ($settlement->excess_status === 'pending') {
            try {
                $this->refundExcess($event, $actor, $approvalToken, $device?->id);
            } catch (BookingException $e) {
                // No refund right now (no approval, no open shift): it stays pending for the manager
                if (!in_array($e->reason, ['approval_required', 'approval_invalid', 'refund_needs_shift'], true)) {
                    throw $e;
                }
            }
        }

        return ['settlement' => $settlement->refresh(), 'bill' => $bill->refresh()];
    }

    /**
     * C10: the settlement bill carries only the advances' offset (no other money, no receipt) and the order changed
     * since: the offset goes back to the advances (AdvanceLedger::reverse, the payment `cancelled`) and the bill is
     * voided, so the order is billed again as ONE settlement bill with the advances offset once more.
     *
     * @param  \Illuminate\Support\Collection<int, Payment>  $taken
     */
    private function releaseOffset(BanquetEvent $event, Order $order, OrderBill $bill, \Illuminate\Support\Collection $taken, User $actor): void
    {
        foreach ($taken as $payment) {
            app(AdvanceLedger::class)->reverse($order, Money::of($payment->amount), (string) $bill->uuid);
            $payment->forceFill(['status' => PaymentStatus::CANCELLED->value])->save();
        }
        $bill->forceFill(['status' => BillStatus::VOID->value, 'voided_at' => now(), 'fiscal_status' => null])->save();
        $this->audit->record('banquet.settlement_rebilled', $event, [], [], [
            'bill' => $bill->uuid,
            'released' => Money::sum(...$taken->pluck('amount')->all())->toString(),
        ], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);
    }

    /**
     * C1 (an event stuck before BanquetBillingGuard): its order was closed by a direct payment while the event was in
     * progress, so it was never settled and its advances never offset. The order is opened again (ready, unpaid) for
     * the settlement's bill, which carries only the banquet charge (the paid bills keep their lines) — this legacy path
     * has two sale receipts: the earlier bill's and the settlement's. A cancelled or refunded order is not reopened.
     */
    private function reopenForSettlement(BanquetEvent $event, Order $order, User $actor): void
    {
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        if ($order->closed_at === null || $order->status !== OrderStatus::DELIVERED) {
            return; // open, or not reopenable (BillService::lockOpenOrder refuses it: order_closed)
        }
        $closedAt = $order->closed_at->toJSON();
        $order->forceFill(['status' => OrderStatus::READY->value, 'closed_at' => null, 'payment_status' => PaymentStatus::PENDING->value]);
        OrderCloser::bumpVersion($order);
        $order->save();
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'user_id' => $actor->id,
            'from_status' => OrderStatus::DELIVERED->value,
            'status' => OrderStatus::READY->value,
            'notes' => 'Banket: yakuniy hisob uchun qayta ochildi',
        ]);
        $this->audit->record('banquet.order_reopened', $event, [], [], ['order' => $order->uuid, 'closed_at' => $closedAt], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);
    }

    /**
     * Gives back the advances paid over the event total, newest advance first, each part against its advance
     * receipt (REFUND). Nothing when there is no excess left.
     */
    public function refundExcess(BanquetEvent $event, User $actor, ?string $approvalToken, ?int $deviceId): BanquetSettlement
    {
        $entries = DB::transaction(function () use ($event, $actor, $approvalToken, $deviceId) {
            // Review C9 (H2): one lock order with a till payment — the terminal's open shift first, then the event
            $openShift = $deviceId !== null
                ? PosShift::where('device_id', $deviceId)->where('branch_id', $event->branch_id)->where('status', ShiftStatus::OPEN->value)->value('id')
                : null;
            $shiftId = $openShift !== null && PosShift::lockForMoney((int) $openShift)->isOpen() ? (int) $openShift : null;
            $event = BanquetEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $settlement = BanquetSettlement::where('banquet_event_id', $event->id)->lockForUpdate()->firstOrFail();
            $rows = BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::PAID->value)->orderByDesc('paid_at')->orderByDesc('id')->lockForUpdate()->get();
            $held = Money::sum(...$rows->map(fn (BanquetPayment $row) => Money::of($row->amount)->minus($row->refunded_amount)->minus($row->retained_amount))->all());
            // Only once the final bill is covered: what the advances hold beyond what it offset
            $bill = $settlement->bill_id !== null ? OrderBill::find($settlement->bill_id) : null;
            $covered = $bill !== null && Money::sum(...Payment::where('bill_id', $bill->id)->where('status', 'paid')->pluck('amount')->all())->isGreaterThanOrEqualTo($bill->total);
            if (!$covered) {
                return [];
            }
            $excess = $held->minus($settlement->applied_amount);
            if (!$excess->isPositive()) {
                $settlement->forceFill(['excess_status' => Money::of($settlement->excess_amount)->isPositive() ? 'refunded' : 'none'])->save();

                return [];
            }

            $branch = Branch::findOrFail($event->branch_id);
            $plan = [];
            $left = $excess;
            foreach ($rows as $row) {
                if (!$left->isPositive()) {
                    break;
                }
                $part = Money::min($left, Money::of($row->amount)->minus($row->refunded_amount)->minus($row->retained_amount));
                if ($part->isPositive()) {
                    $plan[] = [$row, $part];
                    $left = $left->minus($part);
                }
            }
            $needsCash = collect($plan)->contains(fn (array $item) => $item[0]->tender === 'cash');
            if ($needsCash && $shiftId === null) {
                throw new BookingException("Naqd pulni qaytarish uchun kassada ochiq smena kerak: ortiqcha zakladni kassada qaytaring.", 'refund_needs_shift', 409);
            }
            $nonTransfer = collect($plan)->contains(fn (array $item) => $item[0]->tender !== BanquetPaymentService::BANK_TRANSFER);
            $approver = $nonTransfer ? app(ApprovalGate::class)->approver($actor, 'pos.refund', $approvalToken, $deviceId, $event) : null;

            $entries = [];
            foreach ($plan as [$row, $part]) {
                $transfer = $row->tender === BanquetPaymentService::BANK_TRANSFER;
                $entries[] = BanquetPaymentEntry::create([
                    'store_id' => $event->store_id,
                    'branch_id' => $event->branch_id,
                    'banquet_event_id' => $event->id,
                    'banquet_payment_id' => $row->id,
                    'type' => BanquetEntryType::REFUND,
                    'amount' => $part->toString(),
                    'tender' => (string) $row->tender,
                    'card_type' => $row->card_type,
                    'transaction_id' => $row->transaction_id,
                    'business_date' => BusinessDay::of($branch),
                    'shift_id' => $transfer ? null : $shiftId,
                    'device_id' => $transfer ? null : $deviceId,
                    'user_id' => $actor->id,
                    'approved_by' => $approver,
                    'reason' => "Yakuniy hisob: zaklad tadbir summasidan ko'p",
                    'transfer_pending' => $transfer,
                    'original_receipt_uuid' => $row->fiscal_receipt_uuid,
                ]);
                $refunded = Money::of($row->refunded_amount)->plus($part);
                $row->forceFill([
                    'refunded_amount' => $refunded->toString(),
                    'status' => $refunded->plus($row->retained_amount)->isGreaterThanOrEqualTo($row->amount) ? BanquetPaymentStatus::REFUNDED : BanquetPaymentStatus::PAID,
                    'settled_at' => now(),
                ])->save();
            }
            $settlement->forceFill(['excess_status' => 'refunded'])->save();
            $this->audit->record('banquet.excess_refunded', $event, [], [], [
                'amount' => $excess->toString(),
                'approved_by' => $approver,
                'shift_id' => $shiftId,
                'rows' => array_map(fn (array $item) => ['payment_uuid' => $item[0]->uuid, 'tender' => $item[0]->tender, 'amount' => $item[1]->toString()], $plan),
            ], ['actor' => $actor, 'store_id' => (int) $event->store_id, 'branch_id' => (int) $event->branch_id]);

            return $entries;
        });

        $store = Store::findOrFail($event->store_id);
        foreach ($entries as $entry) {
            $this->refundReceipt($store, $entry);
        }

        return BanquetSettlement::where('banquet_event_id', $event->id)->firstOrFail();
    }

    /**
     * The event's order closed (its final bill paid and receipted): the event is settled and its halls released.
     */
    public function markSettled(Order $order): void
    {
        if ($order->closed_at === null) {
            return;
        }
        $event = BanquetEvent::where('order_id', $order->id)->where('status', BanquetEventStatus::IN_PROGRESS->value)->first();
        $settlement = $event !== null ? BanquetSettlement::where('banquet_event_id', $event->id)->first() : null;
        if ($event === null || $settlement === null || $settlement->status === BanquetSettlement::SETTLED) {
            return;
        }

        $this->events->transition($event, BanquetEventStatus::SETTLED, null, null, 'banquet.settled', function (BanquetEvent $event) use ($settlement, $order) {
            $event->settled_at = now();
            // C10: a later add-on (its own bill) is part of the event's orders: the event total follows what was billed
            $orders = Money::max(Money::zero(), Money::of($order->total_price)->minus($order->getAttribute('banquet_amount') ?? '0'));
            if (!$orders->equals($event->orders_total)) {
                $event->orders_total = $orders->toString();
                $event->total = Money::of($event->package_total)->plus($event->hall_total)->plus($event->extras_total)->plus($orders)->toString();
            }
            $settlement->forceFill(['status' => BanquetSettlement::SETTLED, 'settled_at' => now()])->save();
            BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::DUE->value)->delete();
        }, ['bill' => $settlement->bill_uuid, 'total' => Money::of($settlement->total)->toString(), 'applied' => Money::of($settlement->applied_amount)->toString()]);
    }

    /**
     * `extra_bills`: the event order's other bills (not void) — a later add-on billed after the settlement's receipt
     * (C10), or a bill paid before the settlement on an event stuck before the rule (C1) — each with its own receipt.
     *
     * @return array{uuid: string, status: string, bill_uuid: string|null, applied_amount: string, excess_amount: string, excess_status: string, settled_at: string|null, extra_bills: list<array{uuid: string, status: string, total: string, paid_amount: string}>, extra_bills_notice: string|null}
     */
    public static function settlementView(BanquetSettlement $settlement): array
    {
        $extra = OrderBill::where('order_id', $settlement->order_id)
            ->where('status', '!=', BillStatus::VOID->value)
            ->when($settlement->bill_id !== null, fn ($query) => $query->whereKeyNot($settlement->bill_id))
            ->orderBy('number')
            ->get();
        $paid = Payment::whereIn('bill_id', $extra->pluck('id'))->where('status', 'paid')->get(['bill_id', 'amount'])->groupBy('bill_id');

        return [
            'uuid' => (string) $settlement->uuid,
            'status' => (string) $settlement->status,
            'bill_uuid' => $settlement->bill_uuid,
            'applied_amount' => Money::of($settlement->applied_amount)->toString(),
            'excess_amount' => Money::of($settlement->excess_amount)->toString(),
            'excess_status' => (string) $settlement->excess_status,
            'settled_at' => $settlement->settled_at?->toJSON(),
            'extra_bills' => $extra->map(fn (OrderBill $bill) => [
                'uuid' => (string) $bill->uuid,
                'status' => $bill->status->value,
                'total' => Money::of($bill->total)->toString(),
                'paid_amount' => Money::sum(...$paid->get($bill->id, collect())->pluck('amount')->all())->toString(),
            ])->values()->all(),
            'extra_bills_notice' => $extra->isNotEmpty()
                ? "Yakuniy hisob bitta chek bilan yopiladi. Undan tashqari schyotlar (yakuniy hisob chekidan keyin qo'shilgan buyurtmalar) alohida to'lanadi va alohida chek bo'ladi."
                : null,
        ];
    }

    /**
     * The order's `banquet_lines`: one receipt line per package, hall and extra line (BanquetFiscal::LINE).
     *
     * @param  array{lines: list<array{uuid: string, kind: string, name: string, unit_price: string, quantity: string, persons: int|null, amount: string}>}  $parts
     * @return list<array{kind: string, name: string, quantity: string, unit: string, amount: string}>
     */
    private static function receiptLines(array $parts): array
    {
        $out = [];
        foreach ($parts['lines'] as $line) {
            if (!Money::of($line['amount'])->isPositive()) {
                continue;
            }
            $quantity = $line['persons'] !== null ? Quantity::of($line['quantity'])->times($line['persons']) : Quantity::of($line['quantity']);
            $out[] = [
                'kind' => $line['kind'],
                'name' => mb_substr($line['kind'] === BanquetLineKind::PACKAGE->value ? 'Banket paketi: ' . $line['name'] : $line['name'], 0, 255),
                'quantity' => ($quantity->isPositive() ? $quantity : Quantity::of(1))->toString(),
                'unit' => $line['persons'] !== null ? 'kishi' : ($line['kind'] === BanquetLineKind::EXTRA->value ? 'dona' : 'xizmat'),
                'amount' => $line['amount'],
            ];
        }

        return $out;
    }

    private function refundReceipt(Store $store, BanquetPaymentEntry $entry): void
    {
        if ($entry->tender === BanquetPaymentService::BANK_TRANSFER) {
            return; // no advance receipt was issued for a transfer
        }
        $row = BanquetPayment::find($entry->banquet_payment_id);
        $amount = Money::of($entry->amount);
        try {
            $line = app(BanquetFiscalLines::class)->advance($store, $amount, $row !== null ? $row->kind : BanquetPaymentKind::INTERIM);
        } catch (Throwable $e) {
            report($e);
            $entry->forceFill(['fiscal_status' => 'error'])->save();

            return;
        }
        BanquetPaymentService::issue($entry, new FiscalDocument(
            FiscalReceiptType::REFUND,
            (int) $entry->store_id,
            (int) $entry->branch_id,
            $entry->device_id !== null ? (int) $entry->device_id : null,
            [$line],
            [new FiscalPayment(BookingDepositService::formOf((string) $entry->tender), $amount, cardType: $entry->card_type, transactionId: $entry->transaction_id)],
            $amount,
            "banquet_payment:{$entry->uuid}:refund",
            $entry->original_receipt_uuid,
            BanquetPaymentService::FISCAL_SUBJECT,
            (int) $entry->id,
        ));
        if ($row !== null && $entry->fiscal_receipt_uuid !== null) {
            $row->forceFill(['settle_receipt_uuid' => $entry->fiscal_receipt_uuid, 'settle_fiscal_status' => $entry->fiscal_status])->save();
        }
    }
}
