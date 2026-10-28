<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Requests\Admin\AdminRules;
use App\Models\District;
use App\Models\Region;
use App\Services\Admin\AdminAudit;
use App\Services\Admin\System\Locations;
use App\Services\Admin\System\SystemAdminException;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P4-31 Tizim → Viloyatlar va tumanlar (admin.system.manage): list, create, rename, switch on/off, order and delete.
 * Every write needs a step-up and a `reason`. A region or district that branches, saved addresses or store applications
 * point at is never deleted (422 `in_use` with the counts); a region with districts neither (422 `has_districts`).
 */
#[Group('Admin')]
class AdminSystemLocationController extends AdminController
{
    private const ERROR = 'array{success: false, message: string, code: string, data: array{reason: string}}';

    private const NAME = ['required', 'string', 'min:2', 'max:120'];

    public function __construct(
        private readonly Locations $locations,
    ) {}

    /**
     * Regions and districts
     *
     * Every region (by `sort_order`, then name) with its districts and what uses them: `usage` (branches, addresses,
     * applications) and `deletable`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . Locations::REGION . '>}')]
    public function index(): JsonResponse
    {
        return $this->success($this->locations->regions());
    }

    /**
     * Add a region
     *
     * `name` (2-120, unique), `is_active` (default false), `reason`. It goes to the end of the order.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . Locations::REGION . '}')]
    #[ApiDocResponse(422, 'The name is taken (name_taken)', type: self::ERROR)]
    public function storeRegion(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => self::NAME, 'is_active' => 'sometimes|boolean', 'reason' => AdminRules::reason()], UzValidation::messages());

        try {
            $region = $this->locations->createRegion(trim($data['name']), (bool) ($data['is_active'] ?? false));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($region)->changes([], ['name' => $region->name_uz, 'is_active' => (bool) $region->is_active]);

        return $this->success($this->locations->regionRow($region), "Viloyat qo'shildi.", 201);
    }

    /**
     * Change a region
     *
     * `name` and/or `is_active`, `reason`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . Locations::REGION . '}')]
    #[ApiDocResponse(404, 'No such region (not_found)', type: self::ERROR)]
    public function updateRegion(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['name' => ['sometimes', ...array_slice(self::NAME, 1)], 'is_active' => 'sometimes|boolean', 'reason' => AdminRules::reason()], UzValidation::messages());
        $region = Region::query()->find($id);
        if ($region === null) {
            return $this->notFound('Viloyat topilmadi.');
        }

        try {
            $change = $this->locations->updateRegion($region, $this->change($data));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $this->audit($request, $region, $change);

        return $this->success($this->locations->regionRow($region->refresh()), 'Viloyat saqlandi.');
    }

    /**
     * Delete a region
     *
     * `reason`. Only one nothing uses and without districts.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{deleted: true}}')]
    #[ApiDocResponse(422, 'Used (in_use, data.usage) or has districts (has_districts)', type: self::ERROR)]
    public function destroyRegion(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $region = Region::query()->find($id);
        if ($region === null) {
            return $this->notFound('Viloyat topilmadi.');
        }

        try {
            $this->locations->deleteRegion($region);
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($region)->changes(['name' => $region->name_uz], [])->meta(['name' => $region->name_uz]);

        return $this->success(['deleted' => true], "Viloyat o'chirildi.");
    }

    /**
     * Order the regions
     *
     * `ids`: every region id once, in the new order; `reason`. Else 422 `order_mismatch`. Answers the list.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . Locations::REGION . '>}')]
    #[ApiDocResponse(422, 'Not the full list (order_mismatch)', type: self::ERROR)]
    public function orderRegions(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer|distinct|min:1', 'reason' => AdminRules::reason()], UzValidation::messages());

        try {
            $change = $this->locations->orderRegions(array_map('intval', $data['ids']));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $audit = AdminAudit::for($request)->changes(['order' => $change['before']], ['order' => $change['after']]);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->locations->regions(), 'Tartib saqlandi.');
    }

    /**
     * Add a district
     *
     * `name` (2-120, unique in the region), `is_active` (default false), `reason`. It goes to the end of the region.
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . Locations::DISTRICT . '}')]
    #[ApiDocResponse(422, 'The name is taken in the region (name_taken)', type: self::ERROR)]
    public function storeDistrict(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['name' => self::NAME, 'is_active' => 'sometimes|boolean', 'reason' => AdminRules::reason()], UzValidation::messages());
        $region = Region::query()->find($id);
        if ($region === null) {
            return $this->notFound('Viloyat topilmadi.');
        }

        try {
            $district = $this->locations->createDistrict($region, trim($data['name']), (bool) ($data['is_active'] ?? false));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($district)->changes([], ['name' => $district->name_uz, 'is_active' => (bool) $district->is_active, 'region_id' => (int) $region->id]);

        return $this->success($this->locations->districtRow($district), "Tuman qo'shildi.", 201);
    }

    /**
     * Change a district
     *
     * `name`, `is_active` and/or `region_id` (move to another region: only a district nothing uses), `reason`.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . Locations::DISTRICT . '}')]
    #[ApiDocResponse(404, 'No such district (not_found)', type: self::ERROR)]
    public function updateDistrict(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', ...array_slice(self::NAME, 1)],
            'is_active' => 'sometimes|boolean',
            'region_id' => 'sometimes|integer|min:1|max:999999999999999999',
            'reason' => AdminRules::reason(),
        ], UzValidation::messages());
        $district = District::query()->find($id);
        if ($district === null) {
            return $this->notFound('Tuman topilmadi.');
        }

        $change = $this->change($data);
        if (array_key_exists('region_id', $data)) {
            $change['region_id'] = (int) $data['region_id'];
        }

        try {
            $result = $this->locations->updateDistrict($district, $change);
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $this->audit($request, $district, $result);

        return $this->success($this->locations->districtRow($district->refresh()), 'Tuman saqlandi.');
    }

    /**
     * Delete a district
     *
     * `reason`. Only one nothing uses.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{deleted: true}}')]
    #[ApiDocResponse(422, 'Used (in_use, data.usage)', type: self::ERROR)]
    public function destroyDistrict(Request $request, int $id): JsonResponse
    {
        $request->validate(['reason' => AdminRules::reason()], UzValidation::messages());
        $district = District::query()->find($id);
        if ($district === null) {
            return $this->notFound('Tuman topilmadi.');
        }

        try {
            $this->locations->deleteDistrict($district);
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        AdminAudit::for($request)->subject($district)->changes(['name' => $district->name_uz], [])->meta(['name' => $district->name_uz, 'region_id' => (int) $district->region_id]);

        return $this->success(['deleted' => true], "Tuman o'chirildi.");
    }

    /**
     * Order a region's districts
     *
     * `ids`: every district of the region once, in the new order; `reason`. Answers the region.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . Locations::REGION . '}')]
    #[ApiDocResponse(422, 'Not the full list (order_mismatch)', type: self::ERROR)]
    public function orderDistricts(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:500', 'ids.*' => 'integer|distinct|min:1', 'reason' => AdminRules::reason()], UzValidation::messages());
        $region = Region::query()->find($id);
        if ($region === null) {
            return $this->notFound('Viloyat topilmadi.');
        }

        try {
            $change = $this->locations->orderDistricts($region, array_map('intval', $data['ids']));
        } catch (SystemAdminException $e) {
            return $this->refuse($e->getMessage(), $e->reason, $e->status, $e->data);
        }

        $audit = AdminAudit::for($request)->subject($region)->changes(['order' => $change['before']], ['order' => $change['after']]);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }

        return $this->success($this->locations->regionRow($region), 'Tartib saqlandi.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name?: string, is_active?: bool}
     */
    private function change(array $data): array
    {
        $change = [];
        if (array_key_exists('name', $data)) {
            $change['name'] = trim((string) $data['name']);
        }
        if (array_key_exists('is_active', $data)) {
            $change['is_active'] = (bool) $data['is_active'];
        }

        return $change;
    }

    /**
     * @param  array{before: array<string, mixed>, after: array<string, mixed>}  $change
     */
    private function audit(Request $request, Region|District $subject, array $change): void
    {
        $audit = AdminAudit::for($request)->subject($subject)->changes($change['before'], $change['after']);
        if ($change['before'] === $change['after']) {
            $audit->skip();
        }
    }

    private function notFound(string $message): JsonResponse
    {
        return $this->refuse($message, 'not_found', 404);
    }
}
