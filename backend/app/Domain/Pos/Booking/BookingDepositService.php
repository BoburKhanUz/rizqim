<?php

namespace App\Domain\Pos\Booking;

use App\Domain\Fiscal\FiscalDocument;
use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Fiscal\FiscalIssueResult;
use App\Domain\Fiscal\FiscalLine;
use App\Domain\Fiscal\FiscalLineBuilder;
use App\Domain\Fiscal\FiscalPayment;
use App\Domain\Fiscal\FiscalPaymentForm;
use App\Domain\Fiscal\FiscalReceiptType;
use App\Domain\Pos\Accounts\PosOwnerAlerts;
use App\Domain\Pos\Contracts\PosTender;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Store;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Booking deposits (avans, research report R16 and §5 "bo'nak → sotuv"):
 *
 *  - take: the money is recorded first, then an ADVANCE receipt is issued for it (one "deposit" line, FiscalLineBuilder::
 *    forCharge('deposit')). An advance receipt is not revenue: the full SALE receipt at the end of the evening shows
 *    the deposit as prepayment (P3-18, AdvanceLedger). Idempotent: the same Idempotency-Key returns the same deposit,
 *    and the receipt's own idempotency key is the deposit's, so a retry never issues a second receipt;
 *  - refund: what is left of it goes back to the guest, with a manager's approval and a reason. No receipt by default
 *    (UNVERIFIED rule: the accountant's advice is a refund act and the original advance receipt; a store may switch on
 *    a REFUND receipt with fiscal_settings.deposit_refund_receipt);
 *  - retain: after a cancellation or a no-show (or what is left after the bill) the deposit is kept, with a manager's
 *    approval and a reason, and becomes revenue: a SALE receipt for the booking service (`booking_fee` charge) paid
 *    from the advance (UNVERIFIED rule T3; fiscal_settings.retained_deposit_receipt switches it off).
 *
 * The owner hears about every refund and retention on Telegram.
 */
class BookingDepositService
{
    /** Manager approvals: giving money back is a refund; keeping it decides the cancellation's cost to the guest */
    public const REFUND_PERMISSION = 'pos.refund';

    public const RETAIN_PERMISSION = 'orders.cancel';

    public const MAX_AMOUNT = '999999999.99';

    public function __construct(
        protected BookingService $bookings,
        protected ApprovalGate $approvals,
        protected AuditLogger $audit,
        protected PosOwnerAlerts $alerts,
    ) {}

    public function take(Booking $booking, Money $amount, PosTender $tender, ?string $cardType, ?string $transactionId, ?string $key, User $actor, ?int $deviceId): BookingPayment
    {
        if (!in_array($tender, [PosTender::CASH, PosTender::CARD, PosTender::QR], true)) {
            throw new BookingException("Avans faqat naqd, karta yoki QR bilan olinadi.", 'invalid_tender');
        }
        if (!$amount->isPositive() || $amount->isGreaterThan(self::MAX_AMOUNT)) {
            throw new BookingException("Avans summasi noto'g'ri.", 'invalid_amount');
        }

        $store = Store::findOrFail($booking->store_id);
        // Built before the money is recorded: a fiscal setup problem (no MXIK for deposits) refuses the deposit
        $line = app(FiscalLineBuilder::class)->forCharge($store, 'deposit', $amount);

        $confirmed = false;
        $payment = DB::transaction(function () use ($booking, $amount, $tender, $cardType, $transactionId, $key, $actor, $deviceId, &$confirmed) {
            $booking = $this->bookings->lockFresh($booking, null);

            if ($key !== null && ($existing = BookingPayment::where('booking_id', $booking->id)->where('idempotency_key', $key)->first())) {
                return $existing;
            }

            if (!in_array($booking->status, [BookingStatus::REQUESTED, BookingStatus::CONFIRMED], true)) {
                throw new BookingException("Bron \"{$booking->status->label()}\" holatida: avans olib bo'lmaydi.", 'booking_not_editable');
            }

            $payment = BookingPayment::create([
                'booking_id' => $booking->id,
                'store_id' => $booking->store_id,
                'branch_id' => $booking->branch_id,
                'amount' => $amount->toString(),
                'tender' => $tender->value,
                'card_type' => $tender === PosTender::CARD ? $cardType : null,
                'transaction_id' => $tender !== PosTender::CASH ? $transactionId : null,
                'status' => BookingPaymentStatus::TAKEN,
                'device_id' => $deviceId,
                'user_id' => $actor->id,
                'idempotency_key' => $key,
            ]);

            // A deposit confirms the booking (the report's "avans olindi" follows "tasdiqlandi")
            if ($booking->status === BookingStatus::REQUESTED) {
                $booking->status = BookingStatus::CONFIRMED;
                $booking->confirmed_at = now();
                $confirmed = true;
            }
            $booking->version = $booking->version + 1;
            $booking->save();

            $this->audit->record('booking.deposit_taken', $booking, [], [], [
                'payment_uuid' => $payment->uuid,
                'amount' => $amount->toString(),
                'tender' => $tender->value,
            ], ['actor' => $actor]);

            return $payment;
        });

        if ($payment->fiscal_receipt_uuid === null) {
            $this->issue($payment, 'fiscal', new FiscalDocument(
                FiscalReceiptType::ADVANCE,
                (int) $booking->store_id,
                (int) $booking->branch_id,
                $deviceId,
                [$line],
                [new FiscalPayment(self::formOf($payment->tender), Money::of($payment->amount), cardType: $payment->card_type, transactionId: $payment->transaction_id)],
                Money::of($payment->amount),
                "booking_deposit:{$payment->uuid}:advance",
                null,
                'booking_payment',
                (int) $payment->id,
            ));
        }

        $this->bookings->changed($booking->refresh());

        // P3-38: the first deposit confirmed it: the confirmation (with the deposit in it) goes out by itself
        if ($confirmed) {
            $this->bookings->confirmedNow($booking, $actor);
        }

        return $payment->refresh();
    }

