<?php

namespace App\Http\Controllers\Api\V1\PosBilling;

use App\Domain\Pos\Billing\ManagerApprovalService;
use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\Tips\ShiftTips;
use App\Domain\Pos\Tips\TipMode;
use App\Domain\Pos\Tips\TipPresenter;
use App\Domain\Pos\Tips\TipService;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\PosShift;
use App\Models\PosTip;
use App\Support\Money;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\HeaderParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * P6-22: tips (choy puli) on the till and the waiter phone (docs/API.md "Choy puli"): the tips of a shift (a waiter
 * sees only their own), and a manager's correction with a reason (tips.manage, or a manager's PIN approval). Tips
 * are taken with the payment (POST /pos/bills/{uuid}/payments, `tip_amount`).
 */
#[Group('POS: tips', "Kassa: smenaning choy puli va menejer tuzatishi (P6-22).")]
#[HeaderParameter('Idempotency-Key', description: "Required on every change: a new UUID per action, the same one when retrying it (428 without).", type: 'string')]
class PosTipsController extends PosBillingController
{
    /**
     * The tips of a shift
     *
     * The tips of `shift_uuid` (default: the terminal's open shift; without one, the branch's current business day),
     * with the summary (by method, personal, pool, by person). Someone without tips.manage, pos.close_shift or
     * reports.view (a waiter) sees only the tips that go to them. Needs orders.view.
     */
    #[QueryParameter('shift_uuid', description: 'The shift (default: the terminal\'s open shift).', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{shift_uuid: string|null, business_date: string, own_only: bool, summary: ' . ShiftTips::SHAPE . ', tips: list<' . TipPresenter::TIP . '>}}')]
    public function index(Request $request, TipPresenter $presenter): JsonResponse
    {
        $request->validate(['shift_uuid' => 'nullable|uuid']);
        $user = $this->staff($request);

        $shift = null;
        if ($request->filled('shift_uuid')) {
            $shift = $this->shift($user, (string) $request->input('shift_uuid'));
        } elseif (($device = $this->terminal($user)) !== null) {
            $shift = PosShift::where('device_id', $device->id)->where('status', 'open')->latest('id')->first();
        }
        $branchId = $shift !== null ? (int) $shift->branch_id : $this->permissions->actingBranchId($user);
        $branch = $branchId !== null && $branchId > 0 ? Branch::where('store_id', $user->store_id)->find($branchId) : null;
        if ($branch === null) {
            throw new HttpResponseException($this->error('Filial topilmadi.', ['reason' => 'branch_required'], 422));
        }
        $date = $shift !== null ? $shift->business_date->toDateString() : BusinessDay::of($branch);

        $ownOnly = !$this->permissions->allows($user, Permission::TIPS_MANAGE, $branch)
            && !$this->permissions->allows($user, Permission::POS_CLOSE_SHIFT, $branch)
            && !$this->permissions->allows($user, Permission::REPORTS_VIEW, $branch);

        $rows = PosTip::where('branch_id', $branch->id)
            ->when($shift !== null, fn ($query) => $query->where('shift_id', $shift->id), fn ($query) => $query->whereDate('business_date', $date))
            ->orderBy('id')->get();
        if ($ownOnly) {
            $rows = $rows->filter(fn (PosTip $row) => $row->mode === TipMode::PERSONAL && (int) $row->staff_id === (int) $user->id)->values();
        }
        $tipIds = $rows->map(fn (PosTip $row) => $row->corrects_id ?? $row->id)->unique()->values();
        $tips = PosTip::whereIn('id', $tipIds)->orderByDesc('id')->get();

        $listed = $presenter->many($tips);
        if ($ownOnly) {
            // A waiter's own share of each tip (a split correction may give part of it to someone else)
            $listed = array_map(function (array $tip) use ($user) {
                $mine = collect($tip['holders'])->filter(fn (array $holder) => ($holder['staff']['id'] ?? null) === (int) $user->id);
                $tip['amount'] = Money::sum('0', ...$mine->pluck('amount')->all())->toString();
                $tip['holders'] = $mine->values()->all();

                return $tip;
            }, $listed);
        }

        return $this->success([
            'shift_uuid' => $shift?->uuid,
            'business_date' => $date,
            'own_only' => $ownOnly,
            'summary' => ShiftTips::summarize($rows),
            'tips' => $listed,
        ]);
    }

    /**
     * Correct a tip
     *
     * A manager's correction with a `reason`: the tip now gives `amount` (null: as now) to `mode` personal (`staff_id`;
     * null: unassigned) or pool. tips.manage, or a manager's `approval_token` for it (else 403 approval_required).
     * 409 `tips_period_closed` (a closed pool split covers it); 422 `nothing_to_correct`, `staff_not_found`.
     * Idempotent. Audited (pos.tip_corrected).
     */
    #[BodyParameter('amount', description: 'The tip\'s amount now (so\'m; null: unchanged).', type: 'string', required: false, example: '20000')]
    #[BodyParameter('mode', description: 'personal or pool.', type: 'string', required: true, example: 'personal')]
    #[BodyParameter('staff_id', description: 'Whom a personal tip goes to (null: unassigned).', type: 'int', required: false)]
    #[BodyParameter('reason', description: 'Why (required).', type: 'string', required: true)]
    #[BodyParameter('approval_token', description: "A manager's approval for tips.manage.", type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . TipPresenter::TIP . '}')]
    public function correct(Request $request, TipService $tips, TipPresenter $presenter, ManagerApprovalService $approvals, string $uuid): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999', 'regex:/^\d+(\.\d{1,2})?$/'],
            'mode' => ['required', 'string', Rule::enum(TipMode::class)],
            'staff_id' => 'nullable|integer|min:1|max:999999999999999999',
            'reason' => 'required|string|max:300',
            'approval_token' => 'nullable|string|max:100',
        ]);

        $tip = Str::isUuid($uuid) ? PosTip::where('uuid', $uuid)->where('store_id', $user->store_id)->where('kind', 'tip')->first() : null;
        $branchIds = $this->scope->branchIds($user);
        if ($tip === null || ($branchIds !== null && !in_array((int) $tip->branch_id, $branchIds, true))) {
            throw new HttpResponseException($this->error('Choy puli topilmadi yoki sizning filialingizga tegishli emas.', [], 404));
        }
        $branch = Branch::findOrFail($tip->branch_id);
        $device = $this->terminal($user);

        DB::transaction(function () use ($request, $user, $tip, $data, $tips, $approvals, $branch, $device) {
            $approvedBy = $approvals->authorize($user, Permission::TIPS_MANAGE, $branch, $data['approval_token'] ?? null, $device);
            $tips->correct(
                $user,
                $tip,
                isset($data['amount']) ? Money::of((string) $data['amount']) : null,
                TipMode::from($data['mode']),
                isset($data['staff_id']) ? (int) $data['staff_id'] : null,
                (string) $data['reason'],
                $approvedBy,
                $device,
                $this->applied($request),
            );
        });

        return $this->success($presenter->one($tip->refresh()), 'Choy puli tuzatildi.');
    }
}
