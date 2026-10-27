<?php

use App\Http\Controllers\Api\V1\Admin\AdminMonitoringAlertController;
use App\Http\Controllers\Api\V1\Admin\AdminMonitoringFailedJobController;
use App\Http\Controllers\Api\V1\Admin\AdminMonitoringHealthController;
use App\Http\Controllers\Api\V1\Admin\AdminMonitoringRuleController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

// P4-15: Monitoring. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Reading: admin.monitoring.view. Acting (retry, acknowledge, resolve): admin.monitoring.manage. Deleting a failed job
// and changing a rule also need a step-up (admin.recent) and a reason.
Route::prefix('monitoring')->name('monitoring.')->group(function () {
    Route::middleware('admin.can:monitoring.view')->group(function () {
        Route::get('/health', [AdminMonitoringHealthController::class, 'show'])->name('health');
        Route::get('/failed-jobs', [AdminMonitoringFailedJobController::class, 'index'])->name('failed_jobs');
        Route::get('/failed-jobs/{uuid}', [AdminMonitoringFailedJobController::class, 'show'])->whereUuid('uuid')->name('failed_job');
        Route::get('/alerts', [AdminMonitoringAlertController::class, 'index'])->name('alerts');
        Route::get('/alerts/{id}', [AdminMonitoringAlertController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)->name('alert');
        Route::get('/rules', [AdminMonitoringRuleController::class, 'index'])->name('rules');
    });

    Route::middleware('admin.can:monitoring.manage')->group(function () {
        Route::post('/failed-jobs/{uuid}/retry', [AdminMonitoringFailedJobController::class, 'retry'])->whereUuid('uuid')->name('failed_job_retried');
        Route::post('/alerts/{id}/acknowledge', [AdminMonitoringAlertController::class, 'acknowledge'])->where('id', RowId::ROUTE_PATTERN)->name('alert_acknowledged');
        Route::post('/alerts/{id}/resolve', [AdminMonitoringAlertController::class, 'resolve'])->where('id', RowId::ROUTE_PATTERN)->name('alert_resolved');

        Route::middleware('admin.recent')->group(function () {
            Route::delete('/failed-jobs/{uuid}', [AdminMonitoringFailedJobController::class, 'destroy'])->whereUuid('uuid')->name('failed_job_deleted');
            Route::put('/rules/{kind}', [AdminMonitoringRuleController::class, 'update'])->where('kind', '[a-z_]{1,40}')->name('rule_updated');
        });
    });
});
