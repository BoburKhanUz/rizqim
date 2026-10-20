<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Http\Controllers\ApiDocsController;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Gate;
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
        // Scramble's own routes would serve the document without ApiSpec's normalization; ours are registered below
        Scramble::ignoreDefaultRoutes();
    }

    public function boot(): void
    {
        // Outside local/testing (and without API_DOCS_PUBLIC) only a superadmin signed in to the admin panel
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $user?->role === UserRole::SUPER_ADMIN);

        // Laravel registers broadcasting/auth for GET and POST; laravel-echo uses POST, so that is the documented one
        Scramble::configure()->resolveOperationMethodsUsing(
            fn (RoutingRoute $route) => $route->uri() === 'api/broadcasting/auth' ? 'POST' : $route->methods()[0],
        );

        if (!$this->app->routesAreCached()) {
            Route::middleware((array) config('scramble.middleware', []))->group(function () {
                Route::get('docs/api', [ApiDocsController::class, 'ui'])->name('scramble.docs.ui');
                Route::get('docs/api.json', [ApiDocsController::class, 'document'])->name('scramble.docs.document');
            });
        }
    }
}
