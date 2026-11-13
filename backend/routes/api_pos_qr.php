<?php

/*
| P6-41/P6-42: Click, Payme and UzQR dynamic QR at the till (docs/API.md "QR to'lov kassada"). Required from
| routes/api.php inside its /v1 prefix group. The owner's settings are the partner web panel's (never a terminal PIN
| session); the /pos ones the till's. Branch scoping lives in the controllers. The `qr_payments` module gates making a
| QR and changing the settings only: reading, cancelling, the providers' confirmations (the Click/Payme webhooks) and
| refunds never ask.
*/

use App\Http\Controllers\Api\V1\Management\QrPaymentSettingsController;
use App\Http\Controllers\Api\V1\PosBilling\PosQrPaymentController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::middleware('not-staff-session')->group(function () {
        Route::get('/owner/pos/qr-payments', [QrPaymentSettingsController::class, 'show'])->middleware('permission:settings.manage');
        Route::put('/owner/pos/qr-payments', [QrPaymentSettingsController::class, 'update'])->middleware(['permission:settings.manage', 'pos.module:qr_payments']);
    });

    Route::prefix('pos')->group(function () {
        Route::get('/bills/{uuid}/qr', [PosQrPaymentController::class, 'index'])->whereUuid('uuid')->middleware('permission:orders.view');
        Route::post('/bills/{uuid}/qr', [PosQrPaymentController::class, 'store'])->whereUuid('uuid')
            ->middleware(['permission:pos.take_payment', 'pos.module:qr_payments', EnsureIdempotency::class . ':pos.qr.create']);
        Route::get('/qr/{uuid}', [PosQrPaymentController::class, 'show'])->whereUuid('uuid')->middleware('permission:orders.view');
        Route::delete('/qr/{uuid}', [PosQrPaymentController::class, 'destroy'])->whereUuid('uuid')
            ->middleware(['permission:pos.take_payment', EnsureIdempotency::class . ':pos.qr.cancel']);
        // Demo and e2e stands: plays the provider's confirmation of a sandbox QR (404 when switched off)
        Route::post('/qr/{uuid}/simulate-paid', [PosQrPaymentController::class, 'simulatePaid'])->whereUuid('uuid')
            ->middleware(['permission:pos.take_payment', EnsureIdempotency::class . ':pos.qr.simulate']);
    });
});
