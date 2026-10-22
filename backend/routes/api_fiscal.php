<?php

/*
| P3-11: fiscal settings, readiness and the receipts list for the partner web panel (docs/API.md "Fiskal cheklar").
| Required from routes/api.php inside its /v1 prefix group. Store and branch scoping live in the controllers.
*/

use App\Http\Controllers\Api\V1\Fiscal\FiscalReceiptController;
use App\Http\Controllers\Api\V1\Fiscal\FiscalSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // Receipts: read by finance.view or reports.view (a branch-bound caller or a terminal PIN session: their branch)
    Route::get('/owner/fiscal/receipts', [FiscalReceiptController::class, 'index'])->middleware('permission:finance.view,reports.view');
    Route::get('/owner/fiscal/receipts/{uuid}', [FiscalReceiptController::class, 'show'])->whereUuid('uuid')->middleware('permission:finance.view,reports.view');

    // What still blocks fiscal selling (products and charges without MXIK)
    Route::get('/owner/fiscal/readiness', [FiscalSettingsController::class, 'readiness'])->middleware('permission:menu.view,settings.manage');

    // Settings and retries: never from a staff PIN session on a terminal
    Route::middleware(['not-staff-session', 'permission:settings.manage'])->group(function () {
        Route::get('/owner/fiscal/settings', [FiscalSettingsController::class, 'show']);
        Route::put('/owner/fiscal/settings', [FiscalSettingsController::class, 'update']);
        Route::post('/owner/fiscal/receipts/{uuid}/retry', [FiscalReceiptController::class, 'retry'])->whereUuid('uuid');
    });
});
