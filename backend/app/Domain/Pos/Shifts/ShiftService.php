<?php

namespace App\Domain\Pos\Shifts;

use App\Domain\Fiscal\FiscalIssuer;
use App\Domain\Pos\Billing\BillFiscalizer;
use App\Domain\Pos\Billing\BillStatus;
use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Domain\Pos\Billing\PosActionException;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Events\Broadcast\ShiftChangedBroadcast;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderBill;
use App\Models\Payment;
use App\Models\PosRefund;
use App\Models\PosShift;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\PermissionService;
use App\Support\Money;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A till's shift (POS_SPEC 2.3, research R21, L7, L10, L11):
 *
 *  - opened on a terminal (a staff PIN session) with the opening cash; one open shift per terminal; its business day
 *    follows the branch's day start (BusinessDay);
 *  - cash put in or taken out by hand with a category (bazaar purchase, wage paid to an employee...), never more than
 *    the drawer holds;
 *  - closed with the cash counted by banknote: the difference to the expected cash is kept, the Z report is frozen in
 *    `summary`, numbered per branch (`z_number`) and sent to the owner. A shift with open orders or sales whose
 *    receipt is still pending does not close, unless a manager forces it with a reason (open orders then wait for the
 *    next shift).
 */
final class ShiftService
{
    /** Banknotes and coins (so'm) the cashier counts at closing. */
    public const DENOMINATIONS = [200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ManagerApprovalService $approvals,
        private readonly AuditLogger $audit,
        private readonly ShiftReport $report,
        private readonly FiscalIssuer $fiscal,
        private readonly BillFiscalizer $fiscalizer,
        private readonly ShiftOwnerSummary $summary,
    ) {}

    public function current(Device $device): ?PosShift
    {
        return PosShift::where('device_id', $device->id)->where('status', ShiftStatus::OPEN->value)->first();
    }

    /**
     * The shift a POS payment or refund lands in: the named one (open, in the caller's branch), else the open shift
     * of the caller's terminal.
     *
     * @throws PosActionException
     */
    public function forAction(User $user, ?string $shiftUuid, int $branchId): PosShift
    {
        if ($shiftUuid !== null && $shiftUuid !== '') {
            $shift = PosShift::where('uuid', $shiftUuid)->where('store_id', $user->store_id)->first();
            if ($shift === null || !$this->permissions->allows($user, Permission::ORDERS_VIEW, $shift)) {
                throw PosActionException::invalid('shift_not_found', 'Smena topilmadi.');
            }
        } else {
            $device = $this->permissions->terminalOf($user);
            $shift = $device !== null ? $this->current($device) : null;
        }

        if ($shift === null || !$shift->isOpen()) {
            throw PosActionException::conflict('shift_not_open', "Ochiq smena yo'q. Avval smenani oching.");
        }
        if ((int) $shift->branch_id !== $branchId) {
            throw PosActionException::conflict('shift_other_branch', 'Smena boshqa filialniki.');
        }

        return $shift;
    }

    /**
     * @throws PosActionException
     */
    public function open(User $user, Device $device, Money $openingCash, ?string $note = null, ?Closure $applied = null): PosShift
    {
        if ($device->branch_id === null) {
            throw PosActionException::invalid('terminal_without_branch', "Smena filialga ulangan kassa terminalida ochiladi.");
        }
        $branch = Branch::findOrFail($device->branch_id);

        try {
            $shift = DB::transaction(function () use ($user, $device, $branch, $openingCash, $note, $applied) {
                if ($existing = $this->current($device)) {
                    throw PosActionException::conflict('shift_already_open', 'Bu terminalda smena allaqachon ochiq.', ['shift_uuid' => $existing->uuid]);
                }

                $shift = PosShift::create([
                    'store_id' => $device->store_id,
                    'branch_id' => $branch->id,
                    'device_id' => $device->id,
                    'business_date' => BusinessDay::of($branch),
                    'status' => ShiftStatus::OPEN->value,
                    'opened_by' => $user->id,
                    'opened_at' => now(),
                    'opening_cash' => $openingCash->toString(),
                ]);

                $this->movement($shift, CashMovementType::OPENING, $openingCash, $user, $device, ['note' => $note]);

                $this->audit->record('pos.shift_opened', $shift, [], [], [
                    'opening_cash' => $openingCash->toString(),
                    'business_date' => $shift->business_date->toDateString(),
                ], ['actor' => $user, 'device' => $device]);

                if ($applied) {
                    $applied();
                }

                return $shift;
            });
        } catch (UniqueConstraintViolationException) {
            // Two opens on one terminal at the same moment: the partial unique index let one through
            throw PosActionException::conflict('shift_already_open', 'Bu terminalda smena allaqachon ochiq.');
        }

        event(new ShiftChangedBroadcast($shift, 'opened'));

        return $shift;
    }

