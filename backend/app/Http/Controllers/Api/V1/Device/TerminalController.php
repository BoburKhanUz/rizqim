<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
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

    /**
     * GET /terminal: the terminal, its store and branch.
     *
     * P2-30: `branches`, the branches whose real-time channel (`private-pos-branch.{id}`) it may join: its own branch,
     * or every branch of the store for a terminal paired without one (a store-wide kitchen display subscribes to each).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{device: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}, store: array{id: int, name: string}|null, branch: array{id: int, name: string}|null, branches: list<array{id: int, name: string}>}}')]
    public function show(Request $request): JsonResponse
    {
        $device = $this->device($request);
        $device->load(['store:id,name', 'branch:id,name']);

        $branches = $device->store_id === null ? collect() : \App\Models\Branch::where('store_id', $device->store_id)
            ->when($device->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
            ->orderBy('id')
            ->get(['id', 'name']);

        return $this->success([
            'device' => $device->toPublicArray(),
            'store' => $device->store?->only(['id', 'name']),
            'branch' => $device->branch?->only(['id', 'name']),
            'branches' => $branches->map(fn (\App\Models\Branch $branch) => ['id' => (int) $branch->id, 'name' => (string) $branch->name])->values()->all(),
        ]);
    }

    /**
     * GET /terminal/staff: who can sign in on this terminal (name, role, staff code), for the PIN pad
     *
     * P2-30: `role` (comma-separated, e.g. `cook,manager,owner` on a kitchen display) lists only those roles; an
     * unknown role: 422.
     */
    #[QueryParameter('role', description: 'Comma-separated roles to list: owner, manager, cashier, waiter, cook.', type: 'string', required: false)]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{id: int, name: string, role: string, staff_code: string|null, photo_url: string|null}>}')]
    public function staff(Request $request): JsonResponse
    {
        $request->validate(['role' => 'nullable|string|max:100']);
        $terminalRoles = array_map(fn (\App\Enums\UserRole $role) => $role->value, \App\Enums\UserRole::terminalRoles());
        $roles = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $request->query('role', ''))), fn ($role) => $role !== '')));
        if (array_diff($roles, $terminalRoles) !== []) {
            throw \Illuminate\Validation\ValidationException::withMessages(['role' => "Rol noto'g'ri: faqat " . implode(', ', $terminalRoles) . '.']);
        }

        $staff = $this->pins->eligibleStaff($this->device($request))
            ->when($roles !== [], fn ($query) => $query->whereIn('role', $roles))
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
     * Unpair this terminal (P2-33)
     *
     * The terminal revokes itself with its own token (`auth:device`), e.g. before the kitchen display forgets its
     * pairing: the same as the owner's `POST /owner/devices/{uuid}/revoke` — the device is revoked, its token and every
     * staff PIN session on it stop working at once, and the audit log records `device.revoked` (`self_revoked: true`).
     * Pairing again needs a new code. Calling it again with the old token: 401.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    public function destroy(Request $request, \App\Services\Auth\DeviceService $devices): JsonResponse
    {
        $devices->revoke($this->device($request), null, true);

        return $this->success(null, "Terminal uzildi. Qayta ulash uchun yangi kod kerak.");
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
