<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Domain\Pos\Setup\PosCatalog;
use App\Enums\OrderChannel;
use App\Enums\Permission;
use App\Http\Controllers\Api\V1\Menu\MenuStaffController;
use App\Models\Store;
use App\Services\Auth\PermissionService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The POS channels of GET /catalog (`channel=pos_hall|pos_takeaway`, P3-13): the branch menu for the store's own
 * staff. CatalogController hands these channels over here, so a customer (or another store's staff) asking for a POS
 * channel gets 403/404 and never a POS price list, and the customer shapes of telegram/mobile stay as they were.
 */
class PosCatalogController extends MenuStaffController
{
    public function __construct(PermissionService $permissions, private readonly PosCatalog $catalog)
    {
        parent::__construct($permissions);
    }

    /**
     * Whether `channel` names a POS channel (the only ones this controller answers).
     */
    public static function handles(mixed $channel): bool
    {
        return in_array($channel, [OrderChannel::POS_HALL->value, OrderChannel::POS_TAKEAWAY->value], true);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['branch_id' => 'nullable|integer|min:1|max:999999999999999999']);

        $branch = $this->branch($request, Permission::MENU_VIEW);

        // `store_id` is optional here: the caller's own store is the only one they may read
        $requested = $request->query('store_id');
        if ($requested !== null && $requested !== '') {
            $store = Store::find($branch->store_id);
            if (!$store instanceof Store || !in_array((string) $requested, [(string) $store->id, (string) $store->uuid], true)) {
                throw new HttpResponseException($this->error("Do'kon topilmadi.", [], 404));
            }
        }

        return $this->success($this->catalog->build($branch, OrderChannel::from((string) $request->query('channel'))));
    }
}
