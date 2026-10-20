<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Http\Controllers\Api\V1\Auth\DeviceRegistrationRules;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use App\Models\Device;
use App\Models\User;
use App\Services\Auth\DeviceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own app installs: register or update one (name, version, push token), list, revoke.
 */
#[Group('Devices', "Foydalanuvchining o'z qurilmalari (ilova o'rnatmalari): ro'yxatga olish, push token, o'chirish.")]
class DeviceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected DeviceService $devices,
    ) {}

    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}>}')]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->success(
            Device::where('user_id', $user->id)->active()->latest('last_seen_at')->get()->map->toPublicArray()
        );
    }

    /**
     * POST /devices: register this install, or update it when `uuid` is one of the user's devices.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{uuid: string, type: string, name: string, platform: string|null, app_version: string|null, store_id: int|null, branch_id: int|null, user: array{id: int, name: string, role: string}|null, has_push_token: bool, last_seen_at: string|null, revoked_at: string|null, created_at: string|null}}')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(DeviceRegistrationRules::rules(''));

        /** @var User $user */
        $user = $request->user();
        $device = $this->devices->register($user, $data);

        return $this->success($device->toPublicArray(), "Qurilma saqlandi.", $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $device = Device::where('uuid', $uuid)->where('user_id', $user->id)->active()->first();
        if (!$device) {
            return $this->error('Qurilma topilmadi.', [], 404);
        }

        $this->devices->revoke($device, $user);

        return $this->success(null, "Qurilma o'chirildi.");
    }
}
