<?php

/*
| P6-34 (wave "Fast food va qahvaxona"): packaging, 24/7 and the pickup time.
|  - the owner's packaging rules (`packaging`), pickup-time rules (`scheduled_pickup`) and the business-day report
|    (`round_the_clock`) for the partner web;
|  - on the till: a packaging line taken off or put back, a takeaway's pickup time and the times to offer;
|  - public: the pickup times of a branch for the Mini App (POST /orders takes one as `pickup_at`).
| Every POS change takes an Idempotency-Key (required) and the order's version. Required from routes/api.php inside its
| /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Management\BranchBusinessDayReportController;
use App\Http\Controllers\Api\V1\Management\BranchPackagingRulesController;
use App\Http\Controllers\Api\V1\Management\BranchPickupSettingsController;
use App\Http\Controllers\Api\V1\Order\PickupSlotsController;
use App\Http\Controllers\Api\V1\PosHall\PosTakeawayController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::get('/stores/{store}/branches/{branch}/pickup-slots', [PickupSlotsController::class, 'index'])
    ->where(['store' => RowId::ROUTE_PATTERN, 'branch' => RowId::ROUTE_PATTERN])->middleware('throttle:delivery-quote');

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // Web panel: settings and reports, so never from a terminal PIN session
    Route::middleware('not-staff-session')->group(function () {
        Route::get('/owner/branches/{branch}/packaging-rules', [BranchPackagingRulesController::class, 'index'])->where('branch', RowId::ROUTE_PATTERN)
            ->middleware(['permission:settings.manage', 'pos.module:packaging']);
        Route::post('/owner/branches/{branch}/packaging-rules', [BranchPackagingRulesController::class, 'store'])->where('branch', RowId::ROUTE_PATTERN)
            ->middleware(['permission:settings.manage', 'pos.module:packaging', 'billing.unlocked']);
        Route::put('/owner/branches/{branch}/packaging-rules/{rule}', [BranchPackagingRulesController::class, 'update'])->where(['branch' => RowId::ROUTE_PATTERN, 'rule' => RowId::ROUTE_PATTERN])
            ->middleware(['permission:settings.manage', 'pos.module:packaging', 'billing.unlocked']);
        Route::delete('/owner/branches/{branch}/packaging-rules/{rule}', [BranchPackagingRulesController::class, 'destroy'])->where(['branch' => RowId::ROUTE_PATTERN, 'rule' => RowId::ROUTE_PATTERN])
            ->middleware(['permission:settings.manage', 'pos.module:packaging', 'billing.unlocked']);

        Route::get('/owner/branches/{branch}/pickup-settings', [BranchPickupSettingsController::class, 'show'])->where('branch', RowId::ROUTE_PATTERN)
            ->middleware(['permission:settings.manage', 'pos.module:scheduled_pickup']);
        Route::put('/owner/branches/{branch}/pickup-settings', [BranchPickupSettingsController::class, 'update'])->where('branch', RowId::ROUTE_PATTERN)
            ->middleware(['permission:settings.manage', 'pos.module:scheduled_pickup', 'billing.unlocked']);

        Route::get('/owner/branches/{branch}/business-days', [BranchBusinessDayReportController::class, 'index'])->where('branch', RowId::ROUTE_PATTERN)
            ->middleware(['permission:reports.view', 'pos.module:round_the_clock']);
    });

    Route::prefix('pos')->group(function () {
        $idempotent = fn (string $scope) => EnsureIdempotency::class . ':' . $scope;

        Route::post('/orders/{uuid}/packaging/{rule}/remove', [PosTakeawayController::class, 'removePackaging'])->whereUuid('uuid')->where('rule', RowId::ROUTE_PATTERN)
            ->middleware(['permission:orders.manage', 'pos.module:packaging', $idempotent('pos.orders.packaging_remove')]);
        Route::post('/orders/{uuid}/packaging/{rule}/restore', [PosTakeawayController::class, 'restorePackaging'])->whereUuid('uuid')->where('rule', RowId::ROUTE_PATTERN)
            ->middleware(['permission:orders.manage', 'pos.module:packaging', $idempotent('pos.orders.packaging_restore')]);

        Route::put('/orders/{uuid}/pickup-time', [PosTakeawayController::class, 'setPickupTime'])->whereUuid('uuid')
            ->middleware(['permission:orders.manage', 'pos.module:scheduled_pickup', $idempotent('pos.orders.pickup_time')]);
        Route::get('/pickup-slots', [PosTakeawayController::class, 'slots'])
            ->middleware(['permission:orders.manage', 'pos.module:scheduled_pickup']);
    });
});
