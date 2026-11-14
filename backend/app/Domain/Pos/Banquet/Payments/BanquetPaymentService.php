<?php

namespace App\Domain\Pos\Banquet\Payments;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Banquet\BanquetEventStatus;
use App\Domain\Pos\Banquet\BanquetPaymentKind;
use App\Domain\Pos\Banquet\BanquetPaymentStatus;
use App\Domain\Pos\Billing\PaymentService;
use App\Domain\Pos\Billing\PosActionException;
use App\Domain\Pos\Booking\BookingDepositService;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Shifts\ShiftService;
use App\Models\BanquetEvent;
use App\Models\BanquetPayment;
use App\Models\BanquetPaymentEntry;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * P6-53: taking the money of an event's payment schedule.
 *
 *  - pay (the till, pos.take_payment): cash, card (card type and the terminal's transaction id) or a static QR
 *    (optional reference) against one unpaid row — the whole row or a part of it (the rest stays an unpaid row). The
 *    money lands in the open shift of the terminal (its X/Z counts it under `banquets`, read from
 *    banquet_payment_entries like the vouchers; never copied into cash_movements), then an ADVANCE receipt is issued at
 *    once (FiscalIssuer; its key is the entry's, so a retry never issues a second one). The event becomes `confirmed`
 *    once its deposit row is paid in full. Idempotent by the Idempotency-Key. A dynamic Click/Payme/UzQR QR is bound to
 *    a bill (QrPayments) and is not offered for schedule rows yet (stage B decision);
 *  - bank transfer (the manager, banquets.manage): recorded with its reference and date; no shift, no receipt (the
 *    invoice/ESF is issued outside the system — owner decision to confirm with the accountant); counted in the event's
 *    paid total and offset in the final sale receipt as prepayment like the advances.
 */
class BanquetPaymentService
{
    public const TILL_TENDERS = [PosTender::CASH, PosTender::CARD, PosTender::QR];

    public const BANK_TRANSFER = 'bank_transfer';

    public const FISCAL_SUBJECT = 'banquet_payment_entry';

    public function __construct(
        protected ShiftService $shifts,
        protected AuditLogger $audit,
        protected BanquetSchedule $schedule,
        protected BanquetFiscalLines $lines,
    ) {}

    /**
     * @param  array{amount?: string|null, tender: string, card_type?: string|null, transaction_id?: string|null, reference?: string|null}  $input
     *
     * @throws PosActionException
     */
    public function pay(BanquetEvent $event, BanquetPayment $row, array $input, ?string $shiftUuid, User $user, ?Device $device, ?string $key, ?\Closure $applied = null): BanquetPaymentEntry
    {
        $tender = PosTender::tryFrom((string) $input['tender']);
        if (!in_array($tender, self::TILL_TENDERS, true)) {
            throw PosActionException::invalid('tender_invalid', "Tadbir to'lovi kassada naqd, karta yoki QR bilan olinadi.");
        }
        $cardType = null;
        $transactionId = null;
        if ($tender === PosTender::CARD) {
            $cardType = strtolower(trim((string) ($input['card_type'] ?? '')));
            $transactionId = trim((string) ($input['transaction_id'] ?? ''));
            if (!in_array($cardType, PaymentService::CARD_TYPES, true)) {
                throw PosActionException::invalid('card_type_required', 'Karta turini tanlang (Uzcard, Humo, Visa...).');
            }
            if ($transactionId === '') {
                throw PosActionException::invalid('transaction_id_required', 'Bank terminali chekidagi tranzaksiya raqamini kiriting.');
            }
        }
        $reference = $tender === PosTender::QR && filled($input['reference'] ?? null) ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

        $amount = $this->amount($row, $input['amount'] ?? null);
        $store = Store::findOrFail($event->store_id);
        // Built before the money is recorded: a fiscal setup problem (no MXIK for advances) refuses the payment
        $line = $this->lines->advance($store, $amount, $row->kind);
        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $event->branch_id);

        try {
            $entry = DB::transaction(function () use ($event, $row, $amount, $tender, $cardType, $transactionId, $reference, $shift, $user, $device, $key, $applied) {
                $shift = PosShift::lockForMoney((int) $shift->id);
                if (!$shift->isOpen()) {
                    throw PosActionException::conflict('shift_closed', 'Smena yopilgan.');
                }
                [$event, $row, $existing] = $this->lock($event, $row, $key);
                if ($existing !== null) {
                    return $existing;
                }
                $row = $this->schedule->split($row, $this->amount($row, $amount->toString()));

                return $this->record($event, $row, BanquetEntryType::PAYMENT, $tender->value, [
                    'card_type' => $cardType,
                    'transaction_id' => $transactionId,
                    'reference' => $reference,
                    'shift_id' => $shift->id,
                    'device_id' => $device?->id,
                ], $user, $key, $applied);
            });
        } catch (UniqueConstraintViolationException) {
            $entry = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('idempotency_key', $key)->firstOrFail();
        }

        if ($entry->fiscal_receipt_uuid === null && $entry->fiscal_status === null) {
            $this->issueAdvance($entry, $line);
        }

        return $entry->refresh();
    }

    /**
     * The manager records a bank transfer against a row (all of it or a part): `reference` (the payment order), `paid_on`.
     *
     * @param  array{amount?: string|null, reference: string, paid_on?: string|null}  $input
     */
    public function transfer(BanquetEvent $event, BanquetPayment $row, array $input, User $user, ?string $key, ?\Closure $applied = null): BanquetPaymentEntry
    {
        $amount = $this->amount($row, $input['amount'] ?? null);
        $reference = mb_substr(trim((string) $input['reference']), 0, 120);
        $branch = Branch::findOrFail($event->branch_id);
        $paidOn = filled($input['paid_on'] ?? null) ? Carbon::parse((string) $input['paid_on'])->toDateString() : BusinessDay::of($branch);
        if ($paidOn > BusinessDay::of($branch)) {
            throw PosActionException::invalid('paid_on_future', "O'tkazma sanasi kelajakda bo'lmasin.");
        }

        try {
            return DB::transaction(function () use ($event, $row, $amount, $reference, $paidOn, $user, $key, $applied) {
                [$event, $row, $existing] = $this->lock($event, $row, $key);
                if ($existing !== null) {
                    return $existing;
                }
                $row = $this->schedule->split($row, $this->amount($row, $amount->toString()));

                return $this->record($event, $row, BanquetEntryType::TRANSFER, self::BANK_TRANSFER, [
                    'reference' => $reference,
                    'business_date' => $paidOn,
                ], $user, $key, $applied);
            });
        } catch (UniqueConstraintViolationException) {
            return BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('idempotency_key', $key)->firstOrFail();
        }
    }

    /**
     * Review-style retry: an advance receipt that could not be issued (`error`) or that the provider refused (`failed`)
     * is sent again under the same key (the same receipt, never a second).
     */
    public function retryReceipt(BanquetPaymentEntry $entry): void
    {
        if ($entry->type !== BanquetEntryType::PAYMENT || !in_array($entry->fiscal_status, ['error', 'failed'], true)) {
            return;
        }
        $row = BanquetPayment::find($entry->banquet_payment_id);
        $store = Store::findOrFail($entry->store_id);
        $this->issueAdvance($entry, $this->lines->advance($store, Money::of($entry->amount), $row->kind ?? BanquetPaymentKind::INTERIM), true);
    }

    /**
     * The event and the row locked; the entry an earlier request with the same key recorded (a retry), if any.
     *
     * @return array{0: BanquetEvent, 1: BanquetPayment, 2: BanquetPaymentEntry|null}
     */
    private function lock(BanquetEvent $event, BanquetPayment $row, ?string $key): array
    {
        $event = BanquetEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
        if ($key !== null && ($existing = BanquetPaymentEntry::where('banquet_event_id', $event->id)->where('idempotency_key', $key)->first())) {
            return [$event, $row, $existing];
        }
        if (!in_array($event->status, [BanquetEventStatus::HOLD, BanquetEventStatus::CONFIRMED, BanquetEventStatus::IN_PROGRESS], true)) {
            throw PosActionException::conflict('event_not_payable', "Tadbir \"{$event->status->label()}\" holatida: to'lov olib bo'lmaydi.");
        }
        $row = BanquetPayment::whereKey($row->id)->where('banquet_event_id', $event->id)->lockForUpdate()->firstOrFail();
        if ($row->status !== BanquetPaymentStatus::DUE) {
            throw PosActionException::conflict('payment_not_due', "Bu qator allaqachon to'langan.");
        }

        return [$event, $row, null];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function record(BanquetEvent $event, BanquetPayment $row, BanquetEntryType $type, string $tender, array $fields, User $user, ?string $key, ?\Closure $applied): BanquetPaymentEntry
    {
        $branch = Branch::findOrFail($event->branch_id);
        $amount = Money::of($row->amount);
        $row->forceFill([
            'status' => BanquetPaymentStatus::PAID,
            'tender' => $tender,
            'card_type' => $fields['card_type'] ?? null,
            'transaction_id' => $fields['transaction_id'] ?? null,
            'reference' => $fields['reference'] ?? null,
            'shift_id' => $fields['shift_id'] ?? null,
            'device_id' => $fields['device_id'] ?? null,
            'paid_at' => now(),
            'paid_by' => $user->id,
            'idempotency_key' => $key,
        ])->save();

        $entry = BanquetPaymentEntry::create([
            'store_id' => $event->store_id,
            'branch_id' => $event->branch_id,
            'banquet_event_id' => $event->id,
            'banquet_payment_id' => $row->id,
            'type' => $type,
            'amount' => $amount->toString(),
            'tender' => $tender,
            'business_date' => $fields['business_date'] ?? BusinessDay::of($branch),
            'user_id' => $user->id,
            'idempotency_key' => $key,
        ] + array_intersect_key($fields, array_flip(['card_type', 'transaction_id', 'reference', 'shift_id', 'device_id'])));

        $before = $event->status;
        $event->paid_total = Money::of($event->paid_total)->plus($amount)->toString();
        // The deposit (zaklad) confirms the event once no part of it is left unpaid; a schedule without a deposit
        // row: the first payment does
        $depositDue = BanquetPayment::where('banquet_event_id', $event->id)->where('kind', BanquetPaymentKind::DEPOSIT->value)
            ->where('status', BanquetPaymentStatus::DUE->value)->exists();
        if ($event->status === BanquetEventStatus::HOLD && !$depositDue) {
            $event->status = BanquetEventStatus::CONFIRMED;
            $event->confirmed_at = now();
            $event->hold_expires_at = null;
            event(new \App\Domain\Pos\Banquet\BanquetEventChanged($event, BanquetEventStatus::CONFIRMED->value, false));
        }
        $event->version = (int) $event->version + 1;
        $event->save();

        $this->audit->record($type === BanquetEntryType::PAYMENT ? 'banquet.payment_taken' : 'banquet.transfer_recorded', $event, ['status' => $before->value], ['status' => $event->status->value], array_filter([
            'payment_uuid' => $row->uuid,
            'entry_uuid' => $entry->uuid,
            'kind' => $row->kind->value,
            'amount' => $amount->toString(),
            'tender' => $tender,
            'reference' => $fields['reference'] ?? null,
            'shift_id' => $fields['shift_id'] ?? null,
        ], fn ($value) => $value !== null), ['actor' => $user]);

        if ($applied) {
            $applied();
        }

        return $entry;
    }

    private function amount(BanquetPayment $row, mixed $input): Money
    {
        $amount = filled($input) ? Money::of((string) $input) : Money::of($row->amount);
        if (!$amount->isPositive() || !$amount->roundTo(2)->equals($amount)) {
            throw PosActionException::invalid('amount_invalid', "To'lov summasi musbat bo'lsin.");
        }
        if ($amount->isGreaterThan($row->amount)) {
            throw PosActionException::invalid('amount_exceeds_row', "Summa qatordagidan ko'p: " . Money::of($row->amount)->toString() . " so'mdan oshmasin.", [
                'row_amount' => Money::of($row->amount)->toString(),
            ]);
        }

        return $amount;
    }

    private function issueAdvance(BanquetPaymentEntry $entry, \App\Domain\Fiscal\FiscalLine $line, bool $again = false): void
    {
        $document = new FiscalDocument(
            FiscalReceiptType::ADVANCE,
            (int) $entry->store_id,
            (int) $entry->branch_id,
            $entry->device_id !== null ? (int) $entry->device_id : null,
            [$line],
            [new FiscalPayment(BookingDepositService::formOf($entry->tender), Money::of($entry->amount), cardType: $entry->card_type, transactionId: $entry->transaction_id)],
            Money::of($entry->amount),
            "banquet_payment:{$entry->uuid}:advance",
            null,
            self::FISCAL_SUBJECT,
            (int) $entry->id,
            $entry->created_at,
        );
        self::issue($entry, $document, $again);
        if ($entry->banquet_payment_id !== null) {
            BanquetPayment::whereKey($entry->banquet_payment_id)->update(['fiscal_receipt_uuid' => $entry->fiscal_receipt_uuid, 'fiscal_status' => $entry->fiscal_status]);
        }
    }

    /**
     * The money is already recorded: a receipt that cannot be issued now stays visible as fiscal_status `error` (the
     * fiscal queue retries a `pending` one) instead of failing the request.
     */
    public static function issue(BanquetPaymentEntry $entry, FiscalDocument $document, bool $again = false): void
    {
        try {
            $issuer = app(FiscalIssuer::class);
            $result = $again ? $issuer->reissue($document) : $issuer->issue($document);
        } catch (Throwable $e) {
            report($e);
            Log::warning("Banquet payment entry {$entry->uuid}: the {$document->type->value} receipt failed: {$e->getMessage()}");
            $entry->forceFill(['fiscal_status' => 'error'])->save();

            return;
        }
        $entry->forceFill(['fiscal_receipt_uuid' => $result->receiptUuid, 'fiscal_status' => $result->status])->save();
    }
}
