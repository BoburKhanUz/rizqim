<?php

use App\Http\Controllers\Api\V1\Onboarding\StoreApplicationController;
use Illuminate\Support\Facades\Route;

// P4-22: the public store application form (the website, later the bot). Loaded by App\Providers\AdminStoresServiceProvider
// under the `api` middleware group and the /api/v1 prefix. No token; throttled per IP and per phone, honeypot field.
Route::post('/store-applications', [StoreApplicationController::class, 'store'])
    ->middleware('throttle:store-applications')
    ->name('store_applications.store');
