<?php

namespace App\Providers;

use App\Services\Admin\Attention\AttentionSource;
use App\Services\Admin\Integrations\Attention\StuckIntegrationOrdersSource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * P4-31: the Tizim and Integratsiyalar sections. Their "Diqqat talab qiladi" source: orders an outside POS has not
 * confirmed (empty until phase 5 adapters write the integration journal).
 *
 * The test SMS (POST /admin/system/settings/sms-test) is limited to 5 an hour per member, answered with its own code.
 */
class AdminSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            StuckIntegrationOrdersSource::class,
        ], AttentionSource::TAG);
    }

    public function boot(): void
    {
        RateLimiter::for('admin-sms-test', fn (Request $request) => Limit::perHour(5)
            ->by('admin-sms-test:' . ($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(function (Request $request, array $headers) {
                $minutes = max(1, (int) ceil(((int) ($headers['Retry-After'] ?? 3600)) / 60));

                return response()->json([
                    'success' => false,
                    'message' => "Sinov SMS soatiga 5 martadan ko'p yuborilmaydi. {$minutes} daqiqadan so'ng qayta urinib ko'ring.",
                    'code' => 'sms_test_rate_limited',
                    'data' => ['reason' => 'sms_test_rate_limited', 'retry_after' => (int) ($headers['Retry-After'] ?? 3600)],
                ], 429, $headers);
            }));
    }
}
