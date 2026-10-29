<?php

/*
| P6-23 (C3): staff pay — the wage rules of a branch ("Ish haqi qoidalari"), its staff's work time and the pay
| statements per period ("Hisob-kitob") for the partner web, and the clock-in/out of the till and the waiter app
| (docs/API.md "Ish haqi qoidalari"). Required from routes/api.php inside its /v1 prefix group. Behind the `staff_pay`
| module (the controllers check a record's own branch). The owner's side never from a staff PIN session.
*/

use App\Http\Controllers\Api\V1\StaffPay\PosClockController;
use App\Http\Controllers\Api\V1\StaffPay\StaffPayRuleController;
use App\Http\Controllers\Api\V1\StaffPay\StaffPayStatementController;
use App\Http\Controllers\Api\V1\StaffPay\StaffWorkSessionController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api', 'not-staff-session', 'pos.module:staff_pay'])->prefix('owner/staff-pay')->group(function () {
    $read = 'permission:staff_pay.view,staff_pay.manage';
    $manage = 'permission:staff_pay.manage';

    Route::get('/rules', [StaffPayRuleController::class, 'index'])->middleware($read);
    Route::put('/settings', [StaffPayRuleController::class, 'settings'])->middleware($manage);
    Route::post('/rules', [StaffPayRuleController::class, 'store'])->middleware($manage);
    Route::put('/rules/{uuid}', [StaffPayRuleController::class, 'update'])->whereUuid('uuid')->middleware($manage);
    Route::delete('/rules/{uuid}', [StaffPayRuleController::class, 'destroy'])->whereUuid('uuid')->middleware($manage);

    Route::get('/work-sessions', [StaffWorkSessionController::class, 'index'])->middleware($read);
    Route::post('/work-sessions', [StaffWorkSessionController::class, 'store'])->middleware($manage);
    Route::put('/work-sessions/{uuid}', [StaffWorkSessionController::class, 'update'])->whereUuid('uuid')->middleware($manage);
    Route::delete('/work-sessions/{uuid}', [StaffWorkSessionController::class, 'destroy'])->whereUuid('uuid')->middleware($manage);

    Route::get('/statements', [StaffPayStatementController::class, 'index'])->middleware($read);
    Route::post('/statements', [StaffPayStatementController::class, 'store'])->middleware([$manage, EnsureIdempotency::class . ':owner.staff_pay.statements,optional']);
    Route::get('/statements/{uuid}', [StaffPayStatementController::class, 'show'])->whereUuid('uuid')->middleware($read);
    Route::delete('/statements/{uuid}', [StaffPayStatementController::class, 'destroy'])->whereUuid('uuid')->middleware($manage);
    Route::post('/statements/{uuid}/recompute', [StaffPayStatementController::class, 'recompute'])->whereUuid('uuid')->middleware($manage);
    Route::post('/statements/{uuid}/close', [StaffPayStatementController::class, 'close'])->whereUuid('uuid')->middleware($manage);
    Route::post('/statements/{uuid}/reopen', [StaffPayStatementController::class, 'reopen'])->whereUuid('uuid')->middleware($manage);
    Route::put('/statements/{uuid}/lines/{line}', [StaffPayStatementController::class, 'adjust'])->whereUuid(['uuid', 'line'])->middleware($manage);
    // A payment from the till writes a cash movement: an Idempotency-Key is required (checked in the controller; a pos.
    // scope, so the API document says so)
    Route::post('/statements/{uuid}/lines/{line}/pay', [StaffPayStatementController::class, 'pay'])->whereUuid(['uuid', 'line'])
        ->middleware([$manage, EnsureIdempotency::class . ':pos.staff_pay.pay']);
});

// The till's and the waiter app's clock-in/out: the signed-in person's own work time (every terminal role)
Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api', 'pos.module:staff_pay'])->prefix('pos/clock')->group(function () {
    Route::get('/', [PosClockController::class, 'show']);
    Route::post('/in', [PosClockController::class, 'in'])->middleware(EnsureIdempotency::class . ':pos.clock.in');
    Route::post('/out', [PosClockController::class, 'out'])->middleware(EnsureIdempotency::class . ':pos.clock.out');
});
