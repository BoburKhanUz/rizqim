<?php

namespace App\Domain\Pos\Banquet;

use App\Domain\Pos\Banquet\Payments\BanquetSchedule;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\BanquetEvent;
use App\Models\BanquetEventLine;
use App\Models\BanquetPayment;
use App\Models\Branch;
use App\Models\Hall;
use App\Models\Order;
use App\Services\Audit\AuditRedactor;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\StoreTimezone;

/**
 * A banquet event as the till and the panel read it (docs/API.md "Tadbirlar"). Money as decimal strings in so'm, the
 * quote currency only as `quote` (display); times as UTC ISO 8601, the slot's local times in `start_time`/`end_time`.
 * `warnings`: guests over the store's thresholds, a time outside the window (never refusals). `mask`: the customer's
 * phone masked (report-only readers).
 */
class BanquetPresenter
{
    public const WARNING = 'array{code: string, message: string}';

    public const LINE = 'array{uuid: string, kind: string, kind_label: string, name: string, extra_id: int|null, hall_id: int|null, quantity: string, persons: int|null, per_person: bool, unit_price: string, amount: string, frozen: bool, note: string|null}';

    public const PAYMENT = 'array{uuid: string, kind: string, kind_label: string, due_date: string|null, amount: string, status: string, status_label: string, tender: string|null, paid_at: string|null, fiscal_receipt_uuid: string|null}';

    public const SUMMARY = 'array{uuid: string, branch_id: int, event_date: string, slot: string, slot_label: string, start_time: string, end_time: string, starts_at: string, ends_at: string, hall_ids: list<int>, halls: list<array{id: int, name: string}>, event_type: string, event_type_label: string, status: string, status_label: string, title: string|null, customer_name: string, customer_phone: string|null, guests_guaranteed: int, guests_minimum: int, guests_actual: int|null, guests_billed: int, package_id: int|null, package_name: string|null, total: string, paid_total: string, outstanding: string, hold_expires_at: string|null, frozen: bool, contract_no: string|null, version: int, overdue: bool}';

    public const DETAIL = 'array{uuid: string, branch_id: int, event_date: string, slot: string, slot_label: string, start_time: string, end_time: string, starts_at: string, ends_at: string, hall_ids: list<int>, halls: list<array{id: int, name: string}>, event_type: string, event_type_label: string, status: string, status_label: string, title: string|null, customer_name: string, customer_phone: string|null, guests_guaranteed: int, guests_minimum: int, guests_actual: int|null, guests_billed: int, package_id: int|null, package_name: string|null, total: string, paid_total: string, outstanding: string, hold_expires_at: string|null, frozen: bool, contract_no: string|null, version: int, overdue: bool, package_snapshot: array<string, mixed>|null, package_total: string, hall_total: string, extras_total: string, orders_total: string, quote: array{currency: string, rate: string, total: string}|null, frozen_at: string|null, note: string|null, order_uuid: string|null, cancel_reason: string|null, created_by: int|null, confirmed_at: string|null, started_at: string|null, settled_at: string|null, cancelled_at: string|null, released_at: string|null, created_at: string|null, updated_at: string|null, lines: list<' . self::LINE . '>, payments: list<' . self::PAYMENT . '>, warnings: list<' . self::WARNING . '>}';

    /** @var array<int, string> the business day per branch id (one presenter lists many events) */
    private array $today = [];

