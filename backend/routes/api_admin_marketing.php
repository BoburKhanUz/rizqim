<?php

use App\Http\Controllers\Api\V1\Admin\AdminMarketingBannerController;
use App\Http\Controllers\Api\V1\Admin\AdminMarketingCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminMarketingLookupController;
use App\Http\Controllers\Api\V1\Admin\AdminMarketingPromoCodeController;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

// P4-31: Marketing. Loaded from routes/api_admin.php inside the auth:admin group (prefix /api/v1/admin, names admin.*).
// Reads need admin.marketing.view; every change admin.marketing.manage and a `reason`; deleting also a step-up
// (admin.recent). The route name is the audit action.

$id = RowId::ROUTE_PATTERN;

Route::middleware('admin.can:marketing.view')->group(function () use ($id) {
    Route::get('/marketing/lookup', [AdminMarketingLookupController::class, 'index'])->name('marketing.lookup');

    Route::get('/marketing/promos', [AdminMarketingBannerController::class, 'index'])->name('marketing.promos');
    Route::get('/marketing/promos/preview', [AdminMarketingBannerController::class, 'preview'])->name('marketing.promos.preview');
    Route::get('/marketing/promos/{id}/image', [AdminMarketingBannerController::class, 'image'])->where('id', $id)->name('marketing.promos.image');

    Route::get('/marketing/promo-codes', [AdminMarketingPromoCodeController::class, 'index'])->name('marketing.promo_codes');
    Route::get('/marketing/promo-codes/{id}', [AdminMarketingPromoCodeController::class, 'show'])->where('id', $id)->name('marketing.promo_code');

    Route::get('/marketing/categories', [AdminMarketingCategoryController::class, 'index'])->name('marketing.categories');
    Route::get('/marketing/categories/{id}/image', [AdminMarketingCategoryController::class, 'image'])->where('id', $id)->name('marketing.categories.image');
});

Route::middleware('admin.can:marketing.manage')->group(function () use ($id) {
    // Banners and promotions (images: multipart/form-data, so an update is a POST)
    Route::post('/marketing/promos', [AdminMarketingBannerController::class, 'store'])->name('marketing.promos.created');
    Route::put('/marketing/promos/order', [AdminMarketingBannerController::class, 'reorder'])->name('marketing.promos.reordered');
    Route::post('/marketing/promos/{id}', [AdminMarketingBannerController::class, 'update'])->where('id', $id)->name('marketing.promos.updated');

    // Promo codes
    Route::post('/marketing/promo-codes', [AdminMarketingPromoCodeController::class, 'store'])->name('marketing.promo_codes.created');
    Route::patch('/marketing/promo-codes/{id}', [AdminMarketingPromoCodeController::class, 'update'])->where('id', $id)->name('marketing.promo_codes.updated');
    Route::post('/marketing/promo-codes/{id}/activate', [AdminMarketingPromoCodeController::class, 'activate'])->where('id', $id)->name('marketing.promo_codes.activated');
    Route::post('/marketing/promo-codes/{id}/deactivate', [AdminMarketingPromoCodeController::class, 'deactivate'])->where('id', $id)->name('marketing.promo_codes.deactivated');

    // Categories
    Route::post('/marketing/categories', [AdminMarketingCategoryController::class, 'store'])->name('marketing.categories.created');
    Route::put('/marketing/categories/order', [AdminMarketingCategoryController::class, 'reorder'])->name('marketing.categories.reordered');
    Route::post('/marketing/categories/{id}', [AdminMarketingCategoryController::class, 'update'])->where('id', $id)->name('marketing.categories.updated');

    // Deleting needs a fresh confirmation
    Route::middleware('admin.recent')->group(function () use ($id) {
        Route::delete('/marketing/promos/{id}', [AdminMarketingBannerController::class, 'destroy'])->where('id', $id)->name('marketing.promos.deleted');
        Route::delete('/marketing/promo-codes/{id}', [AdminMarketingPromoCodeController::class, 'destroy'])->where('id', $id)->name('marketing.promo_codes.deleted');
        Route::delete('/marketing/categories/{id}', [AdminMarketingCategoryController::class, 'destroy'])->where('id', $id)->name('marketing.categories.deleted');
    });
});
