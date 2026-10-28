<?php

use App\Http\Controllers\Api\V1\Admin\AdminFinanceAdjustmentController;
use App\Http\Controllers\Api\V1\Admin\AdminFinanceController;
use App\Http\Controllers\Api\V1\Admin\AdminFinancePayoutController;
use App\Http\Controllers\Api\V1\Admin\AdminFinanceReportController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

// P4-23: Moliya. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Reads need admin.finance.view; payout decisions admin.payouts.approve, adjustments and settlement retries
// admin.finance.manage, the Excel export also admin.export. Every action needs a step-up (admin.recent) and a `reason`;
// money actions take an Idempotency-Key. The route name is the audit action (docs/API.md "Moliya").

Route::prefix('finance')->name('finance.')->group(function () {
    Route::middleware('admin.can:finance.view')->group(function () {
        Route::get('/summary', [AdminFinanceController::class, 'summary'])->name('summary');
        Route::get('/payouts', [AdminFinancePayoutController::class, 'index'])->name('payouts');
        Route::get('/payouts/{id}', [AdminFinancePayoutController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)->name('payouts.payout');
        Route::get('/topups', [AdminFinanceController::class, 'topups'])->name('topups');
        Route::get('/settlement-failures', [AdminFinanceController::class, 'settlementFailures'])->name('settlement_failures');
        Route::get('/balances', [AdminFinanceController::class, 'balances'])->name('balances');
        Route::get('/stores/{id}/ledger', [AdminFinanceController::class, 'ledger'])->where('id', RowId::ROUTE_PATTERN)->name('stores.ledger');
        Route::get('/adjustments', [AdminFinanceAdjustmentController::class, 'index'])->name('adjustments');
        Route::get('/adjustments/{id}', [AdminFinanceAdjustmentController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)->name('adjustments.adjustment');
        Route::get('/adjustments/{id}/document', [AdminFinanceAdjustmentController::class, 'document'])->where('id', RowId::ROUTE_PATTERN)->name('adjustments.document');
        Route::get('/reports/monthly', [AdminFinanceReportController::class, 'monthly'])->name('reports.monthly');
        Route::get('/reports/monthly/export', [AdminFinanceReportController::class, 'export'])->middleware('admin.can:export')->name('reports.monthly_exported');
    });

    Route::middleware(['admin.can:payouts.approve', 'admin.recent'])->group(function () {
        Route::post('/payouts/{id}/approve', [AdminFinancePayoutController::class, 'approve'])
            ->where('id', RowId::ROUTE_PATTERN)
            ->middleware(EnsureIdempotency::class . ':admin.finance.payouts.approve')
            ->name('payouts.approved');
        Route::post('/payouts/{id}/reject', [AdminFinancePayoutController::class, 'reject'])
            ->where('id', RowId::ROUTE_PATTERN)
            ->middleware(EnsureIdempotency::class . ':admin.finance.payouts.reject')
            ->name('payouts.rejected');
    });

    Route::middleware(['admin.can:finance.manage', 'admin.recent'])->group(function () {
        Route::post('/settlement-failures/{uuid}/retry', [AdminFinanceController::class, 'retrySettlement'])
            ->whereUuid('uuid')
            ->middleware(EnsureIdempotency::class . ':admin.finance.settlements.retry')
            ->name('settlement_failures.retried');
        Route::post('/adjustments', [AdminFinanceAdjustmentController::class, 'store'])
            ->middleware(EnsureIdempotency::class . ':admin.finance.adjustments.store')
            ->name('adjustments.created');
        Route::post('/adjustments/{id}/approve', [AdminFinanceAdjustmentController::class, 'approve'])
            ->where('id', RowId::ROUTE_PATTERN)
            ->middleware(EnsureIdempotency::class . ':admin.finance.adjustments.approve')
            ->name('adjustments.approved');
        Route::post('/adjustments/{id}/reject', [AdminFinanceAdjustmentController::class, 'reject'])
            ->where('id', RowId::ROUTE_PATTERN)
            ->middleware(EnsureIdempotency::class . ':admin.finance.adjustments.reject')
            ->name('adjustments.rejected');
    });
});
