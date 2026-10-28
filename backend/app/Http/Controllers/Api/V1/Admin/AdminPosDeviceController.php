<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DeviceType;
use App\Http\Requests\Admin\AdminRules;
use App\Models\Device;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\Pos\PosDevices;
use App\Services\Admin\Pos\PosPage;
use App\Services\Auth\DeviceService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * P4-24 Qurilmalar: every store's devices (admin.devices.view). Revoking one: admin.stores.manage, a step-up and a
 * `reason`; everything bound to the device stops at once (its tokens and sessions), through DeviceService.
 */
#[Group('Admin')]
class AdminPosDeviceController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    public function __construct(
        private readonly PosDevices $devices,
        private readonly DeviceService $service,
    ) {}

    /**
     * Devices (all stores)
     *
     * Pages (`page`, `per_page` 1-100, default 30), the longest unseen first. Filters: `state` (online: seen in the last
     * 10 minutes; offline; never: never seen; revoked; all; without it, every device that is not revoked), `type`
     * (pos, kds, partner_mobile, courier, ...), `terminal=1` (kassa and KDS only), `store_id`, `outdated=1` (an older app
     * version than the newest seen on an active device of the same type), `q` (device or store name, uuid start). Each row
     * has `state`, `latest_version`, `outdated` and the offline queue the till last reported. `counts` and
     * `latest_versions` ignore the filters.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{data: list<' . PosDevices::ROW . '>, ' . PosPage::SHAPE . ', counts: array{total: int, online: int, offline: int, never: int, revoked: int, outdated: int, by_type: array<string, int>}, latest_versions: array<string, string>}}')]
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', Rule::in(['online', 'offline', 'never', 'revoked', 'all'])],
            'type' => ['nullable', Rule::enum(DeviceType::class)],
            'terminal' => 'nullable|boolean',
            'store_id' => 'nullable|integer|min:1|max:999999999999999999',
            'outdated' => 'nullable|boolean',
            'q' => 'nullable|string|min:2|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ], UzValidation::messages());

        $query = $this->devices->withRelations($this->devices->query($filters));
        // The longest unseen first; devices never seen last among the unseen
        $page = $query->orderByRaw('devices.last_seen_at asc nulls last')->orderBy('devices.id')->paginate(PosPage::perPage($filters['per_page'] ?? null));

        return $this->success(PosPage::of($page, $this->devices->rows($page->items()), [
            'counts' => $this->devices->counts(),
            'latest_versions' => $this->devices->latestVersions(),
        ]));
    }

    /**
     * A device
     *
     * The same row as in the list, by the device's uuid (the Bugun links open it).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosDevices::ROW . '}')]
    #[ApiDocResponse(404, 'No such device (not_found)', type: self::ERROR)]
    public function show(string $uuid): JsonResponse
    {
        $device = $this->find($uuid);

        return $device === null ? $this->notFound() : $this->success($this->devices->rows([$device])[0]);
    }

    /**
     * Revoke a device
     *
     * `reason`. The device and everything signed in on it stops working at once; a terminal has to be paired again with
     * a new code by the owner. Revoking a revoked device changes nothing. Answers the device.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . PosDevices::ROW . '}')]
    public function revoke(Request $request, string $uuid): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $device = $this->find($uuid);
        if ($device === null) {
            return $this->notFound();
        }

        $wasRevoked = $device->isRevoked();
        $this->service->revoke($device, $this->member($request));
        $device->refresh();

        $audit = AdminAudit::for($request)->subject($device)->store((int) $device->store_id)
            ->changes(['revoked' => $wasRevoked], ['revoked' => $device->isRevoked()])
            ->meta(['type' => $device->type->value, 'name' => $device->name]);
        if ($wasRevoked) {
            $audit->skip();
        }

        return $this->success($this->devices->rows([$this->find($uuid) ?? $device])[0], 'Qurilma o\'chirildi. Undagi barcha sessiyalar yopildi.');
    }

    private function find(string $uuid): ?Device
    {
        return $this->devices->withRelations(Device::query()->whereNotNull('store_id')->where('uuid', strtolower($uuid)))->first();
    }

    private function notFound(): JsonResponse
    {
        return $this->refuse('Qurilma topilmadi.', 'not_found', 404);
    }
}
