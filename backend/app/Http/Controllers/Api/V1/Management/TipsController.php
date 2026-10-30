<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPoolDistributor;
use App\Domain\Pos\Tips\TipPoolPeriod;
use App\Domain\Pos\Tips\TipPoolRule;
use App\Domain\Pos\Tips\TipPresenter;
use App\Domain\Pos\Tips\TipService;
use App\Domain\Pos\Tips\TipSettings;
use App\Domain\Pos\Tips\TipsReport;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Models\PosTipDistribution;
use App\Services\Finance\Billing\Entitlements;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * P6-22 (C2, owner decision 29.10.2026): tips (choy puli) for the partner web panel (docs/API.md "Choy puli"): the
 * branch's settings (settings.manage; changing them needs the `tips` module), the report and the tips themselves
 * (reports.view), a manager's correction with a reason and the pool's split per business day or shift (tips.manage).
 * Never from a terminal PIN session; a branch-bound caller works on their own branch only. Every change is audited.
 * Tips are not revenue: none of this touches sales, receipts or refunds.
 */
#[Group('Owner: tips', "Choy puli: filial sozlamasi (shaxsiy yoki umumiy qozon), hisobot, tuzatish va qozonni bo'lish (P6-22).")]
class TipsController extends ManagementController
{
    private const SETTINGS = 'array{settings: ' . TipSettings::VIEW . ', options: array{modes: list<array{key: string, label: string}>, pool_rules: list<array{key: string, label: string}>, pool_periods: list<array{key: string, label: string}>, roles: list<array{key: string, label: string}>}}';

    private const MAX_DAYS = 92;

