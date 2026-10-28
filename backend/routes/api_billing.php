<?php

/*
| P1-23: tariff plan and subscription invoices for the store owner (docs/BILLING.md).
| Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Billing\OwnerBillingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api', 'not-staff-session', 'permission:finance.view'])->group(function () {
    Route::get('/owner/subscription', [OwnerBillingController::class, 'subscription']);
    Route::get('/owner/invoices', [OwnerBillingController::class, 'invoices']);
});

// P4-21: what the store's plan lets it use (features, devices, status, the dunning notice), for every member of the
// store and the terminals (the partner web, the till, waiter phones and the KDS show "Asosiy tarifda bor" from it)
Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
    Route::get('/owner/billing/entitlements', [OwnerBillingController::class, 'entitlements']);
});
