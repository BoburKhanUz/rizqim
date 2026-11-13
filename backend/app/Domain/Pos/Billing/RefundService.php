<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\QrPayments\QrRefundResult;
use App\Domain\Pos\QrPayments\QrRefunds;
use App\Domain\Pos\QrPayments\QrRefundStatus;
use App\Domain\Pos\Shifts\ShiftOwnerSummary;
use App\Domain\Pos\Shifts\ShiftReport;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\Permission;
use App\Enums\PaymentStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosQrPayment;
use App\Models\PosQrRefundAttempt;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Money given back from a paid POS bill within the shift it was taken in (pos.refund, POS_SPEC 2.10, L3): a reason,
 * the payment it comes from, never more than is left of that payment. A cashier without pos.refund needs a manager's
 * approval. Cash leaves the drawer (a refund cash movement, never more than the drawer holds, checked under the
 * shift's row lock so a Z closing at the same moment never misses it); card and QR go back through their provider.
 * Review P3-35: a deposit part (PosTender::PREPAYMENT) goes back to the booking's deposit (AdvanceLedger::reverse:
 * to be offset again, refunded or retained in bookings), a nasiya part (PosTender::ACCOUNT) comes off the customer's
 * debt (CustomerAccountLedger::reverse); neither moves cash. Every refund asks for a REFUND receipt naming the bill's
 * sale receipt, its payment in the refunded payment's form (the deposit part as `prepayment`, the nasiya part as
 * `credit`). P6-43: a gift certificate part (PosTender::VOUCHER) goes back onto the certificate (VoucherLedger::
 * reverse; `prepayment` on the refund receipt), never as cash. Review P6W4 C2: a QR payment a provider confirmed
 * is refunded in two phases (refundQr, QrRefunds): the provider is never asked while the shift's lock is held. A payment refunded in full is `refunded`; an order whose payments all are, too (and may be cancelled).
 */
final class RefundService
{
    public const TENDERS = [PosTender::CASH, PosTender::CARD, PosTender::QR, PosTender::PREPAYMENT, PosTender::ACCOUNT, PosTender::VOUCHER];

    public function __construct(
        private readonly ShiftService $shifts,
        private readonly ShiftReport $report,
        private readonly ManagerApprovalService $approvals,
        private readonly BillFiscalizer $fiscalizer,
        private readonly AuditLogger $audit,
        private readonly ShiftOwnerSummary $owner,
        private readonly AdvanceLedger $advances,
        private readonly CustomerAccountLedger $accounts,
        private readonly \App\Domain\Pos\Contracts\VoucherLedger $vouchers,
    ) {}

    /**
     * @throws PosActionException
     */
    public function refund(User $user, Payment $payment, Money $amount, string $reason, ?string $approvalToken, ?string $shiftUuid, ?Device $device, ?Closure $applied = null): PosRefund
    {
        $order = Order::findOrFail($payment->order_id);
        $tender = PosTender::tryFrom((string) $payment->provider);
        if (!in_array($tender, self::TENDERS, true) || $payment->bill_id === null) {
            throw PosActionException::invalid('refund_tender_invalid', "Bu to'lovni kassada qaytarib bo'lmaydi (schyotga tegishli naqd, karta, QR, avans yoki nasiya to'lovi emas).");
        }
        if (!$amount->isPositive()) {
            throw PosActionException::invalid('amount_invalid', "Summa musbat bo'lishi kerak.");
        }

        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $order->branch_id);
        if ((int) $payment->shift_id !== (int) $shift->id) {
            throw PosActionException::conflict('refund_outside_shift', "Faqat shu smenada olingan to'lovni qaytarish mumkin.");
        }

        if ($tender === PosTender::QR && ($qr = PosQrPayment::where('payment_id', $payment->id)->first()) !== null) {
            return $this->refundQr($user, $payment, $order, $qr, $amount, $reason, $approvalToken, $shift, $device, $applied);
        }

        $refund = DB::transaction(function () use ($user, $payment, $order, $tender, $amount, $reason, $approvalToken, $shift, $device, $applied) {
            [$shift, $payment, $bill, $left] = $this->lockAndCheck($shift, $payment, $amount);

            $approvedBy = $this->approvals->authorize($user, Permission::POS_REFUND, $order, $approvalToken, $device);

            if ($tender === PosTender::CASH && $amount->isGreaterThan($this->report->expectedCash($shift))) {
                throw PosActionException::conflict('not_enough_cash', "Kassada buncha naqd pul yo'q.");
            }

            return $this->record($user, $payment, $order, $bill, $shift, $tender, $amount, $left, $reason, $approvedBy, $device, $applied);
        });

