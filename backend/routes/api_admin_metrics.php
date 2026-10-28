<?php

use App\Http\Controllers\Api\V1\Admin\AdminExportController;
use App\Http\Controllers\Api\V1\Admin\AdminMetricsController;
use Illuminate\Support\Facades\Route;

// P4-31: Ko'rsatkichlar and the Excel exports. Loaded from routes/api_admin.php inside the auth:admin group (prefix
// /api/v1/admin, names admin.*; the route name is the audit action).
//
// Metrics: admin.finance.view or admin.stores.view (read-only). Exports: admin.export AND the list's own view
// permission, throttled; every export is recorded in the audit log by the controller (a GET is not audited by
// admin.audit), docs/API.md "Excel eksport".

Route::prefix('metrics')->name('metrics.')->middleware('admin.can:finance.view,stores.view')->group(function () {
    Route::get('/', [AdminMetricsController::class, 'index'])->name('overview');
    Route::get('/funnel', [AdminMetricsController::class, 'funnel'])->name('funnel');
});

Route::prefix('exports')->name('exports.')->middleware(['admin.can:export', 'throttle:admin-export'])->group(function () {
    Route::get('/orders', [AdminExportController::class, 'orders'])->middleware('admin.can:orders.view')->name('orders');
    Route::get('/stores', [AdminExportController::class, 'stores'])->middleware('admin.can:stores.view')->name('stores');
    Route::get('/store-applications', [AdminExportController::class, 'applications'])->middleware('admin.can:stores.view')->name('store_applications');
    Route::get('/finance/payouts', [AdminExportController::class, 'payouts'])->middleware('admin.can:finance.view')->name('finance_payouts');
    Route::get('/finance/topups', [AdminExportController::class, 'topups'])->middleware('admin.can:finance.view')->name('finance_topups');
    Route::get('/finance/balances', [AdminExportController::class, 'balances'])->middleware('admin.can:finance.view')->name('finance_balances');
    Route::get('/pos/invoices', [AdminExportController::class, 'invoices'])->middleware('admin.can:subscriptions.manage,finance.view')->name('pos_invoices');
    Route::get('/pos/devices', [AdminExportController::class, 'devices'])->middleware('admin.can:devices.view')->name('pos_devices');
    Route::get('/audit-logs', [AdminExportController::class, 'auditLogs'])->middleware('admin.can:audit.view')->name('audit_logs');
});
