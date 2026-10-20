<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\Device;
use App\Services\Auth\AuthTokenException;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\PermissionService;
use App\Services\Auth\StaffPinService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * What a POS/KDS terminal itself calls: pairing with a code, its own details, the staff PIN pad and staff sessions.
 * Everything but pairing is authenticated by the terminal's own token (auth:device).
 */
#[Group('Terminal', "POS/KDS terminali: ulash va xodimning PIN sessiyasi. Ulashdan keyingi so'rovlar terminal tokeni bilan.")]
class TerminalController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DevicePairingService $pairing,
        protected StaffPinService $pins,
        protected PermissionService $permissions,
    ) {}

    /**
     * POST /devices/pair {code, name?, platform?, app_version?} (no auth, rate limited).
     *
     * `code` is the 8-character pairing code shown as ABCD-EFGH; the dash is optional and case does not matter.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{token: string, device: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null}}')]
    public function pair(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:16',
            'name' => 'nullable|string|max:100',
            'platform' => 'nullable|string|max:32',
            'app_version' => 'nullable|string|max:32',
        ]);

        $this->watchPairingVolume();

        try {
            $result = $this->pairing->pair((string) $data['code'], $data);
        } catch (AuthTokenException $e) {
            return $this->error($e->getMessage(), $e->data(), $e->status);
        }

        $device = $result['device']->load(['store:id,name', 'branch:id,name']);

        return $this->success([
            'token' => $result['token'],
            'device' => $device->toPublicArray(),
            'store' => $device->store?->only(['id', 'name']),
            'branch' => $device->branch?->only(['id', 'name']),
        ], 'Terminal ulandi.', 201);
    }

    public function show(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $device->load(['store:id,name', 'branch:id,name']);

        return $this->success([
            'device' => $device->toPublicArray(),
            'store' => $device->store?->only(['id', 'name']),
            'branch' => $device->branch?->only(['id', 'name']),
        ]);
    }

    /**
     * GET /terminal/staff: who can sign in on this terminal (name, role, staff code), for the PIN pad.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{id: int, name: string, role: string, staff_code: string|null, photo_url: string|null}>}')]
    public function staff(Request $request): JsonResponse
    {
        $staff = $this->pins->eligibleStaff($this->device($request))
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'staff_code', 'branch_id', 'telegram_photo_url'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
                'staff_code' => $user->staff_code,
                'photo_url' => $user->telegram_photo_url,
            ]);

        return $this->success($staff);
    }

    /**
     * POST /devices/{device}/staff-session {user_id | staff_code, pin}: a staff session token bound to this terminal.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{token: string, expires_at: string, user: array{id: int, name: string, role: string, branch_id: int|null}, permissions: list<string>}}')]
    public function startStaffSession(Request $request, string $deviceUuid): JsonResponse
    {
        $device = $this->device($request);
        if (!hash_equals($device->uuid, $deviceUuid)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $data = $request->validate([
            'user_id' => 'required_without:staff_code|nullable|integer',
            'staff_code' => 'required_without:user_id|nullable|string|max:8',
            'pin' => 'required|string|max:16',
        ]);

        try {
            $session = $this->pins->startSession(
                $device,
                isset($data['user_id']) ? (int) $data['user_id'] : null,
                $data['staff_code'] ?? null,
                (string) $data['pin'],
            );
        } catch (AuthTokenException $e) {
            $response = $this->error($e->getMessage(), $e->data(), $e->status);

            return $e->retryAfter ? $response->header('Retry-After', (string) $e->retryAfter) : $response;
        }

        $user = $session['user'];

        return $this->success([
            'token' => $session['token'],
            'expires_at' => $session['expires_at']->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role->value,
                'branch_id' => $user->branch_id,
            ],
            'permissions' => array_map(fn ($permission) => $permission->value, $this->permissions->permissionsFor($user)),
        ], 'Xush kelibsiz!');
    }

    /**
     * DELETE /devices/{device}/staff-session: the terminal signs whoever is on it out (screen lock, shift end).
     */
    public function endStaffSession(Request $request, string $deviceUuid): JsonResponse
    {
        $device = $this->device($request);
        if (!hash_equals($device->uuid, $deviceUuid)) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        $this->pins->endSessionsOn($device);

        return $this->success(null, 'Xodim sessiyasi yopildi.');
    }

    /**
     * Pairing attempts are limited per address and per subnet (the "device-pair" limiter), not platform-wide, so nobody
     * can block every terminal's pairing. An unusual platform-wide volume is logged for the on-call instead.
     */
    private function watchPairingVolume(): void
    {
        $key = 'pairing-attempts:' . now()->format('YmdHi');
        Cache::add($key, 0, 120);
        $count = (int) Cache::increment($key);

        if ($count === max(1, (int) config('auth.pairing.alert_attempts_per_minute', 600))) {
            Log::warning('Unusually many terminal pairing attempts this minute: possible code guessing from many addresses.', [
                'attempts' => $count,
            ]);
        }
    }

    private function device(Request $request): Device
    {
        $device = $request->user('device');
        abort_unless($device instanceof Device, 401);

        return $device;
    }
}
