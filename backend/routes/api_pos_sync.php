<?php

/*
| P3-31: the offline limited mode (docs/POS_SPEC.md §5, docs/API.md "Internetsiz rejim"). The till sends its offline
| queue and reads its sync status with its own terminal token (auth:device: the staff PIN session may have expired
| during the outage); the "Sinxronlash xatolari" list and their resolution are for staff (a PIN session on the till or
| the web panel). Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Pos\PosSyncController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:device', 'throttle:pos-sync'])->prefix('pos/sync')->group(function () {
    // Each operation is idempotent by its op_id; an Idempotency-Key on the batch is optional
    Route::post('/', [PosSyncController::class, 'store'])->middleware(EnsureIdempotency::class . ':pos.sync,optional');
    Route::get('/status', [PosSyncController::class, 'status']);
});

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->prefix('pos/sync')->group(function () {
    Route::get('/errors', [PosSyncController::class, 'errors'])->middleware('permission:orders.view');
    Route::post('/errors/{opId}/resolve', [PosSyncController::class, 'resolve'])->whereUuid('opId')->middleware(['permission:orders.cancel', EnsureIdempotency::class . ':pos.sync.resolve,optional']);
});
