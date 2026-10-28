<?php

/*
| P3-17: bookings, pre-orders, deposits and customer accounts (nasiya) for the till and the web panel (docs/API.md
| "Bron va nasiya"). A normal staff sign-in or a staff PIN session on a paired terminal; store and branch scoping in the
| controllers (another store's or branch's record: 404). Money-moving requests take an Idempotency-Key (required for
| deposits and repayments). Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Pos\BookingController;
use App\Http\Controllers\Api\V1\Pos\CustomerAccountController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->prefix('pos')->group(function () {
    Route::middleware('permission:bookings.manage')->group(function () {
        Route::get('/bookings/calendar', [BookingController::class, 'calendar']);
        Route::get('/bookings', [BookingController::class, 'index']);
        // P4-21: a new booking and a new deposit need the plan's bookings / deposits (403 plan_feature_missing); the
        // existing ones go on (confirm, arrive, cancel, refund or retain a deposit) whatever the plan
        Route::post('/bookings', [BookingController::class, 'store'])->middleware(['plan.feature:bookings', EnsureIdempotency::class]);
        Route::get('/bookings/{uuid}', [BookingController::class, 'show'])->whereUuid('uuid');
        Route::patch('/bookings/{uuid}', [BookingController::class, 'update'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        Route::post('/bookings/{uuid}/confirm', [BookingController::class, 'confirm'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        Route::post('/bookings/{uuid}/cancel', [BookingController::class, 'cancel'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        Route::post('/bookings/{uuid}/no-show', [BookingController::class, 'noShow'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        Route::post('/bookings/{uuid}/arrive', [BookingController::class, 'arrive'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        // P3-38: the confirmation the guest receives (the partner web reads bookings here too, with the owner's sign-in)
        Route::get('/bookings/{uuid}/confirmation', [BookingController::class, 'confirmation'])->whereUuid('uuid');
        Route::post('/bookings/{uuid}/confirmation/send', [BookingController::class, 'sendConfirmation'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);

        Route::post('/bookings/{uuid}/items', [BookingController::class, 'addItem'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
        Route::patch('/bookings/{uuid}/items/{item}', [BookingController::class, 'updateItem'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)->middleware(EnsureIdempotency::class);
        Route::delete('/bookings/{uuid}/items/{item}', [BookingController::class, 'removeItem'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)->middleware(EnsureIdempotency::class);

        Route::post('/bookings/{uuid}/deposits', [BookingController::class, 'takeDeposit'])->whereUuid('uuid')->middleware(['plan.feature:deposits', EnsureIdempotency::class . ':pos.bookings.deposit']);
        Route::post('/bookings/{uuid}/deposits/{payment}/refund', [BookingController::class, 'refundDeposit'])->whereUuid(['uuid', 'payment'])->middleware(EnsureIdempotency::class);
        Route::post('/bookings/{uuid}/deposits/{payment}/retain', [BookingController::class, 'retainDeposit'])->whereUuid(['uuid', 'payment'])->middleware(EnsureIdempotency::class);
    });

    // Reading accounts and taking a repayment: the manager or the cashier at the till
    Route::middleware('permission:customer_accounts.manage,pos.take_payment')->group(function () {
        Route::get('/customer-accounts', [CustomerAccountController::class, 'index']);
        Route::get('/customer-accounts/debtors', [CustomerAccountController::class, 'debtors']);
        Route::get('/customer-accounts/{uuid}', [CustomerAccountController::class, 'show'])->whereUuid('uuid');
        Route::get('/customer-accounts/{uuid}/statement', [CustomerAccountController::class, 'statement'])->whereUuid('uuid');
        Route::post('/customer-accounts/{uuid}/repayments', [CustomerAccountController::class, 'repay'])->whereUuid('uuid')->middleware(EnsureIdempotency::class . ':pos.accounts.repay');
        // P3-26: opening or changing an account at the till: customer_accounts.manage, or a cashier with a manager's
        // approval token for it (checked in the controller, ApprovalGate)
        // P4-21: opening a nasiya account needs the plan's customer_accounts; repayments and paying a bill to an existing
        // account never depend on the plan
        Route::post('/customer-accounts', [CustomerAccountController::class, 'store'])->middleware(['plan.feature:customer_accounts', EnsureIdempotency::class]);
        Route::patch('/customer-accounts/{uuid}', [CustomerAccountController::class, 'update'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
    });

    Route::middleware('permission:customer_accounts.manage')->group(function () {
        Route::post('/customer-accounts/{uuid}/adjustments', [CustomerAccountController::class, 'adjust'])->whereUuid('uuid')->middleware(EnsureIdempotency::class);
    });
});
