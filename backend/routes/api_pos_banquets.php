<?php

/*
| P6-51/P6-52: banquet events (to'yxona): the calendar, events, prices and their freeze for the till and the web panel,
| and the owner's banquet setup (packages, extras, hall rent, settings) (docs/API.md "Tadbirlar"). Required from
| routes/api.php inside its /v1 prefix group. Reading events: banquets.manage or pos.take_payment (the cashier takes
| the scheduled payments, P6-53); every change: banquets.manage. A new hold and the setup writes are new flows
| (`pos.module:banquet`, which carries the plan's banquet feature); existing events keep working whatever the module.
| Store and branch scoping in the controllers (another store's event: 404).
*/

use App\Http\Controllers\Api\V1\Management\BanquetSetupController;
use App\Http\Controllers\Api\V1\Pos\BanquetController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::prefix('pos')->group(function () {
        Route::middleware('permission:banquets.manage,pos.take_payment')->group(function () {
            Route::get('/banquets/calendar', [BanquetController::class, 'calendar']);
            Route::get('/banquets/catalog', [BanquetController::class, 'catalog']);
            Route::get('/banquets', [BanquetController::class, 'index']);
            Route::get('/banquets/{uuid}', [BanquetController::class, 'show'])->whereUuid('uuid');
        });
        Route::middleware('permission:banquets.manage')->group(function () {
            Route::post('/banquets', [BanquetController::class, 'store'])->middleware(['pos.module:banquet', EnsureIdempotency::class]);
            Route::patch('/banquets/{uuid}', [BanquetController::class, 'update'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
            Route::post('/banquets/{uuid}/freeze', [BanquetController::class, 'freeze'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
            Route::post('/banquets/{uuid}/unfreeze', [BanquetController::class, 'unfreeze'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        });
    });

    Route::middleware(['not-staff-session', 'permission:banquets.manage'])->prefix('owner/pos/banquets')->group(function () {
        Route::get('/settings', [BanquetSetupController::class, 'settings']);
        Route::put('/settings', [BanquetSetupController::class, 'updateSettings'])->middleware('pos.module:banquet');
        Route::get('/packages', [BanquetSetupController::class, 'packages']);
        Route::post('/packages', [BanquetSetupController::class, 'storePackage'])->middleware('pos.module:banquet');
        Route::patch('/packages/{package}', [BanquetSetupController::class, 'updatePackage'])->where('package', RowId::ROUTE_PATTERN)->middleware('pos.module:banquet');
        Route::delete('/packages/{package}', [BanquetSetupController::class, 'destroyPackage'])->where('package', RowId::ROUTE_PATTERN);
        Route::get('/extras', [BanquetSetupController::class, 'extras']);
        Route::post('/extras', [BanquetSetupController::class, 'storeExtra'])->middleware('pos.module:banquet');
        Route::patch('/extras/{extra}', [BanquetSetupController::class, 'updateExtra'])->where('extra', RowId::ROUTE_PATTERN)->middleware('pos.module:banquet');
        Route::delete('/extras/{extra}', [BanquetSetupController::class, 'destroyExtra'])->where('extra', RowId::ROUTE_PATTERN);
        Route::get('/halls', [BanquetSetupController::class, 'halls']);
        Route::put('/halls/{hall}/rates', [BanquetSetupController::class, 'updateHallRates'])->where('hall', RowId::ROUTE_PATTERN)->middleware('pos.module:banquet');
    });
});
