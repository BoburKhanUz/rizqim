<?php

namespace App\Http\Controllers\Api\V1\StaffPay;

use App\Domain\Pos\Shifts\BusinessDay;
use App\Domain\Pos\StaffPay\StaffPayPresenter;
use App\Domain\Pos\StaffPay\WorkTime;
use App\Http\Controllers\Api\V1\PosBilling\PosBillingController;
use App\Models\Branch;
use App\Models\Device;
use App\Models\StaffWorkSession;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-23: the till's clock-in/out ("Ishga keldim" / "Ishdan ketdim"): the signed-in person marks their own work time
 * from their PIN session on a paired terminal of the branch (docs/POS_SPEC.md "Ish haqi qoidalari"). Every terminal
 * role; behind the `staff_pay` module; writes take an Idempotency-Key.
 */
#[Group('POS: staff pay', "Kassa va ofitsiant ilovasida ish vaqtini belgilash (P6-23).")]
class PosClockController extends PosBillingController
{
    public const STATE = 'array{session: ' . StaffPayPresenter::SESSION . '|null, today_minutes: int, business_date: string}';

    /**
     * My work time
     *
     * The caller's open session (null when not clocked in) and the minutes of their closed sessions of the branch's
     * business day today.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function show(Request $request, WorkTime $workTime): JsonResponse
    {
        $user = $this->staff($request);
        $branch = $this->branchOf($user);

        return $this->success($this->state($user, $branch, $workTime));
    }

    /**
     * Clock in
     *
     * From the caller's PIN session on a paired terminal (403 `terminal_required` otherwise). 409
     * `already_clocked_in`; a session the person left open on an earlier business day is set aside for a manager.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function in(Request $request, WorkTime $workTime): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $device = $this->device($user);

        $workTime->clockIn($user, $device, $this->applied($request));

        return $this->success($this->state($user, $this->branchOf($user), $workTime), 'Ishga kelganingiz belgilandi.', 201);
    }

    /**
     * Clock out
     *
     * Ends the caller's open session (409 `not_clocked_in`). A session longer than 24 hours is set aside for a manager
     * instead of counted.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::STATE . '}')]
    public function out(Request $request, WorkTime $workTime): JsonResponse
    {
        $user = $this->staff($request);
        $this->requireIdempotencyKey($request);
        $device = $this->device($user);

        $workTime->clockOut($user, $device, $this->applied($request));

        return $this->success($this->state($user, $this->branchOf($user), $workTime), 'Ishdan ketganingiz belgilandi.');
    }

    private function device(User $user): Device
    {
        $device = $this->terminal($user);
        if ($device === null) {
            throw new HttpResponseException($this->error("Ish vaqti kassa yoki ofitsiant terminalida PIN bilan kirib belgilanadi.", ['reason' => 'terminal_required'], 403));
        }

        return $device;
    }

    private function branchOf(User $user): ?Branch
    {
        $branchId = $this->permissions->actingBranchId($user);

        return $branchId !== null && $branchId > 0 ? Branch::where('store_id', $user->store_id)->find($branchId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function state(User $user, ?Branch $branch, WorkTime $workTime): array
    {
        $open = $workTime->current($user);
        $today = $branch !== null ? BusinessDay::of($branch) : now()->toDateString();
        $minutes = $branch !== null ? (int) StaffWorkSession::query()->where('user_id', $user->id)->where('branch_id', $branch->id)
            ->where('business_date', $today)->whereNotNull('clock_out_at')->get()->sum(fn (StaffWorkSession $session) => $session->minutes()) : 0;

        return [
            'session' => $open !== null ? StaffPayPresenter::session($open, [(int) $user->id => (string) $user->name]) : null,
            'today_minutes' => $minutes,
            'business_date' => $today,
        ];
    }
}