    public function refund(Booking $booking, BookingPayment $payment, string $reason, ?string $approvalToken, User $actor, ?int $deviceId): BookingPayment
    {
        [$payment, $amount] = $this->settle($booking, $payment, BookingPaymentStatus::REFUNDED, $reason, $approvalToken, $actor, $deviceId);

        $store = Store::findOrFail($booking->store_id);
        if (PosSettingsReader::fiscalFlag($store, 'deposit_refund_receipt')) {
            $this->issue($payment, 'settle', new FiscalDocument(
                FiscalReceiptType::REFUND,
                (int) $booking->store_id,
                (int) $booking->branch_id,
                $deviceId,
                [app(FiscalLineBuilder::class)->forCharge($store, 'deposit', $amount)],
                [new FiscalPayment(self::formOf($payment->tender), $amount, cardType: $payment->card_type, transactionId: $payment->transaction_id)],
                $amount,
                "booking_deposit:{$payment->uuid}:refund",
                $payment->fiscal_receipt_uuid,
                'booking_payment',
                (int) $payment->id,
            ));
        }

        $this->alerts->send((int) $booking->store_id, "↩️ Avans qaytarildi: " . PosOwnerAlerts::sum($amount)
            . "\nBron: {$this->describe($booking)}\nSabab: {$reason}\nTasdiqladi: " . $this->nameOf($payment->approved_by));

        return $payment->refresh();
    }

    public function retain(Booking $booking, BookingPayment $payment, string $reason, ?string $approvalToken, User $actor, ?int $deviceId): BookingPayment
    {
        [$payment, $amount] = $this->settle($booking, $payment, BookingPaymentStatus::RETAINED, $reason, $approvalToken, $actor, $deviceId);

        $store = Store::findOrFail($booking->store_id);
        if (PosSettingsReader::fiscalFlag($store, 'retained_deposit_receipt')) {
            // The money was received with the advance receipt: the sale is paid by prepayment
            $this->issue($payment, 'settle', new FiscalDocument(
                FiscalReceiptType::SALE,
                (int) $booking->store_id,
                (int) $booking->branch_id,
                $deviceId,
                [app(FiscalLineBuilder::class)->forCharge($store, 'booking_fee', $amount)],
                [new FiscalPayment(self::formOf($payment->tender), $amount, prepayment: true, cardType: $payment->card_type, transactionId: $payment->transaction_id)],
                $amount,
                "booking_deposit:{$payment->uuid}:retain",
                null,
                'booking_payment',
                (int) $payment->id,
            ));
        }

        $this->alerts->send((int) $booking->store_id, "⚠️ Avans ushlab qolindi: " . PosOwnerAlerts::sum($amount)
            . "\nBron: {$this->describe($booking)}\nSabab: {$reason}\nTasdiqladi: " . $this->nameOf($payment->approved_by));

        return $payment->refresh();
    }

    public static function formOf(string $tender): FiscalPaymentForm
    {
        return match ($tender) {
            PosTender::CARD->value => FiscalPaymentForm::CARD,
            PosTender::QR->value => FiscalPaymentForm::QR,
            default => FiscalPaymentForm::CASH,
        };
    }

