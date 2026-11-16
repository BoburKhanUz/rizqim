<?php

namespace App\Providers;

use App\Http\Controllers\ApiDocsController;
use App\OpenApi\IdempotencyKeyHeader;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * OpenAPI docs of the API (dedoc/scramble, config/scramble.php): who may read them, the parts Scramble needs told,
 * and the /docs/api (UI) and /docs/api.json routes, which serve App\OpenApi\ApiSpec's normalized document rather
 * than Scramble's raw one, so they match packages/api-spec/openapi.json.
 */
class ApiDocsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // M2-4: Scramble is not auto-discovered (composer.json extra.laravel.dont-discover): its provider cost ~0.4 ms
        // of CPU on every API request, for documents only the docs routes and the console (api-spec:export) build
        if (!$this->servesDocs()) {
            return;
        }

        // Scramble's own routes would serve the document without ApiSpec's normalization; ours are registered below
        Scramble::ignoreDefaultRoutes();
        $this->app->register(\Dedoc\Scramble\ScrambleServiceProvider::class);
    }

    public function boot(): void
    {
        if ($this->servesDocs()) {
            $this->configureScramble();
        }

        if (!$this->app->routesAreCached()) {
            Route::middleware((array) config('scramble.middleware', []))->group(function () {
                Route::get('docs/api', [ApiDocsController::class, 'ui'])->name('scramble.docs.ui');
                Route::get('docs/api.json', [ApiDocsController::class, 'document'])->name('scramble.docs.document');
            });
        }
    }

    /** The console (artisan, tests) and the two docs routes; never another HTTP request. */
    private function servesDocs(): bool
    {
        if ($this->app->runningInConsole()) {
            return true;
        }

        return $this->app->bound('request') && $this->app['request']->is('docs/api', 'docs/api.json');
    }

    private function configureScramble(): void
    {
        // Laravel registers broadcasting/auth for GET and POST; laravel-echo uses POST, so that is the documented one
        Scramble::configure()->resolveOperationMethodsUsing(
            fn (RoutingRoute $route) => $route->uri() === 'api/broadcasting/auth' ? 'POST' : $route->methods()[0],
        )
            // P3-26: the Idempotency-Key header on every route that honours it (required on the POS ones)
            ->withOperationTransformers(IdempotencyKeyHeader::class)
            // Wave M2: If-None-Match and the 304 answer on the conditional (polled) GETs
            ->withOperationTransformers(\App\OpenApi\ConditionalGetDocs::class);
    }
}
