<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        $schedule->job(new \App\Jobs\CheckUnacceptedAssignmentsJob)->everyMinute();
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null;
            }
            return '/login';
        });

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
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
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
