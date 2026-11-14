<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\BanquetSettlement;
use App\Models\Branch;
use App\Services\Audit\AuditRedactor;
use App\Support\Money;

/**
 * P6-53: the owner's "Tadbirlar" report for the events dated `from`..`to` (one branch, or the whole store): per event
 * and in total — guests, the event total, what was paid (advances and bank transfers received), what is still to pay,
 * what is overdue (unpaid rows due before the branch's business day), and for cancelled events what was retained
 * and refunded. Cancelled events count apart (their totals are not in `total`/`outstanding`). The customer's phone is
 * masked for a report-only reader. Money as decimal strings.
 *
 * An event billed by its final settlement (P6-54): the schedule's unpaid rows are gone, so `paid` also counts what was
 * paid on the final bill at the till (its payments other than the advances' prepayment offset), and `outstanding` is
 * what is still due on that bill (zero once it is paid and the event settled).
 *
 * Review P6 wave 5 (C7): the figures are Ops\BanquetFigures' (the till and the panel show the same): `received` is the
 * money that came in (schedule payments, bank transfers, the event order's bills at the till), `paid` is it less what
 * was refunded (a cancellation, advances over the total), `refunded` and `retained` apart; `outstanding` = total − paid
 * (zero for a settled or cancelled event). `overdue`: unpaid schedule rows due before the branch's business day, and
 * for an event billed by its settlement whose bill is still unpaid after the event date, that remainder.
 */
final class BanquetReport
{
    public const EVENT = 'array{uuid: string, event_date: string, slot: string, event_type: string, title: string|null, branch_id: int, customer_name: string, customer_phone: string|null, status: string, status_label: string, guests_guaranteed: int, guests_minimum: int, guests_actual: int|null, guests: int, total: string, paid: string, received: string, refunded: string, retained: string, outstanding: string, overdue: string, transfer_pending: string}';

    public const REPORT = 'array{from: string, to: string, branch_id: int|null, events: int, cancelled: int, by_status: list<array{status: string, count: int}>, guests: int, total: string, received: string, paid: string, outstanding: string, overdue: array{count: int, amount: string}, retained: string, refunded: string, transfer_pending: string, rows: list<' . self::EVENT . '>}';

