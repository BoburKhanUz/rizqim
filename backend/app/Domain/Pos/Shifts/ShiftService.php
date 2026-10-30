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
 *    the drawer holds; one movement out of at least pos_settings.alerts.cash_out_amount alerts the owner (P3-37);
 *  - closed with the cash counted by banknote: the difference to the expected cash is kept, the Z report is frozen in
 *    `summary`, numbered per branch (`z_number`) and sent to the owner. A shift with open orders or sales whose
 *    receipt is still pending does not close, unless a manager forces it with a reason (open orders then wait for the
 *    next shift). P3-31: nor while the till's offline queue still holds operations (as the till last reported it) or
 *    while operations it synced were rejected and nobody resolved them ("Sinxronlash xatolari"). P6-16: the bank
 *    terminal's day total given with the close is compared with the shift's card money (BankTerminalReconciler),
 *    after the Z is written: it never keeps a shift from closing.
 */
final class ShiftService
{
    /** Banknotes and coins (so'm) the cashier counts at closing. */
    public const DENOMINATIONS = [200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100];

    /** The manual movement categories as the owner's alert names them (CashMovementType::categories()). */
    private const CATEGORY_LABELS = [
        'collection' => 'inkassatsiya', 'owner' => 'egaga', 'supplies' => "xo'jalik mollari", 'utilities' => "kommunal to'lov",
        'other' => 'boshqa', 'daily_wage' => 'kunlik ish haqi', 'advance' => 'avans', 'bonus' => 'mukofot', 'payroll' => 'ish haqi hisob-kitobi', 'tips' => 'choy puli',
        'bazaar' => 'bozorlik', 'meat' => "go'sht", 'bread' => 'non',
    ];

    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ManagerApprovalService $approvals,
        private readonly AuditLogger $audit,
        private readonly ShiftReport $report,
        private readonly FiscalIssuer $fiscal,
        private readonly BillFiscalizer $fiscalizer,
        private readonly ShiftOwnerSummary $summary,
        private readonly \App\Domain\Pos\Sync\PosSyncErrors $syncErrors,
        private readonly \App\Domain\Pos\Accounts\PosOwnerAlerts $alerts,
        private readonly \App\Domain\Pos\Tips\ShiftTips $tips,
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
     * Review P6 wave 2 (S1): the `payroll` wage category is written only by paying a pay statement's line
     * (StaffPayStatements::pay, `$statementPayment`); by hand it is refused (422 movement_category_reserved).
     * Review P6 wave 2 (S2/S7): tips paid out (wage, category `tips`) need tips.manage, or a manager's approval token
     * for it (used once; it stands for pos.cash_in_out too), and stay within the shift's cash tips less what was
     * paid out of them (409 tip_payout_exceeds_tips, `available`) and what the person is owed in cash on the shift's
     * business day (409 tip_payout_exceeds_owed, `owed`) — ShiftTips.
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
        bool $statementPayment = false,
    ): CashMovement {
        if (!in_array($type->value, CashMovementType::MANUAL, true)) {
            throw PosActionException::invalid('movement_type_invalid', "Kassa harakati turi noto'g'ri.");
        }
        if (!in_array($category, $type->categories(), true)) {
            throw PosActionException::invalid('movement_category_invalid', "Kategoriya noto'g'ri.", ['categories' => $type->categories()]);
        }
        if (($category === CashMovementType::PAYROLL_CATEGORY) !== $statementPayment) {
            throw PosActionException::invalid('movement_category_reserved', "Ish haqi hisob-kitobi kassadan faqat \"Ish haqi\" bo'limidagi hisob-kitob qatori orqali to'lanadi.", ['categories' => array_values(array_diff($type->categories(), [CashMovementType::PAYROLL_CATEGORY]))]);
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
            $shift = PosShift::lockForMoney((int) $shift->id);
            if (!$shift->isOpen()) {
                throw PosActionException::conflict('shift_closed', 'Smena yopilgan.');
            }

            if ($category === CashMovementType::TIPS_CATEGORY && $employee !== null) {
                $approvedBy = $this->permissions->allows($user, Permission::TIPS_MANAGE, $shift)
                    ? $this->approvals->authorize($user, Permission::POS_CASH_IN_OUT, $shift, $approvalToken, $device)
                    : $this->approvals->authorize($user, Permission::TIPS_MANAGE, $shift, $approvalToken, $device);
                $this->assertTipPayout($shift, $employee, $amount);
            } else {
                $approvedBy = $this->approvals->authorize($user, Permission::POS_CASH_IN_OUT, $shift, $approvalToken, $device);
            }

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

            if ($type->sign() < 0) {
                $this->cashOutAlert($shift, $type, $category, $amount, $note, $employee, $user, $approvedBy);
            }

            if ($applied) {
                $applied();
            }

            return $movement;
        });

        event(new ShiftChangedBroadcast($shift->refresh(), 'cash_movement'));

        return $movement;
    }

    /**
     * A tip payout within the shift's cash tips and what the person is owed (inside the shift's lock; the person's row
     * is locked too, so two tills never pay the same tips at once).
     *
     * @throws PosActionException
     */
    private function assertTipPayout(PosShift $shift, User $employee, Money $amount): void
    {
        User::whereKey($employee->id)->lockForUpdate()->first();
        $available = $this->tips->payable($shift);
        if ($amount->isGreaterThan($available)) {
            throw PosActionException::conflict('tip_payout_exceeds_tips', "Bu smenada buncha naqd choy puli yo'q: berish mumkin — {$available->toString()} so'm.", ['available' => $available->toString()]);
        }
        $owed = $this->tips->owed((int) $shift->branch_id, $shift->business_date->toDateString(), (int) $employee->id);
        if ($amount->isGreaterThan($owed)) {
            throw PosActionException::conflict('tip_payout_exceeds_owed', "{$employee->name}ga bugun buncha naqd choy puli tegishli emas: berish mumkin — {$owed->toString()} so'm.", ['owed' => $owed->toString()]);
        }
    }

    /**
     * P3-37: the owner hears about one movement out of the drawer (cash out, wage, purchase) of at least the branch's
     * pos_settings.alerts.cash_out_amount (null: never). After the movement commits (PosOwnerAlerts).
     */
    private function cashOutAlert(PosShift $shift, CashMovementType $type, string $category, Money $amount, ?string $note, ?User $employee, User $user, ?int $approvedBy): void
    {
        $branch = Branch::find($shift->branch_id);
        $stored = $branch?->getAttribute('pos_settings');
        $threshold = \App\Domain\Pos\Setup\PosSettings::resolve(is_array($stored) ? $stored : null)['alerts']['cash_out_amount'];
        if ($threshold === null || $amount->isLessThan($threshold)) {
            return;
        }

        $labels = [CashMovementType::CASH_OUT->value => 'Kassadan pul chiqarildi', CashMovementType::WAGE->value => 'Kassadan ish haqi berildi', CashMovementType::PURCHASE->value => 'Kassadan xarid qilindi'];
        $approver = $approvedBy !== null ? User::whereKey($approvedBy)->value('name') : null;

        $this->alerts->send((int) $shift->store_id, implode("\n", array_filter([
            '💸 ' . ($labels[$type->value] ?? 'Kassadan chiqim') . ': ' . \App\Domain\Pos\Accounts\PosOwnerAlerts::sum($amount),
            $branch?->name ? "Filial: {$branch->name}" : null,
            'Sabab: ' . (self::CATEGORY_LABELS[$category] ?? $category) . ($note !== null && $note !== '' ? " — {$note}" : ''),
            $employee !== null ? "Xodim: {$employee->name}" : null,
            "Kim: {$user->name}" . ($approver !== null ? "\nTasdiqladi: {$approver}" : ''),
        ])));
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
     * @param  array{total: string, count?: int|null, terminal_ref?: string|null, note?: string|null}|null  $bankTerminal  P6-16: the bank terminal's day total
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
        ?array $bankTerminal = null,
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

        // P3-31: the till's offline queue first (POS_SPEC 5.4): what it still holds is not on the server yet
        $unsynced = (int) (Device::whereKey($shift->device_id)->value('sync_pending_ops') ?? 0);
        if ($unsynced > 0 && !$force) {
            throw PosActionException::conflict('shift_has_unsynced_ops', "Kassada serverga yuborilmagan offlayn amallar bor ({$unsynced} ta): avval internet tiklanib, navbat bo'shasin yoki menejer smenani majburan yopsin.", [
                'pending_ops' => $unsynced,
            ]);
        }

        // Receipts the provider confirmed meanwhile finish their bills and refunds first
        $pending = $this->pendingReceipts($shift);
        $openOrders = $this->openOrders($shift)->pluck('uuid')->all();
        $syncErrors = $this->syncErrors->blockingShift($shift);

        if ($syncErrors !== [] && $openOrders === [] && $pending['bills'] === [] && $pending['refunds'] === [] && !$force) {
            throw PosActionException::conflict('shift_has_sync_errors', "Sinxronlash xatolari bor (" . count($syncErrors) . " ta): menejer ularni ko'rib chiqsin yoki smenani majburan yopsin.", [
                'sync_errors' => $syncErrors,
            ]);
        }

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
            $shift = PosShift::lockForMoney((int) $shift->id);
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
        $this->bankTerminal($shift, $bankTerminal, $user, $device);
        $this->summary->send($shift, (array) $shift->summary);

        return $shift;
    }

    /**
     * P6-16 (D5 d): after the Z, the bank terminal's day total given with the close is recorded, or one entered
     * earlier in the shift is compared again with the whole shift. Only while the branch has terminal_card_flow; a
     * problem here is reported and never undoes the close.
     *
     * @param  array{total: string, count?: int|null, terminal_ref?: string|null, note?: string|null}|null  $input
     */
    private function bankTerminal(PosShift $shift, ?array $input, User $user, ?Device $device): void
    {
        try {
            $branch = Branch::find($shift->branch_id);
            if ($branch === null || !app(\App\Domain\Pos\Setup\PosModules::class)->enabled($branch, \App\Domain\Pos\Setup\PosModule::TERMINAL_CARD_FLOW)) {
                return;
            }
            $reconciler = app(\App\Domain\Pos\TerminalFlow\BankTerminalReconciler::class);
            if ($input !== null) {
                $reconciler->record($shift, [
                    'source' => 'manual',
                    'bank_total' => $input['total'],
                    'bank_count' => $input['count'] ?? null,
                    'terminal_ref' => $input['terminal_ref'] ?? null,
                    'note' => $input['note'] ?? null,
                ], $user, $device);
            } else {
                $reconciler->refresh($shift);
            }
        } catch (\Throwable $e) {
            report($e);
        }
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
