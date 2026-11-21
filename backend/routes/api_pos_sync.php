<?php

/*
| P3-31: the offline limited mode (docs/POS_SPEC.md §5, docs/API.md "Internetsiz rejim"). The till sends its offline
| queue and reads its sync status with its own terminal token (auth:device: the staff PIN session may have expired
| during the outage); the "Sinxronlash xatolari" list and their resolution are for staff (a PIN session on the till or
| the web panel). Required from routes/api.php inside its /v1 prefix group.
|
| P6 local bridge (docs/POS_SPEC.md §5.5): a terminal's LAN keys with its own token (GET /pos/lan; a PIN session gets them
| in GET /pos/bootstrap `lan`), and the owner's hub choice and key rotation (never from a PIN session; the key itself is
| never in these answers).
*/

use App\Http\Controllers\Api\V1\Management\BranchLanController;
use App\Http\Controllers\Api\V1\Management\SyncImportController;
use App\Http\Controllers\Api\V1\Management\SyncSigningController;
use App\Http\Controllers\Api\V1\Pos\PosLanController;
use App\Http\Controllers\Api\V1\Pos\PosSyncController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:device', 'tenant', 'throttle:pos-sync'])->prefix('pos/sync')->group(function () {
    // Each operation is idempotent by its op_id; an Idempotency-Key on the batch is optional
    Route::post('/', [PosSyncController::class, 'store'])->middleware(EnsureIdempotency::class . ':pos.sync,optional');
    Route::get('/status', [PosSyncController::class, 'status']);
});

Route::middleware(['auth:sanctum', 'staff.store', 'tenant', 'throttle:api'])->prefix('pos/sync')->group(function () {
    Route::get('/errors', [PosSyncController::class, 'errors'])->middleware('permission:orders.view');
    Route::post('/errors/{opId}/resolve', [PosSyncController::class, 'resolve'])->whereUuid('opId')->middleware(['permission:orders.cancel', EnsureIdempotency::class . ':pos.sync.resolve,optional']);
});

Route::middleware(['auth:device', 'tenant', 'throttle:pos-sync'])->get('/pos/lan', [PosLanController::class, 'show']);

Route::middleware(['auth:sanctum', 'staff.store', 'tenant', 'throttle:api', 'not-staff-session', 'permission:settings.manage'])->prefix('owner/pos/lan')->group(function () {
    Route::get('/', [BranchLanController::class, 'show']);
    Route::put('/', [BranchLanController::class, 'update']);
    Route::post('/rotate', [BranchLanController::class, 'rotate']);
});

// Wave M5 S3-d: the store's signature mode of the offline queue (observe / enforce), the owner's web panel only
Route::middleware(['auth:sanctum', 'staff.store', 'tenant', 'throttle:api', 'not-staff-session', 'permission:settings.manage'])->prefix('owner/pos/sync-signing')->group(function () {
    Route::get('/', [SyncSigningController::class, 'show']);
    Route::put('/', [SyncSigningController::class, 'update']);
});

// Wave M5 S4-b: a till's rescue file ("Navbatni faylga saqlash") uploaded in the owner's web panel and applied as that
// till (docs/scale/M5_DESIGN.md §5). Who manages devices, never from a terminal PIN session; a branch manager only
// their branch's tills (the controller). Every operation is applied once by its op_id: no Idempotency-Key is needed
Route::middleware(['auth:sanctum', 'staff.store', 'tenant', 'throttle:api', 'not-staff-session', 'permission:devices.manage'])
    ->post('/owner/pos/sync/import', [SyncImportController::class, 'store']);
