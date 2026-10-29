<?php

namespace App\Http\Controllers\Api\V1\StaffPay;

use App\Domain\Pos\StaffPay\StaffPayPresenter;
use App\Domain\Pos\StaffPay\StaffPayRules;
use App\Domain\Pos\StaffPay\WorkTime;
use App\Enums\Permission;
use App\Models\StaffWorkSession;
use App\Models\User;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * P6-23: the work time of a branch's staff ("Ish vaqti"): the clock-in/out sessions staff make from their PIN session,
 * and a manager's additions and corrections with a reason (audited). Read with staff_pay.view (or staff_pay.manage),
 * changed with staff_pay.manage. Behind the `staff_pay` module.
 */
#[Group('Owner: staff pay')]
class StaffWorkSessionController extends StaffPayOwnerController
{
    /**
     * Work sessions
     *
     * Of a branch, by business date (`from`–`to`, YYYY-MM-DD, default: the last 7 days), optionally one person's
     * (`user_id`); newest first, at most 500. `open`: still clocked in; `abandoned`: left open on an earlier day and
     * set aside (not counted until it has an end). staff_pay.view or staff_pay.manage.
     */
    #[QueryParameter('branch_id', type: 'int', required: false)]
    #[QueryParameter('from', description: 'YYYY-MM-DD', type: 'string', required: false)]
    #[QueryParameter('to', description: 'YYYY-MM-DD', type: 'string', required: false)]
    #[QueryParameter('user_id', type: 'int', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . StaffPayPresenter::SESSION . '>}')]
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'user_id' => UzValidation::rowId(),
        ], UzValidation::messages());
        $branch = $this->moduleBranch($request, $this->readPermission($request));
        $to = (string) ($data['to'] ?? \App\Domain\Pos\Shifts\BusinessDay::of($branch));
        $from = (string) ($data['from'] ?? Carbon::parse($to)->subDays(6)->toDateString());

        $sessions = StaffWorkSession::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('business_date', [$from, $to])
            ->when(isset($data['user_id']), fn ($query) => $query->where('user_id', (int) $data['user_id']))
            ->orderByDesc('clock_in_at')
            ->limit(500)
            ->get();
        $names = User::whereIn('id', $sessions->pluck('user_id'))->pluck('name', 'id')->all();

        return $this->success($sessions->map(fn (StaffWorkSession $session) => StaffPayPresenter::session($session, $names))->values()->all());
    }

    /**
     * Add a work session
     *
     * For a staff member of the branch who did not clock in: `clock_in_at`, `clock_out_at` (ISO 8601 moments; without
     * an end it is open), `reason`. At most 24 hours, never overlapping another of the person's sessions, not in the
     * future (422). Audited. staff_pay.manage.
     */
    #[BodyParameter('branch_id', type: 'int', required: false)]
    #[BodyParameter('user_id', type: 'int', required: true)]
    #[BodyParameter('clock_in_at', type: 'string', required: true)]
    #[BodyParameter('clock_out_at', type: 'string|null', required: false)]
    #[BodyParameter('reason', type: 'string', required: true)]
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::SESSION . '}')]
    public function store(Request $request, WorkTime $workTime, StaffPayRules $rules): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => UzValidation::rowId(),
            'user_id' => UzValidation::rowId(true),
            'clock_in_at' => 'required|date',
            'clock_out_at' => 'nullable|date',
            'reason' => 'required|string|max:500',
        ], UzValidation::messages(), self::ATTRIBUTES);
        $branch = $this->moduleBranch($request, Permission::STAFF_PAY_MANAGE);
        $person = $rules->staff($branch)->whereKey((int) $data['user_id'])->first();
        if ($person === null) {
            throw ValidationException::withMessages(['user_id' => 'Xodim topilmadi yoki bu filialda ishlamaydi.']);
        }
        /** @var User $user */
        $user = $request->user();

        $session = $workTime->add($branch, $person, Carbon::parse((string) $data['clock_in_at']), isset($data['clock_out_at']) ? Carbon::parse((string) $data['clock_out_at']) : null, trim((string) $data['reason']), $user);

        return $this->success(StaffPayPresenter::session($session), 'Saqlandi.', 201);
    }

    /**
     * Correct a work session
     *
     * Its `clock_in_at` and `clock_out_at` (null: still open) with a `reason`; a set-aside session gets its end this
     * way. Same limits as adding. Audited. staff_pay.manage.
     */
    #[BodyParameter('clock_in_at', type: 'string', required: true)]
    #[BodyParameter('clock_out_at', type: 'string|null', required: false)]
    #[BodyParameter('reason', type: 'string', required: true)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . StaffPayPresenter::SESSION . '}')]
    public function update(Request $request, WorkTime $workTime, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'clock_in_at' => 'required|date',
            'clock_out_at' => 'nullable|date',
            'reason' => 'required|string|max:500',
        ], UzValidation::messages(), self::ATTRIBUTES);
        [$session, $branch] = $this->record($request, StaffWorkSession::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Ish vaqti topilmadi.');
        /** @var User $user */
        $user = $request->user();

        $session = $workTime->correct($session, $branch, Carbon::parse((string) $data['clock_in_at']), isset($data['clock_out_at']) ? Carbon::parse((string) $data['clock_out_at']) : null, trim((string) $data['reason']), $user);

        return $this->success(StaffPayPresenter::session($session), 'Saqlandi.');
    }

    /**
     * Remove a work session
     *
     * With a `reason`. Audited. staff_pay.manage.
     */
    #[QueryParameter('reason', type: 'string', required: true)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, WorkTime $workTime, string $uuid): JsonResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], UzValidation::messages(), self::ATTRIBUTES);
        [$session] = $this->record($request, StaffWorkSession::class, $uuid, Permission::STAFF_PAY_MANAGE, 'Ish vaqti topilmadi.');
        /** @var User $user */
        $user = $request->user();
        $workTime->remove($session, trim((string) $data['reason']), $user);

        return $this->success(null, "O'chirildi.");
    }

    private const ATTRIBUTES = ['user_id' => 'Xodim', 'clock_in_at' => 'Kelgan vaqt', 'clock_out_at' => 'Ketgan vaqt', 'reason' => 'Sabab'];
}
