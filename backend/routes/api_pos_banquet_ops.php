<?php

/*
| P6-54: the banquet event's production sheet, the osh calculator, the shopping list, the contract, the event day and
| the final settlement for the till and the web panel, and the owner's operations settings (docs/API.md "Tadbirlar:
| ishlab chiqarish va yakuniy hisob"). Required from routes/api.php inside its /v1 prefix group. The papers, the
| contract and starting the event day are new flows (`pos.module:banquet`); the settlement is the event's payment and
| never asks the module. Store and branch scoping in the controller (another store's event: 404); the permissions
| finer than the route's group are checked there too.
*/

use App\Http\Controllers\Api\V1\Management\BanquetOpsSettingsController;
use App\Http\Controllers\Api\V1\Pos\BanquetOpsController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::prefix('pos/banquets')->group(function () {
        // The kitchen's papers: the cook too (kitchen.manage), no customer data on them
        Route::middleware(['permission:banquets.manage,pos.take_payment,reports.view,kitchen.manage', 'pos.module:banquet'])->group(function () {
            Route::get('/shopping-list', [BanquetOpsController::class, 'shopping']);
            Route::get('/shopping-list/export', [BanquetOpsController::class, 'shoppingExport']);
            Route::get('/osh-calculator', [BanquetOpsController::class, 'osh']);
            Route::get('/{uuid}/production-sheet', [BanquetOpsController::class, 'production'])->whereUuid('uuid');
            Route::get('/{uuid}/production-sheet/export', [BanquetOpsController::class, 'productionExport'])->whereUuid('uuid');
        });
        Route::get('/{uuid}/warnings', [BanquetOpsController::class, 'warnings'])->whereUuid('uuid')->middleware('permission:banquets.manage,pos.take_payment,reports.view');

        Route::middleware('permission:banquets.manage')->group(function () {
            Route::get('/{uuid}/contract', [BanquetOpsController::class, 'contract'])->whereUuid('uuid')->middleware('pos.module:banquet');
            Route::post('/{uuid}/contract/number', [BanquetOpsController::class, 'numberContract'])->whereUuid('uuid')->middleware(['pos.module:banquet', EnsureIdempotency::class]);
            Route::patch('/{uuid}/guests', [BanquetOpsController::class, 'guests'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
            Route::put('/{uuid}/staff', [BanquetOpsController::class, 'updateStaff'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        });

        Route::middleware('permission:banquets.manage,pos.take_payment')->group(function () {
            Route::get('/{uuid}/staff', [BanquetOpsController::class, 'staff'])->whereUuid('uuid');
            Route::get('/{uuid}/settlement', [BanquetOpsController::class, 'settlement'])->whereUuid('uuid');
            Route::post('/{uuid}/start', [BanquetOpsController::class, 'start'])->whereUuid('uuid')->middleware(['pos.module:banquet', EnsureIdempotency::class]);
        });

        // The final bill: the cashier at the till (a payment: never the module)
        Route::middleware('permission:pos.take_payment')->group(function () {
            Route::post('/{uuid}/settle', [BanquetOpsController::class, 'settle'])->whereUuid('uuid')->middleware(EnsureIdempotency::class . ':pos.banquets.settle');
            Route::post('/{uuid}/settlement/refund-excess', [BanquetOpsController::class, 'refundExcess'])->whereUuid('uuid')->middleware(EnsureIdempotency::class . ':pos.banquets.refund_excess');
        });
    });

    Route::middleware(['not-staff-session', 'permission:banquets.manage'])->prefix('owner/pos/banquets')->group(function () {
        Route::get('/ops-settings', [BanquetOpsSettingsController::class, 'show']);
        Route::put('/ops-settings', [BanquetOpsSettingsController::class, 'update'])->middleware(['pos.module:banquet', EnsureIdempotency::class]);
    });
});
