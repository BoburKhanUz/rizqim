<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\TelegramAuthController;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/telegram', [TelegramAuthController::class, 'authenticate'])->middleware('throttle:telegram-auth');
    });

    Route::middleware(['auth:sanctum', 'staff.store', 'throttle:api'])->group(function () {
        Route::get('/user', function (Request $request) {
            $user = $request->user();
            $store = $user->store;
            if ($store) {
                $store->load('branches');
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'store' => $store,
                    'sms_verification_required' => setting('sms_driver', 'log') === 'eskiz',
                ]
            ]);
        });

        // Phone and profile changes (and the account linking behind them): never from a staff PIN session on a terminal
        Route::post('/phone/send-code', [\App\Http\Controllers\Api\V1\Auth\PhoneVerificationController::class, 'sendCode'])->middleware(['not-staff-session', 'throttle:otp-send']);
        Route::post('/phone/verify-code', [\App\Http\Controllers\Api\V1\Auth\PhoneVerificationController::class, 'verifyCode'])->middleware(['not-staff-session', 'throttle:otp-verify']);

        Route::middleware('not-staff-session')->post('/user/profile', function (Request $request) {
            $user = $request->user();
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:1000',
            ]);

            // With SMS verification on, a new phone may only be set via /phone/send-code + /phone/verify-code
            if (array_key_exists('phone', $data)
                && setting('sms_driver', 'log') === 'eskiz'
                && (string) $data['phone'] !== (string) $user->phone) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'phone' => "Telefon raqamini o'zgartirish uchun uni SMS kod orqali tasdiqlang.",
                ]);
            }

            $user->update($data);
            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully.',
                'data' => $user,
            ]);
        });

        Route::get('/addresses', [\App\Http\Controllers\Api\V1\User\AddressController::class, 'index']);
        Route::post('/addresses', [\App\Http\Controllers\Api\V1\User\AddressController::class, 'store']);
        Route::put('/addresses/{id}', [\App\Http\Controllers\Api\V1\User\AddressController::class, 'update']);
        Route::delete('/addresses/{id}', [\App\Http\Controllers\Api\V1\User\AddressController::class, 'destroy']);
        Route::get('/locations', [\App\Http\Controllers\Api\V1\LocationController::class, 'index']);

        // Server-side proxy to the geocoding provider (the Mini App must not call Nominatim itself)
        Route::get('/geocode/reverse', [\App\Http\Controllers\Api\V1\GeocodeController::class, 'reverse'])->middleware('throttle:geocode');
        Route::get('/geocode/search', [\App\Http\Controllers\Api\V1\GeocodeController::class, 'search'])->middleware('throttle:geocode');

        Route::get('/stores', [\App\Http\Controllers\Api\V1\Store\StoreController::class, 'index']);
        Route::post('/stores/{id}/rate', [\App\Http\Controllers\Api\V1\StoreRatingController::class, 'rate'])->where('id', \App\Support\RowId::ROUTE_PATTERN)->middleware('throttle:store-ratings');
        Route::get('/catalog', [\App\Http\Controllers\Api\V1\Catalog\CatalogController::class, 'index']);
        Route::get('/orders', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'index']);
        Route::get('/notifications', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'notifications']);
        Route::post('/notifications/read', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'markNotificationsRead']);
        // P1-21: an Idempotency-Key header makes a retried checkout return the first order instead of placing another
        Route::post('/orders', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'store'])->middleware(['throttle:orders', \App\Http\Middleware\EnsureIdempotency::class . ':orders.store']);
        Route::post('/orders/{uuid}/complete', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'complete']);
        Route::post('/orders/{uuid}/cancel', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'cancel'])->middleware('throttle:order-cancel');
        Route::post('/orders/{uuid}/status', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'updateStatus']);
        Route::post('/orders/{uuid}/assign-courier', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'assignCourier'])->middleware('permission:orders.assign_courier');
        // [P2-30] orders.assign_courier (owner, manager by default; the store may grant it) instead of the owner/manager roles
        Route::get('/couriers', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'couriers'])->middleware('permission:orders.assign_courier');
        Route::post('/reports/export', [\App\Http\Controllers\Api\V1\Order\OrderController::class, 'exportReport'])->middleware('throttle:report-export');
        Route::post('/promo/validate', [\App\Http\Controllers\Api\V1\PromoCodeController::class, 'validateCode'])->middleware('throttle:promo');

        // Complaints Routes
        Route::get('/complaints', [\App\Http\Controllers\Api\V1\Order\ComplaintController::class, 'index']);
        Route::post('/complaints', [\App\Http\Controllers\Api\V1\Order\ComplaintController::class, 'store'])->middleware('throttle:complaints');
        Route::post('/complaints/{id}/reply', [\App\Http\Controllers\Api\V1\Order\ComplaintController::class, 'reply']);
        Route::post('/complaints/{id}/escalate', [\App\Http\Controllers\Api\V1\Order\ComplaintController::class, 'escalate']);

        // Owner Catalog Routes
        // [P2-30] by permission, not role: menu.view reads (managers, cashiers... by default), menu.manage writes (owner)
        Route::get('/owner/categories', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'getCategories'])->middleware('permission:menu.view');
        Route::post('/owner/categories', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'storeCategory'])->middleware('role:super_admin');

        Route::get('/owner/products', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'getProducts'])->middleware('permission:menu.view');
        Route::middleware('permission:menu.manage')->group(function () {
            Route::post('/owner/products', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'storeProduct']);
            Route::post('/owner/products/{id}', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'updateProduct'])->where('id', \App\Support\RowId::ROUTE_PATTERN); // POST, not PUT: multipart/form-data
            Route::delete('/owner/products/{id}', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'deleteProduct'])->where('id', \App\Support\RowId::ROUTE_PATTERN);
            // [P2-30] remove the product's image (and its thumbnails)
            Route::delete('/owner/products/{id}/image', [\App\Http\Controllers\Api\V1\Store\OwnerCatalogController::class, 'deleteImage'])->where('id', \App\Support\RowId::ROUTE_PATTERN);
        });

        // Managers toggle availability for their own branch (enforced in the controller)
        Route::middleware('role:owner,manager')->group(function () {
            Route::get('/owner/branch-variants', [\App\Http\Controllers\Api\V1\Catalog\BranchProductVariantController::class, 'index']);
            Route::post('/owner/branch-variants', [\App\Http\Controllers\Api\V1\Catalog\BranchProductVariantController::class, 'update']);
        });

        // [P1-14] Server-side sales aggregates for the Mini App's analytics screen (a branch manager sees their branch only)
        Route::get('/owner/analytics', [\App\Http\Controllers\Api\V1\Store\OwnerAnalyticsController::class, 'index'])->middleware(['role:owner,manager', 'permission:reports.view']);

        // Staff, money and settings: not from a staff PIN session on a terminal (docs/AUTH.md)
        Route::middleware(['role:owner', 'not-staff-session'])->group(function () {
            // Owner Employee Routes
            Route::get('/owner/employees', [\App\Http\Controllers\Api\V1\Store\OwnerEmployeeController::class, 'index']);
            Route::post('/owner/employees/invite', [\App\Http\Controllers\Api\V1\Store\OwnerEmployeeController::class, 'generateInvite']);
            Route::post('/owner/employees/{id}/transfer', [\App\Http\Controllers\Api\V1\Store\OwnerEmployeeController::class, 'updateBranch']);
            Route::delete('/owner/employees/{id}', [\App\Http\Controllers\Api\V1\Store\OwnerEmployeeController::class, 'destroy']);

            // Owner Finance Routes (P2-30: GET /owner/finance is finance.view, below; payouts and top-up stay the owner's)
            Route::post('/owner/payouts', [\App\Http\Controllers\Api\V1\Store\OwnerFinanceController::class, 'requestPayout']);
            Route::post('/owner/finance/top-up', [\App\Http\Controllers\Api\V1\Store\OwnerFinanceController::class, 'initiateTopup']);

            // Owner Settings Routes
            Route::get('/owner/settings', [\App\Http\Controllers\Api\V1\Store\OwnerSettingsController::class, 'index']);
            Route::post('/owner/settings', [\App\Http\Controllers\Api\V1\Store\OwnerSettingsController::class, 'update']);
            Route::post('/owner/settings/upload', [\App\Http\Controllers\Api\V1\Store\OwnerSettingsController::class, 'upload']);
        });

        // [P2-30] Balances and recent money movements: finance.view (the owner; an owner may grant it to managers), as
        // the finance history endpoints; never from a terminal PIN session
        Route::get('/owner/finance', [\App\Http\Controllers\Api\V1\Store\OwnerFinanceController::class, 'index'])->middleware(['not-staff-session', 'permission:finance.view']);

        // Feature flags (Pennant) of the user's store; toggled in Superadmin > Feature flaglar
        Route::get('/features', [\App\Http\Controllers\Api\V1\FeatureController::class, 'index']);

        // Super Admin Routes
        Route::middleware('role:super_admin')->group(function () {
            Route::get('/superadmin/dashboard', [\App\Http\Controllers\Api\V1\Superadmin\SuperadminController::class, 'dashboard']);
            Route::get('/superadmin/locations', [\App\Http\Controllers\Api\V1\Superadmin\SuperadminController::class, 'getLocations']);
            Route::get('/superadmin/stores', [\App\Http\Controllers\Api\V1\Superadmin\SuperadminController::class, 'stores']);
            Route::post('/superadmin/stores', [\App\Http\Controllers\Api\V1\Superadmin\SuperadminController::class, 'createStore']);
        });
    });

    // Decoupled Billing Webhooks (server-to-server: no auth, no throttle)
    Route::post('/payments/click/webhook', [\App\Http\Controllers\Api\V1\Payment\ClickWebhookController::class, 'handle']);
    Route::post('/payments/payme/webhook', [\App\Http\Controllers\Api\V1\Payment\PaymeWebhookController::class, 'handle']);

    // Telegram Webhook
    Route::post('/telegram/webhook', [\App\Http\Controllers\Api\V1\Telegram\TelegramWebhookController::class, 'handle'])
        ->middleware(\App\Http\Middleware\VerifyTelegramSecretToken::class);

    // P1-24: delivery zones and the delivery quote (public, read-only; docs/ZONES.md)
    Route::get('/stores/{store}/zones', [\App\Http\Controllers\Api\V1\Delivery\DeliveryZoneController::class, 'index'])->middleware('throttle:delivery-zones');
    Route::get('/delivery/quote', [\App\Http\Controllers\Api\V1\Delivery\DeliveryZoneController::class, 'quote'])->middleware('throttle:delivery-quote');

    // P1-12: refresh tokens, devices, terminal pairing, staff PIN sessions, permissions, audit log
    require __DIR__ . '/api_access.php';

    // P1-21: menu model: availability, modifiers, batches, stop-list, channel prices, service fees (docs/MENU.md)
    require __DIR__ . '/api_menu.php';

    // P1-23: tariff plan and subscription invoices for the owner (docs/BILLING.md)
    require __DIR__ . '/api_billing.php';

    // P2-12: management API for the partner web panel: zones, branches, staff, reports, money history (docs/API.md)
    require __DIR__ . '/api_management.php';

    // P2-11: staff orders (board, Mini App staff screens, kitchen display) and the kitchen bump (docs/API.md §9)
    require __DIR__ . '/api_staff_orders.php';

    // P3-13: POS settings: kitchen stations, printers, branch POS settings, GET /pos/bootstrap (docs/API.md)
    require __DIR__ . '/api_pos_setup.php';

    // P3-11: fiscal settings, readiness, receipts list and retry for the web panel (docs/API.md)
    require __DIR__ . '/api_fiscal.php';

    // P3-17: bookings, pre-orders, deposits and customer accounts (nasiya) (docs/API.md "Bron va nasiya")
    require __DIR__ . '/api_pos_bookings.php';

    // P3-12: halls, tables, room tariffs, per-guest items (web panel) and POS hall orders: floor, rounds, send, void,
    // move, merge, charges (docs/API.md)
    require __DIR__ . '/api_pos_hall.php';

    // P3-18: bills, split, payments, refunds, shifts with X/Z, manager approvals (docs/API.md "Schyot, to'lov va smena")
    require __DIR__ . '/api_pos_billing.php';
});
