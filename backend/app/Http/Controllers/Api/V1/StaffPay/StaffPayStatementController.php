<?php

namespace App\Http\Controllers\Api\V1\StaffPay;

use App\Domain\Pos\Shifts\ShiftReport;
use App\Domain\Pos\Shifts\ShiftStatus;
use App\Domain\Pos\StaffPay\StaffPayPresenter;
use App\Domain\Pos\StaffPay\StaffPayStatements;
use App\Domain\Pos\StaffPay\StatementStatus;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Device;
use App\Models\PosShift;
use App\Models\StaffPayStatement;
use App\Models\StaffPayStatementLine;
use App\Models\User;
use App\Services\Idempotency\IdempotencyService;
use App\Support\Money;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * P6-23 (C3): "Hisob-kitob" — a branch's pay statements per period: computed from the wage rules and the period's
 * work and sales, closed, paid (from the till as a wage cash movement, or another way). Read with staff_pay.view (or
 * staff_pay.manage), every change with staff_pay.manage; audited. Behind the `staff_pay` module.
 */
#[Group('Owner: staff pay')]
class StaffPayStatementController extends StaffPayOwnerController
{
    /**
     * Pay statements
     *
     * Newest period first: the branches the caller sees (`branch_id` narrows to one), periods overlapping `from`–`to`
     * (business dates), `status` (draft, closed, paid), at most `limit` (50, up to 200). staff_pay.view or
     * staff_pay.manage.
     */
    #[QueryParameter('branch_id', type: 'int', required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD', type: 'string', required: false)]
    #[QueryParameter('status', description: 'App\Domain\Pos\StaffPay\StatementStatus', type: 'string', required: false)]
    #[QueryParameter('limit', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . StaffPayPresenter::SUMMARY . '>}')]
    public function index(Request $request): JsonResponse
    {
        $store = $this->currentStore($request);
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'status' => ['nullable', 'string', Rule::in(array_map(fn (StatementStatus $status) => $status->value, StatementStatus::cases()))],
            'limit' => 'nullable|integer|min:1|max:200',
        ], UzValidation::messages());
        $branch = $this->branchFilter($request, $this->readPermission($request));

        $statements = StaffPayStatement::query()
            ->where('store_id', $store->id)
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->when(isset($data['from']), fn ($query) => $query->where('period_to', '>=', $data['from']))
            ->when(isset($data['to']), fn ($query) => $query->where('period_from', '<=', $data['to']))
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->withCount('lines')
            ->orderByDesc('period_from')
            ->orderByDesc('id')
            ->limit((int) ($data['limit'] ?? 50))
            ->get();

        return $this->success($statements->map(fn (StaffPayStatement $statement) => StaffPayPresenter::summary($statement, (int) $statement->getAttribute('lines_count')))->values()->all());
    }

    /**
     * Make a pay statement
     *
     * A draft for the branch's pay period holding `date` (default: the branch's business day today): a line per staff
     * member with a rule and any work, sales or events in the period. 409 `statement_exists` (the period has one:
     * `statement_uuid`) or `statement_overlaps` (the pay period changed and another statement covers some of these
     * days). Audited. staff_pay.manage.
     */
    #[BodyParameter('branch_id', type: 'int', required: false)]
    #[BodyParameter('date', type: 'string', required: false, description: 'YYYY-MM-DD: a business date in the period.')]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function store(Request $request, StaffPayStatements $statements): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'date' => 'nullable|date_format:Y-m-d',
        ], UzValidation::messages(), ['date' => 'Sana']);
        $branch = $this->moduleBranch($request, Permission::STAFF_PAY_MANAGE);
        /** @var User $user */
        $user = $request->user();

        $statement = $statements->create($branch, (string) ($data['date'] ?? \App\Domain\Pos\Shifts\BusinessDay::of($branch)), $user);

        return $this->success($this->detail($statement, $branch), 'Hisob-kitob tuzildi.', 201);
    }

    /**
     * A pay statement
     *
     * Its lines: per person the components with what each gave and how (`detail`), the work and sales behind them
     * (`metrics`: shifts, minutes, bills, sales, service charge, events, tips, sessions not counted), the wage money
     * already given from the till in the period (`advances`), the adjustment and what is to pay; `warnings` (worked
     * without a rule, sessions left open); `open_shifts`: the branch's open till shifts a line can be paid from.
     * staff_pay.view or staff_pay.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function show(Request $request, string $uuid): JsonResponse
    {
        [$statement, $branch] = $this->record($request, StaffPayStatement::class, $uuid, $this->readPermission($request), 'Hisob-kitob topilmadi.');

        return $this->success($this->detail($statement, $branch));
    }

    /**
     * Recompute a draft
     *
     * From the rules and the work and sales as they are now; adjustments stay. 409 `statement_not_draft`. Audited.
     * staff_pay.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function recompute(Request $request, StaffPayStatements $statements, string $uuid): JsonResponse
    {
        [$statement, $branch] = $this->record($request, StaffPayStatement::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Hisob-kitob topilmadi.');
        /** @var User $user */
        $user = $request->user();

        return $this->success($this->detail($statements->recompute($statement, $user), $branch), 'Qayta hisoblandi.');
    }

    /**
     * Close a statement
     *
     * Recomputed one last time and frozen; its lines can then be paid. 409 `statement_not_draft`. Audited.
     * staff_pay.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function close(Request $request, StaffPayStatements $statements, string $uuid): JsonResponse
    {
        [$statement, $branch] = $this->record($request, StaffPayStatement::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Hisob-kitob topilmadi.');
        /** @var User $user */
        $user = $request->user();

        return $this->success($this->detail($statements->close($statement, $user), $branch), 'Hisob-kitob yopildi.');
    }

    /**
     * Reopen a closed statement
     *
     * Back to a draft (`reason` required) while no line is paid: 409 `statement_not_closed`, `statement_has_payments`.
     * Audited. staff_pay.manage.
     */
    #[BodyParameter('reason', type: 'string', required: true)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function reopen(Request $request, StaffPayStatements $statements, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], UzValidation::messages(), ['reason' => 'Sabab']);
        [$statement, $branch] = $this->record($request, StaffPayStatement::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Hisob-kitob topilmadi.');
        /** @var User $user */
        $user = $request->user();

        return $this->success($this->detail($statements->reopen($statement, trim((string) $data['reason']), $user), $branch), 'Qayta ochildi.');
    }

    /**
     * Delete a draft
     *
     * 409 `statement_not_draft`. Audited. staff_pay.manage.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, StaffPayStatements $statements, string $uuid): JsonResponse
    {
        [$statement] = $this->record($request, StaffPayStatement::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Hisob-kitob topilmadi.');
        /** @var User $user */
        $user = $request->user();
        $statements->delete($statement, $user);

        return $this->success(null, "Hisob-kitob o'chirildi.");
    }

    /**
     * Adjust a line of a draft
     *
     * `adjustment` (so'm, + or −, e.g. "-50000" for a fine, "100000" for a bonus) with a `reason` (required unless 0);
     * it stays through recomputes. 409 `statement_not_draft`. Audited. staff_pay.manage.
     */
    #[BodyParameter('adjustment', type: 'string', required: true)]
    #[BodyParameter('reason', type: 'string|null', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function adjust(Request $request, StaffPayStatements $statements, string $uuid, string $line): JsonResponse
    {
        $data = $request->validate([
            'adjustment' => ['required', 'regex:/^-?\d{1,10}(\.\d{1,2})?$/'],
            'reason' => 'nullable|string|max:500',
        ], UzValidation::messages(['adjustment.regex' => "Tuzatish summasi noto'g'ri (masalan 50000 yoki -50000)."]), ['adjustment' => 'Tuzatish', 'reason' => 'Sabab']);
        [$statement, $branch] = $this->record($request, StaffPayStatement::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Hisob-kitob topilmadi.');
        $model = $this->line($statement, $line);
        /** @var User $user */
        $user = $request->user();

        $statements->adjust($model, Money::of((string) $data['adjustment']), $this->trimmed($request, 'reason'), $user);

        return $this->success($this->detail($statement->refresh(), $branch), 'Saqlandi.');
    }

    /**
     * Pay a line
     *
     * Of a closed statement, once. `method`: `cash_drawer` — from an open till shift of the branch (`shift_uuid`,
     * one of the statement's `open_shifts`): a `wage` cash movement (category payroll) of the line's amount for that
     * person, which the drawer must hold (409 `not_enough_cash`) and which shows in the shift's X/Z; or `other`
     * (bank transfer, card...) — only marked paid. An Idempotency-Key is required (428 without). 409 `statement_not_closed`,
     * `line_already_paid`; 422 `nothing_to_pay`, `shift_not_found`. Audited. staff_pay.manage (the till movement also
     * pos.cash_in_out).
     */
    #[BodyParameter('method', type: 'string', required: true, description: 'cash_drawer | other')]
    #[BodyParameter('shift_uuid', type: 'string|null', required: false)]
    #[BodyParameter('note', type: 'string|null', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::DETAIL . '}')]
    public function pay(Request $request, StaffPayStatements $statements, IdempotencyService $idempotency, string $uuid, string $line): JsonResponse
    {
        if (blank($request->headers->get(IdempotencyService::HEADER))) {
            return $this->error("Idempotency-Key sarlavhasi majburiy: har bir to'lov uchun yangi UUID yuboring va qayta urinishda o'shasini ishlating.", ['reason' => 'idempotency_key_required'], 428);
        }
        $data = $request->validate([
            'method' => ['required', 'string', Rule::in(['cash_drawer', 'other'])],
            'shift_uuid' => ['nullable', 'required_if:method,cash_drawer', 'uuid'],
            'note' => 'nullable|string|max:500',
        ], UzValidation::messages(['method.in' => "To'lov usuli: cash_drawer yoki other."]), ['method' => "To'lov usuli", 'shift_uuid' => 'Smena', 'note' => 'Izoh']);
        [$statement, $branch] = $this->record($request, StaffPayStatement::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Hisob-kitob topilmadi.');
        $model = $this->line($statement, $line);
        /** @var User $user */
        $user = $request->user();

        $shift = null;
        if ($data['method'] === 'cash_drawer') {
            $shift = PosShift::where('store_id', $statement->store_id)->where('uuid', (string) $data['shift_uuid'])->first();
            if ($shift === null || (int) $shift->branch_id !== (int) $branch->id || !$shift->isOpen()) {
                return $this->error('Filialning ochiq smenasi topilmadi.', ['reason' => 'shift_not_found'], 422);
            }
        }

        $statements->pay($model, (string) $data['method'], $shift, $this->trimmed($request, 'note'), $user, fn () => $idempotency->markApplied($request));

        return $this->success($this->detail($statement->refresh(), $branch), "To'landi.");
    }

    private function line(StaffPayStatement $statement, string $uuid): StaffPayStatementLine
    {
        $line = Str::isUuid($uuid) ? StaffPayStatementLine::where('statement_id', $statement->id)->where('uuid', $uuid)->first() : null;
        if ($line === null) {
            throw new HttpResponseException($this->error('Qator topilmadi.', [], 404));
        }

        return $line;
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(StaffPayStatement $statement, Branch $branch): array
    {
        $report = app(ShiftReport::class);
        $shifts = PosShift::query()->where('branch_id', $branch->id)->where('status', ShiftStatus::OPEN->value)->orderBy('id')->get();
        $devices = Device::whereIn('id', $shifts->pluck('device_id'))->pluck('name', 'id');

        return StaffPayPresenter::detail($statement) + [
            'open_shifts' => $shifts->map(fn (PosShift $shift) => [
                'uuid' => (string) $shift->uuid,
                'device_name' => $devices[$shift->device_id] ?? null,
                'business_date' => $shift->business_date->toDateString(),
                'opened_at' => $shift->opened_at->toIso8601String(),
                'expected_cash' => $report->expectedCash($shift)->toString(),
            ])->values()->all(),
        ];
    }
}
