<?php

use App\Http\Controllers\Api\V1\Admin\AdminIntegrationConnectionController;
use App\Http\Controllers\Api\V1\Admin\AdminIntegrationEventController;
use Illuminate\Support\Facades\Route;

// P4-31 Integratsiyalar (docs/PHASE5_PLAN.md 5): the connections to outside systems, the integration journal and the
// stuck orders, read-only until the phase 5 adapters; the team can pause and resume a connection. Loaded from
// routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*). Reads:
// admin.integrations.view; pause/resume: admin.integrations.manage, a step-up (admin.recent) and a `reason`. The route
// name is the audit action.
Route::prefix('integrations')->name('integrations.')->group(function () {
    Route::middleware('admin.can:integrations.view')->group(function () {
        Route::get('/connections', [AdminIntegrationConnectionController::class, 'index'])->name('connections');
        Route::get('/connections/{uuid}', [AdminIntegrationConnectionController::class, 'show'])->whereUuid('uuid')->name('connection');
        Route::get('/events', [AdminIntegrationEventController::class, 'index'])->name('events');
        Route::get('/events/{uuid}', [AdminIntegrationEventController::class, 'show'])->whereUuid('uuid')->name('event');
        Route::get('/stuck-orders', [AdminIntegrationEventController::class, 'stuck'])->name('stuck_orders');
    });

    Route::middleware(['admin.can:integrations.manage', 'admin.recent'])->group(function () {
        Route::post('/connections/{uuid}/pause', [AdminIntegrationConnectionController::class, 'pause'])->whereUuid('uuid')->name('connection_paused');
        Route::post('/connections/{uuid}/resume', [AdminIntegrationConnectionController::class, 'resume'])->whereUuid('uuid')->name('connection_resumed');
    });
});