    /**
     * A cash movement the cashier records: cash_in, cash_out, wage (to an employee) or purchase (bazaar).
     *
     * @throws PosActionException
     */
    public function addMovement(
        User $user,
        PosShift $shift,
        CashMovementType $type,
        string $category,
        Money $amount,
        ?string $note,
        ?int $employeeId,
        ?string $approvalToken,
        ?Device $device,
        ?Closure $applied = null,
    ): CashMovement {
        if (!in_array($type->value, CashMovementType::MANUAL, true)) {
            throw PosActionException::invalid('movement_type_invalid', "Kassa harakati turi noto'g'ri.");
        }
        if (!in_array($category, $type->categories(), true)) {
            throw PosActionException::invalid('movement_category_invalid', "Kategoriya noto'g'ri.", ['categories' => $type->categories()]);
        }
        if ($category === 'other' && blank($note)) {
            throw PosActionException::invalid('movement_note_required', '"Boshqa" kategoriyasi uchun izoh yozing.');
        }
        if (!$amount->isPositive()) {
            throw PosActionException::invalid('amount_invalid', "Summa musbat bo'lishi kerak.");
        }

        $employee = null;
        if ($type === CashMovementType::WAGE) {
            $employee = $employeeId !== null ? User::whereKey($employeeId)->where('store_id', $shift->store_id)
                ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::storeRoles()))
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $shift->branch_id))
                ->first() : null;
            if ($employee === null) {
                throw PosActionException::invalid('employee_required', "Ish haqi qaysi xodimga berilganini tanlang.");
            }
        }

        $movement = DB::transaction(function () use ($user, $shift, $type, $category, $amount, $note, $employee, $approvalToken, $device, $applied) {
            $shift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
            if (!$shift->isOpen()) {
                throw PosActionException::conflict('shift_closed', 'Smena yopilgan.');
            }

            $approvedBy = $this->approvals->authorize($user, Permission::POS_CASH_IN_OUT, $shift, $approvalToken, $device);

            if ($type->sign() < 0 && $amount->isGreaterThan($this->report->expectedCash($shift))) {
                throw PosActionException::conflict('not_enough_cash', "Kassada buncha naqd pul yo'q.", ['expected_cash' => $this->report->expectedCash($shift)->toString()]);
            }

            $movement = $this->movement($shift, $type, $amount, $user, $device, [
                'category' => $category,
                'note' => $note,
                'employee_id' => $employee?->id,
                'approved_by' => $approvedBy,
            ]);

            $this->audit->record('pos.cash_movement', $shift, [], [], array_filter([
                'type' => $type->value,
                'category' => $category,
                'amount' => $amount->toString(),
                'note' => $note,
                'employee_id' => $employee?->id,
                'employee_name' => $employee?->name,
                'approved_by' => $approvedBy,
            ], fn ($value) => $value !== null), ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return $movement;
        });

        event(new ShiftChangedBroadcast($shift->refresh(), 'cash_movement'));

        return $movement;
    }

    /**
     * Cash a sale put into the drawer (the amount kept: tendered less change).
     */
    public function recordSale(PosShift $shift, Payment $payment, User $user, ?Device $device): CashMovement
    {
        return $this->movement($shift, CashMovementType::SALE, Money::of($payment->amount), $user, $device, ['payment_id' => $payment->id]);
    }

    /**
     * Cash a refund took out of the drawer.
     */
    public function recordRefund(PosShift $shift, PosRefund $refund, User $user, ?Device $device, ?int $approvedBy): CashMovement
    {
        return $this->movement($shift, CashMovementType::REFUND, Money::of($refund->amount), $user, $device, [
            'payment_id' => $refund->payment_id,
            'note' => $refund->reason,
            'approved_by' => $approvedBy,
        ]);
    }

    /**
     * Closes the shift (Z).
     *
     * @param  array<string|int, int>  $breakdown  banknote => count
     *
     * @throws PosActionException
     */
    public function close(
        User $user,
        PosShift $shift,
        array $breakdown,
        bool $force,
        ?string $reason,
        ?string $approvalToken,
        ?Device $device,
        ?Closure $applied = null,
    ): PosShift {
        $counted = Money::zero();
        $normalized = [];
        foreach ($breakdown as $denomination => $count) {
            if (!in_array((int) $denomination, self::DENOMINATIONS, true) || (int) $count < 0) {
                throw PosActionException::invalid('breakdown_invalid', "Kupyura yoki soni noto'g'ri: {$denomination}.");
            }
            if ((int) $count > 0) {
                $normalized[(string) (int) $denomination] = (int) $count;
                $counted = $counted->plus(Money::of((int) $denomination)->times((int) $count));
            }
        }
        krsort($normalized, SORT_NUMERIC);

        if (!$shift->isOpen()) {
            throw PosActionException::conflict('shift_closed', 'Smena allaqachon yopilgan.');
        }

        // Receipts the provider confirmed meanwhile finish their bills and refunds first
        $pending = $this->pendingReceipts($shift);
        $openOrders = $this->openOrders($shift)->pluck('uuid')->all();

        if (($pending['bills'] !== [] || $pending['refunds'] !== [] || $openOrders !== []) && !$force) {
            throw $openOrders !== []
                ? PosActionException::conflict('shift_has_open_orders', "Ochiq buyurtmalar bor: avval ularni yoping yoki menejer smenani majburan yopsin.", [
                    'open_orders' => $openOrders,
                    'pending_bills' => $pending['bills'],
                    'pending_refunds' => $pending['refunds'],
                ])
                : PosActionException::conflict('shift_has_pending_receipts', "Fiskal cheki chiqmagan to'lovlar bor (fiskal kutmoqda). Chekni qayta yuboring yoki menejer smenani majburan yopsin.", [
                    'pending_bills' => $pending['bills'],
                    'pending_refunds' => $pending['refunds'],
                ]);
        }
        if ($force && blank($reason)) {
            throw PosActionException::invalid('force_reason_required', 'Majburan yopish sababini yozing.');
        }

        $shift = DB::transaction(function () use ($user, $shift, $normalized, $counted, $force, $reason, $approvalToken, $device, $openOrders, $pending, $applied) {
            $shift = PosShift::whereKey($shift->id)->lockForUpdate()->firstOrFail();
            if (!$shift->isOpen()) {
                throw PosActionException::conflict('shift_closed', 'Smena allaqachon yopilgan.');
            }

            $forcedBy = null;
            if ($force) {
                // Leaving orders open or receipts pending is a manager's call
                $forcedBy = $this->approvals->authorize($user, Permission::ORDERS_CANCEL, $shift, $approvalToken, $device) ?? $user->id;
                Order::where('shift_id', $shift->id)->whereIn('uuid', $openOrders)->update(['shift_id' => null]);
            }

            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(?, ?)', [(int) $shift->branch_id % 2147483647, 0x5a]);
            }
            $zNumber = (int) PosShift::where('branch_id', $shift->branch_id)->max('z_number') + 1;

            $closedAt = now();
            $shift->forceFill(['closed_at' => $closedAt]);
            $report = $this->report->build($shift);
            $expected = Money::of($report['cash']['expected']);
            $report['open_orders'] = $openOrders;
            $report['pending_receipts'] = $pending;

            $shift->forceFill([
                'status' => ShiftStatus::CLOSED->value,
                'closed_by' => $user->id,
                'expected_cash' => $expected->toString(),
                'counted_cash' => $counted->toString(),
                'counted_breakdown' => $normalized,
                'difference' => $counted->minus($expected)->toString(),
                'z_number' => $zNumber,
                'force_closed' => $force,
                'close_note' => $force ? $reason : null,
                'forced_by' => $forcedBy,
                'summary' => $report,
            ])->save();

            $this->audit->record('pos.shift_closed', $shift, [], [], array_filter([
                'z_number' => $zNumber,
                'expected_cash' => $expected->toString(),
                'counted_cash' => $counted->toString(),
                'difference' => $counted->minus($expected)->toString(),
                'forced' => $force ?: null,
                'reason' => $force ? $reason : null,
                'forced_by' => $forcedBy,
                'open_orders' => $openOrders ?: null,
            ], fn ($value) => $value !== null), ['actor' => $user, 'device' => $device]);

            if ($applied) {
                $applied();
            }

            return $shift;
        });

        event(new ShiftChangedBroadcast($shift, 'closed'));
        $this->summary->send($shift, (array) $shift->summary);

        return $shift;
    }

    /**
     * POS orders still open that keep this shift from closing: the ones opened in it, and, when it is the branch's
     * last open shift, every open POS order of the branch (nobody would be left to close them).
     *
     * @return Builder<Order>
     */
    public function openOrders(PosShift $shift): Builder
    {
        $lastOpen = !PosShift::where('branch_id', $shift->branch_id)->where('status', ShiftStatus::OPEN->value)->whereKeyNot($shift->id)->exists();

        return Order::query()
            ->where('branch_id', $shift->branch_id)
            ->whereIn('channel', array_map(fn ($channel) => $channel->value, OrderStamp::CHANNELS))
            ->whereIn('status', [OrderStatus::NEW->value, OrderStatus::ACCEPTED->value, OrderStatus::PREPARING->value, OrderStatus::READY->value])
            ->when(!$lastOpen, fn (Builder $query) => $query->where('shift_id', $shift->id))
            ->orderBy('id');
    }

    /**
     * Bills and refunds of this shift whose receipt is not issued: each pending one is asked about again first
     * (FiscalIssuer::hasPending), and one the provider has settled meanwhile is finished (paid, or failed).
     *
     * @return array{bills: list<string>, refunds: list<string>}
     */
    public function pendingReceipts(PosShift $shift): array
    {
        $bills = OrderBill::where('shift_id', $shift->id)
            ->whereNotIn('status', [BillStatus::PAID->value, BillStatus::VOID->value])
            ->get();
        $stillPending = [];
        foreach ($bills as $bill) {
            if ($bill->getAttribute('fiscal_status') !== 'pending' || !$this->fiscal->hasPending(OrderBill::FISCAL_SUBJECT, (int) $bill->id)) {
                $bill = $this->fiscalizer->issue($bill);
            }
            if ($bill->status !== BillStatus::PAID) {
                $stillPending[] = $bill->uuid;
            }
        }

        $refunds = PosRefund::where('shift_id', $shift->id)->where(fn ($query) => $query->whereNull('fiscal_status')->orWhere('fiscal_status', '!=', 'issued'))->get();
        $refundsPending = [];
        foreach ($refunds as $refund) {
            if ($refund->getAttribute('fiscal_status') !== 'pending' || !$this->fiscal->hasPending(PosRefund::FISCAL_SUBJECT, (int) $refund->id)) {
                $refund = $this->fiscalizer->issueRefund($refund);
            }
            if ($refund->getAttribute('fiscal_status') !== 'issued') {
                $refundsPending[] = $refund->uuid;
            }
        }

        return ['bills' => $stillPending, 'refunds' => $refundsPending];
    }

    /**
     * @param  array{category?: ?string, note?: ?string, payment_id?: ?int, employee_id?: ?int, approved_by?: ?int}  $attributes
     */
    private function movement(PosShift $shift, CashMovementType $type, Money $amount, User $user, ?Device $device, array $attributes = []): CashMovement
    {
        return CashMovement::create([
            'store_id' => $shift->store_id,
            'branch_id' => $shift->branch_id,
            'shift_id' => $shift->id,
            'type' => $type->value,
            'amount' => $amount->toString(),
            'user_id' => $user->id,
            'device_id' => $device?->id,
        ] + array_filter($attributes, fn ($value) => $value !== null));
    }
}
