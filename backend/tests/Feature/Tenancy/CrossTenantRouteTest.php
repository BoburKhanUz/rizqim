<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * M4 stage 1, the acceptance test of tenant isolation (docs/scale/M4_DESIGN.md 3.1): every tenant route, called by
 * A's owner, A's branch-bound manager, A's cashier PIN session on A's till (and A's terminal token where the route
 * takes device auth) with B's ids, is refused (401/403/404/409/422), leaves every row of B as it was and shows nothing
 * of B; the same call with A's own ids gets the answer the manifest (routes.php) names, so the refusal is about the
 * store and not about a wrong mapping. A route the manifest does not know fails test_every_route_is_classified.
 *
 * Every call runs inside a savepoint that is rolled back, so one world serves a whole pass and no call sees another's
 * writes. A route listed in routes.php `known` is a finding handed to the fixing agent: the passes leave it out and
 * test_known_findings_are_fixed fails until it holds.
 */
class CrossTenantRouteTest extends TestCase
{
    use RefreshDatabase;
    use TenancySuite;

    public const REFUSALS = [401, 403, 404, 409, 422];

    public const SANCTUM_ACTORS = ['owner', 'manager', 'cashier'];

    /**
     * Body keys that name no record of the route: a manager's approval token is ignored for a caller that holds the
     * permission, so B's token alone may be answered 2xx (it must still change nothing of B and show nothing of it).
     */
    public const NOT_A_RECORD = ['approval_token'];

    /** @var array{not_tenant: array<string, string>, known: array<string, string>, routes: array<string, array<string, mixed>>} */
    private static array $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        self::$manifest ??= require __DIR__ . '/routes.php';
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::preventStrayRequests();
        Http::fake();
    }

    public static function manifest(): array
    {
        return self::$manifest ??= require __DIR__ . '/routes.php';
    }

    /**
     * Tenant routes by the design's prefixes: METHOD + URI => the route.
     *
     * @return array<string, LaravelRoute>
     */
    public static function tenantRoutes(): array
    {
        $routes = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (self::isTenantUri($route->uri())) {
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $routes["{$method} {$route->uri()}"] = $route;
                }
            }
        }
        ksort($routes);

        return $routes;
    }

    public static function isTenantUri(string $uri): bool
    {
        return preg_match('#^api/v1/(pos|owner|staff|devices|terminal)(/|$)#', $uri) === 1
            || preg_match('#^api/v1/orders/\{[^}]+\}/(status|complete|assign-courier)$#', $uri) === 1
            || in_array($uri, ['api/v1/couriers', 'api/v1/reports/export'], true);
    }

    public function test_every_route_is_classified(): void
    {
        $manifest = self::manifest();
        $all = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $all["{$method} {$route->uri()}"] = $route;
            }
        }

        $problems = [];
        foreach ($all as $key => $route) {
            $inManifest = isset($manifest['routes'][$key]);
            $notTenant = self::notTenantReason($key, $route);
            if (self::isTenantUri($route->uri()) && !$inManifest && $notTenant === null) {
                $problems[] = "{$key}: tenant route missing from tests/Feature/Tenancy/routes.php: classify this route";
            } elseif (!$inManifest && $notTenant === null) {
                $problems[] = "{$key}: neither in the manifest nor in NOT_TENANT: classify this route";
            } elseif ($inManifest && $notTenant !== null) {
                $problems[] = "{$key}: both in the manifest and in NOT_TENANT";
            }
        }
        foreach (array_keys($manifest['routes']) as $key) {
            if (!isset($all[$key])) {
                $problems[] = "{$key}: in the manifest but no such route";
            }
        }
        foreach (array_keys($manifest['not_tenant']) as $key) {
            if (!str_ends_with($key, '*') && !isset($all[$key])) {
                $problems[] = "{$key}: in NOT_TENANT but no such route";
            }
        }
        foreach (array_keys($manifest['known']) as $key) {
            if (!isset($manifest['routes'][$key])) {
                $problems[] = "{$key}: a known finding must be a manifest route";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Why a route is not a tenant route, or null. An `api/v1/admin/*` route is one only behind the admin guard (or one
     * of the admin sign-in steps listed by name).
     */
    private static function notTenantReason(string $key, LaravelRoute $route): ?string
    {
        $list = self::manifest()['not_tenant'];
        if (isset($list[$key])) {
            return $list[$key];
        }
        if (str_starts_with($route->uri(), 'api/v1/admin/') && in_array('auth:admin', $route->gatherMiddleware(), true)) {
            return $list['api/v1/admin/*'];
        }

        return null;
    }

    public function test_pos_routes(): void
    {
        $this->pass(fn (string $key) => str_starts_with(self::uriOf($key), 'api/v1/pos/'));
    }

    public function test_owner_routes(): void
    {
        $this->pass(fn (string $key) => str_starts_with(self::uriOf($key), 'api/v1/owner/'));
    }

    public function test_staff_device_terminal_and_order_routes(): void
    {
        $this->pass(fn (string $key) => !str_starts_with(self::uriOf($key), 'api/v1/pos/') && !str_starts_with(self::uriOf($key), 'api/v1/owner/'));
    }

    public function test_known_findings_are_fixed(): void
    {
        $known = self::manifest()['known'];
        if ($known === []) {
            $this->assertSame([], $known);

            return;
        }
        $this->pass(fn (string $key) => isset($known[$key]), true);
    }

    private static function uriOf(string $key): string
    {
        return explode(' ', $key, 2)[1];
    }

    /**
     * @param  callable(string): bool  $select
     */
    private function pass(callable $select, bool $known = false): void
    {
        $this->buildTenancyWorld();
        $this->prepareObservation();
        $this->bBefore = $this->snapshotB();
        $manifest = self::manifest();
        $routes = self::tenantRoutes();

        $failures = [];
        $ran = 0;
        foreach ($manifest['routes'] as $key => $entry) {
            if (!$select($key) || (!$known && isset($manifest['known'][$key])) || !isset($routes[$key])) {
                continue;
            }
            $ran++;
            try {
                $found = $this->routeFailures($key, $routes[$key], $entry);
            } catch (\Throwable $e) {
                $found = ["{$key}: the manifest entry fails: " . get_class($e) . ': ' . Str::limit($e->getMessage(), 300)];
            }
            foreach ($found as $failure) {
                $failures[] = (isset($manifest['known'][$key]) ? "[known: {$manifest['known'][$key]}] " : '') . $failure;
            }
            if ($known && $found === []) {
                $failures[] = "{$key}: the known finding holds now: remove it from `known` in routes.php";
            }
        }

        $this->assertSame($this->bBefore, $this->snapshotB(), 'B changed outside the isolated calls');
        $this->assertGreaterThan(0, $ran, 'no route in this pass');
        $this->assertSame([], $failures, count($failures) . " failure(s) in {$ran} routes:\n" . implode("\n", $failures));
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$covered !== [] && getenv('TENANCY_COVERAGE')) {
            fwrite(STDERR, "\nCovered routes per prefix and actor: " . json_encode(self::$covered) . "\n");
        }
        parent::tearDownAfterClass();
    }
}
