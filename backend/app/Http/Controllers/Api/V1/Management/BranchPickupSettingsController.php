<?php

namespace App\Http\Controllers\Api\V1\Management;

use App\Domain\Pos\Takeaway\ScheduledPickup;
use App\Enums\Permission;
use App\Support\RowId;
use App\Support\UzValidation;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ApiDocResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * P6-34 (module `scheduled_pickup`): a branch's rules for orders picked up later ("Oldindan buyurtma"): the soonest a
 * pickup time may be, the step of the times offered, how many days ahead, the kitchen's prep time. settings.manage;
 * never from a terminal PIN session; audited.
 */
#[Group('Owner: packaging')]
class BranchPickupSettingsController extends ManagementController
{
    /**
     * The branch's pickup-time rules
     *
     * Every key with its default when nothing was saved (`configured: false`): `pickup_min_lead_minutes` (20),
     * `pickup_slot_minutes` (15), `pickup_max_days_ahead` (1; 0: today's business day only), `pickup_prep_minutes` (15:
     * the kitchen gets the order this long before the pickup time, or the longest `wait_minutes` of its dishes).
     * `round_the_clock`: the 24/7 module (times at any hour); `opening_hours`: the store's hours the times keep to.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . ScheduledPickup::SETTINGS . '}')]
    public function show(Request $request, string $branch, ScheduledPickup $pickup): JsonResponse
    {
        return $this->success($pickup->view($this->branch($request, Permission::SETTINGS_MANAGE, RowId::parse($branch))));
    }

    /**
     * Change the branch's pickup-time rules
     *
     * Send only what changes. Audited.
     */
    #[ApiDocResponse(200, type: 'array{success: true, message: string, data: ' . ScheduledPickup::SETTINGS . '}')]
    public function update(Request $request, string $branch, ScheduledPickup $pickup): JsonResponse
    {
        $model = $this->branch($request, Permission::SETTINGS_MANAGE, RowId::parse($branch));
        $data = $request->validate([
            'pickup_min_lead_minutes' => ['sometimes', 'integer', 'min:0', 'max:' . ScheduledPickup::MAX_LEAD_MINUTES],
            'pickup_slot_minutes' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'pickup_max_days_ahead' => ['sometimes', 'integer', 'min:0', 'max:' . ScheduledPickup::MAX_DAYS_AHEAD],
            'pickup_prep_minutes' => ['sometimes', 'integer', 'min:0', 'max:' . ScheduledPickup::MAX_PREP_MINUTES],
        ], UzValidation::messages(), [
            'pickup_min_lead_minutes' => 'Eng kam oldindan vaqt (daqiqa)',
            'pickup_slot_minutes' => 'Vaqt oralig\'i (daqiqa)',
            'pickup_max_days_ahead' => 'Necha kun oldin',
            'pickup_prep_minutes' => 'Tayyorlash vaqti (daqiqa)',
        ]);

        return $this->success($pickup->update($model, $data, $request->user()), 'Olib ketish vaqti sozlamalari saqlandi.');
    }
}
