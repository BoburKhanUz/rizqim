<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Enums\Permission;
use App\Models\Branch;
use App\Services\Store\BranchService;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P2-12: the store's branches for the partner web panel, with the rules of the old store panel's branch screen:
 * the owner creates and deletes (settings.manage), owners and managers edit (branches.manage; a branch-bound manager
 * only their own branch), staff who work with orders, the menu, staff or reports list them.
 */
#[Group('Owner: branches', "Filiallar: manzil, joylashuv, ish vaqti, ochiq/yopiq holati (P2-12).")]
class BranchController extends ManagementController
{
    private const BRANCH = 'array{id: int, name: string, phone: string|null, address: string|null, region_id: int|null, district_id: int|null, latitude: float|null, longitude: float|null, radius_km: float, status: string, is_active: bool, working_hours: array{open: string|null, close: string|null, all_day: bool, overnight: bool, timezone: string}, is_open_now: bool, created_at: string|null, updated_at: string|null}';

    /**
     * The store's branches
     *
     * Oldest first. A branch-bound caller gets their own branch only. `working_hours` is one daily window in the
     * store's timezone (`all_day`: no hours set; `overnight`: closes after midnight); `is_open_now` is what delivery
     * checkout sees now (status, active flag and hours).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::BRANCH . '>}')]
    public function index(Request $request, BranchService $branches): JsonResponse
    {
        $store = $this->currentStore($request);
        $user = $request->user();

        // P2-33: a branch-bound caller, or a PIN session on a branch terminal, sees that branch only
        $acting = $this->permissions->actingBranchId($user);
        $list = Branch::where('store_id', $store->id)
            ->when($acting !== null, fn ($query) => $query->whereKey($acting))
            ->orderBy('id')
            ->get();

        return $this->success($list->map(fn (Branch $branch) => $branches->present($branch, $store))->values());
    }

    /**
     * One branch
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::BRANCH . '}')]
    public function show(Request $request, int $id, BranchService $branches): JsonResponse
    {
        $branch = $this->branchOf($request, $id);
        $this->scopedTo($request, $branch);

        return $this->success($branches->present($branch, $this->currentStore($request)));
    }

    /**
     * Create a branch
     *
     * Owner only (settings.manage). Required as in the panel: `name`, `address`, `region_id`, `district_id` (of that
     * region), `latitude`, `longitude`. `open_time` and `close_time` ("HH:MM", both or neither): one daily window;
     * equal times or none = round the clock, closing before opening = overnight (e.g. 18:00–02:00).
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::BRANCH . '}')]
    public function store(Request $request, BranchService $branches): JsonResponse
    {
        $store = $this->currentStore($request);
        // Literal rules (the OpenAPI document reads them); the region and district are checked against each other after
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'region_id' => ['required', 'integer', 'min:1', 'max:999999999999999999'],
            'district_id' => ['required', 'integer', 'min:1', 'max:999999999999999999'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'open_time' => ['nullable', 'required_with:close_time', 'string', 'regex:' . BranchService::HOURS_PATTERN],
            'close_time' => ['nullable', 'required_with:open_time', 'string', 'regex:' . BranchService::HOURS_PATTERN],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive,temporarily_closed'],
        ], UzValidation::messages($this->messages()), BranchService::attributeNames());
        $branches->validateLocation($data, null, UzValidation::messages($this->messages()));

        $branch = $branches->create($store, $data);

        return $this->success($branches->present($branch, $store), 'Filial yaratildi.', 201);
    }

    /**
     * Update a branch
     *
     * Any subset of the create fields, plus `status` (active, inactive, temporarily_closed) and `is_active`.
     * branches.manage: a branch-bound manager edits their own branch only (403 for another one).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::BRANCH . '}')]
    public function update(Request $request, int $id, BranchService $branches): JsonResponse
    {
        $branch = $this->branchOf($request, $id);
        $this->authorizeFor($request, Permission::BRANCHES_MANAGE, $branch);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'region_id' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999999999999'],
            'district_id' => ['sometimes', 'required', 'integer', 'min:1', 'max:999999999999999999'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'open_time' => ['nullable', 'required_with:close_time', 'string', 'regex:' . BranchService::HOURS_PATTERN],
            'close_time' => ['nullable', 'required_with:open_time', 'string', 'regex:' . BranchService::HOURS_PATTERN],
            'is_active' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:active,inactive,temporarily_closed'],
        ], UzValidation::messages($this->messages()), BranchService::attributeNames());
        // A district is checked against the region it will have after this change
        $branches->validateLocation($data, $branch, UzValidation::messages($this->messages()));

        // One window: the end left out keeps its stored value, but both must be set or cleared together
        if (array_key_exists('open_time', $data) xor array_key_exists('close_time', $data)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'open_time' => "Ochilish va yopilish vaqtini birga yuboring.",
            ]);
        }
        // A new region needs its district: the stored one belongs to the old region
        if (array_key_exists('region_id', $data) && !array_key_exists('district_id', $data) && (int) $data['region_id'] !== (int) $branch->region_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'district_id' => "Viloyat o'zgarsa, tumanni ham tanlang.",
            ]);
        }

        $branches->update($branch, $data);

        return $this->success($branches->present($branch, $this->currentStore($request)), 'Filial saqlandi.');
    }

    /**
     * Delete a branch
     *
     * Owner only (settings.manage). Refused (422) for a branch that has orders, or the store's only branch: make it
     * inactive instead. P2-33: refused too while staff, unrevoked devices (terminals) or delivery zones are tied to it
     * (they would become store-wide); `data` carries their counts (`staff`, `devices`, `zones`).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: null}')]
    #[ApiDocResponse(422, type: 'array{success: false, message: string, data: array{reason: string, staff: int, devices: int, zones: int}}', description: "Filialni o'chirib bo'lmaydi (`branch_in_use`)")]
    public function destroy(Request $request, int $id, BranchService $branches): JsonResponse
    {
        $branch = $this->branchOf($request, $id);

        $refusal = $branches->deletionRefusal($branch);
        if ($refusal !== null) {
            return $this->error($refusal, ['reason' => 'branch_in_use'] + $branches->references($branch), 422);
        }

        $branches->delete($branch);

        return $this->success(null, "Filial o'chirildi.");
    }

    private function branchOf(Request $request, int $id): Branch
    {
        $branch = Branch::where('store_id', $this->storeId($request))->find($id);
        if (!$branch instanceof Branch) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }

        return $branch;
    }

    /**
     * A branch-bound caller sees their own branch only (as the list).
     */
    private function scopedTo(Request $request, Branch $branch): void
    {
        $user = $request->user();
        $acting = $this->permissions->actingBranchId($user); // P2-33: a terminal PIN session too
        if ($acting !== null && $acting !== (int) $branch->id) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'open_time.regex' => "Ochilish vaqti SS:DD ko'rinishida bo'lishi kerak (masalan 09:00).",
            'close_time.regex' => "Yopilish vaqti SS:DD ko'rinishida bo'lishi kerak (masalan 23:00).",
            'open_time.required_with' => 'Ochilish vaqtini ham kiriting (yoki ikkalasini ham bo\'sh qoldiring).',
            'close_time.required_with' => 'Yopilish vaqtini ham kiriting (yoki ikkalasini ham bo\'sh qoldiring).',
            'district_id.exists' => "Tuman topilmadi yoki tanlangan viloyatga tegishli emas.",
            'region_id.exists' => 'Viloyat topilmadi.',
        ];
    }
}
