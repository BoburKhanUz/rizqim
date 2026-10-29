<?php

/*
| P6-14: the customer display (docs/API.md "Mijoz ekrani"): a paired terminal's own token (auth:device; a display has no
| staff session). GET /pos/display/state and /pos/display/queue are for the display (and any terminal of the branch);
| POST /pos/display/cart is the till publishing the cart it shows on the second screen. Gated by the format modules
| (customer_display, queue_calling) inside the controller. Required from routes/api.php inside its /v1 prefix group.
*/

use App\Http\Controllers\Api\V1\Pos\PosDisplayController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:device'])->prefix('pos/display')->group(function () {
    Route::get('/state', [PosDisplayController::class, 'state'])->middleware('throttle:90,1');
    Route::get('/queue', [PosDisplayController::class, 'queue'])->middleware('throttle:90,1');
    // The cart changes with every tap of the cashier: a debounced app stays well below this
    Route::post('/cart', [PosDisplayController::class, 'publish'])->middleware(['throttle:300,1', EnsureIdempotency::class . ':pos.display.cart,optional']);
});
