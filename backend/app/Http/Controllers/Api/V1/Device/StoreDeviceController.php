<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Enums\DeviceType;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\Branch;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\AuthTokenException;
use App\Services\Auth\DevicePairingService;
use App\Services\Auth\DeviceService;
use App\Services\Auth\PermissionService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A store's devices for its owner and managers (devices.view / devices.manage): the terminals and the staff's app
 * installs, pairing codes for new terminals, renaming and revoking. A branch manager sees and manages only their
 * branch's devices, and nobody sees the app installs of people ranked as high as them or higher (a manager never
 * sees the owner's phone). Another store's device is simply not found.
 */
#[Group('Owner: devices', "Do'kon qurilmalari: terminal ulash kodi, nomini o'zgartirish, o'chirish (ega, menejer).")]
class StoreDeviceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PermissionService $permissions,
        protected DeviceService $devices,
        protected DevicePairingService $pairing,
    ) {}

    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}>}')]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::enum(DeviceType::class)],
            'include_revoked' => 'nullable|boolean',
        ]);

        /** @var User $user */
        $user = $request->user();

        $devices = $this->visible($user)
            ->with('user:id,name,role')
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', $request->input('type')))
            ->when(!$request->boolean('include_revoked'), fn (Builder $query) => $query->whereNull('revoked_at'))
            ->latest('id')
            ->limit(500)
            ->get();

        return $this->success($devices->map->toPublicArray());
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|min:1|max:100']);

        /** @var User $user */
        $user = $request->user();
        $device = $this->findManageable($user, $uuid);
        if (!$device) {
            return $this->error('Qurilma topilmadi.', [], 404);
        }

        $this->devices->rename($device, $data['name'], $user);

        return $this->success($device->toPublicArray(), 'Qurilma nomi o\'zgartirildi.');
    }

    public function revoke(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->findManageable($user, $uuid);
        if (!$device) {
            return $this->error('Qurilma topilmadi.', [], 404);
        }

        $this->devices->revoke($device, $user);

        return $this->success($device->refresh()->toPublicArray(), "Qurilma o'chirildi. Undagi barcha sessiyalar yopildi.");
    }

    /**
     * POST /owner/devices/pairing-codes {type: pos|kds, branch_id?, name?}: a one-time code for a terminal.
     *
     * `code` is 8 characters shown as ABCD-EFGH (digits and capitals without I, L, O, U); it lasts AUTH_PAIRING_CODE_TTL
     * minutes and works once.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: array{code: string, expires_at: string, type: string, branch_id: int|null}}')]
    public function createPairingCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_map(fn (DeviceType $type) => $type->value, DeviceType::terminals()))],
            'branch_id' => 'nullable|integer',
            'name' => 'nullable|string|max:100',
        ]);

        /** @var User $user */
        $user = $request->user();
        $store = $user->store;
        if (!$store) {
            return $this->error('Ruxsat etilmagan', [], 403);
        }

        // A branch manager pairs terminals for their own branch only
        $branchId = $data['branch_id'] ?? ($this->permissions->isBranchScoped($user) ? $user->branch_id : null);
        $branch = $branchId !== null ? Branch::where('store_id', $store->id)->whereKey($branchId)->first() : null;

        if (($branchId !== null && !$branch) || !$this->permissions->allows($user, Permission::DEVICES_MANAGE, $branch ?? $store)
            || ($branch === null && $this->permissions->isBranchScoped($user))) {
            return $this->error("Noto'g'ri filial tanlandi.", [], 422);
        }

        // P4-21: a KDS needs the plan's kds; while the subscription is locked only up to the plan's terminals (a
        // broken till is still replaced). 403 plan_feature_missing / device_limit
        app(\App\Services\Finance\Billing\Entitlements::class)->assertCanPair($store, DeviceType::from($data['type']));
        // P6-11: a KDS needs the branch's kds module (the store's: on for at least one branch); 403 module_disabled
        if ($data['type'] === DeviceType::KDS->value) {
            $modules = app(\App\Domain\Pos\Setup\PosModules::class);
            $branch !== null
                ? $modules->assertEnabled($branch, \App\Domain\Pos\Setup\PosModule::KDS)
                : $modules->assertEnabledInStore((int) $store->id, \App\Domain\Pos\Setup\PosModule::KDS);
        }

        try {
            $result = $this->pairing->createCode($store, $branch, DeviceType::from($data['type']), $data['name'] ?? null, $user);
        } catch (AuthTokenException $e) {
            return $this->error($e->getMessage(), $e->data(), $e->status);
        }

        return $this->success([
            'code' => $result['code'],
            'expires_at' => $result['expires_at']->toIso8601String(),
            'type' => $data['type'],
            'branch_id' => $branch?->id,
        ], 'Ulash kodi yaratildi. Uni terminalda kiriting.', 201);
    }

    /**
     * @return Builder<Device>
     */
    private function visible(User $user): Builder
    {
        return $this->devices->withinRankOf(Device::query(), $user)
            ->where('store_id', $user->store_id)
            ->when($this->permissions->isBranchScoped($user), fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function findManageable(User $user, string $uuid): ?Device
    {
        $device = $this->visible($user)->where('uuid', $uuid)->whereNull('revoked_at')->first();

        return $device && $this->devices->canManage($user, $device) ? $device : null;
    }
}
