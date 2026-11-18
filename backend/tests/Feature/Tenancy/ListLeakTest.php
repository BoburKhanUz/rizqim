<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * M4 stage 1 (docs/scale/M4_DESIGN.md 3.1): every tenant GET without path parameters (a list, a report, a settings
 * page, the till's bootstrap), called by each of A's actors with no query and with the manifest's query for A, shows
 * nothing of B: neither the `ZZB-` marker of B's names nor one of B's uuids. Catches a list or report that forgets the
 * store scope, which a call with B's ids cannot. The staff lists outside the tenant prefixes (orders, notifications,
 * complaints) are scanned too.
 */
class ListLeakTest extends TestCase
{
    use RefreshDatabase;
    use TenancyWorld;

    /** Lists outside the tenant prefixes that a staff token also reads. */
    public const EXTRA_LISTS = ['GET api/v1/orders', 'GET api/v1/orders?scope=active', 'GET api/v1/notifications', 'GET api/v1/complaints'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_no_list_or_report_shows_another_store(): void
    {
        $this->buildTenancyWorld();
        $this->prepareObservation();
        $manifest = CrossTenantRouteTest::manifest();
        $routes = CrossTenantRouteTest::tenantRoutes();

        $calls = [];
        foreach ($manifest['routes'] as $key => $entry) {
            [$method, $uri] = explode(' ', $key, 2);
            if ($method !== 'GET' || str_contains($uri, '{') || !isset($routes[$key])) {
                continue;
            }
            $actors = in_array('auth:device', $routes[$key]->gatherMiddleware(), true) ? ['device'] : CrossTenantRouteTest::SANCTUM_ACTORS;
            $query = isset($entry['body']) ? ($entry['body'])($this->world['A']) : [];
            foreach ($actors as $actor) {
                $calls[] = [$key, $uri, $actor, $entry['device'] ?? 'till', []];
                if ($query !== []) {
                    $calls[] = [$key, $uri, $actor, $entry['device'] ?? 'till', $query];
                }
            }
        }
        foreach (self::EXTRA_LISTS as $key) {
            [, $target] = explode(' ', $key, 2);
            [$uri, $query] = array_pad(explode('?', $target, 2), 2, '');
            parse_str($query, $params);
            foreach (CrossTenantRouteTest::SANCTUM_ACTORS as $actor) {
                $calls[] = [$key, $uri, $actor, 'till', $params];
            }
        }

        $failures = [];
        $answered = 0;
        foreach ($calls as [$key, $uri, $actor, $device, $query]) {
            $token = $actor === 'device' ? $this->world['A'][$device . '_token'] : $this->world['A'][$actor . '_token'];
            $call = $this->isolatedCall('GET', $uri, $token, $query, false);
            $label = "{$key} as {$actor}" . ($query !== [] ? ' ?' . http_build_query($query) : '');
            if ($call['status'] >= 500) {
                $failures[] = "{$label}: {$call['status']}: " . Str::limit($call['content'], 300);
            }
            if ($call['status'] < 300) {
                $answered++;
            }
            if ($leak = $this->bLeakIn($call['content'], $call['request'])) {
                $failures[] = "{$label} LEAKS B ({$call['status']}): {$leak}";
            }
        }

        // The scan reads real lists: most calls must be answered, else the world or the tokens are wrong
        $this->assertGreaterThan(count($calls) / 2, $answered, "only {$answered} of " . count($calls) . ' list calls were answered');
        $this->assertSame([], $failures, count($failures) . ' failure(s) in ' . count($calls) . " list calls:\n" . implode("\n", $failures));
    }
}
