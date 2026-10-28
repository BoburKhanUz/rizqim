<?php

use App\Http\Controllers\Api\V1\Admin\AdminStoreActionController;
use App\Http\Controllers\Api\V1\Admin\AdminStoreApplicationController;
use App\Http\Controllers\Api\V1\Admin\AdminStoreBroadcastController;
use App\Http\Controllers\Api\V1\Admin\AdminStoreController;
use App\Http\Controllers\Api\V1\Admin\AdminStoreCrmController;
use Illuminate\Support\Facades\Route;

// P4-22: Ovqatxonalar. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Reads need admin.stores.view (the picker also admin.orders.view); pipeline edits and CRM admin.stores.manage; actions
// on a store, converting or rejecting an application and broadcasts also a step-up (admin.recent) and a `reason`. The
// route name is the audit action. The public application form is routes/api_store_applications.php.

$id = '[1-9][0-9]{0,17}';

Route::get('/stores/lookup', [AdminStoreController::class, 'lookup'])
    ->middleware('admin.can:stores.view,orders.view')
    ->name('stores.lookup');

Route::middleware('admin.can:stores.view')->group(function () use ($id) {
    Route::get('/stores', [AdminStoreController::class, 'index'])->name('stores');
    Route::get('/stores/{id}', [AdminStoreController::class, 'show'])->where('id', $id)->name('stores.store');
    Route::get('/store-applications', [AdminStoreApplicationController::class, 'index'])->name('stores.applications');
    Route::get('/store-applications/{uuid}', [AdminStoreApplicationController::class, 'show'])->whereUuid('uuid')->name('stores.application');
    Route::get('/store-tasks', [AdminStoreCrmController::class, 'tasks'])->name('stores.tasks');
    Route::get('/store-broadcasts', [AdminStoreBroadcastController::class, 'index'])->name('stores.broadcasts');
});

Route::middleware('admin.can:stores.manage')->group(function () use ($id) {
    // Pipeline (the move to `faol` checks the step-up itself: it activates the store)
    Route::post('/store-applications', [AdminStoreApplicationController::class, 'store'])->name('stores.applications.created');
    Route::patch('/store-applications/{uuid}', [AdminStoreApplicationController::class, 'update'])->whereUuid('uuid')->name('stores.applications.updated');
    Route::post('/store-applications/{uuid}/stage', [AdminStoreApplicationController::class, 'stage'])->whereUuid('uuid')->name('stores.applications.stage_changed');
    Route::post('/store-applications/{uuid}/checklist', [AdminStoreApplicationController::class, 'checklist'])->whereUuid('uuid')->name('stores.applications.checklist_updated');
    Route::post('/store-applications/{uuid}/notes', [AdminStoreCrmController::class, 'applicationNote'])->whereUuid('uuid')->name('stores.applications.note_added');

    // CRM-lite
    Route::post('/stores/{id}/notes', [AdminStoreCrmController::class, 'storeNote'])->where('id', $id)->name('stores.note_added');
    Route::put('/stores/{id}/next-contact', [AdminStoreCrmController::class, 'nextContact'])->where('id', $id)->name('stores.next_contact_set');
    Route::post('/store-tasks', [AdminStoreCrmController::class, 'storeTask'])->name('stores.tasks.created');
    Route::patch('/store-tasks/{id}', [AdminStoreCrmController::class, 'updateTask'])->where('id', $id)->name('stores.tasks.updated');

    // Broadcast audience preview (a POST for the store list; nothing recorded)
    Route::post('/store-broadcasts/preview', [AdminStoreBroadcastController::class, 'preview'])->name('stores.broadcasts.preview');

    Route::middleware('admin.recent')->group(function () use ($id) {
        Route::post('/store-applications/{uuid}/convert', [AdminStoreApplicationController::class, 'convert'])->whereUuid('uuid')->name('stores.applications.converted');
        Route::post('/store-applications/{uuid}/reject', [AdminStoreApplicationController::class, 'reject'])->whereUuid('uuid')->name('stores.applications.rejected');

        Route::post('/stores/{id}/suspend', [AdminStoreActionController::class, 'suspend'])->where('id', $id)->name('stores.suspended');
        Route::post('/stores/{id}/activate', [AdminStoreActionController::class, 'activate'])->where('id', $id)->name('stores.activated');
        Route::put('/stores/{id}/credit-limit', [AdminStoreActionController::class, 'creditLimit'])->where('id', $id)->name('stores.credit_limit_changed');
        Route::put('/stores/{id}/display', [AdminStoreActionController::class, 'display'])->where('id', $id)->name('stores.display_changed');
        Route::post('/stores/{id}/telegram-link', [AdminStoreActionController::class, 'telegramLink'])->where('id', $id)->name('stores.telegram_link_regenerated');
        Route::post('/stores/{id}/message', [AdminStoreActionController::class, 'message'])->where('id', $id)->name('stores.owner_messaged');

        Route::post('/store-broadcasts', [AdminStoreBroadcastController::class, 'store'])
            ->middleware('throttle:admin-store-broadcast')
            ->name('stores.broadcast_sent');
    });
});
