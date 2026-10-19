<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Monitoring endpoint (docs/OPERATIONS.md). No auth, no session, 60 requests/minute per IP. /health is for Docker and
// uptime checks that reach the backend directly; /api/v1/health returns the same report through the Mini App nginx
// (it proxies /api/ only). Loaded from bootstrap/app.php (withRouting `then`), so routes/api.php stays untouched.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/health', HealthController::class)->name('health');
    Route::get('/api/v1/health', HealthController::class)->name('health.api');
});
