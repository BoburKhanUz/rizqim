<?php

/*
| P3-13: POS settings (docs/API.md, "POS sozlamalari"): kitchen stations and the routing of dishes to them, printers and
| the branch's POS settings for the partner web panel (settings.manage, never from a terminal PIN session), and
| GET /pos/bootstrap for the terminals and staff apps. The POS menu is GET /catalog?channel=pos_hall|pos_takeaway
| (CatalogController hands it to PosCatalogController). Required from routes/api.php inside its /v1 prefix group.
| Branch scoping lives in the controllers (a branch-bound caller or a terminal PIN session acts on its branch only).
*/

use App\Http\Controllers\Api\V1\Management\BranchPosSettingsController;
use App\Http\Controllers\Api\V1\Management\KitchenStationController;
use App\Http\Controllers\Api\V1\Management\KitchenStationRouteController;
use App\Http\Controllers\Api\V1\Management\PrinterController;
use App\Http\Controllers\Api\V1\Pos\PosBootstrapController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // Everything a terminal needs to start work on its branch (a terminal PIN session or a normal staff sign-in)
    Route::get('/pos/bootstrap', [PosBootstrapController::class, 'show'])->middleware('permission:orders.view');

    Route::middleware(['not-staff-session', 'permission:settings.manage'])->group(function () {
        Route::get('/owner/kitchen-stations', [KitchenStationController::class, 'index']);
        Route::post('/owner/kitchen-stations', [KitchenStationController::class, 'store']);
        Route::patch('/owner/kitchen-stations/{id}', [KitchenStationController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
        Route::delete('/owner/kitchen-stations/{id}', [KitchenStationController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);

        Route::get('/owner/kitchen-station-routes', [KitchenStationRouteController::class, 'index']);
        Route::put('/owner/kitchen-station-routes', [KitchenStationRouteController::class, 'assign']);
        Route::delete('/owner/kitchen-station-routes/{id}', [KitchenStationRouteController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);

        Route::get('/owner/printers', [PrinterController::class, 'index']);
        Route::post('/owner/printers', [PrinterController::class, 'store']);
        Route::patch('/owner/printers/{id}', [PrinterController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
        Route::delete('/owner/printers/{id}', [PrinterController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);

        Route::get('/owner/branches/{id}/pos-settings', [BranchPosSettingsController::class, 'show'])->where('id', RowId::ROUTE_PATTERN);
        Route::put('/owner/branches/{id}/pos-settings', [BranchPosSettingsController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
    });
});