    public function __construct(
        private readonly BanquetSettings $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(BanquetEvent $event, bool $mask = false): array
    {
        $event->loadMissing(['halls' => fn ($query) => $query->withTrashed(), 'store']);
        $tz = StoreTimezone::of($event->store);
        $halls = $event->halls->sortBy('id')->values();
        $snapshotName = is_array($event->package_snapshot) ? ($event->package_snapshot['name'] ?? null) : null;

        return [
            'uuid' => (string) $event->uuid,
            'branch_id' => (int) $event->branch_id,
            'event_date' => $event->event_date->toDateString(),
            'slot' => $event->slot->value,
            'slot_label' => $event->slot->label(),
            'start_time' => $event->starts_at->copy()->setTimezone($tz)->format('H:i'),
            'end_time' => $event->ends_at->copy()->setTimezone($tz)->format('H:i'),
            'starts_at' => $event->starts_at->toJSON(),
            'ends_at' => $event->ends_at->toJSON(),
            'hall_ids' => $halls->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'halls' => $halls->map(fn (Hall $hall) => ['id' => (int) $hall->id, 'name' => (string) $hall->name])->all(),
            'event_type' => $event->event_type->value,
            'event_type_label' => $event->event_type->label(),
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'title' => $event->title,
            'customer_name' => $event->customer_name,
            'customer_phone' => $mask ? AuditRedactor::maskPhone($event->customer_phone) : $event->customer_phone,
            'guests_guaranteed' => (int) $event->guests_guaranteed,
            'guests_minimum' => (int) $event->guests_minimum,
            'guests_actual' => $event->guests_actual,
            'guests_billed' => $event->billedGuests(),
            'package_id' => $event->banquet_package_id !== null ? (int) $event->banquet_package_id : null,
            'package_name' => $snapshotName !== null ? (string) $snapshotName : null,
            'total' => Money::of($event->total)->toString(),
            'paid_total' => Money::of($event->paid_total)->toString(),
            'outstanding' => Money::max(Money::zero(), Money::of($event->total)->minus($event->paid_total))->toString(),
            'hold_expires_at' => $event->hold_expires_at?->toJSON(),
            'frozen' => $event->frozen_at !== null,
            'contract_no' => $event->contract_no,
            'version' => (int) $event->version,
            // An unpaid schedule row due before the branch's business day (BanquetSchedule::summary): the badge
            'overdue' => BanquetSchedule::summary($event, $event->relationLoaded('payments') ? $event->payments : $event->payments()->get(), $this->today[(int) $event->branch_id] ??= BusinessDay::of(Branch::findOrFail($event->branch_id)))['overdue'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(BanquetEvent $event, bool $mask = false): array
    {
        $lines = BanquetEventLine::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        $payments = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        $quote = null;
        if ($event->quote_currency !== null && $event->quote_rate !== null && Money::of($event->quote_rate)->isPositive()) {
            $quote = [
                'currency' => $event->quote_currency,
                'rate' => (string) $event->quote_rate,
                'total' => \Brick\Math\BigDecimal::of(Money::of($event->total)->toString())->dividedBy((string) $event->quote_rate, 2, \Brick\Math\RoundingMode::HALF_UP)->__toString(),
            ];
        }

        return $this->summary($event, $mask) + [
            'package_snapshot' => $event->package_snapshot,
            'package_total' => Money::of($event->package_total)->toString(),
            'hall_total' => Money::of($event->hall_total)->toString(),
            'extras_total' => Money::of($event->extras_total)->toString(),
            'orders_total' => Money::of($event->orders_total)->toString(),
            'quote' => $quote,
            'frozen_at' => $event->frozen_at?->toJSON(),
            'note' => $event->note,
            'order_uuid' => $event->order_id !== null ? Order::withTrashed()->whereKey($event->order_id)->value('uuid') : null,
            'cancel_reason' => $event->cancel_reason,
            'created_by' => $event->created_by !== null ? (int) $event->created_by : null,
            'confirmed_at' => $event->confirmed_at?->toJSON(),
            'started_at' => $event->started_at?->toJSON(),
            'settled_at' => $event->settled_at?->toJSON(),
            'cancelled_at' => $event->cancelled_at?->toJSON(),
            'released_at' => $event->released_at?->toJSON(),
            'created_at' => $event->created_at?->toJSON(),
            'updated_at' => $event->updated_at?->toJSON(),
            'lines' => $lines->map(fn (BanquetEventLine $line) => $this->line($line))->values()->all(),
            'payments' => $payments->map(fn (BanquetPayment $payment) => [
                'uuid' => (string) $payment->uuid,
                'kind' => $payment->kind->value,
                'kind_label' => $payment->kind->label(),
                'due_date' => $payment->due_date?->toDateString(),
                'amount' => Money::of($payment->amount)->toString(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'tender' => $payment->tender,
                'paid_at' => $payment->paid_at?->toJSON(),
                'fiscal_receipt_uuid' => $payment->fiscal_receipt_uuid,
            ])->values()->all(),
            'warnings' => $this->warnings($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function line(BanquetEventLine $line): array
    {
        return [
            'uuid' => (string) $line->uuid,
            'kind' => $line->kind->value,
            'kind_label' => $line->kind->label(),
            'name' => $line->name,
            'extra_id' => $line->banquet_extra_id !== null ? (int) $line->banquet_extra_id : null,
            'hall_id' => $line->hall_id !== null ? (int) $line->hall_id : null,
            'quantity' => Quantity::of($line->quantity)->toString(),
            'persons' => $line->persons,
            'per_person' => $line->persons !== null,
            'unit_price' => Money::of($line->unit_price)->toString(),
            'amount' => Money::of($line->amount)->toString(),
            'frozen' => (bool) $line->frozen,
            'note' => $line->kind === BanquetLineKind::HALL_RENT ? null : $line->note,
        ];
    }

    /**
     * Guests over a threshold (the highest one passed), a start or end outside the store's time window.
     *
     * @return list<array{code: string, message: string}>
     */
    public function warnings(BanquetEvent $event): array
    {
        $event->loadMissing('store');
        $settings = $this->settings->view((int) $event->store_id);
        $warnings = [];

        $guests = max((int) $event->guests_guaranteed, (int) $event->guests_minimum, (int) ($event->guests_actual ?? 0));
        $passed = array_values(array_filter($settings['guest_warning_thresholds'], fn (int $limit) => $guests > $limit));
        if ($passed !== []) {
            $limit = max($passed);
            $warnings[] = ['code' => 'guests_over_threshold', 'message' => "Mehmonlar soni {$guests}: {$limit} dan ko'p. Zal sig'imi va xodimlarni tekshiring."];
        }

        $tz = StoreTimezone::of($event->store);
        $start = $event->starts_at->copy()->setTimezone($tz);
        $end = $event->ends_at->copy()->setTimezone($tz);
        $windowStart = $start->copy()->setTimeFromTimeString($settings['window_start']);
        $windowEnd = $start->copy()->setTimeFromTimeString($settings['window_end']);
        if (!$windowEnd->greaterThan($windowStart)) {
            $windowEnd->addDay();
        }
        if ($start->lessThan($windowStart) || $end->greaterThan($windowEnd)) {
            $warnings[] = ['code' => 'outside_time_window', 'message' => "Tadbir vaqti {$settings['window_start']}–{$settings['window_end']} oralig'idan chiqadi."];
        }

        return $warnings;
    }
}
