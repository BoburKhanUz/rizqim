<?php

/*
| P6-13: order numbers and calling (docs/API.md "Buyurtma raqami va chaqiruv"): the pickup board, the till's number
| blocks, and a number's moves (ready, call again, handed). A normal staff sign-in or a staff PIN session on a paired
| terminal; store and branch scoping in the controller. Gated by the format modules (pos.module): `queue_numbers` for
| the blocks, `queue_calling` for the board and a new call (ready and handed check it in the controller, so a number
| already in the queue can be finished after the module was switched off). Every change takes an Idempotency-Key (required). Required
| from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Pos\PosQueueController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->prefix('pos')->group(function () {
    $idempotent = fn (string $scope) => EnsureIdempotency::class . ':' . $scope;

    Route::get('/queue', [PosQueueController::class, 'board'])->middleware(['permission:orders.view', 'pos.module:queue_calling']);
    Route::post('/queue/blocks', [PosQueueController::class, 'reserve'])->middleware(['permission:orders.manage', 'pos.module:queue_numbers', $idempotent('pos.queue.blocks')]);

    Route::middleware(['permission:orders.manage,kitchen.manage'])->group(function () use ($idempotent) {
        // Review P6 C#L6: ready and handed finish a number already in the queue even after `queue_calling` was switched
        // off; the controller (PickupQueue) refuses an order not in it with the same 403 module_disabled there
        Route::post('/orders/{uuid}/ready', [PosQueueController::class, 'ready'])->whereUuid('uuid')->middleware($idempotent('pos.queue.ready'));
        Route::post('/orders/{uuid}/call', [PosQueueController::class, 'call'])->whereUuid('uuid')->middleware(['pos.module:queue_calling', $idempotent('pos.queue.call')]);
        Route::post('/orders/{uuid}/handed', [PosQueueController::class, 'handed'])->whereUuid('uuid')->middleware($idempotent('pos.queue.handed'));
    });
});