    /**
     * The branch's tip settings
     *
     * `mode` personal (to the order's waiter, or the one picked at payment) or pool; `pool_rule` equal, hours, sales
     * or role_weights; `pool_period` day or shift; `roles` taking part in the pool; `role_weights` per role ("1.00"
     * unless set). `enabled`: the branch has the `tips` module. `options`: the choices with their labels.
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SETTINGS . '}')]
    public function settings(Request $request, TipSettings $settings): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);

        return $this->success($this->settingsView($branch, $settings));
    }

    /**
     * Change the branch's tip settings
     *
     * Send what changes: `mode`, `pool_rule`, `pool_period`, `roles` (at least one; owner is never one), `role_weights`
     * ({role: 0.01-100}). Applies to tips taken from now on (a tip keeps the mode it was taken in). Needs the `tips`
     * module (403 module_disabled); a locked subscription changes nothing (403 subscription_locked). Audited
     * (settings.tips_changed).
     */
    #[BodyParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::SETTINGS . '}')]
    public function updateSettings(Request $request, TipSettings $settings, PosModules $modules, Entitlements $entitlements): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'mode' => ['sometimes', 'string', Rule::enum(TipMode::class)],
            'pool_rule' => ['sometimes', 'string', Rule::enum(TipPoolRule::class)],
            'pool_period' => ['sometimes', 'string', Rule::enum(TipPoolPeriod::class)],
            'roles' => 'sometimes|array|min:1',
            'roles.*' => ['string', Rule::in(TipSettings::poolRoles())],
            'role_weights' => 'sometimes|array',
            'role_weights.*' => 'numeric|gt:0|max:' . TipSettings::MAX_WEIGHT . '|decimal:0,2',
        ]);
        $unknown = array_diff(array_map('strval', array_keys((array) ($data['role_weights'] ?? []))), TipSettings::poolRoles());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['role_weights' => "Noma'lum rol: " . implode(', ', $unknown) . '.']);
        }

        $branch = $this->branch($request, Permission::SETTINGS_MANAGE);
        $entitlements->assertSettingsUnlocked((int) $branch->store_id);
        $modules->assertEnabled($branch, PosModule::TIPS);

        $settings->update($branch, array_intersect_key($data, array_flip(['mode', 'pool_rule', 'pool_period', 'roles', 'role_weights'])), $request->user());

        return $this->success($this->settingsView($branch, $settings), 'Choy puli sozlamalari saqlandi.');
    }

    /**
     * The tips report
     *
     * The branch's tips for business days `from`..`to` (at most 92 days; default: today's business day): `totals`
     * (count, amount, by method, personal, pool, unassigned personal tips, by person, corrections), `by_day` (each
     * day's amount, personal, pool, cash/card/qr, its pool splits and what of the pool is not split yet), `by_staff`
     * (personal tips, pool shares of the splits so far and of the closed ones, total). Tips are not revenue: they are
     * never in the sales reports.
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . TipsReport::VIEW . '}')]
    public function report(Request $request, TipsReport $report): JsonResponse
    {
        $branch = $this->branch($request, Permission::REPORTS_VIEW);
        [$from, $to] = $this->range($request, $branch);

        return $this->success($report->build($branch, $from, $to));
    }

    /**
     * The tips taken
     *
     * Each tip of business days `from`..`to` (newest first, at most 500) with what it gives whom now, its corrections
     * and the bill, payment and shift it came with. `staff_id`: one person's (whom a tip goes to now). `closed`: a
     * closed pool split covers it (no more corrections).
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('staff_id', description: 'Only the tips that go to this person now.', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{tips: list<' . TipPresenter::TIP . '>, truncated: bool}}')]
    public function entries(Request $request, TipPresenter $presenter): JsonResponse
    {
        $request->validate(['staff_id' => 'nullable|integer|min:1|max:999999999999999999']);
        $branch = $this->branch($request, Permission::REPORTS_VIEW);
        [$from, $to] = $this->range($request, $branch);
        $staffId = $request->filled('staff_id') ? (int) $request->input('staff_id') : null;

        $query = PosTip::where('branch_id', $branch->id)->where('kind', 'tip')->whereBetween('business_date', [$from, $to])
            ->when($staffId !== null, fn ($query) => $query->where(fn ($query) => $query->where('staff_id', $staffId)
                ->orWhereIn('id', PosTip::where('staff_id', $staffId)->whereNotNull('corrects_id')->select('corrects_id'))))
            ->orderByDesc('id');
        $tips = $query->limit(501)->get();
        $rows = $presenter->many($tips->take(500));
        if ($staffId !== null) {
            $rows = array_values(array_filter($rows, fn (array $row) => collect($row['holders'])->contains(fn (array $holder) => ($holder['staff']['id'] ?? null) === $staffId)));
        }

        return $this->success(['tips' => $rows, 'truncated' => $tips->count() > 500]);
    }

    /**
     * Correct a tip
     *
     * A manager's correction (tips.manage) with a `reason`: the tip now gives `amount` (null: as now) to `mode`
     * personal (`staff_id`; null: unassigned) or pool. Written as signed rows next to the tip (the original stays);
     * a refund never does this by itself. 409 `tips_period_closed` when a closed pool split covers the tip; 422
     * `nothing_to_correct`, `reason_required`, `staff_not_found`. Audited (pos.tip_corrected).
     */
    #[BodyParameter('amount', description: 'The tip\'s amount now (so\'m; null: unchanged).', type: 'string', required: false, example: '20000')]
    #[BodyParameter('mode', description: 'personal or pool.', type: 'string', required: true, example: 'personal')]
    #[BodyParameter('staff_id', description: 'Whom a personal tip goes to (null: unassigned).', type: 'int', required: false)]
    #[BodyParameter('reason', description: 'Why (required).', type: 'string', required: true)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . TipPresenter::TIP . '}')]
    public function correct(Request $request, TipService $tips, TipPresenter $presenter, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'mode' => ['required', 'string', Rule::enum(TipMode::class)],
            'staff_id' => 'nullable|integer|min:1|max:999999999999999999',
            'reason' => 'required|string|max:300',
        ]);
        $tip = $this->tip($request, $uuid);
        $this->authorizeFor($request, Permission::TIPS_MANAGE, Branch::findOrFail($tip->branch_id));

        $tips->correct(
            $request->user(),
            $tip,
            isset($data['amount']) ? Money::of((string) $data['amount']) : null,
            TipMode::from($data['mode']),
            isset($data['staff_id']) ? (int) $data['staff_id'] : null,
            (string) $data['reason'],
            null,
            null,
        );

        return $this->success($presenter->one($tip->refresh()), 'Choy puli tuzatildi.');
    }

    /**
     * The pool splits
     *
     * The branch's pool splits of business days `from`..`to`, each with its rule, pool, lines (person, role, basis —
     * 1, minutes, sales in so'm or the role weight — and amount in whole so'm) and status. `pool_period`: the branch's
     * period now (day: one split per business day; shift: one per shift, computed with `shift_uuid`).
     */
    #[QueryParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[QueryParameter('from', description: 'First business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[QueryParameter('to', description: 'Last business day (YYYY-MM-DD).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{pool_period: string, distributions: list<' . TipPoolDistributor::VIEW . '>}}')]
    public function distributions(Request $request, TipPoolDistributor $distributor, TipSettings $settings): JsonResponse
    {
        $branch = $this->branch($request, Permission::REPORTS_VIEW);
        [$from, $to] = $this->range($request, $branch);

        $rows = PosTipDistribution::where('branch_id', $branch->id)->whereBetween('business_date', [$from, $to])->orderByDesc('business_date')->orderBy('id')->get();

        return $this->success(['pool_period' => ($settings->row($branch)->pool_period ?? TipPoolPeriod::DAY)->value, 'distributions' => $rows->map(fn (PosTipDistribution $row) => $distributor->present($row))->values()->all()]);
    }

    /**
     * Compute the pool split
     *
     * Splits the pool tips of `business_date` (or of the shift `shift_uuid`) by the branch's rule among the people of
     * its roles who worked then (clock-in sessions, the till shifts they opened, else the orders they served), or
     * among `participants` ([{staff_id, minutes?}], the manager's own list: people of the branch whose role is one of
     * the pool's `roles` — else 422 `staff_not_in_pool` / `staff_not_found` — and never the caller unless the owner:
     * 403 `tips_pool_self`). The branch's `pool_period` decides the period: `day` refuses `shift_uuid` (422
     * `tips_pool_period_day`), `shift` requires it (422 `tips_pool_period_shift`, pick a shift of the day); a day split
     * and shift splits of one date never both exist (409 `tips_pool_period_conflict`). Stored; computing again
     * replaces it until it is closed (409 `tips_distribution_closed`); two first computes at once: 409
     * `tips_distribution_busy` for the second (try again). Every basis 0: split equally. Shares are whole so'm: the
     * so'm left over go to the largest remainders, any tiyin to the largest share — the lines add up to the pool.
     * Staff pay counts a split once it is closed. tips.manage. Audited (pos.tips_distribution_computed, with the
     * people and their shares).
     */
    #[BodyParameter('branch_id', description: 'The branch (a branch-bound caller: their own when omitted).', type: 'int', required: false)]
    #[BodyParameter('business_date', description: 'The business day (YYYY-MM-DD).', type: 'string', required: true)]
    #[BodyParameter('shift_uuid', description: 'One shift instead of the whole day.', type: 'string', required: false)]
    #[BodyParameter('participants', description: 'The people of the split: [{staff_id, minutes?}] (null: who worked).', type: 'array', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . TipPoolDistributor::VIEW . '}')]
    public function compute(Request $request, TipPoolDistributor $distributor): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1|max:999999999999999999',
            'business_date' => 'required|date_format:Y-m-d',
            'shift_uuid' => 'nullable|uuid',
            'participants' => 'nullable|array|min:1|max:200',
            'participants.*.staff_id' => 'required|integer|min:1|max:999999999999999999',
            'participants.*.minutes' => 'nullable|integer|min:0|max:1440',
        ]);
        $branch = $this->branch($request, Permission::TIPS_MANAGE);

        $shift = null;
        if (isset($data['shift_uuid'])) {
            $shift = PosShift::where('uuid', $data['shift_uuid'])->where('branch_id', $branch->id)->first();
            if ($shift === null) {
                throw new HttpResponseException($this->error('Smena topilmadi.', [], 404));
            }
            if ($shift->business_date->toDateString() !== $data['business_date']) {
                throw ValidationException::withMessages(['shift_uuid' => 'Smena boshqa biznes kunga tegishli.']);
            }
        }

        $participants = isset($data['participants']) ? array_map(fn (array $row) => ['staff_id' => (int) $row['staff_id'], 'minutes' => isset($row['minutes']) ? (int) $row['minutes'] : null], $data['participants']) : null;
        $distribution = $distributor->compute($branch, $data['business_date'], $shift, $participants, $request->user());

        return $this->success($distributor->present($distribution), "Choy puli bo'lindi.");
    }

    /**
     * Close the pool split
     *
     * Freezes the split: no more recomputing and no more corrections of the tips it covers. The pool changed since
     * it was computed (a tip came, a correction): 409 `tips_distribution_stale` (compute again first); a pool nobody
     * takes (no people in the split): 409 `tips_distribution_unassigned`; already closed: 409
     * `tips_distribution_closed`. From then on the pay statements count its shares. tips.manage. Audited
     * (pos.tips_distribution_closed).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . TipPoolDistributor::VIEW . '}')]
    public function close(Request $request, TipPoolDistributor $distributor, string $uuid): JsonResponse
    {
        $distribution = Str::isUuid($uuid) ? PosTipDistribution::where('uuid', $uuid)->where('store_id', $this->storeId($request))->first() : null;
        if ($distribution === null) {
            throw new HttpResponseException($this->error("Bo'linish topilmadi.", [], 404));
        }
        $this->authorizeFor($request, Permission::TIPS_MANAGE, Branch::findOrFail($distribution->branch_id));

        return $this->success($distributor->present($distributor->close($distribution, $request->user())), "Choy puli bo'linishi yopildi.");
    }

    private function tip(Request $request, string $uuid): PosTip
    {
        $tip = Str::isUuid($uuid) ? PosTip::where('uuid', $uuid)->where('store_id', $this->storeId($request))->where('kind', 'tip')->first() : null;
        if ($tip === null) {
            throw new HttpResponseException($this->error('Choy puli topilmadi.', [], 404));
        }
        $this->authorizeFor($request, Permission::REPORTS_VIEW, Branch::findOrFail($tip->branch_id));

        return $tip;
    }

    /**
     * `from`..`to` (business days; default today's), at most MAX_DAYS.
     *
     * @return array{0: string, 1: string}
     */
    private function range(Request $request, Branch $branch): array
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]);
        $today = \App\Domain\Pos\Shifts\BusinessDay::of($branch);
        $from = (string) ($request->input('from') ?: $request->input('to') ?: $today);
        $to = (string) ($request->input('to') ?: max($from, $today));
        if ($to < $from) {
            throw ValidationException::withMessages(['to' => "Oxirgi kun birinchi kundan oldin bo'lmasin."]);
        }
        if (CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) >= self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Davr ' . self::MAX_DAYS . " kundan oshmasin."]);
        }

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsView(Branch $branch, TipSettings $settings): array
    {
        $options = fn (array $cases) => array_map(fn ($case) => ['key' => $case->value, 'label' => $case->label()], $cases);

        return [
            'settings' => $settings->present($branch),
            'options' => [
                'modes' => $options(TipMode::cases()),
                'pool_rules' => $options(TipPoolRule::cases()),
                'pool_periods' => $options(TipPoolPeriod::cases()),
                'roles' => array_map(fn (string $role) => ['key' => $role, 'label' => UserRole::from($role)->labelUz()], TipSettings::poolRoles()),
            ],
        ];
    }
}
