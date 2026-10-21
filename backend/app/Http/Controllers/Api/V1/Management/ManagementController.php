<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\Branch;
use App\Models\Store;
use App\Support\RowId;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * P2-12: shared scoping of the management endpoints the partner web panel uses (zones, branches, reports, finance
 * history, staff). Everything is the caller's own store; staff bound to a branch see their own branch only (another
 * store's record: 404, another branch of the store: 403), as in MenuStaffController.
 */
abstract class ManagementController extends MenuStaffController
{
    protected function currentStore(Request $request): Store
    {
        $store = Store::find($this->storeId($request));
        if (!$store instanceof Store) {
            throw new HttpResponseException($this->error("Do'kon topilmadi.", [], 404));
        }

        return $store;
    }

    /**
     * An optional `branch_id` filter: the whole store when absent (a branch-bound caller: their branch), the store's
     * branch when given. A malformed id is a 422, another store's branch a 404, a branch the caller may not act on a 403.
     */
    protected function branchFilter(Request $request, Permission $permission): ?Branch
    {
        $user = $request->user();
        $value = $request->input('branch_id');

        if ($value === null || $value === '') {
            // P2-33: a branch-bound caller, or a PIN session on a branch terminal: that branch
            $acting = $this->permissions->actingBranchId($user);
            if ($acting === null) {
                return null;
            }
            $value = $acting;
        }

        $branchId = RowId::parse(is_int($value) ? $value : (is_string($value) ? $value : null));
        if ($branchId === null) {
            throw ValidationException::withMessages(['branch_id' => "Filial raqami noto'g'ri."]);
        }

        $branch = Branch::where('store_id', $this->storeId($request))->find($branchId);
        if (!$branch instanceof Branch) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }

        $this->authorizeFor($request, $permission, $branch);

        return $branch;
    }
}