    /**
     * Closes what is left of the deposit as refunded or retained, under the booking's and the deposit's locks.
     *
     * @return array{0: BookingPayment, 1: Money}
     */
    private function settle(Booking $booking, BookingPayment $payment, BookingPaymentStatus $to, string $reason, ?string $approvalToken, User $actor, ?int $deviceId): array
    {
        $result = DB::transaction(function () use ($booking, $payment, $to, $reason, $approvalToken, $actor, $deviceId) {
            $booking = $this->bookings->lockFresh($booking, null);
            $payment = BookingPayment::whereKey($payment->id)->where('booking_id', $booking->id)->lockForUpdate()->firstOrFail();

            $allowed = $to === BookingPaymentStatus::REFUNDED
                // Not while the guests are here: their bill may still take it
                ? [BookingStatus::REQUESTED, BookingStatus::CONFIRMED, BookingStatus::CANCELED, BookingStatus::NO_SHOW, BookingStatus::COMPLETED]
                : [BookingStatus::CANCELED, BookingStatus::NO_SHOW, BookingStatus::COMPLETED];
            if (!in_array($booking->status, $allowed, true)) {
                throw new BookingException($to === BookingPaymentStatus::REFUNDED
                    ? "Bron \"{$booking->status->label()}\" holatida: avansni qaytarib bo'lmaydi."
                    : "Avans faqat bekor qilingan, kelmagan yoki yopilgan bron uchun ushlab qolinadi.", 'invalid_deposit_state');
            }

            $amount = $payment->remaining();
            if ($payment->status !== BookingPaymentStatus::TAKEN || !$amount->isPositive()) {
                throw new BookingException("Bu avansdan qaytaradigan yoki ushlab qoladigan summa qolmagan.", 'deposit_settled');
            }

            $approver = $this->approvals->approver(
                $actor,
                $to === BookingPaymentStatus::REFUNDED ? self::REFUND_PERMISSION : self::RETAIN_PERMISSION,
                $approvalToken,
                $deviceId,
                $booking,
            );

            $payment->forceFill([
                $to === BookingPaymentStatus::REFUNDED ? 'refunded_amount' : 'retained_amount' => $amount->toString(),
                'status' => $to,
                'settle_reason' => $reason,
                'settled_at' => now(),
                'settled_by' => $actor->id,
                'approved_by' => $approver,
            ] + ($to === BookingPaymentStatus::REFUNDED ? $this->refundDrawer($booking, $deviceId) : []))->save();

            $booking->version = $booking->version + 1;
            $booking->save();

            $this->audit->record($to === BookingPaymentStatus::REFUNDED ? 'booking.deposit_refunded' : 'booking.deposit_retained', $booking, [], [], array_filter([
                'payment_uuid' => $payment->uuid,
                'amount' => $amount->toString(),
                'reason' => $reason,
                'approved_by' => $approver,
                'shift_id' => $payment->refunded_shift_id,
            ], fn ($value) => $value !== null), ['actor' => $actor]);

            return [$payment, $amount, $booking];
        });

        $this->bookings->changed($result[2]);

        return [$result[0], $result[1]];
    }

    /**
     * Where a refund is made: the terminal, and the shift open on it in the booking's branch (its X/Z lists the
     * refund; a cash one is paid from its drawer, so ShiftReport takes it off the expected cash). The shift row is
     * locked so a closing Z cannot miss a refund made at the same moment. A refund from the web panel or a terminal
     * without an open shift belongs to no shift (no drawer paid it).
     *
     * @return array{refunded_device_id: int|null, refunded_shift_id: int|null}
     */
    private function refundDrawer(Booking $booking, ?int $deviceId): array
    {
        $shiftId = null;
        if ($deviceId !== null) {
            $shiftId = \App\Models\PosShift::where('device_id', $deviceId)
                ->where('branch_id', $booking->branch_id)
                ->where('status', \App\Domain\Pos\Shifts\ShiftStatus::OPEN->value)
                ->lockForUpdate()
                ->value('id');
        }

        return ['refunded_device_id' => $deviceId, 'refunded_shift_id' => $shiftId !== null ? (int) $shiftId : null];
    }

    /**
     * Issues the receipt and records it on the deposit (`fiscal`: the advance receipt; `settle`: the refund or
     * retention receipt). The money is already recorded: a receipt that cannot be issued now stays visible as
     * fiscal_status `error` (P3-11's queue retries a `pending` one) instead of failing the request.
     */
    private function issue(BookingPayment $payment, string $which, FiscalDocument $document): ?FiscalIssueResult
    {
        try {
            $result = app(FiscalIssuer::class)->issue($document);
        } catch (Throwable $e) {
            report($e);
            Log::warning("Booking deposit {$payment->uuid}: the {$document->type->value} receipt failed: {$e->getMessage()}");
            $payment->forceFill([$which === 'fiscal' ? 'fiscal_status' : 'settle_fiscal_status' => 'error'])->save();

            return null;
        }

        $payment->forceFill($which === 'fiscal'
            ? ['fiscal_receipt_uuid' => $result->receiptUuid, 'fiscal_status' => $result->status]
            : ['settle_receipt_uuid' => $result->receiptUuid, 'settle_fiscal_status' => $result->status])->save();

        return $result;
    }

    private function describe(Booking $booking): string
    {
        $tz = \App\Support\StoreTimezone::of(Store::find($booking->store_id));

        return $booking->starts_at->copy()->setTimezone($tz)->format('d.m.Y H:i') . ", {$booking->contact_name}";
    }

    private function nameOf(mixed $userId): string
    {
        return $userId !== null ? (string) (User::whereKey((int) $userId)->value('name') ?? "#{$userId}") : '—';
    }
}
