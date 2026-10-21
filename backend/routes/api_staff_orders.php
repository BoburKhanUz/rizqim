<?php

/*
| P2-11: orders for the store's staff (docs/API.md §9): the partner web board, the Mini App staff screens and the
| kitchen display (KDS). A normal staff sign-in or a staff PIN session on a paired terminal (docs/AUTH.md §5); the
| branch scoping is StaffOrderScope's (another store's or branch's order: 404). Required from routes/api.php inside its
| /v1 prefix group. Couriers and customers keep GET /orders.
*/

use App\Http\Controllers\Api\V1\Order\StaffOrderController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->prefix('staff')->group(function () {
    Route::get('/orders', [StaffOrderController::class, 'index'])->middleware('permission:orders.view');
    Route::get('/orders/{uuid}', [StaffOrderController::class, 'show'])->whereUuid('uuid')->middleware('permission:orders.view');
    Route::post('/orders/{uuid}/status', [StaffOrderController::class, 'updateStatus'])->whereUuid('uuid')->middleware('permission:orders.manage,kitchen.manage');

    // Kitchen display: tickets, and a line bumped ready or recalled
    Route::get('/kitchen/tickets', [StaffOrderController::class, 'tickets'])->middleware('permission:kitchen.manage,orders.view');
    Route::post('/orders/{uuid}/items/{item}/ready', [StaffOrderController::class, 'bumpItem'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)->middleware('permission:kitchen.manage');
    Route::post('/orders/{uuid}/items/{item}/recall', [StaffOrderController::class, 'recallItem'])->whereUuid('uuid')->where('item', RowId::ROUTE_PATTERN)->middleware('permission:kitchen.manage');
});
