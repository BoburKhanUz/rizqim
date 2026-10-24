<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Shifts\CashMovementType;
use App\Domain\Pos\Shifts\ShiftPresenter;
use App\Domain\Pos\Shifts\ShiftReport;
use App\Domain\Pos\Shifts\ShiftService;
use App\Domain\Pos\Shifts\ShiftStatus;
use App\Models\PosShift;
use App\Support\Money;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P3-18: a till's shift, its cash drawer and the X/Z reports (POS_SPEC 2.3).
 */
#[Group('POS: billing', "Kassa: schyot, bo'lish, to'lov, qaytarish, smena, X/Z va menejer tasdig'i (P3-18).")]
#[HeaderParameter('Idempotency-Key', description: "Required on every change: a new UUID per action, the same one when retrying it (428 without).", type: 'string')]
class PosShiftController extends PosBillingController
{
    /**
     * Open a shift
     *
     * On this terminal (a staff PIN session on a terminal paired to a branch; 422 `terminal_required` otherwise),
     * with the `opening_cash` in the drawer. One open shift per terminal (409 `shift_already_open`). Its business day
     * follows the branch's day start (05:00 by default: 02:00 still belongs to the day before). Needs pos.open_shift.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . ShiftPresenter::SHIFT . '}')]
    public function store(Request $request, ShiftService $shifts, ShiftPresenter $presenter): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'note' => 'nullable|string|max:500',
        ]);

        $device = $this->terminal($user);
        if ($device === null) {
            return $this->error('Smena faqat kassa terminalida (xodim PIN sessiyasida) ochiladi.', ['reason' => 'terminal_required'], 422);
        }

        $shift = $shifts->open($user, $device, Money::of((string) $data['opening_cash']), $data['note'] ?? null, $this->applied($request));

        return $this->success($presenter->shift($shift), 'Smena ochildi.', 201);
    }

    /**
     * The open shift
     *
     * A terminal (staff PIN session): its open shift with its X report so far, or `shift: null`. P3-26: a normal
     * sign-in (the owner or a manager in the web panel or the Mini App) gets the branch's open shifts: `branch_id`
     * (default: the caller's own branch; an owner without one names it, else every branch they see is listed in
     * `shifts`); `shift`/`report` is then the most recently opened of them. `shifts`: every open shift with its X
     * report (a terminal: just its own). Another store's branch: 404; one the caller does not work in: 403. Needs
     * pos.open_shift, pos.close_shift, pos.take_payment or (P3-35, read-only) reports.view.
     */
    #[QueryParameter('branch_id', description: "A normal sign-in: the branch (default: the caller's own; an owner without one: every branch).", type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{shift: ' . ShiftPresenter::SHIFT . '|null, report: ' . ShiftReport::REPORT . '|null, shifts: list<array{shift: ' . ShiftPresenter::SHIFT . ', report: ' . ShiftReport::REPORT . '}>}}')]
    public function current(Request $request, ShiftService $shifts, ShiftPresenter $presenter, ShiftReport $report): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999']);
        $device = $this->terminal($user);

        if ($device !== null) {
            $open = ($shift = $shifts->current($device)) !== null ? collect([$shift]) : collect();
        } else {
            $branchIds = $this->scope->branchIds($user);
            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : ($user->branch_id !== null ? (int) $user->branch_id : null);
            if ($branchId !== null) {
                if (!\App\Models\Branch::where('store_id', $user->store_id)->whereKey($branchId)->exists()) {
                    return $this->error('Filial topilmadi.', [], 404);
                }
                if ($branchIds !== null && !in_array($branchId, $branchIds, true)) {
                    return $this->error('Ruxsat etilmagan', [], 403);
                }
            }

            $open = PosShift::query()
                ->where('store_id', $user->store_id)
                ->where('status', ShiftStatus::OPEN->value)
                ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
                ->when($branchId === null && $branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->limit(50)
                ->get();
        }

        $all = $open->map(fn (PosShift $shift) => ['shift' => $presenter->shift($shift), 'report' => $report->build($shift)])->values()->all();

        return $this->success([
            'shift' => $all[0]['shift'] ?? null,
            'report' => $all[0]['report'] ?? null,
            'shifts' => $all,
        ]);
    }

