<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-53: an event's payment schedule (banquet_payments): deposit, interims and the final row with due dates. The
 * unpaid rows always add up to what is still to pay (total − every row already paid); a paid row never changes.
 *
 *  - plan: the first schedule, from the store's template (BanquetPaymentSettings) once the event has a total;
 *  - re-plan (the total changed: guests, package, extras, frozen prices): the difference goes to the last unpaid rows —
 *    an increase onto the last one (a new final row on the event day when none is left), a decrease off the last ones
 *    first (a row brought to zero goes); nothing left to pay: the unpaid rows go (an overpaid event is refunded at the
 *    final settlement, P6-54);
 *  - replace: the manager edits the unpaid rows (dates, amounts, more interims); they must add up to what is left;
 *  - split: a part of a row paid (BanquetPaymentService) leaves the rest as a new unpaid row with the same date.
 *
 * Amounts are whole so'm by the template's percents (half-up); the last row takes the rest.
 */
final class BanquetSchedule
{
    public function __construct(
        private readonly BanquetPaymentSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The rows a template gives for `total` on an event dated `eventDate`, booked `today` (both Y-m-d).
     *
     * @param  list<array{kind: string, percent: string|null, days_before: int|null}>  $template
     * @return list<array{kind: BanquetPaymentKind, due_date: string, amount: Money}>
     */
    public static function plan(Money $total, array $template, string $eventDate, string $today): array
    {
        if (!$total->isPositive()) {
            return [];
        }
        $rows = [];
        $left = $total;
        foreach ($template as $index => $row) {
            $last = $index === count($template) - 1;
            $amount = $last || $row['percent'] === null ? $left : Money::min($left, $total->percent($row['percent'], 0));
            $due = $row['days_before'] === null
                ? $today
                : Carbon::parse($eventDate)->subDays($row['days_before'])->toDateString();
            $due = max($today, min($due, $eventDate));
            if ($due > $eventDate) {
                $due = $eventDate; // an event in the past (booked late): everything is due on its day
            }
            if ($amount->isPositive()) {
                $rows[] = ['kind' => BanquetPaymentKind::tryFrom($row['kind']) ?? BanquetPaymentKind::INTERIM, 'due_date' => $due, 'amount' => $amount];
                $left = $left->minus($amount);
            }
            if ($last || !$left->isPositive()) {
                break;
            }
        }

        return $rows;
    }

    /**
     * What is still to pay by the schedule: the total less every row already paid (refunded or retained ones too:
     * they were paid).
     */
    public static function outstanding(BanquetEvent $event, Collection $rows): Money
    {
        $paid = Money::sum(...$rows->filter(fn (BanquetPayment $row) => $row->status !== BanquetPaymentStatus::DUE)->pluck('amount')->all());

        return Money::of($event->total)->minus($paid);
    }

    /**
     * After the total changed (or on first need): plans the schedule, or moves the difference onto the unpaid rows.
     * Call it with the event row locked (or inside the transaction that changed it). An event that is over (settled,
     * cancelled) keeps its rows.
     */
    public function sync(BanquetEvent $event): void
    {
        if (!in_array($event->status, [BanquetEventStatus::HOLD, BanquetEventStatus::CONFIRMED, BanquetEventStatus::IN_PROGRESS], true)) {
            return;
        }
        $rows = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
        if ($rows->isEmpty()) {
            $branch = Branch::findOrFail($event->branch_id);
            $plan = self::plan(Money::of($event->total), $this->settings->of((int) $event->store_id)['schedule_template'], $event->event_date->toDateString(), BusinessDay::of($branch));
            foreach ($plan as $index => $row) {
                $this->create($event, $row['kind'], $row['due_date'], $row['amount'], ($index + 1) * 10);
            }

            return;
        }

        $target = self::outstanding($event, $rows);
        $due = $rows->filter(fn (BanquetPayment $row) => $row->status === BanquetPaymentStatus::DUE)->values();
        $diff = $target->minus(Money::sum(...$due->pluck('amount')->all()));
        if ($diff->isZero()) {
            return;
        }
        if ($diff->isPositive()) {
            $last = $due->last();
            if ($last instanceof BanquetPayment) {
                $last->amount = Money::of($last->amount)->plus($diff)->toString();
                $last->save();
            } else {
                $this->create($event, BanquetPaymentKind::FINAL, $event->event_date->toDateString(), $diff, ((int) $rows->max('sort_order')) + 10);
            }

            return;
        }

        $cut = $diff->negated();
        foreach ($due->reverse() as $row) {
            if (!$cut->isPositive()) {
                break;
            }
            $amount = Money::of($row->amount);
            if ($amount->isLessThanOrEqualTo($cut)) {
                $cut = $cut->minus($amount);
                $row->delete();
            } else {
                $row->amount = $amount->minus($cut)->toString();
                $row->save();
                $cut = Money::zero();
            }
        }
    }

    /**
     * The manager's own unpaid rows (`rows`: kind, due_date, amount; an existing unpaid row by its uuid keeps its
     * identity). They must add up to what is left to pay; a paid row is never touched. Audited.
     *
     * @param  list<array{uuid?: string|null, kind: string, due_date: string, amount: string}>  $rows
     */
    public function replace(BanquetEvent $event, array $rows, ?int $version, User $actor): BanquetEvent
    {
        return DB::transaction(function () use ($event, $rows, $version, $actor) {
            $event = BanquetEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($version !== null && $version !== (int) $event->version) {
                throw PosActionException::conflict('version_conflict', "Tadbir boshqa joyda o'zgartirilgan: yangilab qayta urinib ko'ring.", ['version' => (int) $event->version]);
            }
            if (!$event->status->isEditable() && $event->status !== BanquetEventStatus::IN_PROGRESS) {
                throw PosActionException::conflict('event_not_editable', "Tadbir \"{$event->status->label()}\" holatida: to'lov jadvalini o'zgartirib bo'lmaydi.");
            }
            $existing = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $target = self::outstanding($event, $existing);
            $sum = Money::sum(...array_map(fn ($row) => Money::of($row['amount']), $rows));
            if (!$sum->equals(Money::max($target, Money::zero()))) {
                throw PosActionException::invalid('schedule_sum_mismatch', "To'lanmagan qatorlar yig'indisi qolgan summaga teng bo'lsin: " . Money::max($target, Money::zero())->toString() . " so'm.", [
                    'expected' => Money::max($target, Money::zero())->toString(),
                    'sum' => $sum->toString(),
                ]);
            }
            $branch = Branch::findOrFail($event->branch_id);
            $today = BusinessDay::of($branch);
            $due = $existing->filter(fn (BanquetPayment $row) => $row->status === BanquetPaymentStatus::DUE)->keyBy('uuid');
            $before = $existing->map(fn (BanquetPayment $row) => [$row->kind->value, $row->due_date?->toDateString(), Money::of($row->amount)->toString(), $row->status->value])->all();
            $order = (int) $existing->where('status', '!==', BanquetPaymentStatus::DUE)->max('sort_order');
            $kept = [];
            foreach ($rows as $index => $input) {
                $amount = Money::of($input['amount']);
                if (!$amount->isPositive()) {
                    throw PosActionException::invalid('amount_invalid', "Qator summasi musbat bo'lsin.");
                }
                if ($input['due_date'] < $today && !($due->has((string) ($input['uuid'] ?? '')) && $due[(string) $input['uuid']]->due_date?->toDateString() === $input['due_date'])) {
                    throw PosActionException::invalid('due_date_past', "To'lov sanasi bugundan oldin bo'lmasin.");
                }
                $kind = BanquetPaymentKind::from($input['kind']);
                $row = filled($input['uuid'] ?? null) ? $due->get((string) $input['uuid']) : null;
                if (filled($input['uuid'] ?? null) && $row === null) {
                    throw PosActionException::invalid('payment_not_editable', "Bu qator to'langan yoki topilmadi: uni o'zgartirib bo'lmaydi.");
                }
                $sort = $order + ($index + 1) * 10;
                if ($row !== null) {
                    $row->forceFill(['kind' => $kind, 'due_date' => $input['due_date'], 'amount' => $amount->toString(), 'sort_order' => $sort])->save();
                    $kept[] = $row->id;
                } else {
                    $kept[] = $this->create($event, $kind, $input['due_date'], $amount, $sort)->id;
                }
            }
            BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::DUE->value)->whereNotIn('id', $kept)->delete();

            $event->version = (int) $event->version + 1;
            $event->save();
            $after = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (BanquetPayment $row) => [$row->kind->value, $row->due_date?->toDateString(), Money::of($row->amount)->toString(), $row->status->value])->all();
            $this->audit->record('banquet.schedule_updated', $event, ['rows' => $before], ['rows' => $after], [], ['actor' => $actor]);

            return $event;
        });
    }

    /**
     * Splits `row` so that `amount` of it is the row being paid now; the rest stays a new unpaid row (same kind and
     * date, right after it). Returns the row to pay. Inside the caller's transaction, with the row locked.
     */
    public function split(BanquetPayment $row, Money $amount): BanquetPayment
    {
        $rest = Money::of($row->amount)->minus($amount);
        if (!$rest->isPositive()) {
            return $row;
        }
        $row->amount = $amount->toString();
        $row->save();
        BanquetPayment::create([
            'store_id' => $row->store_id,
            'branch_id' => $row->branch_id,
            'banquet_event_id' => $row->banquet_event_id,
            'kind' => $row->kind,
            'due_date' => $row->due_date?->toDateString(),
            'amount' => $rest->toString(),
            'status' => BanquetPaymentStatus::DUE,
            'sort_order' => (int) $row->sort_order + 1,
            'note' => $row->note,
        ]);

        return $row;
    }

    /**
     * The schedule's figures for the event card: total, paid (money received), what is still to pay, what is overdue
     * (unpaid rows due before `today`) and the next due row.
     *
     * @return array{total: string, paid: string, outstanding: string, overdue: bool, overdue_amount: string, overdue_count: int, next_due: array{date: string, amount: string, kind: string}|null}
     */
    public static function summary(BanquetEvent $event, Collection $rows, string $today): array
    {
        $due = $rows->filter(fn (BanquetPayment $row) => $row->status === BanquetPaymentStatus::DUE);
        $overdue = $due->filter(fn (BanquetPayment $row) => $row->due_date !== null && $row->due_date->toDateString() < $today);
        $next = $due->sortBy(fn (BanquetPayment $row) => [$row->due_date?->toDateString() ?? '9999-12-31', $row->sort_order, $row->id])->first();
        $paid = Money::sum(...$rows->filter(fn (BanquetPayment $row) => $row->status !== BanquetPaymentStatus::DUE)->pluck('amount')->all());
        $live = !in_array($event->status, [BanquetEventStatus::CANCELLED, BanquetEventStatus::SETTLED], true);

        return [
            'total' => Money::of($event->total)->toString(),
            'paid' => $paid->toString(),
            'outstanding' => $live ? Money::sum(...$due->pluck('amount')->all())->toString() : '0.00',
            'overdue' => $live && $overdue->isNotEmpty(),
            'overdue_amount' => $live ? Money::sum(...$overdue->pluck('amount')->all())->toString() : '0.00',
            'overdue_count' => $live ? $overdue->count() : 0,
            'next_due' => $live && $next instanceof BanquetPayment ? [
                'date' => (string) $next->due_date?->toDateString(),
                'amount' => Money::of($next->amount)->toString(),
                'kind' => $next->kind->value,
            ] : null,
        ];
    }

    private function create(BanquetEvent $event, BanquetPaymentKind $kind, string $dueDate, Money $amount, int $sort): BanquetPayment
    {
        return BanquetPayment::create([
            'store_id' => $event->store_id,
            'branch_id' => $event->branch_id,
            'banquet_event_id' => $event->id,
            'kind' => $kind,
            'due_date' => $dueDate,
            'amount' => $amount->toString(),
            'status' => BanquetPaymentStatus::DUE,
            'sort_order' => $sort,
        ]);
    }
}
