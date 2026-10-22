<?php

/*
| P3-18: bills, split, payments, refunds, shifts with X/Z and manager approvals for the till (a normal staff sign-in
| or a staff PIN session on a paired terminal; docs/API.md "Schyot, to'lov va smena"). Required from routes/api.php
| inside its /v1 prefix group. Store and branch scoping live in the controllers (PosBillingController); every change
| takes an Idempotency-Key (required) and, on an order, its version.
*/

use App\Http\Controllers\Api\V1\PosBilling\PosApprovalController;
use App\Http\Controllers\Api\V1\PosBilling\PosBillController;
use App\Http\Controllers\Api\V1\PosBilling\PosFiscalReceiptController;
use App\Http\Controllers\Api\V1\PosBilling\PosRefundController;
use App\Http\Controllers\Api\V1\PosBilling\PosShiftController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->prefix('pos')->group(function () {
    $idempotent = fn (string $scope) => EnsureIdempotency::class . ':' . $scope;

    // A manager's PIN on the terminal: a one-time approval for one permission (the PIN lockout applies)
    Route::post('/approvals', [PosApprovalController::class, 'store'])->middleware(['throttle:pos-approvals', $idempotent('pos.approvals') . ',optional']);

    // Bills: making and splitting, printing ("NUSXA" on a copy), payments, receipts, closing
    Route::get('/orders/{uuid}/bills', [PosBillController::class, 'index'])->whereUuid('uuid')->middleware('permission:orders.view');
    Route::post('/orders/{uuid}/bills', [PosBillController::class, 'store'])->whereUuid('uuid')->middleware(['permission:orders.manage,pos.take_payment', $idempotent('pos.bills.create')]);
    Route::post('/bills/{uuid}/print', [PosBillController::class, 'print'])->whereUuid('uuid')->middleware(['permission:orders.manage,pos.take_payment', $idempotent('pos.bills.print')]);
    Route::middleware('permission:pos.take_payment')->group(function () use ($idempotent) {
        Route::post('/bills/{uuid}/payments', [PosBillController::class, 'pay'])->whereUuid('uuid')->middleware($idempotent('pos.bills.pay'));
        Route::post('/bills/{uuid}/fiscal-retry', [PosBillController::class, 'fiscalRetry'])->whereUuid('uuid')->middleware($idempotent('pos.bills.fiscal_retry') . ',optional');
        // P3-26: a receipt as registered, to print it at the till (the cashier has no finance.view)
        Route::get('/fiscal-receipts/{uuid}', [PosFiscalReceiptController::class, 'show'])->whereUuid('uuid');
        Route::post('/orders/{uuid}/close', [PosBillController::class, 'close'])->whereUuid('uuid')->middleware($idempotent('pos.orders.close'));
    });

    // Refunds within the shift: pos.refund, or a manager's approval token (checked in the service)
    Route::post('/refunds', [PosRefundController::class, 'store'])->middleware(['permission:pos.take_payment,pos.refund', $idempotent('pos.refunds')]);

    // Shifts, the cash drawer, X and Z
    Route::post('/shifts', [PosShiftController::class, 'store'])->middleware(['permission:pos.open_shift', $idempotent('pos.shifts.open')]);
    Route::get('/shifts/current', [PosShiftController::class, 'current'])->middleware('permission:pos.open_shift,pos.close_shift,pos.take_payment');
    Route::middleware('permission:pos.close_shift,reports.view')->group(function () {
        Route::get('/shifts', [PosShiftController::class, 'index']);
        Route::get('/shifts/{uuid}', [PosShiftController::class, 'show'])->whereUuid('uuid');
        Route::get('/shifts/{uuid}/x-report', [PosShiftController::class, 'xReport'])->whereUuid('uuid');
    });
    // pos.cash_in_out, or a manager's approval token for it (checked in the service)
    Route::post('/shifts/{uuid}/cash-movements', [PosShiftController::class, 'cashMovement'])->whereUuid('uuid')->middleware(['permission:pos.cash_in_out,pos.open_shift,pos.close_shift', $idempotent('pos.shifts.cash')]);
    Route::post('/shifts/{uuid}/close', [PosShiftController::class, 'close'])->whereUuid('uuid')->middleware(['permission:pos.close_shift', $idempotent('pos.shifts.close')]);
});
