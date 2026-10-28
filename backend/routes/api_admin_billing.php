<?php

use App\Http\Controllers\Api\V1\Admin\AdminBillingPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminStoreBillingController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

// P4-21: tariff catalog and store subscriptions. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Reading: admin.subscriptions.manage or admin.finance.view. Changing the catalog: admin.system.manage; a store's plan,
// period, trial and commission: admin.subscriptions.manage. Every change needs a step-up (admin.recent) and a `reason`;
// the route name is the audit action (docs/API.md, "Billing v2").
Route::prefix('billing')->name('billing.')->group(function () {
    Route::middleware('admin.can:subscriptions.manage,finance.view')->group(function () {
        Route::get('/plans', [AdminBillingPlanController::class, 'index'])->name('plans');
        Route::get('/subscriptions', [AdminStoreBillingController::class, 'index'])->name('subscriptions');
        Route::get('/stores/{id}', [AdminStoreBillingController::class, 'show'])->where('id', RowId::ROUTE_PATTERN)->name('store');
    });

    Route::put('/plans/{code}', [AdminBillingPlanController::class, 'update'])
        ->where('code', '[a-z0-9_-]{1,50}')
        ->middleware(['admin.can:system.manage', 'admin.recent'])
        ->name('plan_updated');

    Route::middleware(['admin.can:subscriptions.manage', 'admin.recent'])->prefix('stores/{id}')->where(['id' => RowId::ROUTE_PATTERN])->group(function () {
        Route::post('/subscription', [AdminStoreBillingController::class, 'assign'])->name('subscription_assigned');
        Route::post('/subscription/period', [AdminStoreBillingController::class, 'period'])->name('period_changed');
        Route::post('/subscription/cancel', [AdminStoreBillingController::class, 'cancel'])->name('subscription_cancelled');
        Route::post('/subscription/trial', [AdminStoreBillingController::class, 'trial'])->name('trial_extended');
        Route::put('/commission', [AdminStoreBillingController::class, 'commission'])->name('commission_updated');
    });
});
