<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Booking\ApprovalGate;
use App\Domain\Pos\Booking\BookingDepositService;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\ShiftStatus;
use App\Models\BanquetCancellation;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * P6-53: cancelling a banquet event (hold or confirmed) with what was paid for it:
 *
 *  - the store's rule table (BanquetPaymentSettings) gives the retained % of what was paid by the days left before the
 *    event (the branch's business day to the event date); the retained part is whole so'm (half-up); a manager may
 *    set another retained amount (0 … paid) with a reason — audited and the owner hears it;
 *  - the retained part is taken from the paid rows oldest first (the deposit first), the rest of each row is refunded:
 *    cash from the drawer of the open shift on the terminal (pos.refund, or a manager's one-time approval token; 409
 *    `refund_needs_shift` without one), card and QR the way they were paid (back on the bank terminal / the provider's
 *    cabinet; the refund is recorded on the shift open, if any), a bank transfer only marked "to be returned by
 *    transfer" (`transfer_pending`);
 *  - receipts: each refunded part of an advance is a REFUND receipt against that advance receipt; each retained part
 *    is a SALE receipt of the "Bekor qilish haqi" service line paid by the advance as `prepayment` (in the form it was
 *    paid). Parts paid by bank transfer have no receipt (no advance receipt was issued for them; invoice/ESF outside);
 *  - the unpaid rows go, the event becomes `cancelled` (its halls are free again). Nothing paid: it is just released.
 *
 * Never blocked by a module or a plan (the money side of an existing event).
 */
class BanquetCancellationService
{
    public const REFUND_PERMISSION = 'pos.refund';

    public function __construct(
        protected BanquetPaymentSettings $settings,
        protected BanquetFiscalLines $lines,
        protected ApprovalGate $approvals,
        protected AuditLogger $audit,
        protected PosOwnerAlerts $alerts,
    ) {}

    /**
     * What a cancellation now would do (`retained`: a manager's own retained amount instead of the rule's).
     *
     * @return array{days_before: int, rule_percent: string, paid: string, computed_retained: string, retained: string, refund: string, overridden: bool, cash_refund: string, transfer_refund: string, rows: list<array{payment_uuid: string, kind: string, tender: string|null, amount: string, retain: string, refund: string, refund_path: string|null}>}
     */
    public function quote(BanquetEvent $event, ?Money $retained = null): array
    {
        $rows = BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::PAID->value)->orderBy('sort_order')->orderBy('id')->get();

        return $this->compute($event, $rows, $retained);
    }

    public const QUOTE = 'array{days_before: int, rule_percent: string, paid: string, computed_retained: string, retained: string, refund: string, overridden: bool, cash_refund: string, transfer_refund: string, rows: list<array{payment_uuid: string, kind: string, tender: string|null, amount: string, retain: string, refund: string, refund_path: string|null}>}';

    /**
     * @return BanquetCancellation
     */
    public function cancel(BanquetEvent $event, string $reason, ?Money $retained, ?string $overrideReason, ?string $approvalToken, User $actor, ?int $deviceId, ?\Closure $applied = null): BanquetCancellation
    {
        [$cancellation, $entries, $event] = DB::transaction(function () use ($event, $reason, $retained, $overrideReason, $approvalToken, $actor, $deviceId, $applied) {
            $event = BanquetEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (!$event->status->canMoveTo(BanquetEventStatus::CANCELLED)) {
                throw PosActionException::conflict('event_not_cancellable', "Tadbir \"{$event->status->label()}\" holatida: bekor qilib bo'lmaydi.");
            }
            $rows = BanquetPayment::where('banquet_event_id', $event->id)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get();
            $paidRows = $rows->filter(fn (BanquetPayment $row) => $row->status === BanquetPaymentStatus::PAID)->values();
            $quote = $this->compute($event, $paidRows, $retained);
            if ($quote['overridden'] && blank($overrideReason)) {
                throw PosActionException::invalid('override_reason_required', "Ushlab qolinadigan summani o'zgartirish sababini yozing.");
            }

            $branch = Branch::findOrFail($event->branch_id);
            $today = BusinessDay::of($branch);
            $shiftId = $this->shiftOf($event, $deviceId);
            $approver = null;
            if (Money::of($quote['cash_refund'])->isPositive()) {
                if ($shiftId === null) {
                    throw PosActionException::conflict('refund_needs_shift', "Naqd pulni qaytarish uchun kassada ochiq smena kerak: bekor qilishni kassada bajaring.", ['cash_refund' => $quote['cash_refund']]);
                }
            }
            if (Money::of($quote['refund'])->minus($quote['transfer_refund'])->isPositive()) {
                $approver = $this->approvals->approver($actor, self::REFUND_PERMISSION, $approvalToken, $deviceId, $event);
            }

            $entries = [];
            foreach ($quote['rows'] as $part) {
                $row = $paidRows->firstWhere('uuid', $part['payment_uuid']);
                if (!$row instanceof BanquetPayment) {
                    continue;
                }
                $refund = Money::of($part['refund']);
                $retain = Money::of($part['retain']);
                $common = [
                    'store_id' => $event->store_id,
                    'branch_id' => $event->branch_id,
                    'banquet_event_id' => $event->id,
                    'banquet_payment_id' => $row->id,
                    'tender' => (string) $row->tender,
                    'card_type' => $row->card_type,
                    'transaction_id' => $row->transaction_id,
                    'business_date' => $today,
                    'user_id' => $actor->id,
                    'reason' => mb_substr($reason, 0, 500),
                    'original_receipt_uuid' => $row->fiscal_receipt_uuid,
                ];
                if ($refund->isPositive()) {
                    $transfer = $row->tender === BanquetPaymentService::BANK_TRANSFER;
                    $entries[] = BanquetPaymentEntry::create($common + [
                        'type' => BanquetEntryType::REFUND,
                        'amount' => $refund->toString(),
                        'shift_id' => $transfer ? null : $shiftId,
                        'device_id' => $transfer ? null : $deviceId,
                        'approved_by' => $transfer ? null : $approver,
                        'transfer_pending' => $transfer,
                    ]);
                }
                if ($retain->isPositive()) {
                    $entries[] = BanquetPaymentEntry::create($common + [
                        'type' => BanquetEntryType::RETAIN,
                        'amount' => $retain->toString(),
                        'device_id' => $deviceId,
                        'reason' => mb_substr($quote['overridden'] ? (string) $overrideReason : $reason, 0, 500),
                    ]);
                }
                $row->forceFill([
                    'status' => $retain->isPositive() ? BanquetPaymentStatus::RETAINED : BanquetPaymentStatus::REFUNDED,
                    'refunded_amount' => $refund->toString(),
                    'retained_amount' => $retain->toString(),
                    'settled_at' => now(),
                ])->save();
            }
            BanquetPayment::where('banquet_event_id', $event->id)->where('status', BanquetPaymentStatus::DUE->value)->delete();

            $before = $event->status;
            $event->forceFill([
                'status' => BanquetEventStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => mb_substr($reason, 0, 500),
                'hold_expires_at' => null,
                'version' => (int) $event->version + 1,
            ])->save();

            $cancellation = BanquetCancellation::create([
                'store_id' => $event->store_id,
                'branch_id' => $event->branch_id,
                'banquet_event_id' => $event->id,
                'days_before' => $quote['days_before'],
                'rule_percent' => $quote['rule_percent'],
                'paid_amount' => $quote['paid'],
                'computed_retained' => $quote['computed_retained'],
                'retained_amount' => $quote['retained'],
                'refund_amount' => $quote['refund'],
                'override_reason' => $quote['overridden'] ? mb_substr((string) $overrideReason, 0, 500) : null,
                'reason' => mb_substr($reason, 0, 500),
                'business_date' => $today,
                'cancelled_by' => $actor->id,
                'approved_by' => $approver,
            ]);

            $this->audit->record('banquet.cancelled', $event, ['status' => $before->value], ['status' => BanquetEventStatus::CANCELLED->value], array_filter([
                'reason' => $reason,
                'days_before' => $quote['days_before'],
                'rule_percent' => $quote['rule_percent'],
                'paid' => $quote['paid'],
                'computed_retained' => $quote['computed_retained'],
                'retained' => $quote['retained'],
                'refund' => $quote['refund'],
                'override_reason' => $quote['overridden'] ? $overrideReason : null,
                'approved_by' => $approver,
                'shift_id' => $shiftId,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            event(new \App\Domain\Pos\Banquet\BanquetEventChanged($event, BanquetEventStatus::CANCELLED->value, false));
            if ($applied) {
                $applied();
            }

            return [$cancellation, $entries, $event];
        });

        $store = Store::findOrFail($event->store_id);
        foreach ($entries as $entry) {
            $this->receipt($store, $entry);
        }

        if (Money::of($cancellation->paid_amount)->isPositive()) {
            $text = "❌ Tadbir bekor qilindi: {$this->describe($event)}\nTo'langan: " . PosOwnerAlerts::sum($cancellation->paid_amount)
                . "\nUshlab qolindi: " . PosOwnerAlerts::sum($cancellation->retained_amount)
                . "\nQaytariladi: " . PosOwnerAlerts::sum($cancellation->refund_amount)
                . "\nSabab: {$reason}";
            if ($cancellation->override_reason !== null) {
                $text .= "\n⚠️ Qoida bo'yicha " . PosOwnerAlerts::sum($cancellation->computed_retained) . " edi, menejer o'zgartirdi: {$cancellation->override_reason}";
            }
            $this->alerts->send((int) $event->store_id, $text . "\nBekor qildi: {$actor->name}");
        }

        return $cancellation;
    }

    /**
     * @param  Collection<int, BanquetPayment>  $rows  the paid rows, oldest first
     * @return array{days_before: int, rule_percent: string, paid: string, computed_retained: string, retained: string, refund: string, overridden: bool, cash_refund: string, transfer_refund: string, rows: list<array{payment_uuid: string, kind: string, tender: string|null, amount: string, retain: string, refund: string, refund_path: string|null}>}
     */
    private function compute(BanquetEvent $event, Collection $rows, ?Money $retained): array
    {
        $branch = Branch::findOrFail($event->branch_id);
        $today = BusinessDay::of($branch);
        $days = (int) Carbon::parse($today)->diffInDays(Carbon::parse($event->event_date->toDateString()), false);
        $percent = BanquetPaymentSettings::retainedPercent($this->settings->of((int) $event->store_id)['cancellation_rules'], $days);
        $paid = Money::sum(...$rows->pluck('amount')->all());
        $computed = Money::min($paid, $paid->percent($percent, 0));
        if ($retained !== null && ($retained->isNegative() || $retained->isGreaterThan($paid))) {
            throw PosActionException::invalid('retained_invalid', "Ushlab qolinadigan summa 0 dan " . $paid->toString() . " so'mgacha bo'lsin.", ['paid' => $paid->toString()]);
        }
        $keep = $retained ?? $computed;

        $parts = [];
        $left = $keep;
        $cash = Money::zero();
        $transfer = Money::zero();
        foreach ($rows as $row) {
            $amount = Money::of($row->amount);
            $retain = Money::min($left, $amount);
            $left = $left->minus($retain);
            $refund = $amount->minus($retain);
            $path = $refund->isPositive() ? match ($row->tender) {
                'cash' => 'cash_drawer',
                BanquetPaymentService::BANK_TRANSFER => 'bank_transfer',
                default => (string) $row->tender,
            } : null;
            if ($path === 'cash_drawer') {
                $cash = $cash->plus($refund);
            }
            if ($path === 'bank_transfer') {
                $transfer = $transfer->plus($refund);
            }
            $parts[] = [
                'payment_uuid' => $row->uuid,
                'kind' => $row->kind->value,
                'tender' => $row->tender,
                'amount' => $amount->toString(),
                'retain' => $retain->toString(),
                'refund' => $refund->toString(),
                'refund_path' => $path,
            ];
        }

        return [
            'days_before' => $days,
            'rule_percent' => $percent,
            'paid' => $paid->toString(),
            'computed_retained' => $computed->toString(),
            'retained' => $keep->toString(),
            'refund' => $paid->minus($keep)->toString(),
            'overridden' => $retained !== null && !$retained->equals($computed),
            'cash_refund' => $cash->toString(),
            'transfer_refund' => $transfer->toString(),
            'rows' => $parts,
        ];
    }

    /**
     * The shift open on the terminal in the event's branch, locked (a closing Z cannot miss a refund made at the same
     * moment); none without a terminal (the web panel).
     */
    private function shiftOf(BanquetEvent $event, ?int $deviceId): ?int
    {
        if ($deviceId === null) {
            return null;
        }
        $id = PosShift::where('device_id', $deviceId)->where('branch_id', $event->branch_id)
            ->where('status', ShiftStatus::OPEN->value)->lockForUpdate()->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function receipt(Store $store, BanquetPaymentEntry $entry): void
    {
        if ($entry->tender === BanquetPaymentService::BANK_TRANSFER) {
            return;
        }
        $row = BanquetPayment::find($entry->banquet_payment_id);
        $amount = Money::of($entry->amount);
        $refund = $entry->type === BanquetEntryType::REFUND;
        try {
            $line = $refund ? $this->lines->advance($store, $amount, $row->kind ?? BanquetPaymentKind::INTERIM) : $this->lines->cancellation($store, $amount);
        } catch (\Throwable $e) {
            // The money decision is recorded: a fiscal setup problem stays visible on the entry
            report($e);
            $entry->forceFill(['fiscal_status' => 'error'])->save();

            return;
        }
        $document = new FiscalDocument(
            $refund ? FiscalReceiptType::REFUND : FiscalReceiptType::SALE,
            (int) $entry->store_id,
            (int) $entry->branch_id,
            $entry->device_id !== null ? (int) $entry->device_id : null,
            [$line],
            [new FiscalPayment(BookingDepositService::formOf($entry->tender), $amount, prepayment: !$refund, cardType: $entry->card_type, transactionId: $entry->transaction_id)],
            $amount,
            "banquet_payment:{$entry->uuid}:" . ($refund ? 'refund' : 'retain'),
            $refund ? $entry->original_receipt_uuid : null,
            BanquetPaymentService::FISCAL_SUBJECT,
            (int) $entry->id,
        );
        BanquetPaymentService::issue($entry, $document);
        if ($row !== null && $entry->fiscal_receipt_uuid !== null && ($refund || $row->settle_receipt_uuid === null)) {
            $row->forceFill(['settle_receipt_uuid' => $entry->fiscal_receipt_uuid, 'settle_fiscal_status' => $entry->fiscal_status])->save();
        }
    }

    private function describe(BanquetEvent $event): string
    {
        return $event->event_date->format('d.m.Y') . ", {$event->customer_name}";
    }
}