    /**
     * @return array<string, mixed>
     */
    public function build(int $storeId, ?int $branchId, string $from, string $to, bool $maskPhones): array
    {
        $events = BanquetEvent::where('store_id', $storeId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->whereBetween('event_date', [$from, $to])
            ->orderBy('event_date')->orderBy('starts_at')->orderBy('id')
            ->limit(5000)->get();
        $ids = $events->pluck('id');
        $rows = BanquetPayment::whereIn('banquet_event_id', $ids)->get(['banquet_event_id', 'status', 'amount', 'due_date'])->groupBy('banquet_event_id');
        $entries = BanquetPaymentEntry::whereIn('banquet_event_id', $ids)->get(['banquet_event_id', 'type', 'amount', 'transfer_pending'])->groupBy('banquet_event_id');
        // The events billed by their final settlement
        $finalBills = BanquetSettlement::whereIn('banquet_event_id', $ids)->whereNotNull('bill_id')->pluck('bill_id', 'banquet_event_id');
        $figures = \App\Domain\Pos\Banquet\Ops\BanquetFigures::of($events);
        $todays = Branch::where('store_id', $storeId)->get()->mapWithKeys(fn (Branch $branch) => [(int) $branch->id => BusinessDay::of($branch)])->all();

        $out = [];
        $totals = ['total' => Money::zero(), 'received' => Money::zero(), 'paid' => Money::zero(), 'outstanding' => Money::zero(), 'overdue' => Money::zero(), 'retained' => Money::zero(), 'refunded' => Money::zero(), 'pending' => Money::zero()];
        $overdueCount = 0;
        $guests = 0;
        foreach ($events as $event) {
            $today = $todays[(int) $event->branch_id] ?? now()->toDateString();
            $eventRows = $rows->get($event->id, collect());
            $eventEntries = $entries->get($event->id, collect());
            $sum = fn ($items) => Money::sum(...$items->pluck('amount')->all());
            $live = $event->status !== BanquetEventStatus::CANCELLED;
            $open = $live && $event->status !== BanquetEventStatus::SETTLED;
            $due = $eventRows->filter(fn ($row) => $row->status === BanquetPaymentStatus::DUE);
            $overdue = $open ? $sum($due->filter(fn ($row) => $row->due_date !== null && $row->due_date->toDateString() < $today)) : Money::zero();
            $money = $figures[(int) $event->id];
            $received = $money['received'];
            $paid = $money['paid'];
            $refunded = $money['refunded'];
            $retained = $money['retained'];
            $pending = $sum($eventEntries->where('type', BanquetEntryType::REFUND)->where('transfer_pending', true));
            $outstanding = $money['outstanding'];
            if ($open && $finalBills->has($event->id) && $event->event_date->toDateString() < $today) {
                // Billed, the event is over and its bill is not paid in full: the remainder is overdue
                $overdue = $outstanding;
            }
            $billed = max((int) ($event->guests_actual ?? 0), $event->billedGuests());

            if ($live) {
                $totals['total'] = $totals['total']->plus($event->total);
                $guests += $billed;
            }
            $totals['received'] = $totals['received']->plus($received);
            $totals['paid'] = $totals['paid']->plus($paid);
            $totals['outstanding'] = $totals['outstanding']->plus($outstanding);
            $totals['overdue'] = $totals['overdue']->plus($overdue);
            $totals['retained'] = $totals['retained']->plus($retained);
            $totals['refunded'] = $totals['refunded']->plus($refunded);
            $totals['pending'] = $totals['pending']->plus($pending);
            if ($overdue->isPositive()) {
                $overdueCount++;
            }

            $out[] = [
                'uuid' => $event->uuid,
                'event_date' => $event->event_date->toDateString(),
                'slot' => $event->slot->value,
                'event_type' => $event->event_type->value,
                'title' => $event->title,
                'branch_id' => (int) $event->branch_id,
                'customer_name' => $event->customer_name,
                'customer_phone' => $maskPhones ? AuditRedactor::maskPhone($event->customer_phone) : $event->customer_phone,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'guests_guaranteed' => (int) $event->guests_guaranteed,
                'guests_minimum' => (int) $event->guests_minimum,
                'guests_actual' => $event->guests_actual !== null ? (int) $event->guests_actual : null,
                'guests' => $billed,
                'total' => Money::of($event->total)->toString(),
                'received' => $received->toString(),
                'paid' => $paid->toString(),
                'refunded' => $refunded->toString(),
                'retained' => $retained->toString(),
                'outstanding' => $outstanding->toString(),
                'overdue' => $overdue->toString(),
                'transfer_pending' => $pending->toString(),
            ];
        }

        $byStatus = [];
        foreach (BanquetEventStatus::cases() as $status) {
            $byStatus[] = ['status' => $status->value, 'count' => $events->where('status', $status)->count()];
        }

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
            'events' => $events->where('status', '!==', BanquetEventStatus::CANCELLED)->count(),
            'cancelled' => $events->where('status', BanquetEventStatus::CANCELLED)->count(),
            'by_status' => $byStatus,
            'guests' => $guests,
            'total' => $totals['total']->toString(),
            'received' => $totals['received']->toString(),
            'paid' => $totals['paid']->toString(),
            'outstanding' => $totals['outstanding']->toString(),
            'overdue' => ['count' => $overdueCount, 'amount' => $totals['overdue']->toString()],
            'retained' => $totals['retained']->toString(),
            'refunded' => $totals['refunded']->toString(),
            'transfer_pending' => $totals['pending']->toString(),
            'rows' => $out,
        ];
    }
}
