<?php

use App\Http\Controllers\Api\V1\Admin\AdminSystemFeatureFlagController;
use App\Http\Controllers\Api\V1\Admin\AdminSystemLocationController;
use App\Http\Controllers\Api\V1\Admin\AdminSystemPaymentProviderController;
use App\Http\Controllers\Api\V1\Admin\AdminSystemSettingController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

// P4-31 Tizim: platform settings (SMS, Telegram, Umumiy), payment providers, regions and districts, feature flags.
// Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*). Everything needs
// admin.system.manage; every write a step-up (admin.recent) and a `reason`. The route name is the audit action. The SLA
// limits (orders) and the tariff catalog (billing) stay in their own sections' files.
Route::prefix('system')->name('system.')->middleware('admin.can:system.manage')->group(function () {
    Route::get('/settings', [AdminSystemSettingController::class, 'index'])->name('settings');
    Route::get('/payment-providers', [AdminSystemPaymentProviderController::class, 'index'])->name('payment_providers');
    Route::get('/regions', [AdminSystemLocationController::class, 'index'])->name('regions');
    Route::get('/feature-flags', [AdminSystemFeatureFlagController::class, 'index'])->name('feature_flags');
    Route::get('/feature-flags/stores', [AdminSystemFeatureFlagController::class, 'stores'])->name('feature_flag_stores');

    Route::middleware('admin.recent')->group(function () {
        $key = '[a-z][a-z0-9_]{1,39}';
        $slug = '[a-z0-9][a-z0-9_-]{0,63}';
        $flag = '[a-z0-9][a-z0-9_.-]{0,99}';

        Route::patch('/settings', [AdminSystemSettingController::class, 'update'])->name('settings_updated');
        Route::put('/settings/secrets/{key}', [AdminSystemSettingController::class, 'replaceSecret'])->where('key', $key)->name('setting_secret_replaced');
        Route::delete('/settings/secrets/{key}', [AdminSystemSettingController::class, 'clearSecret'])->where('key', $key)->name('setting_secret_cleared');

        Route::patch('/payment-providers/{slug}', [AdminSystemPaymentProviderController::class, 'update'])->where('slug', $slug)->name('payment_provider_updated');
        Route::put('/payment-providers/{slug}/credentials/{key}', [AdminSystemPaymentProviderController::class, 'replaceCredential'])->where(['slug' => $slug, 'key' => $key])->name('payment_credential_replaced');
        Route::delete('/payment-providers/{slug}/credentials/{key}', [AdminSystemPaymentProviderController::class, 'clearCredential'])->where(['slug' => $slug, 'key' => $key])->name('payment_credential_cleared');

        Route::post('/regions', [AdminSystemLocationController::class, 'storeRegion'])->name('region_created');
        Route::put('/regions/order', [AdminSystemLocationController::class, 'orderRegions'])->name('regions_ordered');
        Route::patch('/regions/{id}', [AdminSystemLocationController::class, 'updateRegion'])->where('id', RowId::ROUTE_PATTERN)->name('region_updated');
        Route::delete('/regions/{id}', [AdminSystemLocationController::class, 'destroyRegion'])->where('id', RowId::ROUTE_PATTERN)->name('region_deleted');
        Route::post('/regions/{id}/districts', [AdminSystemLocationController::class, 'storeDistrict'])->where('id', RowId::ROUTE_PATTERN)->name('district_created');
        Route::put('/regions/{id}/districts/order', [AdminSystemLocationController::class, 'orderDistricts'])->where('id', RowId::ROUTE_PATTERN)->name('districts_ordered');
        Route::patch('/districts/{id}', [AdminSystemLocationController::class, 'updateDistrict'])->where('id', RowId::ROUTE_PATTERN)->name('district_updated');
        Route::delete('/districts/{id}', [AdminSystemLocationController::class, 'destroyDistrict'])->where('id', RowId::ROUTE_PATTERN)->name('district_deleted');

        Route::put('/feature-flags/{flag}/stores/{storeId}', [AdminSystemFeatureFlagController::class, 'set'])->where(['flag' => $flag, 'storeId' => RowId::ROUTE_PATTERN])->name('feature_flag_set');
        Route::delete('/feature-flags/stores/{storeId}', [AdminSystemFeatureFlagController::class, 'reset'])->where('storeId', RowId::ROUTE_PATTERN)->name('feature_flags_reset');
    });
});
