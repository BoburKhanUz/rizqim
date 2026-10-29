<?php

/*
| P6-24 / P6-26 (wave "Restoran va kafe"): the hostess' desk and the restaurant floor's order changes.
|  - the waitlist (kutish ro'yxati), a table's cleaning mark: hostess.manage on a branch with the `hostess` module;
|  - handing an open table to another waiter (`waiter_transfer`) and numbering a line's guest (`guest_split`);
|  - the owner's table booking rules (`table_booking`) and the read-only waitlist view (`hostess`) for the partner web.
| Every POS change takes an Idempotency-Key (required) and, for orders, the order's version. Table bookings themselves
| are the booking book's (routes/api_pos_bookings.php, bookings.manage; the hostess role holds it). Required from
| routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Management\BranchBookingRulesController;
use App\Http\Controllers\Api\V1\Management\OwnerWaitlistController;
use App\Http\Controllers\Api\V1\PosHall\PosOrderHandoverController;
use App\Http\Controllers\Api\V1\PosHall\PosTableStateController;
use App\Http\Controllers\Api\V1\PosHall\PosWaitlistController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // Web panel: settings and reports, so never from a terminal PIN session
    Route::middleware('not-staff-session')->group(function () {
        Route::get('/owner/branches/{id}/booking-rules', [BranchBookingRulesController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)
            ->middleware(['permission:settings.manage', 'pos.module:table_booking']);
        Route::put('/owner/branches/{id}/booking-rules', [BranchBookingRulesController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)
            ->middleware(['permission:settings.manage', 'pos.module:table_booking', 'billing.unlocked']);

        Route::get('/owner/waitlist', [OwnerWaitlistController::class, 'index'])->middleware(['permission:reports.view', 'pos.module:hostess']);
    });

    Route::prefix('pos')->group(function () {
        $idempotent = fn (string $scope) => EnsureIdempotency::class . ':' . $scope;

        Route::middleware(['permission:hostess.manage', 'pos.module:hostess'])->group(function () use ($idempotent) {
            Route::get('/waitlist', [PosWaitlistController::class, 'index']);
            Route::post('/waitlist', [PosWaitlistController::class, 'store'])->middleware($idempotent('pos.waitlist.create'));
            Route::patch('/waitlist/{uuid}', [PosWaitlistController::class, 'update'])->whereUuid('uuid')->middleware($idempotent('pos.waitlist.update'));
            Route::post('/waitlist/{uuid}/notify', [PosWaitlistController::class, 'notify'])->whereUuid('uuid')->middleware($idempotent('pos.waitlist.notify'));
            Route::post('/waitlist/{uuid}/seat', [PosWaitlistController::class, 'seat'])->whereUuid('uuid')->middleware($idempotent('pos.waitlist.seat'));
            Route::post('/waitlist/{uuid}/cancel', [PosWaitlistController::class, 'cancel'])->whereUuid('uuid')->middleware($idempotent('pos.waitlist.cancel'));
            Route::post('/waitlist/{uuid}/no-show', [PosWaitlistController::class, 'noShow'])->whereUuid('uuid')->middleware($idempotent('pos.waitlist.no_show'));
        });

        // A table's cleaning mark: the hostess, or whoever manages tables on the floor
        Route::put('/tables/{id}/state', [PosTableStateController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)
            ->middleware(['permission:hostess.manage,tables.manage', 'pos.module:hostess', $idempotent('pos.tables.state')]);

        // orders.transfer, or orders.manage with a manager's approval token for orders.transfer (checked in the service)
        Route::post('/orders/{uuid}/transfer', [PosOrderHandoverController::class, 'transfer'])->whereUuid('uuid')
            ->middleware(['permission:orders.transfer,orders.manage', 'pos.module:waiter_transfer', $idempotent('pos.orders.transfer')]);
        Route::post('/orders/{uuid}/items/{item}/seat', [PosOrderHandoverController::class, 'seat'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)
            ->middleware(['permission:orders.manage', 'pos.module:guest_split', $idempotent('pos.orders.seat')]);
    });
});