    /**
     * Shifts of a branch
     *
     * Newest first: the caller's branches (`branch_id` narrows to one), optionally by `status` and business day
     * range (`from`, `to`, YYYY-MM-DD), at most `limit` (50, up to 200). For the web panel's shift and Z list too.
     * Needs pos.close_shift or reports.view.
     */
    #[QueryParameter('branch_id', type: 'int', required: false)]
    #[QueryParameter('status', description: 'open or closed', type: 'string', required: false)]
    #[QueryParameter('from', description: 'Business day from (YYYY-MM-DD)', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Business day to (YYYY-MM-DD)', type: 'string', required: false)]
    #[QueryParameter('limit', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . ShiftPresenter::SHIFT . '>}')]
    public function index(Request $request, ShiftPresenter $presenter): JsonResponse
    {
        $user = $this->staff($request);
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1',
            'status' => ['nullable', Rule::in(array_map(fn (ShiftStatus $status) => $status->value, ShiftStatus::cases()))],
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $branchIds = $this->scope->branchIds($user);
        $branchId = isset($data['branch_id']) ? RowId::parse((int) $data['branch_id']) : null;
        if ($branchId !== null && $branchIds !== null && !in_array($branchId, $branchIds, true)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $shifts = PosShift::query()
            ->where('store_id', $user->store_id)
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(isset($data['from']), fn ($query) => $query->where('business_date', '>=', $data['from']))
            ->when(isset($data['to']), fn ($query) => $query->where('business_date', '<=', $data['to']))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->limit((int) ($data['limit'] ?? 50))
            ->get();

        return $this->success($shifts->map(fn (PosShift $shift) => $presenter->shift($shift))->values()->all());
    }

    /**
     * One shift (with its Z report)
     *
     * The shift and, once closed, its Z report as it was at closing (`summary`). Needs pos.close_shift or
     * reports.view.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . ShiftPresenter::DETAIL . '}')]
    public function show(Request $request, ShiftPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);

        return $this->success($presenter->shift($this->shift($user, $uuid), true));
    }

    /**
     * X report
     *
     * The shift's report now, without closing it: takings by tender (`by_tender`, `cards` by card type), by channel,
     * by waiter (with the service charge their bills brought), discounts, voided lines, refunds, deposits taken and
     * applied, sales on credit and repayments, the drawer's movements and the expected cash. A closed shift: as at its
     * close. Needs pos.close_shift or reports.view.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{shift: ' . ShiftPresenter::SHIFT . ', report: ' . ShiftReport::REPORT . '}}')]
    public function xReport(Request $request, ShiftPresenter $presenter, ShiftReport $report, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $shift = $this->shift($user, $uuid);

        return $this->success(['shift' => $presenter->shift($shift), 'report' => $report->build($shift)]);
    }

    /**
     * Cash in or out of the drawer
     *
     * `type`: `cash_in`, `cash_out`, `wage` (paid to an employee: `employee_id` required) or `purchase` (bazaar
     * shopping); `category` from the type's list (cash_in: change_fund, owner, other; cash_out: collection, owner,
     * supplies, utilities, other; wage: daily_wage, advance, bonus; purchase: bazaar, meat, bread, supplies, other);
     * `other` needs a `note`. Money out never exceeds the expected cash (409 `not_enough_cash`). Needs pos.cash_in_out,
     * or a manager's `approval_token` for it (403 `approval_required`). The shift must be open (409 `shift_closed`).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{movement: array{uuid: string, type: string, category: string|null, amount: string, note: string|null, employee_id: int|null, employee: array{id: int, name: string|null}|null, user: array{id: int, name: string|null}|null, approved_by: int|null, approver: array{id: int, name: string|null}|null, created_at: string|null}, shift: ' . ShiftPresenter::SHIFT . '}}')]
    public function cashMovement(Request $request, ShiftService $shifts, ShiftPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(CashMovementType::MANUAL)],
            'category' => 'required|string|max:32',
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'note' => 'nullable|string|max:500',
            'employee_id' => 'nullable|integer|min:1|max:999999999999',
            'approval_token' => 'nullable|string|max:100',
        ]);
        $shift = $this->shift($user, $uuid);

        $movement = $shifts->addMovement(
            $user,
            $shift,
            CashMovementType::from($data['type']),
            (string) $data['category'],
            Money::of((string) $data['amount']),
            $this->optionalString($request, 'note'),
            isset($data['employee_id']) ? (int) $data['employee_id'] : null,
            $data['approval_token'] ?? null,
            $this->terminal($user),
            $this->applied($request),
        );

        return $this->success([
            'movement' => $this->movement($movement),
            'shift' => $presenter->shift($shift->refresh()),
        ], 'Saqlandi.', 201);
    }

    /**
     * Close the shift (Z)
     *
     * `counted_breakdown`: the cash counted, banknote => count (200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000,
     * 500, 200, 100); the difference to the expected cash is kept. Refused while POS orders are open (409
     * `shift_has_open_orders`: the ones opened in this shift, or any of the branch when it is the last open shift) or
     * a sale's receipt is pending (409 `shift_has_pending_receipts`), unless `force: true` with a `reason` by someone
     * with orders.cancel (or a manager's `approval_token` for it): open orders then wait for the next shift. The Z
     * report is kept in `summary`, numbered per branch (`z_number`) and sent to the owner in Telegram. Needs
     * pos.close_shift.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . ShiftPresenter::DETAIL . '}')]
    public function close(Request $request, ShiftService $shifts, ShiftPresenter $presenter, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'counted_breakdown' => 'present|array|max:20',
            'counted_breakdown.*' => 'integer|min:0|max:100000',
            'force' => 'nullable|boolean',
            'reason' => 'nullable|string|max:500',
            'approval_token' => 'nullable|string|max:100',
        ]);
        $shift = $this->shift($user, $uuid);

        $shift = $shifts->close(
            $user,
            $shift,
            (array) $data['counted_breakdown'],
            (bool) ($data['force'] ?? false),
            $this->optionalString($request, 'reason'),
            $data['approval_token'] ?? null,
            $this->terminal($user),
            $this->applied($request),
        );

        return $this->success($presenter->shift($shift, true), 'Smena yopildi.');
    }

    /**
     * A drawer movement in the X report's shape (P3-26: `employee`, `user` and `approver` as {id, name}); the older
     * `employee_id` and `approved_by` ids stay.
     *
     * @return array<string, mixed>
     */
    private function movement(\App\Models\CashMovement $movement): array
    {
        $names = \App\Models\User::whereIn('id', array_filter([$movement->employee_id, $movement->user_id, $movement->approved_by]))->pluck('name', 'id');
        $person = fn ($id) => $id !== null ? ['id' => (int) $id, 'name' => $names[$id] ?? null] : null;

        return [
            'uuid' => (string) $movement->uuid,
            'type' => $movement->type->value,
            'category' => $movement->category,
            'amount' => Money::of($movement->amount)->toString(),
            'note' => $movement->note,
            'employee_id' => $movement->employee_id !== null ? (int) $movement->employee_id : null,
            'employee' => $person($movement->employee_id),
            'user' => $person($movement->user_id),
            'approved_by' => $movement->approved_by !== null ? (int) $movement->approved_by : null,
            'approver' => $person($movement->approved_by),
            'created_at' => $movement->created_at?->toIso8601String(),
        ];
    }
}
