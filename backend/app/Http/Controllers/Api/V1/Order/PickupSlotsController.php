<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Domain\Pos\Setup\PosModule;
use App\Domain\Pos\Setup\PosModules;
use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Enums\BranchStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Support\RowId;
use App\Traits\ApiResponse;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;

/**
 * P6-34 (module `scheduled_pickup`): the pickup times a Mini App / Telegram customer may pick for a branch (public,
 * read-only, like the delivery quote). POST /orders takes one of them as `pickup_at`.
 */
#[Group('Orders')]
class PickupSlotsController extends Controller
{
    use ApiResponse;

    /**
     * Pickup times of a branch
     *
     * `enabled: false` (and no slots) when the branch does not take orders for later. Otherwise every `slot_minutes`
     * from the earliest allowed time (`min_lead_minutes` from now) to the end of the window, inside the opening hours
     * (24/7: all day): `at` (UTC, send it back as `pickup_at`), `time` ("HH:MM", the store's), `business_date`. 404 for a
     * branch that is not the store's or not active.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: array{enabled: bool, min_lead_minutes: int, slot_minutes: int, slots: list<' . ScheduledPickup::SLOT . '>}}')]
    public function index(string $store, string $branch, ScheduledPickup $pickup, PosModules $modules): JsonResponse
    {
        $storeId = RowId::parse($store);
        $branchId = RowId::parse($branch);
        $model = $storeId !== null && $branchId !== null
            ? Branch::where('store_id', $storeId)->where('status', BranchStatus::ACTIVE->value)->find($branchId)
            : null;
        if (!$model instanceof Branch) {
            return $this->error('Filial topilmadi.', ['reason' => 'branch_not_found'], 404);
        }

        $settings = $pickup->settings($model);

        return $this->success([
            'enabled' => $modules->enabled($model, PosModule::SCHEDULED_PICKUP),
            'min_lead_minutes' => $settings['pickup_min_lead_minutes'],
            'slot_minutes' => $settings['pickup_slot_minutes'],
            'slots' => $pickup->slots($model),
        ]);
    }
}
