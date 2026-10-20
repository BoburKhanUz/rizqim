<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Catalog\MenuAvailability;
use App\Support\RowId;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Catalog', "Mijoz uchun menyu: umumiy lenta yoki bitta do'kon menyusi.")]
class MenuAvailabilityController extends Controller
{
    use ApiResponse;

    /**
     * Menu availability per branch
     *
     * What cannot be ordered right now on each active branch of the store (P1-21): `stopped` product/variant/modifier
     * ids (stop-list and branch switches, `stop_list` has reasons and `until`), `sold_out.variants` (batch stock:
     * "tugadi") and the open `batches` with what is left. Refetch it on the `menu.availability_changed` event (public
     * channel `menu.{store_id}`). The same data is in GET /catalog?store_id=… as `availability`.
     */
    #[QueryParameter('store_id', description: "The store's uuid or numeric id.", required: true, type: 'string')]
    #[QueryParameter('branch_id', description: 'Only this branch.', type: 'int')]
    public function index(Request $request, MenuAvailability $availability): JsonResponse
    {
        $storeKey = $request->query('store_id', '');
        $storeKey = is_string($storeKey) ? $storeKey : '';
        $field = ctype_digit($storeKey) ? 'id' : 'uuid';
        if ($storeKey === '' || ($field === 'uuid' && !preg_match('/^[a-f\d]{8}-(?:[a-f\d]{4}-){3}[a-f\d]{12}$/i', $storeKey))) {
            return $this->error('Store not found.', [], 404);
        }

        // P1-34: a numeric id must fit the id column (a longer one used to fail the query with a 500)
        $storeId = $field === 'id' ? RowId::parse($storeKey) : null;
        $branchId = $request->query('branch_id');
        if (($field === 'id' && $storeId === null) || ($branchId !== null && $branchId !== '' && RowId::parse($branchId) === null)) {
            $invalid = $field === 'id' && $storeId === null ? 'store_id' : 'branch_id';
            throw ValidationException::withMessages([$invalid => "{$invalid} musbat butun son bo'lishi kerak."]);
        }

        $store = Store::where($field, $storeId ?? strtolower($storeKey))->first(['id', 'uuid', 'is_active']);
        if (!$store || !$store->is_active) {
            return $this->error('Store not found.', [], 404);
        }

        $branchId = RowId::parse($branchId);

        return $this->success([
            'store_id' => (int) $store->id,
            'branches' => $availability->forStore((int) $store->id, $branchId),
            'generated_at' => now()->toJSON(),
        ]);
    }
}
