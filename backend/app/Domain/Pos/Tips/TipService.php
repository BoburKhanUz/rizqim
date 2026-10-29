<?php

namespace App\Domain\Pos\Tips;

use App\Domain\Pos\Billing\PosActionException;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\PosTipDistribution;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * P6-22 (C2): tips (choy puli) taken with a payment and a manager's corrections of them. Tips are NOT revenue:
 * never a payment row, never on the fiscal receipt (the receipt is the bill: bill total = receipt total, and what
 * the guest handed over = receipt total + tips), counted on their own lines of the X/Z report, and a refund never
 * touches them (a manager corrects one with a reason instead).
 *
 * pos_tips is a ledger: the tip row (`kind` tip) credits its amount to (`mode`, `staff_id`); a correction is one or
 * two signed rows on the same tip (`corrects_id`) — a new amount for the same person, or the amount moved from one
 * person (or the pool) to another. Whatever was corrected, the sums by person and by mode stay exact.
 */
final class TipService
{
    public function __construct(
        private readonly TipSettings $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Records a tip taken with `$payment` (inside the payment's transaction). Personal mode: to `$staffId` (the waiter
     * picked at payment), else the order's waiter, else unassigned. Pool mode: to the branch's pot.
     */
    public function record(Order $order, OrderBill $bill, Payment $payment, PosShift $shift, TipMethod $method, Money $amount, TipSource $source, ?int $staffId, User $user, ?Device $device): PosTip
    {
        $branch = Branch::findOrFail((int) $order->branch_id);
        $mode = $this->settings->mode($branch);
        $recipient = null;
        if ($mode === TipMode::PERSONAL) {
            $recipient = $staffId ?? ($order->waiter_id !== null ? (int) $order->waiter_id : null);
        }

        return PosTip::create([
            'store_id' => (int) $order->store_id,
            'branch_id' => (int) $order->branch_id,
            'order_id' => $order->id,
            'bill_id' => $bill->id,
            'payment_id' => $payment->id,
            'shift_id' => $shift->id,
            'business_date' => $shift->business_date->toDateString(),
            'kind' => TipKind::TIP,
            'method' => $method,
            'source' => $source,
            'mode' => $mode,
            'amount' => $amount->toString(),
            'staff_id' => $recipient,
            'recorded_by' => $user->id,
            'device_id' => $device?->id,
        ]);
    }

    /**
     * The staff member a tip may be given to: a floor member of the store who can work in the branch; null when not.
     */
    public static function recipient(int $storeId, int $branchId, ?int $staffId): ?User
    {
        if ($staffId === null) {
            return null;
        }

        return User::whereKey($staffId)->where('store_id', $storeId)
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::terminalRoles()))
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->first();
    }

    /**
     * The rows of a tip (it and its corrections) summed per (mode, staff): what it gives whom now. Zero groups dropped.
     *
     * @return list<array{mode: TipMode, staff_id: int|null, amount: Money}>
     */
    public function holders(PosTip $tip): array
    {
        $rows = PosTip::where('id', $tip->id)->orWhere('corrects_id', $tip->id)->orderBy('id')->get();
        $groups = [];
        foreach ($rows as $row) {
            $key = $row->mode->value . ':' . ($row->staff_id ?? 0);
            $groups[$key] ??= ['mode' => $row->mode, 'staff_id' => $row->staff_id !== null ? (int) $row->staff_id : null, 'amount' => Money::zero()];
            $groups[$key]['amount'] = $groups[$key]['amount']->plus($row->amount);
        }

        return array_values(array_filter($groups, fn (array $group) => !$group['amount']->isZero()));
    }

    /**
     * The tip's amount now (it and its corrections).
     */
    public function amountOf(PosTip $tip): Money
    {
        return Money::sum('0', ...PosTip::where('id', $tip->id)->orWhere('corrects_id', $tip->id)->pluck('amount')->all());
    }

    /**
     * A manager's correction (tips.manage; the caller checked it or consumed an approval): the tip now gives
     * `$amount` (null: as now) to `$mode`/`$staffId` (personal: the person, null unassigned; pool: the pot). A reason
     * is required. Refused (409 tips_period_closed) when a closed pool split covers the tip's day or shift.
     *
     * @throws PosActionException
     */
    public function correct(User $user, PosTip $tip, ?Money $amount, TipMode $mode, ?int $staffId, string $reason, ?int $approvedBy, ?Device $device, ?\Closure $applied = null): PosTip
    {
        if ($tip->kind !== TipKind::TIP) {
            throw PosActionException::invalid('tip_not_found', "Faqat choy pulining o'zini tuzatish mumkin.");
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw PosActionException::invalid('reason_required', 'Tuzatish sababini yozing.');
        }
        if ($amount !== null && ($amount->isNegative() || !$amount->equals($amount->roundTo(2)))) {
            throw PosActionException::invalid('amount_invalid', "Summa noto'g'ri.");
        }
        if ($mode === TipMode::POOL) {
            $staffId = null;
        }
        if ($staffId !== null && self::recipient((int) $tip->store_id, (int) $tip->branch_id, $staffId) === null) {
            throw PosActionException::invalid('staff_not_found', 'Xodim topilmadi.');
        }

        return DB::transaction(function () use ($user, $tip, $amount, $mode, $staffId, $reason, $approvedBy, $device, $applied) {
            $tip = PosTip::whereKey($tip->id)->lockForUpdate()->firstOrFail();
            if (self::closedFor($tip)) {
                throw PosActionException::conflict('tips_period_closed', "Bu kunning choy puli bo'lingan va yopilgan: tuzatib bo'lmaydi.");
            }

            $holders = $this->holders($tip);
            $current = Money::sum('0', ...array_map(fn (array $holder) => $holder['amount'], $holders));
            $target = $amount ?? $current;

            $rows = [];
            $targetHeld = Money::zero();
            foreach ($holders as $holder) {
                if ($holder['mode'] === $mode && $holder['staff_id'] === $staffId) {
                    $targetHeld = $holder['amount'];
                    continue;
                }
                $rows[] = ['mode' => $holder['mode'], 'staff_id' => $holder['staff_id'], 'amount' => $holder['amount']->negated()];
            }
            $delta = $target->minus($targetHeld);
            if (!$delta->isZero()) {
                $rows[] = ['mode' => $mode, 'staff_id' => $staffId, 'amount' => $delta];
            }
            if ($rows === []) {
                throw PosActionException::invalid('nothing_to_correct', "O'zgartirish yo'q: summa va kimga berilishi avvalgidek.");
            }

            foreach ($rows as $row) {
                PosTip::create([
                    'store_id' => $tip->store_id,
                    'branch_id' => $tip->branch_id,
                    'order_id' => $tip->order_id,
                    'bill_id' => $tip->bill_id,
                    'payment_id' => $tip->payment_id,
                    'shift_id' => $tip->shift_id,
                    'business_date' => $tip->business_date->toDateString(),
                    'kind' => TipKind::CORRECTION,
                    'method' => $tip->method,
                    'mode' => $row['mode'],
                    'amount' => $row['amount']->toString(),
                    'staff_id' => $row['staff_id'],
                    'corrects_id' => $tip->id,
                    'reason' => mb_substr($reason, 0, 300),
                    'recorded_by' => $user->id,
                    'approved_by' => $approvedBy,
                    'device_id' => $device?->id,
                ]);
            }

            $this->audit->record('pos.tip_corrected', $tip, [
                'amount' => $current->toString(),
                'holders' => array_map(fn (array $holder) => ['mode' => $holder['mode']->value, 'staff_id' => $holder['staff_id'], 'amount' => $holder['amount']->toString()], $holders),
            ], [
                'amount' => $target->toString(),
                'holders' => [['mode' => $mode->value, 'staff_id' => $staffId, 'amount' => $target->toString()]],
            ], ['reason' => $reason, 'approved_by' => $approvedBy], ['actor' => $user, 'device' => $device, 'store_id' => (int) $tip->store_id, 'branch_id' => (int) $tip->branch_id]);

            if ($applied) {
                $applied();
            }

            return $tip;
        });
    }

    /**
     * A closed pool split covers the tip: its business day's, or its shift's.
     */
    public static function closedFor(PosTip $tip): bool
    {
        return PosTipDistribution::where('branch_id', $tip->branch_id)
            ->whereDate('business_date', $tip->business_date->toDateString())
            ->where('status', TipDistributionStatus::CLOSED->value)
            ->where(fn ($query) => $query->whereNull('shift_id')->when($tip->shift_id !== null, fn ($query) => $query->orWhere('shift_id', $tip->shift_id)))
            ->exists();
    }
}
