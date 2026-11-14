<?php

/*
| P6-53: a banquet event's payment schedule (zaklad, interims, final), advance receipts, bank transfers, cancellation
| with refund and retained parts, the owner's payment rules and the "Tadbirlar" report (docs/API.md "Tadbirlar: to'lov
| jadvali"). Required from routes/api.php inside its /v1 prefix group. The till and the web panel (a staff PIN session
| or a normal sign-in) as the events themselves; money of an existing event is never gated by the `banquet` module
| (editing the schedule and the rules is). Store and branch scoping in the controllers (another store's event: 404).
*/

use App\Http\Controllers\Api\V1\Management\BanquetPaymentsController;
use App\Http\Controllers\Api\V1\Pos\BanquetPaymentController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::prefix('pos')->group(function () {
        Route::middleware('permission:banquets.manage,pos.take_payment')->group(function () {
            Route::get('/banquets/{uuid}/payments', [BanquetPaymentController::class, 'show'])->whereUuid('uuid');
            Route::post('/banquets/{uuid}/payments/{row}/pay', [BanquetPaymentController::class, 'pay'])->whereUuid(['uuid', 'row'])->middleware(['permission:pos.take_payment', EnsureIdempotency::class . ':pos.banquets.pay']);
        });
        Route::middleware('permission:banquets.manage')->group(function () {
            Route::put('/banquets/{uuid}/payments', [BanquetPaymentController::class, 'update'])->whereUuid('uuid')->middleware(['pos.module:banquet', EnsureIdempotency::class]);
            Route::post('/banquets/{uuid}/payments/{row}/bank-transfer', [BanquetPaymentController::class, 'transfer'])->whereUuid(['uuid', 'row'])->middleware(EnsureIdempotency::class . ':pos.banquets.transfer');
            Route::get('/banquets/{uuid}/cancellation', [BanquetPaymentController::class, 'quote'])->whereUuid('uuid');
            Route::post('/banquets/{uuid}/cancellation', [BanquetPaymentController::class, 'cancel'])->whereUuid('uuid')->middleware(EnsureIdempotency::class . ':pos.banquets.cancel');
        });
    });

    Route::middleware('not-staff-session')->prefix('owner/pos')->group(function () {
        Route::middleware('permission:banquets.manage')->group(function () {
            Route::get('/banquets/payment-settings', [BanquetPaymentsController::class, 'settings']);
            Route::put('/banquets/payment-settings', [BanquetPaymentsController::class, 'updateSettings'])->middleware('pos.module:banquet');
        });
        Route::middleware('permission:banquets.manage,reports.view')->group(function () {
            Route::get('/banquets/report', [BanquetPaymentsController::class, 'report']);
            Route::get('/banquets/export', [BanquetPaymentsController::class, 'export']);
        });
    });
});
