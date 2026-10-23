<?php

namespace App\Domain\Pos\Billing;

use App\Domain\Pos\Contracts\AdvanceLedger;
use App\Domain\Pos\Contracts\CustomerAccountLedger;
use App\Domain\Pos\Contracts\PosTender;
use App\Domain\Pos\Shifts\ShiftOwnerSummary;
use App\Domain\Pos\Shifts\ShiftReport;
use App\Domain\Pos\Shifts\ShiftService;
use App\Enums\Permission;
use App\Enums\PaymentStatus;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Money given back from a paid POS bill within the shift it was taken in (pos.refund, POS_SPEC 2.10, L3): a reason,
 * the payment it comes from, never more than is left of that payment. A cashier without pos.refund needs a manager's
 * approval. Cash leaves the drawer (a refund cash movement, never more than the drawer holds, checked under the
 * shift's row lock so a Z closing at the same moment never misses it); card and QR go back through their provider.
 * Review P3-35: a deposit part (PosTender::PREPAYMENT) goes back to the booking's deposit (AdvanceLedger::reverse:
 * to be offset again, refunded or retained in bookings), a nasiya part (PosTender::ACCOUNT) comes off the customer's
 * debt (CustomerAccountLedger::reverse); neither moves cash. Every refund asks for a REFUND receipt naming the bill's
 * sale receipt, its payment in the refunded payment's form (the deposit part as `prepayment`, the nasiya part as
 * `credit`). A payment refunded in full is `refunded`; an order whose payments all are, too (and may be cancelled).
 */
final class RefundService
{
    public const TENDERS = [PosTender::CASH, PosTender::CARD, PosTender::QR, PosTender::PREPAYMENT, PosTender::ACCOUNT];

    public function __construct(
        private readonly ShiftService $shifts,
        private readonly ShiftReport $report,
        private readonly ManagerApprovalService $approvals,
        private readonly BillFiscalizer $fiscalizer,
        private readonly AuditLogger $audit,
        private readonly ShiftOwnerSummary $owner,
        private readonly AdvanceLedger $advances,
        private readonly CustomerAccountLedger $accounts,
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

        $refund = DB::transaction(function () use ($user, $payment, $order, $tender, $amount, $reason, $approvalToken, $shift, $device, $applied) {
            // The shift first (as payments and the Z lock it): closed meanwhile, nothing leaves its drawer
            $shift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
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

            $approvedBy = $this->approvals->authorize($user, Permission::POS_REFUND, $order, $approvalToken, $device);

            if ($tender === PosTender::CASH && $amount->isGreaterThan($this->report->expectedCash($shift))) {
                throw PosActionException::conflict('not_enough_cash', "Kassada buncha naqd pul yo'q.");
            }

            $refund = PosRefund::create([
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
            ]);
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
        });

        return $this->fiscalizer->issueRefund($refund);
    }
}
