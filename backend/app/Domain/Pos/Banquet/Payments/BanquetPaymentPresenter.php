<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Models\BanquetCancellation;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\Branch;
use App\Models\User;
use App\Support\Money;

/**
 * P6-53: the payment schedule as the till and the panel show it (docs/API.md "Tadbirlar: to'lov jadvali"). Money as
 * decimal strings.
 */
final class BanquetPaymentPresenter
{
    public const ROW = 'array{uuid: string, kind: string, kind_label: string, due_date: string|null, amount: string, status: string, status_label: string, overdue: bool, tender: string|null, card_type: string|null, transaction_id: string|null, reference: string|null, shift_id: int|null, paid_at: string|null, paid_by: array{id: int, name: string|null}|null, fiscal: array{receipt_uuid: string|null, status: string|null}, refunded_amount: string, retained_amount: string, settle_receipt_uuid: string|null, note: string|null}';

    public const ENTRY = 'array{uuid: string, type: string, type_label: string, payment_uuid: string|null, amount: string, tender: string, card_type: string|null, reference: string|null, business_date: string|null, shift_id: int|null, user: array{id: int, name: string|null}|null, approved_by: int|null, reason: string|null, transfer_pending: bool, fiscal: array{receipt_uuid: string|null, status: string|null}, original_receipt_uuid: string|null, created_at: string|null}';

    public const SUMMARY = 'array{total: string, paid: string, outstanding: string, overdue: bool, overdue_amount: string, overdue_count: int, next_due: array{date: string, amount: string, kind: string}|null}';

    public const CANCELLATION = 'array{days_before: int, rule_percent: string, paid: string, computed_retained: string, retained: string, refund: string, override_reason: string|null, reason: string|null, business_date: string|null, cancelled_by: int|null, created_at: string|null}';

    public const SCHEDULE = 'array{event: array{uuid: string, status: string, status_label: string, event_date: string, total: string, paid_total: string, version: int}, summary: ' . self::SUMMARY . ', rows: list<' . self::ROW . '>, entries: list<' . self::ENTRY . '>, cancellation: ' . self::CANCELLATION . '|null}';

    /**
     * @return array<string, mixed>
     */
    public function schedule(BanquetEvent $event): array
    {
        $rows = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->get();
        $entries = BanquetPaymentEntry::where('banquet_event_id', $event->id)->with('user:id,name')->orderBy('id')->get();
        $today = BusinessDay::of(Branch::findOrFail($event->branch_id));
        $names = User::whereIn('id', $rows->pluck('paid_by')->filter()->unique())->pluck('name', 'id');
        $cancellation = BanquetCancellation::where('banquet_event_id', $event->id)->first();

        return [
            'event' => [
                'uuid' => $event->uuid,
                'status' => $event->status->value,
                'status_label' => $event->status->label(),
                'event_date' => $event->event_date->toDateString(),
                'total' => Money::of($event->total)->toString(),
                'paid_total' => Money::of($event->paid_total)->toString(),
                'version' => (int) $event->version,
            ],
            'summary' => BanquetSchedule::summary($event, $rows, $today),
            'rows' => $rows->map(fn (BanquetPayment $row) => $this->row($row, $today, $names->all()))->values()->all(),
            'entries' => $entries->map(fn (BanquetPaymentEntry $entry) => $this->entry($entry))->values()->all(),
            'cancellation' => $cancellation !== null ? $this->cancellation($cancellation) : null,
        ];
    }

    /**
     * @param  array<int|string, string|null>  $names
     * @return array<string, mixed>
     */
    public function row(BanquetPayment $row, string $today, array $names = []): array
    {
        return [
            'uuid' => $row->uuid,
            'kind' => $row->kind->value,
            'kind_label' => $row->kind->label(),
            'due_date' => $row->due_date?->toDateString(),
            'amount' => Money::of($row->amount)->toString(),
            'status' => $row->status->value,
            'status_label' => $row->status->label(),
            'overdue' => $row->status === BanquetPaymentStatus::DUE && $row->due_date !== null && $row->due_date->toDateString() < $today,
            'tender' => $row->tender,
            'card_type' => $row->card_type,
            'transaction_id' => $row->transaction_id,
            'reference' => $row->reference,
            'shift_id' => $row->shift_id !== null ? (int) $row->shift_id : null,
            'paid_at' => $row->paid_at?->toIso8601String(),
            'paid_by' => $row->paid_by !== null ? ['id' => (int) $row->paid_by, 'name' => $names[$row->paid_by] ?? null] : null,
            'fiscal' => ['receipt_uuid' => $row->fiscal_receipt_uuid, 'status' => $row->fiscal_status],
            'refunded_amount' => Money::of($row->refunded_amount)->toString(),
            'retained_amount' => Money::of($row->retained_amount)->toString(),
            'settle_receipt_uuid' => $row->settle_receipt_uuid,
            'note' => $row->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entry(BanquetPaymentEntry $entry): array
    {
        $paymentUuid = $entry->banquet_payment_id !== null ? BanquetPayment::whereKey($entry->banquet_payment_id)->value('uuid') : null;

        return [
            'uuid' => $entry->uuid,
            'type' => $entry->type->value,
            'type_label' => $entry->type->label(),
            'payment_uuid' => $paymentUuid !== null ? (string) $paymentUuid : null,
            'amount' => Money::of($entry->amount)->toString(),
            'tender' => $entry->tender,
            'card_type' => $entry->card_type,
            'reference' => $entry->reference,
            'business_date' => $entry->business_date->toDateString(),
            'shift_id' => $entry->shift_id !== null ? (int) $entry->shift_id : null,
            'user' => $entry->user !== null ? ['id' => (int) $entry->user->id, 'name' => $entry->user->name] : null,
            'approved_by' => $entry->approved_by !== null ? (int) $entry->approved_by : null,
            'reason' => $entry->reason,
            'transfer_pending' => (bool) $entry->transfer_pending,
            'fiscal' => ['receipt_uuid' => $entry->fiscal_receipt_uuid, 'status' => $entry->fiscal_status],
            'original_receipt_uuid' => $entry->original_receipt_uuid,
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancellation(BanquetCancellation $cancellation): array
    {
        return [
            'days_before' => (int) $cancellation->days_before,
            'rule_percent' => rtrim(rtrim((string) $cancellation->rule_percent, '0'), '.'),
            'paid' => Money::of($cancellation->paid_amount)->toString(),
            'computed_retained' => Money::of($cancellation->computed_retained)->toString(),
            'retained' => Money::of($cancellation->retained_amount)->toString(),
            'refund' => Money::of($cancellation->refund_amount)->toString(),
            'override_reason' => $cancellation->override_reason,
            'reason' => $cancellation->reason,
            'business_date' => $cancellation->business_date->toDateString(),
            'cancelled_by' => $cancellation->cancelled_by !== null ? (int) $cancellation->cancelled_by : null,
            'created_at' => $cancellation->created_at?->toIso8601String(),
        ];
    }
}
