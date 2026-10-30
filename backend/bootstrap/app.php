<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // GET /health: monitoring report (routes/health.php; routes/api.php stays as it is)
        then: function () {
            Route::group([], base_path('routes/health.php'));
        },
    )
    // Real-time updates (Laravel Reverb): POST /api/broadcasting/auth signs private channel subscriptions for a Sanctum
    // Bearer token (60 per minute per user, AppServiceProvider); who may join which channel is in routes/channels.php
    // P2-11: a paired POS/KDS terminal's own token (auth:device) too, for its branch's pos-branch.{id} channel only
    // P4-11: an admin session token (`admin` guard) too, for the platform.ops channel only
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['auth:sanctum,device,admin', 'throttle:broadcast-auth'],
    ])
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new \App\Jobs\CheckUnacceptedAssignmentsJob)->everyMinute();
        // P1-12: instead of sanctum:prune-expired, which would also delete paired POS/KDS terminals' long-lived tokens
        $schedule->command('auth:prune-tokens --hours=24')->daily();
        // Scheduler yurak urishi: /health va `php artisan health:scheduler` o'lik scheduler'ni shu bo'yicha aniqlaydi
        $schedule->call([\App\Support\HealthReport::class, 'beat'])->name('health-heartbeat')->everyMinute();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null;
            }
            return '/login';
        });

        // Har bir so'rovga X-Request-Id beradi va log kontekstiga qo'shadi (loglarni so'rov bo'yicha topish uchun)
        $middleware->append(\App\Http\Middleware\AssignRequestId::class);

        // [P2-30] API javoblari o'zbekcha: Laravel'ning validatsiya xabarlari lang/uz dan (APP_LOCALE qanday bo'lmasin)
        $middleware->api(prepend: [\App\Http\Middleware\SetApiLocale::class]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'staff.store' => \App\Http\Middleware\EnsureStaffHasStore::class,
        ]);

        // The API is reached through the Mini App nginx (Docker network) or a local proxy: trust only
        // private-network hops, and only X-Forwarded-For/Proto, so per-IP rate limits see the real client.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry: SENTRY_LARAVEL_DSN bo'sh bo'lsa hech narsa yubormaydi (config/sentry.php)
        \Sentry\Laravel\Integration::handles($exceptions);

        // Review P3-35: a POS, booking, account or fiscal rule refusing an action (409/422/403: approval_required,
        // version_conflict, has_payments, mxik_missing...) is the API's answer, not a server error: never reported
        // (no ERROR log line with a stack trace, nothing sent to Sentry). One that answers 5xx still is.
        $exceptions->dontReportWhen(fn (\Throwable $e) => match (true) {
            $e instanceof \App\Domain\Pos\Billing\PosActionException,
            $e instanceof \App\Domain\Pos\Orders\PosOrderException,
            $e instanceof \App\Domain\Pos\Booking\BookingException,
            $e instanceof \App\Domain\Pos\Accounts\CustomerAccountException => $e->status < 500,
            // Always 422: a document that cannot be made (no MXIK, does not add up, a wrong original receipt)
            $e instanceof \App\Domain\Fiscal\FiscalException,
            // An offline batch the till must fix or resend (409 sync_in_progress, op_id_conflict, seq_reused)
            $e instanceof \App\Domain\Pos\Sync\PosSyncException => true,
            default => false,
        });

        // P4-11: an admin sign-in or team rule refusing a request: {success: false, message, code, data: {reason}}
        $exceptions->dontReport(\App\Services\Admin\AdminAuthException::class);
        $exceptions->render(function (\App\Services\Admin\AdminAuthException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->reason,
                'data' => ['reason' => $e->reason, ...$e->data],
            ], $e->status, $e->headers);
        });

        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });

        // P3-26: a validation error in the API's envelope too ({success: false, message, data: {reason: "validation",
        // errors}}), with Laravel's top-level `errors` kept for the clients that read it there
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            // P4-32: a payment provider switched off by the team keeps this shape with its own reason
            if ($e instanceof \App\Services\Payment\PaymentProviderInactiveException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => $e->reason,
                    'errors' => $e->errors(),
                    'data' => ['reason' => $e->reason, 'payment_method' => $e->method, 'errors' => $e->errors()],
                ], $e->status);
            }

            // P6-31: a combo pick refused (combo_option_unavailable, combo_slot_required...): the same shape, its own code
            if ($e instanceof \App\Domain\Pos\Menu\ComboPickException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => $e->reason,
                    'errors' => $e->errors(),
                    'data' => ['reason' => $e->reason, 'errors' => $e->errors()],
                ], $e->status);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
                'data' => ['reason' => 'validation', 'errors' => $e->errors()],
            ], $e->status);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (!$request->is('api/*')) {
                return null;
            }

            $retryAfter = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));
            $wait = $retryAfter >= 60 ? ceil($retryAfter / 60) . ' daqiqadan' : $retryAfter . ' soniyadan';

            return response()->json([
                'success' => false,
                'message' => "Juda ko'p urinish. Iltimos, {$wait} so'ng qayta urinib ko'ring.",
                'data' => [],
            ], 429, $e->getHeaders());
        });
    })->create();
