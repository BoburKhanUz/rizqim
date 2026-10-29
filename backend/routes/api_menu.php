<?php

/*
| P1-21: menu model (docs/MENU.md): availability for customers; modifiers, channel prices, batches (kazan stock),
| the branch stop-list and the service charge / place fee settings for staff. Required from routes/api.php inside its
| /v1 prefix group. Branch-bound staff act on their own branch only (checked in the controllers).
*/

use App\Http\Controllers\Api\V1\Catalog\MenuAvailabilityController;
use App\Http\Controllers\Api\V1\Menu\BatchController;
use App\Http\Controllers\Api\V1\Menu\BatchPlanController;
use App\Http\Controllers\Api\V1\Menu\BatchWriteOffController;
use App\Http\Controllers\Api\V1\Menu\BatchYieldController;
use App\Http\Controllers\Api\V1\Menu\ChannelPriceController;
use App\Http\Controllers\Api\V1\Menu\DailyMenuController;
use App\Http\Controllers\Api\V1\Menu\MealSetController;
use App\Http\Controllers\Api\V1\Menu\ModifierGroupController;
use App\Http\Controllers\Api\V1\Menu\ProductPosServiceController;
use App\Http\Controllers\Api\V1\Menu\ServiceFeeController;
use App\Http\Controllers\Api\V1\Menu\StopListController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // What is unavailable on a store's branches now (stop-list, sold-out batches): refetched on menu.availability_changed
    Route::get('/catalog/availability', [MenuAvailabilityController::class, 'index']);

    // Modifier groups and modifiers of the store's menu
    Route::get('/owner/modifier-groups', [ModifierGroupController::class, 'index'])->middleware('permission:menu.view,menu.manage');
    Route::post('/owner/modifier-groups', [ModifierGroupController::class, 'store'])->middleware('permission:menu.manage');
    Route::put('/owner/modifier-groups/{id}', [ModifierGroupController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.manage');
    Route::delete('/owner/modifier-groups/{id}', [ModifierGroupController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.manage');
    Route::put('/owner/products/{id}/modifier-groups', [ModifierGroupController::class, 'syncProduct'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.manage');

    // P6-25: a dish's skewer buttons and grill time (module skewer_buttons) and "weight later" (module weight_later),
    // checked per field in the controller (clearing a value never needs the module)
    Route::patch('/owner/products/{id}/pos-service', [ProductPosServiceController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.manage');

    // Per-channel prices and visibility of a product and its variants
    Route::get('/owner/products/{id}/channel-prices', [ChannelPriceController::class, 'index'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.view,menu.manage');
    Route::put('/owner/products/{id}/channel-prices', [ChannelPriceController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.manage');

    // Batch / kazan stock of a branch (cook, manager, owner)
    Route::get('/owner/batches', [BatchController::class, 'index'])->middleware('permission:batches.manage,menu.view');
    Route::post('/owner/batches', [BatchController::class, 'store'])->middleware('permission:batches.manage');
    Route::post('/owner/batches/{id}/adjust', [BatchController::class, 'adjust'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:batches.manage');
    Route::post('/owner/batches/{id}/close', [BatchController::class, 'close'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:batches.manage');

    // P6-15: unsold portions written off with a reason (module batch_writeoff; send an Idempotency-Key), the branch's
    // write-offs, "keyingi partiya HH:MM" plans and the yield report (module batches)
    // batches.manage, or on a terminal a manager's approval_token for it (P6-19: checked in the controller)
    Route::post('/owner/batches/{id}/write-off', [BatchController::class, 'writeOff'])->where('id', RowId::ROUTE_PATTERN)->middleware(['pos.module:batch_writeoff', EnsureIdempotency::class . ':owner.batches.write_off,optional']);
    Route::get('/owner/batch-writeoffs', [BatchWriteOffController::class, 'index'])->middleware(['permission:batches.manage,reports.view', 'pos.module:batch_writeoff']);
    Route::get('/owner/batch-plans', [BatchPlanController::class, 'index'])->middleware(['permission:batches.manage,menu.view', 'pos.module:batches']);
    Route::post('/owner/batch-plans', [BatchPlanController::class, 'store'])->middleware(['permission:batches.manage', 'pos.module:batches']);
    Route::delete('/owner/batch-plans/{id}', [BatchPlanController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->middleware(['permission:batches.manage', 'pos.module:batches']);
    Route::get('/owner/batch-yield', [BatchYieldController::class, 'show'])->middleware(['permission:reports.view,batches.manage', 'pos.module:batches']);

    // P6-15: "Bugungi menyu" (module daily_menu) and kompleks (module combo_meal)
    Route::get('/owner/daily-menu', [DailyMenuController::class, 'show'])->middleware(['permission:menu.view,menu.manage', 'pos.module:daily_menu']);
    Route::put('/owner/daily-menu', [DailyMenuController::class, 'save'])->middleware(['permission:menu.manage,stoplist.manage', 'pos.module:daily_menu']);
    Route::post('/owner/daily-menu/copy', [DailyMenuController::class, 'copy'])->middleware(['permission:menu.manage,stoplist.manage', 'pos.module:daily_menu']);
    Route::put('/owner/daily-menu/rotation', [DailyMenuController::class, 'rotation'])->middleware(['permission:menu.manage,stoplist.manage', 'pos.module:daily_menu']);
    Route::get('/owner/meal-sets', [MealSetController::class, 'index'])->middleware(['permission:menu.view,menu.manage', 'pos.module:combo_meal']);
    Route::post('/owner/meal-sets', [MealSetController::class, 'store'])->middleware(['permission:menu.manage', 'pos.module:combo_meal']);
    Route::put('/owner/meal-sets/{id}', [MealSetController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware(['permission:menu.manage', 'pos.module:combo_meal']);
    Route::delete('/owner/meal-sets/{id}', [MealSetController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->middleware(['permission:menu.manage', 'pos.module:combo_meal']);

    // The branch stop-list
    Route::get('/owner/stop-list', [StopListController::class, 'index'])->middleware('permission:stoplist.manage,menu.view');
    Route::post('/owner/stop-list', [StopListController::class, 'store'])->middleware('permission:stoplist.manage');
    Route::delete('/owner/stop-list/{id}', [StopListController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:stoplist.manage');

    // Service charge and place fee (store default and branch overrides): settings, so never from a terminal PIN session
    Route::middleware('not-staff-session')->group(function () {
        Route::get('/owner/service-fees', [ServiceFeeController::class, 'index'])->middleware('permission:settings.manage');
        // P4-21: a service charge needs the plan's service_charge (checked in the controller); a locked subscription
        // changes no fees (billing.unlocked)
        Route::put('/owner/service-fees', [ServiceFeeController::class, 'update'])->middleware(['permission:settings.manage', 'billing.unlocked']);
    });
});
