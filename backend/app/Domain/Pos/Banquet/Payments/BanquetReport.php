<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\Branch;
use App\Services\Audit\AuditRedactor;
use App\Support\Money;

/**
 * P6-53: the owner's "Tadbirlar" report for the events dated `from`..`to` (one branch, or the whole store): per event
 * and in total — guests, the event total, what was paid (advances and bank transfers received), what is still to pay,
 * what is overdue (unpaid rows due before the branch's business day), and for cancelled events what was retained
 * and refunded. Cancelled events count apart (their totals are not in `total`/`outstanding`). The customer's phone is
 * masked for a report-only reader. Money as decimal strings.
 */
final class BanquetReport
{
    public const EVENT = 'array{uuid: string, event_date: string, slot: string, event_type: string, title: string|null, branch_id: int, customer_name: string, customer_phone: string|null, status: string, status_label: string, guests_guaranteed: int, guests_minimum: int, guests_actual: int|null, guests: int, total: string, paid: string, refunded: string, retained: string, outstanding: string, overdue: string, transfer_pending: string}';

    public const REPORT = 'array{from: string, to: string, branch_id: int|null, events: int, cancelled: int, by_status: list<array{status: string, count: int}>, guests: int, total: string, paid: string, outstanding: string, overdue: array{count: int, amount: string}, retained: string, refunded: string, transfer_pending: string, rows: list<' . self::EVENT . '>}';

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
        $todays = Branch::where('store_id', $storeId)->get()->mapWithKeys(fn (Branch $branch) => [(int) $branch->id => BusinessDay::of($branch)])->all();

        $out = [];
        $totals = ['total' => Money::zero(), 'paid' => Money::zero(), 'outstanding' => Money::zero(), 'overdue' => Money::zero(), 'retained' => Money::zero(), 'refunded' => Money::zero(), 'pending' => Money::zero()];
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
            $paid = $sum($eventEntries->filter(fn ($entry) => $entry->type->isReceipt()));
            $refunded = $sum($eventEntries->where('type', BanquetEntryType::REFUND));
            $retained = $sum($eventEntries->where('type', BanquetEntryType::RETAIN));
            $pending = $sum($eventEntries->where('type', BanquetEntryType::REFUND)->where('transfer_pending', true));
            $outstanding = $open ? $sum($due) : Money::zero();
            $billed = max((int) ($event->guests_actual ?? 0), $event->billedGuests());

            if ($live) {
                $totals['total'] = $totals['total']->plus($event->total);
                $guests += $billed;
            }
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