        return $this->fiscalizer->issueRefund($refund);
    }

    /**
     * Review P6W4 C2: a QR payment a provider confirmed, in two phases (QrRefunds): (1) a short transaction checks
     * the refund and commits its attempt (`requested`), releasing the shift's lock; (2) the provider is asked outside
     * any transaction; (3) settleQr() records the refund, or the attempt `failed` / `unknown` (409).
     *
     * @throws PosActionException
     */
    private function refundQr(User $user, Payment $payment, Order $order, PosQrPayment $qr, Money $amount, string $reason, ?string $approvalToken, PosShift $shift, ?Device $device, ?Closure $applied): PosRefund
    {
        $qrRefunds = app(QrRefunds::class);
        [$attempt, $statusFirst] = DB::transaction(function () use ($qrRefunds, $user, $payment, $order, $qr, $amount, $reason, $approvalToken, $shift, $device) {
            [$shift, $payment, $bill] = $this->lockAndCheck($shift, $payment, $amount);
            // An open attempt (asked, or not answered) holds the payment: its amount is not refunded again meanwhile
            $attempt = $qrRefunds->resumable((int) $payment->id, $amount, (int) $user->id);

            if ($attempt !== null) {
                // The same refund again: the manager approved it already (its cashier), anyone else asks again
                $approvedBy = (int) $attempt->user_id === (int) $user->id
                    ? $attempt->approved_by
                    : $this->approvals->authorize($user, Permission::POS_REFUND, $order, $approvalToken, $device);
                $statusFirst = $attempt->status !== QrRefundStatus::FAILED;
                $attempt->forceFill(['status' => QrRefundStatus::REQUESTED, 'approved_by' => $approvedBy, 'message' => null])->save();
                $attempt->touch();

                return [$attempt, $statusFirst];
            }

            $approvedBy = $this->approvals->authorize($user, Permission::POS_REFUND, $order, $approvalToken, $device);
            $qrRefunds->assertAmount($qr->refresh(), $amount);

            return [PosQrRefundAttempt::create([
                'uuid' => (string) Str::uuid(),
                'store_id' => $order->store_id,
                'qr_payment_id' => $qr->id,
                'payment_id' => $payment->id,
                'order_id' => $order->id,
                'bill_id' => $bill->id,
                'shift_id' => $shift->id,
                'amount' => $amount->toString(),
                'reason' => $reason,
                'user_id' => $user->id,
                'approved_by' => $approvedBy,
                'device_id' => $device?->id,
                'status' => QrRefundStatus::REQUESTED,
            ]), false];
        });

        return $this->settleQr($attempt, $qrRefunds->ask($attempt, $statusFirst), $applied);
    }

    /**
     * Review P6W4 C2: the provider's answer about a QR refund attempt (asked outside any transaction): done — the
     * refund is recorded (its uuid the attempt's, receipts, the shift's refunds; a shift closed meanwhile still gets it:
     * the money went back), refused — `failed` (409 qr_refund_failed), no answer — `unknown` (409 qr_refund_unknown).
     *
     * @throws PosActionException
     */
    public function settleQr(PosQrRefundAttempt $attempt, QrRefundResult $result, ?Closure $applied = null): PosRefund
    {
        $qr = PosQrPayment::findOrFail($attempt->qr_payment_id);
        if (!$result->ok) {
            $status = $result->unknown ? QrRefundStatus::UNKNOWN : QrRefundStatus::FAILED;
            PosQrRefundAttempt::whereKey($attempt->id)->where('status', QrRefundStatus::REQUESTED->value)
                ->update(['status' => $status->value, 'message' => mb_substr((string) $result->message, 0, 500), 'updated_at' => now()]);
            Log::warning('POS QR refund ' . $status->value, ['attempt' => $attempt->uuid, 'qr' => $qr->uuid, 'message' => $result->message]);
            if ($result->unknown) {
                throw QrRefunds::unknownError();
            }

            throw PosActionException::conflict('qr_refund_failed', "{$qr->provider->label()} pulni qaytarmadi ({$result->message}). Qaytarish amalga oshmadi: keyinroq qayta urinib ko'ring yoki pulni {$qr->provider->label()} kabinetidan qaytaring.", ['provider' => $qr->provider->value]);
        }

        $refund = DB::transaction(function () use ($attempt, $result, $applied) {
            $shift = PosShift::lockForMoney((int) $attempt->shift_id);
            $payment = Payment::whereKey($attempt->payment_id)->lockForUpdate()->firstOrFail();
            $attempt = PosQrRefundAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            if ($attempt->status === QrRefundStatus::REFUNDED) {
                return PosRefund::findOrFail($attempt->refund_id);
            }
            if ($attempt->status !== QrRefundStatus::REQUESTED) {
                throw QrRefunds::unknownError();
            }
            $order = Order::findOrFail($attempt->order_id);
            $bill = OrderBill::findOrFail($attempt->bill_id);
            $user = User::findOrFail($attempt->user_id);
            $device = $attempt->device_id !== null ? Device::find($attempt->device_id) : null;
            $amount = Money::of($attempt->amount);
            $left = Money::of($payment->amount)->minus(Money::of(PosRefund::where('payment_id', $payment->id)->sum('amount')));

            return $this->record($user, $payment, $order, $bill, $shift, PosTender::QR, $amount, $left, $attempt->reason, $attempt->approved_by, $device, $applied,
                fn (PosRefund $refund) => app(QrRefunds::class)->settle($attempt, $refund, $result), (string) $attempt->uuid);
        });

        return $this->fiscalizer->issueRefund($refund);
    }

    /**
     * Locks the shift (first, as payments and the Z do: closed meanwhile, nothing leaves its drawer) and the payment,
     * and checks the bill has its sale receipt and the amount fits what is left of the payment.
     *
     * @return array{0: PosShift, 1: Payment, 2: OrderBill, 3: Money}
     *
     * @throws PosActionException
     */
    private function lockAndCheck(PosShift $shift, Payment $payment, Money $amount): array
    {
        $shift = PosShift::lockForMoney((int) $shift->id);
        if (!$shift->isOpen()) {
            throw PosActionException::conflict('shift_closed', 'Smena yopilgan.');
        }
        $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
        $bill = OrderBill::findOrFail($payment->bill_id);
        if ($bill->status !== BillStatus::PAID || $bill->getAttribute('fiscal_receipt_uuid') === null) {
            throw PosActionException::conflict('bill_not_fiscalized', "Schyotning sotuv cheki hali chiqmagan: qaytarishni keyinroq qiling.");
        }

        $refunded = Money::of(PosRefund::where('payment_id', $payment->id)->sum('amount'));
        $left = Money::of($payment->amount)->minus($refunded);
        if ($amount->isGreaterThan($left)) {
            throw PosActionException::invalid('refund_exceeds_payment', "Qaytarish summasi to'lovdan qolganidan ({$left->toString()} so'm) ko'p.", ['left' => $left->toString()]);
        }

        return [$shift, $payment, $bill, $left];
    }

    /**
     * The refund itself, inside the caller's transaction (the shift and the payment locked): the PosRefund, where the
     * money goes back, the payment's (and order's) status, the audit and the owner's message.
     *
     * @param  (Closure(PosRefund): void)|null  $reversed  a QR refund's provider part (QrRefunds::settle)
     *
     * @throws PosActionException
     */
    private function record(User $user, Payment $payment, Order $order, OrderBill $bill, PosShift $shift, PosTender $tender, Money $amount, Money $left, string $reason, ?int $approvedBy, ?Device $device, ?Closure $applied, ?Closure $reversed = null, ?string $uuid = null): PosRefund
    {
        $refund = PosRefund::create(array_filter([
            'uuid' => $uuid,
            'store_id' => $order->store_id,
            'branch_id' => $order->branch_id,
            'order_id' => $order->id,
            'bill_id' => $bill->id,
            'payment_id' => $payment->id,
            'shift_id' => $shift->id,
            'amount' => $amount->toString(),
            'method' => $tender->value,
            'reason' => $reason,
            'user_id' => $user->id,
            'approved_by' => $approvedBy,
            'device_id' => $device?->id,
        ], fn ($value) => $value !== null));
        if ($tender === PosTender::CASH) {
            $this->shifts->recordRefund($shift, $refund, $user, $device, $approvedBy);
        }
        if ($tender === PosTender::PREPAYMENT) {
            $this->advances->reverse($order, $amount, $bill->uuid);
        }
        if ($tender === PosTender::ACCOUNT) {
            $account = (string) (($payment->payload ?? [])['account_uuid'] ?? '');
            if ($account === '') {
                throw PosActionException::conflict('account_unknown', "Nasiya to'lovining hisobi topilmadi: qarzni menejer qo'lda tuzatsin.");
            }
            $this->accounts->reverse($account, $amount, $bill->uuid, (string) $refund->uuid, $approvedBy ?? (int) $user->id);
        }
        if ($tender === PosTender::VOUCHER) {
            // P6-43: back onto the gift certificate, never cash
            $voucher = (string) (($payment->payload ?? [])['voucher_uuid'] ?? '');
            if ($voucher === '') {
                throw PosActionException::conflict('voucher_unknown', "Sertifikat to'lovining sertifikati topilmadi.");
            }
            $this->vouchers->reverse($voucher, $amount, $bill->uuid, (string) $refund->uuid, (int) $user->id, $device?->id, (int) $shift->id);
        }
        if ($reversed) {
            // P6-41: the provider already gave it back (QrRefunds, asked outside this transaction)
            $reversed($refund);
        }

        if ($left->minus($amount)->isZero()) {
            $payment->forceFill(['status' => PaymentStatus::REFUNDED->value])->save();
            $open = Payment::where('order_id', $order->id)->where('status', PaymentStatus::PAID->value)->whereIn('provider', array_map(fn (PosTender $t) => $t->value, PosTender::cases()))->exists();
            if (!$open) {
                Order::whereKey($order->id)->update(['payment_status' => PaymentStatus::REFUNDED->value]);
            }
        }

        $this->audit->record('pos.refund', $order, [], [], array_filter([
            'refund' => $refund->uuid,
            'payment' => $payment->uuid,
            'bill' => $bill->uuid,
            'amount' => $amount->toString(),
            'method' => $tender->value,
            'reason' => $reason,
            'approved_by' => $approvedBy,
        ], fn ($value) => $value !== null), ['actor' => $user, 'device' => $device]);

        $this->owner->refund($refund, $user, $approvedBy);

        if ($applied) {
            $applied();
        }

        return $refund;
    }
}
