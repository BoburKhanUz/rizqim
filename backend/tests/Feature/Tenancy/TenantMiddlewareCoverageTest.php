<?php

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\BindTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * M4 stage 2 (docs/scale/M4_DESIGN.md 3.2): the `tenant` middleware is on every tenant route (the manifest's routes,
 * derived as CrossTenantRouteTest derives them) and on no other route, and it runs after authentication.
 */
class TenantMiddlewareCoverageTest extends TestCase
{
    /** Tenant routes by prefix that are deliberately not bound, with the reason. */
    public const NOT_BOUND = [
        'POST api/v1/orders/{uuid}/cancel' => 'the caller\'s own order as a customer (user_id), whichever store it is at: a member of staff who ordered from another store cancels it here',
    ];

    public function test_every_tenant_route_and_no_other_has_the_tenant_middleware(): void
    {
        $tenant = array_keys(CrossTenantRouteTest::manifest()['routes']);
        $problems = [];
        $seen = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $has = in_array('tenant', $route->gatherMiddleware(), true);
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $key = "{$method} {$route->uri()}";
                $seen[$key] = true;
                $expected = in_array($key, $tenant, true) && !isset(self::NOT_BOUND[$key]);
                if ($expected && !$has) {
                    $problems[] = "{$key}: a tenant route without the `tenant` middleware";
                } elseif (!$expected && $has) {
                    $problems[] = "{$key}: not a tenant route" . (isset(self::NOT_BOUND[$key]) ? ' (NOT_BOUND)' : '') . ', yet it has the `tenant` middleware';
                }
            }
        }
        foreach (array_keys(self::NOT_BOUND) as $key) {
            if (!isset($seen[$key]) || !in_array($key, $tenant, true)) {
                $problems[] = "{$key}: in NOT_BOUND but not a tenant route";
            }
        }

        $this->assertGreaterThan(350, count($tenant));
        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_the_tenant_middleware_runs_after_authentication(): void
    {
        $router = app('router');
        $problems = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (!in_array('tenant', $route->gatherMiddleware(), true)) {
                continue;
            }
            $sorted = array_map(fn ($m) => is_string($m) ? explode(':', $m, 2)[0] : get_class($m), $router->gatherRouteMiddleware($route));
            $bind = array_search(BindTenant::class, $sorted, true);
            $auth = null;
            foreach ($sorted as $i => $class) {
                if (is_subclass_of($class, AuthenticatesRequests::class)) {
                    $auth = $i;
                }
            }
            if ($auth === null || $bind === false || $bind < $auth) {
                $problems[] = $route->uri() . ': ' . implode(', ', $sorted);
            }
        }

        $this->assertSame([], $problems, "`tenant` must run after the auth middleware:\n" . implode("\n", $problems));
    }
}
