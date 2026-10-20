<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

// Monitoring endpoint (docs/OPERATIONS.md). No auth, no session and no rate limit: the limiter counts in the cache
// (Redis), so with Redis down a throttle would answer 500 before the report could say "redis: fail" (503). /health is
// for Docker and uptime checks that reach the backend directly; /api/v1/health returns the same report through the
// Mini App nginx (it proxies /api/ only). Loaded from bootstrap/app.php (withRouting `then`), so routes/api.php stays
// untouched.
Route::get('/health', HealthController::class)->name('health');
Route::get('/api/v1/health', HealthController::class)->name('health.api');
