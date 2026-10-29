<?php

/*
| P6-22: service charge variants and tips (choy puli) (docs/API.md "Xizmat haqi qoidalari", "Choy puli"). Required
| from routes/api.php inside its /v1 prefix group. The owner's endpoints are the partner web panel's (never a terminal
| PIN session); the /pos ones the till's and the waiter phone's. Branch scoping lives in the controllers. Modules gate
| the setup only (`pos.module:service_charge_rules`, `pos.module:tips`): reading, reports, corrections and the pool's
| split never ask, and a payment's tip is checked by the payment itself.
*/

use App\Http\Controllers\Api\V1\Management\ServiceChargeRulesController;
use App\Http\Controllers\Api\V1\Management\TipsController;
use App\Http\Controllers\Api\V1\PosBilling\PosTipsController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::middleware('not-staff-session')->group(function () {
        // Service charge variants (by order type, large group, cover, notice) on the branch's service fee row
        Route::get('/owner/pos/service-charge-rules', [ServiceChargeRulesController::class, 'show'])->middleware('permission:settings.manage');
        Route::put('/owner/pos/service-charge-rules', [ServiceChargeRulesController::class, 'update'])->middleware(['permission:settings.manage', 'pos.module:service_charge_rules']);

        // Tips: settings, report, the tips, corrections, the pool's split
        Route::get('/owner/pos/tips/settings', [TipsController::class, 'settings'])->middleware('permission:settings.manage');
        Route::put('/owner/pos/tips/settings', [TipsController::class, 'updateSettings'])->middleware(['permission:settings.manage', 'pos.module:tips']);
        Route::get('/owner/pos/tips', [TipsController::class, 'report'])->middleware('permission:reports.view');
        Route::get('/owner/pos/tips/entries', [TipsController::class, 'entries'])->middleware('permission:reports.view');
        Route::post('/owner/pos/tips/{uuid}/corrections', [TipsController::class, 'correct'])->whereUuid('uuid')->middleware('permission:tips.manage');
        Route::get('/owner/pos/tips/distributions', [TipsController::class, 'distributions'])->middleware('permission:reports.view');
        Route::post('/owner/pos/tips/distributions', [TipsController::class, 'compute'])->middleware('permission:tips.manage');
        Route::post('/owner/pos/tips/distributions/{uuid}/close', [TipsController::class, 'close'])->whereUuid('uuid')->middleware('permission:tips.manage');
    });

    // The till and the waiter phone
    Route::get('/pos/tips', [PosTipsController::class, 'index'])->middleware('permission:orders.view');
    // tips.manage, or a manager's approval token for it (checked in the controller)
    Route::post('/pos/tips/{uuid}/corrections', [PosTipsController::class, 'correct'])->whereUuid('uuid')
        ->middleware(['permission:orders.view', EnsureIdempotency::class . ':pos.tips.correct']);
});
