<?php

/*
| P6-55 (wave 5 "Banket va dark kitchen", docs/API.md "Ko'p brend, ingredient stop-list, kanal tushumi"): the owner's
| brands (module multi_brand), ingredients and their quick stop (module ingredient_stoplist), the channel net revenue
| report with its Excel and the channel commissions. Required from routes/api.php inside its /v1 prefix group. Store
| and branch scoping in the controllers (another store's brand or ingredient: 404). Reopening an ingredient is never
| gated by the module (it restores what was sold).
*/

use App\Http\Controllers\Api\V1\Management\BrandController;
use App\Http\Controllers\Api\V1\Management\ChannelRevenueController;
use App\Http\Controllers\Api\V1\Management\IngredientController;
use App\Http\Middleware\EnsureIdempotency;
use App\Support\RowId;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::middleware('not-staff-session')->group(function () {
        // Brands (multi_brand)
        Route::get('/owner/brands', [BrandController::class, 'index'])->middleware(['permission:menu.view,menu.manage', 'pos.module:multi_brand']);
        Route::middleware(['permission:menu.manage', 'pos.module:multi_brand', 'billing.unlocked'])->group(function () {
            Route::post('/owner/brands', [BrandController::class, 'store']);
            Route::patch('/owner/brands/{id}', [BrandController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
            Route::delete('/owner/brands/{id}', [BrandController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN);
            Route::put('/owner/brands/{id}/channels', [BrandController::class, 'channels'])->where('id', RowId::ROUTE_PATTERN);
            Route::put('/owner/brands/{id}/branches', [BrandController::class, 'branches'])->where('id', RowId::ROUTE_PATTERN);
            Route::put('/owner/product-brands', [BrandController::class, 'assignProducts']);
        });

        // The channel net revenue report (multi_brand) and the owner's channel commissions
        Route::get('/owner/reports/channel-revenue', [ChannelRevenueController::class, 'show'])->middleware(['permission:reports.view', 'pos.module:multi_brand']);
        Route::get('/owner/reports/channel-revenue/export', [ChannelRevenueController::class, 'export'])->middleware(['permission:reports.view', 'pos.module:multi_brand']);
        Route::get('/owner/channel-commissions', [ChannelRevenueController::class, 'commissions'])->middleware(['permission:settings.manage,reports.view', 'pos.module:multi_brand']);
        Route::put('/owner/channel-commissions', [ChannelRevenueController::class, 'updateCommissions'])->middleware(['permission:settings.manage', 'pos.module:multi_brand', 'billing.unlocked']);

        // Ingredients and their links (ingredient_stoplist)
        Route::middleware(['permission:menu.manage', 'pos.module:ingredient_stoplist'])->group(function () {
            Route::post('/owner/ingredients', [IngredientController::class, 'store']);
            Route::patch('/owner/ingredients/{id}', [IngredientController::class, 'update'])->where('id', RowId::ROUTE_PATTERN);
            Route::put('/owner/ingredients/{id}/products', [IngredientController::class, 'links'])->where('id', RowId::ROUTE_PATTERN);
            Route::post('/owner/ingredients/{id}/link-categories', [IngredientController::class, 'linkCategories'])->where('id', RowId::ROUTE_PATTERN);
        });
        Route::delete('/owner/ingredients/{id}', [IngredientController::class, 'destroy'])->where('id', RowId::ROUTE_PATTERN)->middleware('permission:menu.manage');
    });

    // "Tovuq tugadi": the list and the quick stop (the panel now; the till and the KDS in stage B, hence PIN sessions too)
    Route::get('/owner/ingredients', [IngredientController::class, 'index'])->middleware(['permission:menu.view,menu.manage,stoplist.manage', 'pos.module:ingredient_stoplist']);
    // (an optional Idempotency-Key: a till retrying after a lost answer gets the first answer back)
    Route::post('/owner/ingredients/{id}/stop', [IngredientController::class, 'stop'])->where('id', RowId::ROUTE_PATTERN)->middleware(['permission:stoplist.manage', 'pos.module:ingredient_stoplist', EnsureIdempotency::class . ':owner.ingredients.stop,optional']);
    Route::delete('/owner/ingredients/{id}/stop', [IngredientController::class, 'reopen'])->where('id', RowId::ROUTE_PATTERN)->middleware(['permission:stoplist.manage', EnsureIdempotency::class . ':owner.ingredients.reopen,optional']);
});
