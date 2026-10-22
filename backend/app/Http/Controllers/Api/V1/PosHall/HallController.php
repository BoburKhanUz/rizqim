<?php

namespace App\Http\Controllers\Api\V1\PosHall;

use App\Domain\Pos\Hall\HallLayout;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Management\ManagementController;
use App\Models\DiningTable;
use App\Models\Hall;
use App\Models\ServiceFeeSetting;
use App\Services\Audit\AuditLogger;
use App\Support\RowId;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * P3-12: halls and zones of a branch for the web panel (R1): the main hall, the yard, a summer zone switched off for
 * the season. tables.manage; never from a terminal PIN session. Another store's hall: 404; a branch-bound caller works
 * in their branch only (403).
 */
#[Group('Owner: halls and tables', "Zallar, zonalar, stollar, xonalar va tapchanlar; zal xaritasi (P3-12).")]
class HallController extends ManagementController
{
    public const HALL = 'array{id: int, branch_id: int, name: string, sort_order: int, is_active: bool, service_fee: array{service_charge_percent: string, place_fee_amount: string, place_fee_mode: string}|null, tables: list<' . DiningTableController::TABLE . '>}';

    /**
     * Halls with their tables
     *
     * The halls of a branch (`branch_id`; without it every branch the caller works in) in floor order, each with its
     * own service charge override (null: the branch's applies) and its tables, rooms and tapchans.
     */
    #[QueryParameter('branch_id', description: 'One branch; omit for every branch the caller works in.', type: 'int')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: list<' . self::HALL . '>}')]
    public function index(Request $request, HallLayout $layout): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1']);
        $branch = $this->branchFilter($request, Permission::TABLES_MANAGE);

        $halls = Hall::where('store_id', $this->storeId($request))
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch?->id))
            ->orderBy('branch_id')->orderBy('sort_order')->orderBy('id')
            ->get();

        return $this->success($layout->halls($halls));
    }

    /**
     * Add a hall
     */
    #[ApiDocResponse(201, type: 'array{success: true, message: string, data: ' . self::HALL . '}')]
    public function store(Request $request, AuditLogger $audit, HallLayout $layout): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer|min:1',
            'name' => 'required|string|min:1|max:100',
            'sort_order' => 'nullable|integer|min:0|max:100000',
            'is_active' => 'nullable|boolean',
        ]);
        $branch = $this->branch($request, Permission::TABLES_MANAGE, $data['branch_id']);
        $this->assertUniqueName((int) $branch->id, trim($data['name']), null);

        $hall = Hall::create([
            'store_id' => $branch->store_id,
            'branch_id' => $branch->id,
            'name' => trim($data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $audit->record('pos.hall_created', $hall, [], [], ['name' => $hall->name]);

        return $this->success($layout->halls(collect([$hall]))[0], "Zal qo'shildi.", 201);
    }

    /**
     * Edit a hall
     *
     * Name, order, and `is_active` (a seasonal zone closed: its places leave the floor map; open orders stay).
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . self::HALL . '}')]
    public function update(Request $request, string $id, AuditLogger $audit, HallLayout $layout): JsonResponse
    {
        $hall = $this->hall($request, $id);
        $data = $request->validate([
            'name' => 'sometimes|required|string|min:1|max:100',
            'sort_order' => 'sometimes|integer|min:0|max:100000',
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            $this->assertUniqueName((int) $hall->branch_id, $data['name'], (int) $hall->id);
        }

        $before = $hall->only(['name', 'sort_order', 'is_active']);
        $hall->fill($data)->save();
        $audit->record('pos.hall_updated', $hall, $before, $hall->only(['name', 'sort_order', 'is_active']));

        return $this->success($layout->halls(collect([$hall]))[0], 'Zal saqlandi.');
    }

    /**
     * Delete a hall
     *
     * With its places. 422 `hall_in_use` while an order is open at one of them. Past orders keep the names.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{id: int}}')]
    public function destroy(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $hall = $this->hall($request, $id);

        if (DiningTable::where('hall_id', $hall->id)->whereHas('openOrders')->exists()) {
            return $this->error("Zaldagi stolda ochiq buyurtma bor: avval uni yoping yoki ko'chiring.", ['reason' => 'hall_in_use'], 422);
        }

        DB::transaction(function () use ($hall, $audit) {
            DiningTable::where('hall_id', $hall->id)->delete();
            ServiceFeeSetting::where('hall_id', $hall->id)->delete();
            $hall->delete();
            $audit->record('pos.hall_deleted', $hall, [], [], ['name' => $hall->name]);
        });

        return $this->success(['id' => (int) $hall->id], "Zal o'chirildi.");
    }

    /**
     * Remove a hall's own service charge
     *
     * The hall goes back to the branch's (or the store's) service charge and place fee. settings.manage.
     */
    #[Group('Owner: service fees')]
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{hall_id: int}}')]
    public function destroyServiceFee(Request $request, string $id, AuditLogger $audit): JsonResponse
    {
        $hall = $this->hall($request, $id, Permission::SETTINGS_MANAGE);
        $setting = ServiceFeeSetting::where('hall_id', $hall->id)->first();
        if ($setting !== null) {
            $setting->delete();
            $audit->record('settings.service_fees_changed', $setting, [], [], ['hall_id' => (int) $hall->id, 'removed' => true], ['branch_id' => (int) $hall->branch_id]);
        }

        return $this->success(['hall_id' => (int) $hall->id], "Zal sozlamasi o'chirildi.");
    }

    protected function hall(Request $request, string $id, Permission $permission = Permission::TABLES_MANAGE): Hall
    {
        $hallId = RowId::parse($id);
        $hall = $hallId !== null ? Hall::where('store_id', $this->storeId($request))->find($hallId) : null;
        if (!$hall instanceof Hall) {
            throw new HttpResponseException($this->error('Zal topilmadi.', [], 404));
        }

        $this->authorizeFor($request, $permission, $hall);

        return $hall;
    }

    private function assertUniqueName(int $branchId, string $name, ?int $exceptId): void
    {
        $taken = Hall::where('branch_id', $branchId)
            ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Filialda shu nomli zal bor.']);
        }
    }
}
