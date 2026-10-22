<?php

namespace App\Domain\Pos\Billing;

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
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Money given back from a paid POS bill within the shift it was taken in (pos.refund, POS_SPEC 2.10, L3): a reason,
 * the payment it comes from (cash, card or QR; a deposit or nasiya part is settled in bookings and accounts), never
 * more than is left of that payment. A cashier without pos.refund needs a manager's approval. Cash leaves the drawer
 * (a refund cash movement, never more than the drawer holds); a REFUND receipt naming the bill's sale receipt is
 * asked for. A payment refunded in full is `refunded`; an order whose payments all are, too.
 */
final class RefundService
{
    public const TENDERS = [PosTender::CASH, PosTender::CARD, PosTender::QR];

    public function __construct(
        private readonly ShiftService $shifts,
        private readonly ShiftReport $report,
        private readonly ManagerApprovalService $approvals,
        private readonly BillFiscalizer $fiscalizer,
        private readonly AuditLogger $audit,
        private readonly ShiftOwnerSummary $owner,
    ) {}

    /**
     * @throws PosActionException
     */
    public function refund(User $user, Payment $payment, Money $amount, string $reason, ?string $approvalToken, ?string $shiftUuid, ?Device $device, ?Closure $applied = null): PosRefund
    {
        $order = Order::findOrFail($payment->order_id);
        $tender = PosTender::tryFrom((string) $payment->provider);
        if (!in_array($tender, self::TENDERS, true) || $payment->bill_id === null) {
            throw PosActionException::invalid('refund_tender_invalid', "Faqat naqd, karta yoki QR to'lovini qaytarish mumkin (avans va nasiya o'z bo'limida).");
        }
        if (!$amount->isPositive()) {
            throw PosActionException::invalid('amount_invalid', "Summa musbat bo'lishi kerak.");
        }

        $shift = $this->shifts->forAction($user, $shiftUuid, (int) $order->branch_id);
        if ((int) $payment->shift_id !== (int) $shift->id) {
            throw PosActionException::conflict('refund_outside_shift', "Faqat shu smenada olingan to'lovni qaytarish mumkin.");
        }

        $refund = DB::transaction(function () use ($user, $payment, $order, $tender, $amount, $reason, $approvalToken, $shift, $device, $applied) {
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
