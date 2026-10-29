<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            \App\Services\Sms\SmsServiceInterface::class,
            \App\Services\Sms\EskizSmsService::class
        );

        // P4-32: the payment providers' switches, read once per request or queued job (a store list asks per store)
        $this->app->scoped(\App\Services\Payment\PaymentProviderSwitch::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        $this->configureRateLimiting();

        // Feature flags (Pennant): definitions live in app/Features
        \App\Features\Features::register();
    }

    protected function configureRateLimiting(): void
    {
        $byUser = fn (Request $request): string => $request->user() ? 'user:' . $request->user()->id : 'ip:' . $request->ip();

        // Generous: staff dashboards poll several endpoints every 8-15s
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($byUser($request)));

        RateLimiter::for('telegram-auth', function (Request $request) {
            // Generous per IP: behind an outer proxy / carrier NAT many users share one address
            $limits = [Limit::perMinute(300)->by('ip:' . $request->ip())];

            // Unverified id, used only as a bucket key; scoped to the IP so nobody can lock a victim's account out remotely
            parse_str((string) $request->input('initData'), $initData);
            $telegramUser = is_string($initData['user'] ?? null) ? json_decode($initData['user'], true) : null;
            $telegramId = is_array($telegramUser) ? ($telegramUser['id'] ?? null) : null;

            if (is_scalar($telegramId) && ctype_digit((string) $telegramId)) {
                $limits[] = Limit::perMinute(10)->by('tg:' . $telegramId . '|ip:' . $request->ip());
            }

            return $limits;
        });

        RateLimiter::for('otp-send', function (Request $request) use ($byUser) {
            $user = $byUser($request);
            $phone = 'phone:' . (string) $request->input('phone');

            return [
                Limit::perMinute(1)->by('cooldown:' . $user),
                Limit::perMinute(1)->by('cooldown:' . $phone),
                Limit::perHour(5)->by('hourly:' . $user),
                Limit::perHour(5)->by('hourly:' . $phone),
            ];
        });

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by($byUser($request)));

        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(10)->by($byUser($request)));

        RateLimiter::for('promo', fn (Request $request) => Limit::perMinute(20)->by($byUser($request)));

        RateLimiter::for('store-ratings', fn (Request $request) => Limit::perMinute(10)->by($byUser($request)));

        RateLimiter::for('complaints', fn (Request $request) => Limit::perMinute(5)->by($byUser($request)));

        // A customer cancelling their own order (POST /orders/{uuid}/cancel)
        RateLimiter::for('order-cancel', fn (Request $request) => Limit::perMinute(5)->by($byUser($request)));

        // Report exports: each one is a heavy worker job (a whole period's orders as a CSV)
        RateLimiter::for('report-export', fn (Request $request) => [
            Limit::perMinute(3)->by('minute:' . $byUser($request)),
            Limit::perDay(20)->by('day:' . $byUser($request)),
        ]);

        // POST /api/broadcasting/auth signs one private channel per call: a few per (re)connect of the Mini App
        // P2-11: a paired terminal (device guard) has a bucket of its own, never a user's with the same id
        RateLimiter::for('broadcast-auth', fn (Request $request) => Limit::perMinute(60)->by(
            $request->user() instanceof \App\Models\Device ? 'device:' . $request->user()->getAuthIdentifier() : $byUser($request)
        ));

        // Address lookups behind /geocode/*: one shared bucket per user; the provider itself allows ~1 request/s for everybody
        RateLimiter::for('geocode', fn (Request $request) => Limit::perMinute(30)->by($byUser($request)));

        // P1-24: public delivery zones and quotes; a customer dragging the map pin asks for a quote at every stop
        RateLimiter::for('delivery-zones', fn (Request $request) => Limit::perMinute(120)->by('ip:' . $request->ip()));
        RateLimiter::for('delivery-quote', fn (Request $request) => Limit::perMinute(60)->by('ip:' . $request->ip()));

        // P2-12: CSV report downloads from the partner web panel (streamed, but a period's orders are read in full)
        RateLimiter::for('report-csv', fn (Request $request) => [
            Limit::perMinute(6)->by('minute:' . $byUser($request)),
            Limit::perDay(100)->by('day:' . $byUser($request)),
        ]);
    }
}
