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
    use TenancyWorld;

    public const REFUSALS = [401, 403, 404, 409, 422];

    public const SANCTUM_ACTORS = ['owner', 'manager', 'cashier'];

    /**
     * Body keys that name no record of the route: a manager's approval token is ignored for a caller that holds the
     * permission, so B's token alone may be answered 2xx (it must still change nothing of B and show nothing of it).
     */
    public const NOT_A_RECORD = ['approval_token'];

    /** @var array{not_tenant: array<string, string>, known: array<string, string>, routes: array<string, array<string, mixed>>} */
    private static array $manifest;

    /** @var array<string, string> B before the pass */
    private array $bBefore = [];

    /** @var array<string, array<string, int>> per prefix and actor: routes whose isolation the pass proved */
    public static array $covered = [];

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
        }

        $this->assertSame($this->bBefore, $this->snapshotB(), 'B changed outside the isolated calls');
        $this->assertGreaterThan(0, $ran, 'no route in this pass');
        $this->assertSame([], $failures, count($failures) . " failure(s) in {$ran} routes:\n" . implode("\n", $failures));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<string>
     */
    private function routeFailures(string $key, LaravelRoute $route, array $entry): array
    {
        [$method, $uri] = explode(' ', $key, 2);
        $middleware = $route->gatherMiddleware();
        $actors = in_array('auth:device', $middleware, true) ? ['device'] : self::SANCTUM_ACTORS;
        $ok = is_array($entry['ok']) ? $entry['ok'] : array_fill_keys($actors, $entry['ok']);
        if (array_diff($actors, array_keys($ok)) !== [] || array_diff(array_keys($ok), $actors) !== []) {
            return ["{$key}: `ok` must name exactly the actors " . implode(', ', $actors)];
        }

        $paramsOf = $entry['params'] ?? fn (array $s) => [];
        $bodyOf = $entry['body'] ?? fn (array $s) => [];
        $a = $this->world['A'];
        $b = $this->world['B'];
        $params = ['A' => $paramsOf($a), 'B' => $paramsOf($b)];
        $bodies = ['A' => $bodyOf($a), 'B' => $bodyOf($b)];
        preg_match_all('/\{(\w+)\??\}/', $uri, $names);
        if (array_diff($names[1], array_keys($params['A'])) !== []) {
            return ["{$key}: params must give " . implode(', ', $names[1])];
        }

        // Variants with B's ids: all of them, then (when the route takes more than one) each one alone
        $variants = ['B ids' => [$params['B'], $bodies['B'], false]];
        $differing = [];
        foreach ($params['A'] as $name => $value) {
            if ($params['B'][$name] != $value) {
                $differing[] = ['param', $name];
            }
        }
        foreach ($bodies['A'] as $name => $value) {
            if (($bodies['B'][$name] ?? null) != $value) {
                $differing[] = ['body', $name];
            }
        }
        if (count($differing) > 1) {
            foreach ($differing as [$where, $name]) {
                $p = $params['A'];
                $q = $bodies['A'];
                if ($where === 'param') {
                    $p[$name] = $params['B'][$name];
                } else {
                    $q[$name] = $bodies['B'][$name];
                }
                $variants["B {$where} {$name}"] = [$p, $q, in_array($name, self::NOT_A_RECORD, true)];
            }
        }
        if ($differing === []) {
            // Nothing of a store to name (the caller's own till, shift, clock): A's call must still leave B alone
            $variants = [];
        }

        $failures = [];
        foreach ($actors as $actor) {
            $token = $this->tokenOf($actor, $entry);
            $expected = (array) $ok[$actor];
            $positive = $this->isolatedCall($method, self::fill($uri, $params['A']), $token, $bodies['A'], $variants === []);
            $label = "{$key} as {$actor}";
            $mapped = in_array($positive['status'], $expected, true);
            if (!$mapped) {
                $failures[] = "{$label} with A's ids: {$positive['status']}, manifest expects " . implode('|', $expected) . ': ' . Str::limit($positive['content'], 300);
            }
            if ($leak = $this->bLeakIn($positive['content'])) {
                $failures[] = "{$label} with A's ids LEAKS B: {$leak}";
            }
            if ($positive['after'] !== null && $positive['after'] != $this->bBefore) {
                $failures[] = "{$label} with A's ids CHANGES B in " . implode(', ', $this->changedTables($positive['after']));
            }
            $selfRefused = array_intersect($expected, [401, 403, 404]) === $expected || in_array($actor, $entry['self_refused'] ?? [], true);
            $covered = true;

            foreach ($variants as $variant => [$p, $q, $soft]) {
                $negative = $this->isolatedCall($method, self::fill($uri, $p), $token, $q, true);
                $where = "{$label} [{$variant}]";
                $answered = $negative['status'] >= 200 && $negative['status'] < 300;
                $refused = in_array($negative['status'], self::REFUSALS, true)
                    // A list filtered by B's id may answer with A's own (or no) rows: nothing of B is the check
                    || ($answered && (($entry['filter'] ?? false) || $soft))
                    // A batch (offline sync) answers 200 and refuses per operation
                    || ($answered && isset($entry['refused']) && ($entry['refused'])($negative['json']));
                if (!$refused) {
                    $failures[] = "{$where}: status {$negative['status']} (not a refusal): " . Str::limit($negative['content'], 300);
                    $covered = false;
                }
                if ($negative['after'] != $this->bBefore) {
                    $failures[] = "{$where}: CHANGES B in " . implode(', ', $this->changedTables($negative['after'])) . " (status {$negative['status']})";
                    $covered = false;
                }
                if ($leak = $this->bLeakIn($negative['content'])) {
                    $failures[] = "{$where}: LEAKS B: {$leak}";
                    $covered = false;
                }
                if ($mapped && !$selfRefused && !$answered && !$soft && self::signature($negative) === self::signature($positive)) {
                    $failures[] = "{$where}: same answer as with A's ids ({$negative['status']}): the mapping proves nothing: " . Str::limit($negative['content'], 200);
                    $covered = false;
                }
            }
            if ($covered && $mapped && !$selfRefused) {
                $prefix = preg_match('#^api/v1/(\w+)#', $uri, $m) ? $m[1] : $uri;
                $as = $actor . ($variants === [] ? ' (no ids)' : '');
                self::$covered[$prefix][$as] = (self::$covered[$prefix][$as] ?? 0) + 1;
            }
        }

        return $failures;
    }

    /**
     * @param  array<string, string>  $after
     * @return list<string>
     */
    private function changedTables(array $after): array
    {
        return array_keys(array_diff_assoc($after, $this->bBefore) + array_diff_key($this->bBefore, $after));
    }

    private function tokenOf(string $actor, array $entry): string
    {
        $side = $this->world['A'];

        return match ($actor) {
            'owner' => $side['owner_token'],
            'manager' => $side['manager_token'],
            'cashier' => $side['cashier_token'],
            'device' => $side[($entry['device'] ?? 'till') . '_token'],
        };
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private static function fill(string $uri, array $params): string
    {
        return preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => rawurlencode((string) $params[$m[1]]), $uri);
    }

    /**
     * @param  array{status: int, json: mixed}  $call
     */
    private static function signature(array $call): string
    {
        $json = is_array($call['json']) ? $call['json'] : [];
        $errors = is_array($json['errors'] ?? null) ? array_keys($json['errors']) : [];
        sort($errors);

        return $call['status'] . '|' . json_encode([$json['message'] ?? null, $json['code'] ?? null, is_array($json['data'] ?? null) ? ($json['data']['reason'] ?? null) : null, $errors]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$covered !== [] && getenv('TENANCY_COVERAGE')) {
            fwrite(STDERR, "\nCovered routes per prefix and actor: " . json_encode(self::$covered) . "\n");
        }
        parent::tearDownAfterClass();
    }
}
