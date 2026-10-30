<?php

/*
| P6-33: KDS v2 (docs/API.md "KDS v2"): the expo (assembly) station's bump, the kitchen's time targets and the prep time
| report. A normal staff sign-in or a staff PIN session on a paired terminal for the bump (like the line bumps of
| api_staff_orders.php); the owner's settings never from a PIN session. Gated by the format modules (pos.module):
| `kds_expo` for the expo bump, `kds` for the report; setting a target needs `kds_expo` (the controllers: clearing one never
| does). Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Management\KdsTargetsController;
use App\Http\Controllers\Api\V1\Management\PrepTimeReportController;
use App\Http\Controllers\Api\V1\Order\KitchenExpoController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::post('/staff/orders/{uuid}/assembled', [KitchenExpoController::class, 'assembled'])->whereUuid('uuid')
        ->middleware(['permission:kitchen.manage', 'pos.module:kds_expo', EnsureIdempotency::class . ':pos.kds.assembled,optional']);

    Route::get('/owner/reports/kitchen-times', [PrepTimeReportController::class, 'show'])
        ->middleware(['permission:reports.view', 'pos.module:kds']);

    Route::middleware(['not-staff-session', 'permission:settings.manage'])->group(function () {
        Route::get('/owner/kds/targets', [KdsTargetsController::class, 'show']);
        // Setting a dish's target needs `kds_expo` (checked in the controller: clearing one never does)
        Route::put('/owner/kds/targets/products', [KdsTargetsController::class, 'updateProducts'])->middleware('billing.unlocked');
    });
});
