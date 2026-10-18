<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*')) {
                return null;
            }
            return '/login';
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            if ($request->is('api/*')) {
                return true;
            }
            return $request->expectsJson();
        });
    })->create();
