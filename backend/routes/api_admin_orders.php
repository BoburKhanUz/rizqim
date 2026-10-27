<?php

use App\Http\Controllers\Api\V1\Admin\AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\AdminOrderInterventionController;
use App\Http\Controllers\Api\V1\Admin\AdminRefundRequestController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

// P4-14: Buyurtmalar. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Reads need admin.orders.view; every action a step-up (admin.recent) and a `reason`; money actions also take an
// Idempotency-Key. The route name is the audit action.

Route::middleware('admin.can:orders.view')->group(function () {
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/sla', [AdminOrderController::class, 'sla'])->name('orders.sla');
    Route::get('/orders/store-quality', [AdminOrderController::class, 'storeQuality'])->name('orders.store_quality');
    Route::get('/orders/{uuid}', [AdminOrderController::class, 'show'])->whereUuid('uuid')->name('orders.order');
    Route::get('/refund-requests', [AdminRefundRequestController::class, 'index'])->name('refund_requests');
});

// Tizim → SLA chegaralari
Route::put('/orders/sla', [AdminOrderController::class, 'updateSla'])
    ->middleware(['admin.can:system.manage', 'admin.recent'])
    ->name('orders.sla.updated');

Route::middleware(['admin.can:orders.intervene', 'admin.recent'])->group(function () {
    Route::post('/orders/{uuid}/cancel', [AdminOrderInterventionController::class, 'cancel'])->whereUuid('uuid')->name('orders.cancelled');
    Route::post('/orders/{uuid}/status', [AdminOrderInterventionController::class, 'status'])->whereUuid('uuid')->name('orders.status_fixed');
    Route::post('/orders/{uuid}/message', [AdminOrderInterventionController::class, 'message'])->whereUuid('uuid')->name('orders.messaged');
});

Route::post('/orders/{uuid}/refund-requests', [AdminRefundRequestController::class, 'store'])
    ->whereUuid('uuid')
    ->middleware(['admin.can:orders.refund_request', 'admin.recent', EnsureIdempotency::class . ':admin.refund_requests.store'])
    ->name('orders.refund_requested');

Route::middleware(['admin.can:orders.refund_approve', 'admin.recent'])->group(function () {
    Route::post('/refund-requests/{id}/approve', [AdminRefundRequestController::class, 'approve'])
        ->where('id', RowId::ROUTE_PATTERN)
        ->middleware(EnsureIdempotency::class . ':admin.refund_requests.approve')
        ->name('refund_requests.approved');
    Route::post('/refund-requests/{id}/reject', [AdminRefundRequestController::class, 'reject'])
        ->where('id', RowId::ROUTE_PATTERN)
        ->middleware(EnsureIdempotency::class . ':admin.refund_requests.reject')
        ->name('refund_requests.rejected');
});
