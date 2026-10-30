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
     * picked at payment, payRecipient), else the order's waiter, else unassigned. Pool mode: to the branch's pot.
     * `$approvedBy`: the manager who approved giving it to someone other than the waiter.
     */
    public function record(Order $order, OrderBill $bill, Payment $payment, PosShift $shift, TipMethod $method, Money $amount, TipSource $source, ?int $staffId, User $user, ?Device $device, ?int $approvedBy = null): PosTip
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
            'approved_by' => $approvedBy,
            'device_id' => $device?->id,
        ]);
    }

    /**
     * Review P6 wave 2 (S5): whom a personal tip taken with a payment goes to, as the cashier picked it (`$staffId`)
     * against the order's waiter at payment (`$waiterId`). Pool mode: nobody (the pot), whatever was picked. The
     * waiter (or no pick): the waiter, no approval. Someone else: a staff member of the branch whose role is one of
     * its tip roles (TipSettings::roles, waiters by default), and the caller must hold tips.manage or consume a
     * manager's approval for it (`approval` true) — online an unknown person is 422 tip_staff_not_found, one outside
     * the roles 422 tip_staff_not_eligible, both before any money is taken. Offline (`$offline`: the money was taken,
     * nothing is refused and no approval can be asked) an unknown, ineligible or unapproved (`$mayPick` false: the
     * staff member who took it lacks tips.manage) pick falls back to the waiter.
     *
     * @return array{staff_id: int|null, approval: bool}
     *
     * @throws PosActionException
     */
    public function payRecipient(Order $order, ?int $staffId, ?int $waiterId, bool $offline, bool $mayPick): array
    {
        $branch = Branch::findOrFail((int) $order->branch_id);
        if ($this->settings->mode($branch) !== TipMode::PERSONAL) {
            return ['staff_id' => null, 'approval' => false];
        }
        if ($staffId === null || $staffId === $waiterId) {
            return ['staff_id' => $waiterId, 'approval' => false];
        }

        $person = self::recipient((int) $order->store_id, (int) $order->branch_id, $staffId);
        $eligible = $person !== null && in_array($person->role->value, $this->settings->roles($this->settings->row($branch)), true);
        if ($offline) {
            return ['staff_id' => $eligible && $mayPick ? $staffId : $waiterId, 'approval' => false];
        }
        if ($person === null) {
            throw PosActionException::invalid('tip_staff_not_found', 'Choy puli beriladigan xodim topilmadi.');
        }
        if (!$eligible) {
            throw PosActionException::invalid('tip_staff_not_eligible', "Bu xodimga choy puli berilmaydi: filialda choy puli faqat belgilangan lavozimlarga (odatda ofitsiantlarga) beriladi.");
        }

        return ['staff_id' => $staffId, 'approval' => !$mayPick];
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
     * is required. Refused (409 tips_period_closed) when a closed pool split covers the tip's day or shift; the splits
     * covering it are locked first, so a close at the same moment either waits for the correction (and finds the
     * pool changed: stale) or is seen. Review P6 wave 2 (S6): the corrected total stays between 0 and the amount
     * taken, whatever the method (422 tip_correction_too_high): a correction fixes a mistake or moves the tip, it never
     * makes up money; and it never changes the drawer's expected cash (ShiftTips::cash).
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
        if ($amount !== null && $amount->isGreaterThan($tip->amount)) {
            throw PosActionException::invalid('tip_correction_too_high', "Tuzatilgan choy puli olinganidan ko'p bo'lmaydi: ko'pi bilan " . Money::of($tip->amount)->toString() . " so'm.", ['original_amount' => Money::of($tip->amount)->toString()]);
        }
        if ($mode === TipMode::POOL) {
            $staffId = null;
        }
        if ($staffId !== null && self::recipient((int) $tip->store_id, (int) $tip->branch_id, $staffId) === null) {
            throw PosActionException::invalid('staff_not_found', 'Xodim topilmadi.');
        }

        return DB::transaction(function () use ($user, $tip, $amount, $mode, $staffId, $reason, $approvedBy, $device, $applied) {
            $tip = PosTip::whereKey($tip->id)->lockForUpdate()->firstOrFail();
            self::covering($tip)->lockForUpdate()->get(['id']);
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
        return self::covering($tip)->where('status', TipDistributionStatus::CLOSED->value)->exists();
    }

    /**
     * The pool splits covering the tip: its business day's, and its shift's.
     *
     * @return \Illuminate\Database\Eloquent\Builder<PosTipDistribution>
     */
    private static function covering(PosTip $tip): \Illuminate\Database\Eloquent\Builder
    {
        return PosTipDistribution::where('branch_id', $tip->branch_id)
            ->whereDate('business_date', $tip->business_date->toDateString())
            ->where(fn ($query) => $query->whereNull('shift_id')->when($tip->shift_id !== null, fn ($query) => $query->orWhere('shift_id', $tip->shift_id)));
    }
}
