<?php

/*
| P3-12: halls and tables, room tariffs and the per-guest ("choy-non") items for the web panel (never from a terminal
| PIN session), and the POS hall orders for the till and the waiter phones (a normal staff sign-in or a staff PIN
| session on a paired terminal): the floor map, opening an order, rounds, sending to the kitchen, voids, guests, move,
| merge, charge waivers (docs/API.md "Zal va xona hisobi"). Required from routes/api.php inside its /v1 prefix group.
| Store and branch scoping live in the controllers; every POS change takes an Idempotency-Key (required) and the
| order's version.
*/

use App\Http\Controllers\Api\V1\PosHall\AutoGuestItemController;
use App\Http\Controllers\Api\V1\PosHall\DiningTableController;
use App\Http\Controllers\Api\V1\PosHall\HallController;
use App\Http\Controllers\Api\V1\PosHall\PosFloorController;
use App\Http\Controllers\Api\V1\PosHall\PosOrderController;
use App\Http\Controllers\Api\V1\PosHall\RoomTariffController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    // Web panel: settings, so never from a terminal PIN session
    Route::middleware('not-staff-session')->group(function () {
        Route::middleware('permission:tables.manage')->group(function () {
            Route::get('/owner/halls', [HallController::class, 'index']);
            Route::get('/owner/dining-tables', [DiningTableController::class, 'index']);

            // P3 integration fix: the floor layout is the owner's and the manager's. tables.manage alone is held by
            // cashiers and waiters too (seat, move, merge on the POS), so changing halls and tables also needs the
            // owner or manager role: the owner always holds tables.manage, a manager unless the store took it away
            // P4-21: a locked subscription changes no floor plan (billing.unlocked)
            Route::middleware(['role:owner,manager', 'billing.unlocked'])->group(function () {
                Route::post('/owner/halls', [HallController::class, 'store']);
                Route::patch('/owner/halls/{id}', [HallController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
                Route::delete('/owner/halls/{id}', [HallController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);

                Route::post('/owner/dining-tables', [DiningTableController::class, 'store']);
                Route::put('/owner/dining-tables/layout', [DiningTableController::class, 'layout']);
                Route::patch('/owner/dining-tables/{id}', [DiningTableController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
                Route::delete('/owner/dining-tables/{id}', [DiningTableController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);
            });
        });

        // P4-21: a locked subscription changes no hall fees, room tariffs or choy-non items (billing.unlocked: writes only)
        // P6-11: a new or changed room tariff / choy-non item needs the branch's room_fee / guest_items module (403
        // module_disabled); reading and deleting them never does
        Route::middleware(['permission:settings.manage', 'billing.unlocked'])->group(function () {
            Route::delete('/owner/service-fees/halls/{id}', [HallController::class, 'destroyServiceFee'])->where('id', RowId::ROUTE_PATTERN);

            Route::get('/owner/room-tariffs', [RoomTariffController::class, 'index']);
            Route::post('/owner/room-tariffs', [RoomTariffController::class, 'store'])->middleware('pos.module:room_fee');
            Route::put('/owner/room-tariffs/calendar', [RoomTariffController::class, 'calendarUpdate']);
            Route::patch('/owner/room-tariffs/{id}', [RoomTariffController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('pos.module:room_fee');
            Route::delete('/owner/room-tariffs/{id}', [RoomTariffController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);

            Route::get('/owner/auto-guest-items', [AutoGuestItemController::class, 'index']);
            Route::post('/owner/auto-guest-items', [AutoGuestItemController::class, 'store'])->middleware('pos.module:guest_items');
            Route::patch('/owner/auto-guest-items/{id}', [AutoGuestItemController::class, 'update'])->where('id', RowId::ROUTE_PATTERN)->middleware('pos.module:guest_items');
            Route::delete('/owner/auto-guest-items/{id}', [AutoGuestItemController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);
        });
    });

    // POS: the till and the waiter phones
    Route::prefix('pos')->group(function () {
        Route::get('/floor', [PosFloorController::class, 'show'])->middleware('permission:orders.view');
        // P3 integration fix: the open orders of every channel (one queue for the till)
        Route::get('/orders', [PosOrderController::class, 'index'])->middleware('permission:orders.view');
        Route::get('/orders/{uuid}', [PosOrderController::class, 'show'])->whereUuid('uuid')->middleware('permission:orders.view');

        $idempotent = fn (string $scope) => EnsureIdempotency::class . ':' . $scope;

        Route::middleware('permission:orders.manage')->group(function () use ($idempotent) {
            Route::post('/orders', [PosOrderController::class, 'store'])->middleware($idempotent('pos.orders.open'));
            Route::post('/orders/{uuid}/rounds', [PosOrderController::class, 'addRound'])->whereUuid('uuid')->middleware($idempotent('pos.orders.rounds'));
            Route::post('/orders/{uuid}/send', [PosOrderController::class, 'send'])->whereUuid('uuid')->middleware($idempotent('pos.orders.send'));
            Route::post('/orders/{uuid}/items/{item}/void', [PosOrderController::class, 'void'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)->middleware($idempotent('pos.orders.void'));
            Route::post('/orders/{uuid}/guests', [PosOrderController::class, 'guests'])->whereUuid('uuid')->middleware($idempotent('pos.orders.guests'));
            Route::post('/orders/{uuid}/charges/{charge}/waive', [PosOrderController::class, 'waive'])->whereUuid('uuid')->where('charge', '[a-z_]{1,32}')->middleware($idempotent('pos.orders.waive'));
            Route::post('/orders/{uuid}/charges/{charge}/restore', [PosOrderController::class, 'restoreCharge'])->whereUuid('uuid')->where('charge', '[a-z_]{1,32}')->middleware($idempotent('pos.orders.restore'));
            // P3 integration fix: a line reached the guests (ready → served)
            Route::post('/orders/{uuid}/items/{item}/served', [PosOrderController::class, 'served'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)->middleware($idempotent('pos.orders.served'));
            // P3-26: the waiter asks the till for the bill; a new online order accepted at the till
            Route::post('/orders/{uuid}/request-bill', [PosOrderController::class, 'requestBill'])->whereUuid('uuid')->middleware($idempotent('pos.orders.request_bill'));
            Route::post('/orders/{uuid}/accept', [PosOrderController::class, 'accept'])->whereUuid('uuid')->middleware($idempotent('pos.orders.accept'));
        });

        // P3-26: reject a new online order — orders.cancel, or orders.manage with a manager's approval token (checked in
        // PosOnlineOrders::reject)
        Route::post('/orders/{uuid}/reject', [PosOrderController::class, 'reject'])->whereUuid('uuid')->middleware(['permission:orders.cancel,orders.manage', $idempotent('pos.orders.reject')]);

        // P3 integration fix: cancel a whole order — orders.cancel, or orders.manage with a manager's approval token
        // (checked in PosOrderService::cancel)
        Route::post('/orders/{uuid}/cancel', [PosOrderController::class, 'cancel'])->whereUuid('uuid')->middleware(['permission:orders.cancel,orders.manage', $idempotent('pos.orders.cancel')]);

        // P3-37: a discount — orders.discount, a cashier within the branch's limit, or orders.manage with a manager's
        // approval token (checked in PosOrderService::discount)
        Route::post('/orders/{uuid}/discount', [PosOrderController::class, 'discount'])->whereUuid('uuid')->middleware(['permission:orders.discount,orders.manage', $idempotent('pos.orders.discount')]);

        Route::middleware('permission:tables.manage')->group(function () use ($idempotent) {
            Route::post('/orders/{uuid}/move', [PosOrderController::class, 'move'])->whereUuid('uuid')->middleware($idempotent('pos.orders.move'));
            Route::post('/orders/{uuid}/merge', [PosOrderController::class, 'merge'])->whereUuid('uuid')->middleware($idempotent('pos.orders.merge'));
        });
    });
});
