<?php

namespace App\Domain\Pos\Banquet\Ops;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\Payments\BanquetEntryType;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Contracts\PosTender;
use App\Models\BanquetEvent;
use App\Models\BanquetPaymentEntry;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Support\Money;

/**
 * Review P6 wave 5 (C4, C7): an event's money as the till, the panel and the report count it.
 *
 *   received    = the schedule's payments and bank transfers (banquet_payment_entries `payment`, `transfer`) + what the
 *                 till took on the bills of the event's order (every tender but `prepayment`: the advances' offset is
 *                 the same money, never counted twice)
 *   refunded    = the refunds (cancellation, advances over the total)
 *   retained    = what a cancellation kept
 *   paid        = received − refunded (money the restaurant holds for the event)
 *   outstanding = total − paid, never below zero; zero for a settled or cancelled event
 *
 * banquet_events.paid_total stays what it is: the money received on the schedule (P6-53), never reduced.
 */
final class BanquetFigures
{
    /**
     * @param  iterable<BanquetEvent>  $events
     * @return array<int, array{received: Money, refunded: Money, retained: Money, paid: Money, outstanding: Money}> by event id
     */
    public static function of(iterable $events): array
    {
        $byId = [];
        foreach ($events as $event) {
            $byId[(int) $event->id] = $event;
        }
        if ($byId === []) {
            return [];
        }

        $entries = BanquetPaymentEntry::whereIn('banquet_event_id', array_keys($byId))->get(['banquet_event_id', 'type', 'amount'])->groupBy('banquet_event_id');
        $orderIds = array_values(array_filter(array_map(fn (BanquetEvent $event) => $event->order_id !== null ? (int) $event->order_id : null, $byId)));
        $tillPaid = [];
        if ($orderIds !== []) {
            $bills = OrderBill::whereIn('order_id', $orderIds)->where('status', '!=', BillStatus::VOID->value)->pluck('order_id', 'id');
            foreach (Payment::whereIn('bill_id', $bills->keys())->where('status', 'paid')->where('provider', '!=', PosTender::PREPAYMENT->value)->get(['bill_id', 'amount']) as $payment) {
                $orderId = (int) $bills->get($payment->bill_id);
                $tillPaid[$orderId] = ($tillPaid[$orderId] ?? Money::zero())->plus($payment->amount);
            }
        }

        $out = [];
        foreach ($byId as $id => $event) {
            $mine = $entries->get($id, collect());
            $sum = fn (BanquetEntryType ...$types) => Money::sum(...$mine->filter(fn (BanquetPaymentEntry $entry) => in_array($entry->type, $types, true))->pluck('amount')->all());
            $received = $sum(BanquetEntryType::PAYMENT, BanquetEntryType::TRANSFER)->plus($event->order_id !== null ? ($tillPaid[(int) $event->order_id] ?? Money::zero()) : Money::zero());
            $refunded = $sum(BanquetEntryType::REFUND);
            $paid = $received->minus($refunded);
            $closed = in_array($event->status, [BanquetEventStatus::SETTLED, BanquetEventStatus::CANCELLED], true);
            $out[$id] = [
                'received' => $received,
                'refunded' => $refunded,
                'retained' => $sum(BanquetEntryType::RETAIN),
                'paid' => $paid,
                'outstanding' => $closed ? Money::zero() : Money::max(Money::zero(), Money::of($event->total)->minus($paid)),
            ];
        }

        return $out;
    }

    /**
     * @return array{received: Money, refunded: Money, retained: Money, paid: Money, outstanding: Money}
     */
    public static function one(BanquetEvent $event): array
    {
        return self::of([$event])[(int) $event->id];
    }
}
