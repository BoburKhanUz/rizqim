<?php

/*
| P6-43: gift certificates and promo codes at the till, and the owner's certificates page (docs/API.md "Sertifikat va
| vaucher"). Required from routes/api.php inside its /v1 prefix group. Selling a certificate and applying a promo code
| are new flows (`pos.module:vouchers`); spending one is a bill tender (POST /pos/bills/{uuid}/payments) and the lookup
| serves it, so neither asks the module. The owner's endpoints are the partner web panel's (never a terminal PIN
| session). Store and branch scoping in the controllers (another store's code: 404).
*/

use App\Http\Controllers\Api\V1\Management\VouchersController;
use App\Http\Controllers\Api\V1\PosBilling\PosVoucherController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::prefix('pos')->group(function () {
        Route::middleware('permission:pos.take_payment')->group(function () {
            Route::post('/vouchers/sell', [PosVoucherController::class, 'sell'])->middleware(['pos.module:vouchers', EnsureIdempotency::class . ':pos.vouchers.sell']);
            Route::get('/vouchers/{code}', [PosVoucherController::class, 'show'])->where('code', '[A-Za-z0-9\- ]{12,20}');
        });
        // A discount voucher: a store promo code on a POS order (the waiter's phone too)
        Route::post('/orders/{uuid}/promo', [PosVoucherController::class, 'applyPromo'])->whereUuid('uuid')->middleware(['permission:orders.manage', 'pos.module:vouchers', EnsureIdempotency::class . ':pos.orders.promo']);
        Route::delete('/orders/{uuid}/promo', [PosVoucherController::class, 'removePromo'])->whereUuid('uuid')->middleware(['permission:orders.manage', EnsureIdempotency::class . ':pos.orders.promo_remove']);
    });

    Route::middleware('not-staff-session')->prefix('owner/pos')->group(function () {
        Route::middleware('permission:vouchers.manage,reports.view')->group(function () {
            Route::get('/vouchers', [VouchersController::class, 'index']);
            Route::get('/vouchers/report', [VouchersController::class, 'report']);
            Route::get('/vouchers/export', [VouchersController::class, 'export']);
            Route::get('/vouchers/{uuid}', [VouchersController::class, 'show'])->whereUuid('uuid');
        });
        Route::middleware('permission:vouchers.manage')->group(function () {
            Route::post('/vouchers/{uuid}/block', [VouchersController::class, 'block'])->whereUuid('uuid');
            Route::post('/vouchers/{uuid}/unblock', [VouchersController::class, 'unblock'])->whereUuid('uuid');
        });
    });
});
