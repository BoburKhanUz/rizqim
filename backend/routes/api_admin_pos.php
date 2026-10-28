<?php

use App\Http\Controllers\Api\V1\Admin\AdminPosDeviceController;
use App\Http\Controllers\Api\V1\Admin\AdminPosFiscalController;
use App\Http\Controllers\Api\V1\Admin\AdminPosInvoiceController;
use App\Http\Controllers\Api\V1\Admin\AdminPosOverviewController;
use App\Http\Controllers\Api\V1\Admin\AdminPosShiftController;
use App\Http\Controllers\Api\V1\Admin\AdminPosSyncErrorController;
use Illuminate\Support\Facades\Route;

// P4-24: POS va obunalar. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names
// admin.*). The subscription list, the store subscription panel and the tariff catalog are P4-21's billing API
// (routes/api_admin_billing.php); this file adds what is missing: the invoices across stores, devices, shifts, fiscal
// receipts and offline sync errors.
// Reads: admin.subscriptions.manage or admin.finance.view for invoices, admin.devices.view for the rest. Writes: a
// step-up (admin.recent) and a `reason`; invoices admin.finance.manage, the rest admin.stores.manage. The route name is
// the audit action.
Route::prefix('pos')->name('pos.')->group(function () {
    Route::get('/overview', [AdminPosOverviewController::class, 'index'])
        ->middleware('admin.can:subscriptions.manage,finance.view,devices.view')
        ->name('overview');

    Route::middleware('admin.can:subscriptions.manage,finance.view')->group(function () {
        Route::get('/invoices', [AdminPosInvoiceController::class, 'index'])->name('invoices');
        Route::get('/invoices/{uuid}', [AdminPosInvoiceController::class, 'show'])->whereUuid('uuid')->name('invoice');
    });

    Route::middleware(['admin.can:finance.manage', 'admin.recent'])->group(function () {
        Route::post('/invoices/{uuid}/paid', [AdminPosInvoiceController::class, 'markPaid'])->whereUuid('uuid')->name('invoice_paid');
        Route::post('/invoices/{uuid}/void', [AdminPosInvoiceController::class, 'void'])->whereUuid('uuid')->name('invoice_voided');
    });

    Route::middleware('admin.can:devices.view')->group(function () {
        Route::get('/devices', [AdminPosDeviceController::class, 'index'])->name('devices');
        Route::get('/devices/{uuid}', [AdminPosDeviceController::class, 'show'])->whereUuid('uuid')->name('device');
        Route::get('/shifts', [AdminPosShiftController::class, 'index'])->name('shifts');
        Route::get('/shifts/{uuid}', [AdminPosShiftController::class, 'show'])->whereUuid('uuid')->name('shift');
        Route::get('/fiscal-receipts', [AdminPosFiscalController::class, 'index'])->name('fiscal_receipts');
        Route::get('/fiscal-receipts/{uuid}', [AdminPosFiscalController::class, 'show'])->whereUuid('uuid')->name('fiscal_receipt');
        Route::get('/sync-errors', [AdminPosSyncErrorController::class, 'index'])->name('sync_errors');
        Route::get('/sync-errors/{opId}', [AdminPosSyncErrorController::class, 'show'])->whereUuid('opId')->name('sync_error');
    });

    Route::middleware(['admin.can:stores.manage', 'admin.recent'])->group(function () {
        Route::post('/devices/{uuid}/revoke', [AdminPosDeviceController::class, 'revoke'])->whereUuid('uuid')->name('device_revoked');
        Route::post('/fiscal-receipts/{uuid}/retry', [AdminPosFiscalController::class, 'retry'])->whereUuid('uuid')->name('fiscal_receipt_retried');
        Route::post('/sync-errors/{opId}/resolve', [AdminPosSyncErrorController::class, 'resolve'])->whereUuid('opId')->name('sync_error_resolved');
    });
});
