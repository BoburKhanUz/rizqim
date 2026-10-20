<?php

namespace App\Http\Controllers\Api\V1\Menu;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Traits\ApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

/**
 * Shared scoping of the P1-21 staff menu endpoints: everything is the caller's own store; staff bound to a branch
 * act on their branch only (another branch of the store: 403, another store's record: 404).
 */
abstract class MenuStaffController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PermissionService $permissions,
    ) {}

    protected function storeId(Request $request): int
    {
        $user = $request->user();
        if (!$user instanceof User || $user->store_id === null) {
            throw new HttpResponseException($this->error("Do'kon biriktirilmagan.", ['reason' => 'store_required'], 403));
        }

        return (int) $user->store_id;
    }

    /**
     * The branch the request is about: `branch_id` (input), or the caller's own branch when they are bound to one.
     * Without either: 422.
     */
    protected function branch(Request $request, Permission $permission, mixed $branchId = null): Branch
    {
        $user = $request->user();
        $storeId = $this->storeId($request);
        $branchId ??= $request->input('branch_id');

        if (($branchId === null || $branchId === '') && $this->permissions->isBranchScoped($user)) {
            $branchId = $user->branch_id;
        }
        if ($branchId === null || $branchId === '' || filter_var($branchId, FILTER_VALIDATE_INT) === false) {
            throw new HttpResponseException($this->error('Filialni tanlang (branch_id).', ['errors' => ['branch_id' => ['Filialni tanlang.']]], 422));
        }

        $branch = Branch::where('store_id', $storeId)->find((int) $branchId);
        if (!$branch) {
            throw new HttpResponseException($this->error('Filial topilmadi.', [], 404));
        }

        $this->authorizeFor($request, $permission, $branch);

        return $branch;
    }

    /**
     * The permission on this record's branch (PermissionService: branch-bound staff only on their own).
     */
    protected function authorizeFor(Request $request, Permission $permission, mixed $subject): void
    {
        if (!$this->permissions->allows($request->user(), $permission, $subject)) {
            throw new HttpResponseException($this->error('Ruxsat etilmagan', [], 403));
        }
    }

    protected function product(Request $request, int $id): Product
    {
        $product = Product::where('store_id', $this->storeId($request))->find($id);
        if (!$product) {
            throw new HttpResponseException($this->error('Mahsulot topilmadi.', [], 404));
        }

        return $product;
    }
}
