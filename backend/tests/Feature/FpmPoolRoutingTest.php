<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Wave M1 workload isolation: backend/docker/nginx.conf sends each request to the php-fpm pool its path belongs to
 * (critical: tills, KDS, terminals, payment webhooks, health; market: Mini App customer reads; default: the rest).
 * This test reads the two routing maps of that file and applies them to every registered route the way nginx does
 * (regexes in order, first match wins), so a new route prefix cannot silently land in a pool nobody chose for it:
 * it fails until the prefix is assigned in nginx.conf. It also pins the rules the owner relies on: a till route is
 * never in a limited pool, and no write ever goes to the market pool.
 */
class FpmPoolRoutingTest extends TestCase
{
    /** @var array<string, list<array{0: string, 1: string}>>|null */
    private static ?array $maps = null;

    public function test_every_route_is_assigned_to_a_pool_explicitly(): void
    {
        $unassigned = [];
        foreach ($this->routes() as [$method, $uri]) {
            if ($this->pathClass($uri) === 'unassigned') {
                $unassigned[] = "$method $uri";
            }
        }

        $this->assertSame([], $unassigned, "These routes match no entry of `map \$rizqim_uri \$rizqim_path_class` in "
            .'backend/docker/nginx.conf. Assign their prefix to critical (tills, KDS, terminals, payment webhooks), '
            .'customer (Mini App customer screens) or default (panel, admin, the rest), and list it in docs/OPERATIONS.md.');
    }

    public function test_till_kitchen_and_terminal_routes_go_to_the_critical_pool(): void
    {
        $wrong = [];
        foreach ($this->routes() as [$method, $uri]) {
            if (preg_match('#^/api/v1/(pos|staff|terminal)(/|$)#', $uri) && $this->pool($method, $uri) !== 'fpm_critical') {
                $wrong[] = "$method $uri";
            }
        }

        $this->assertSame([], $wrong);
    }

    public function test_no_write_goes_to_the_market_pool(): void
    {
        $writes = [];
        foreach ($this->routes() as [$method, $uri]) {
            if ($method !== 'GET' && $this->pool($method, $uri) === 'fpm_market') {
                $writes[] = "$method $uri";
            }
        }

        // The market pool is capped and rate limited: only customer reads may be refused there
        $this->assertSame([], $writes);
    }

    public function test_owner_and_admin_routes_go_to_the_default_pool(): void
    {
        $wrong = [];
        foreach ($this->routes() as [$method, $uri]) {
            if (preg_match('#^/api/v1/(owner|admin)(/|$)#', $uri) && $this->pool($method, $uri) !== 'fpm_default') {
                $wrong[] = "$method $uri";
            }
        }

        $this->assertSame([], $wrong);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function decisions(): array
    {
        return [
            'health (docker)' => ['GET', '/health', '', '', 'fpm_critical'],
            'up' => ['GET', '/up', '', '', 'fpm_critical'],
            'health through the Mini App nginx' => ['GET', '/api/v1/health', '', '', 'fpm_critical'],
            'till payment' => ['POST', '/api/v1/pos/bills/x1/payments', '', '', 'fpm_critical'],
            'till offline sync' => ['POST', '/api/v1/pos/sync', '', '', 'fpm_critical'],
            'kitchen tickets' => ['GET', '/api/v1/staff/kitchen/tickets', '', '', 'fpm_critical'],
            'staff PIN session' => ['POST', '/api/v1/devices/0190a3b0-0000-7000-8000-000000000001/staff-session', '', '', 'fpm_critical'],
            'terminal pairing' => ['POST', '/api/v1/devices/pair', '', '', 'fpm_critical'],
            'token refresh' => ['POST', '/api/v1/auth/refresh', '', '', 'fpm_critical'],
            'Payme webhook' => ['POST', '/api/v1/payments/payme/webhook', '', '', 'fpm_critical'],
            'Click webhook' => ['POST', '/api/v1/payments/click/webhook', '', '', 'fpm_critical'],
            'till menu' => ['GET', '/api/v1/catalog', 'pos_hall', '', 'fpm_critical'],
            'till takeaway menu' => ['GET', '/api/v1/catalog', 'pos_takeaway', '7', 'fpm_critical'],
            'till availability' => ['GET', '/api/v1/catalog/availability', '', '7', 'fpm_critical'],
            'customer catalogue' => ['GET', '/api/v1/catalog', '', '', 'fpm_market'],
            'customer catalogue, mobile prices' => ['GET', '/api/v1/catalog', 'mobile', '', 'fpm_market'],
            'customer availability' => ['GET', '/api/v1/catalog/availability', '', '', 'fpm_market'],
            'store list' => ['GET', '/api/v1/stores', '', '', 'fpm_market'],
            'own orders' => ['GET', '/api/v1/orders', '', '', 'fpm_market'],
            'pickup slots' => ['GET', '/api/v1/stores/1/branches/2/pickup-slots', '', '', 'fpm_market'],
            'checkout' => ['POST', '/api/v1/orders', '', '', 'fpm_default'],
            'order cancel' => ['POST', '/api/v1/orders/x1/cancel', '', '', 'fpm_default'],
            'Mini App sign-in' => ['POST', '/api/v1/auth/telegram', '', '', 'fpm_default'],
            'promo check at checkout' => ['POST', '/api/v1/promo/validate', '', '', 'fpm_default'],
            'own account' => ['GET', '/api/v1/user', '', '', 'fpm_default'],
            'owner panel' => ['GET', '/api/v1/owner/reports/sales', '', '', 'fpm_default'],
            'admin' => ['GET', '/api/v1/admin/stores', '', '', 'fpm_default'],
            'unknown API path' => ['GET', '/api/v1/no-such-thing', '', '', 'fpm_default'],
            'a request for /index.php itself' => ['GET', '', '', '', 'fpm_default'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_pool_decisions(string $method, string $uri, string $channel, string $branch, string $pool): void
    {
        $this->assertSame($pool, $this->pool($method, $uri, $channel, $branch));
    }

    public function test_only_the_market_pool_is_limited(): void
    {
        $conf = $this->nginxConf();
        foreach (['rizqim_market_client', 'rizqim_market_all'] as $key) {
            $this->assertMatchesRegularExpression(
                '/map \$rizqim_fpm \$'.$key.' \{\s*default\s+"";\s*fpm_market\s+\S+;\s*\}/',
                $conf,
                "$key must be empty (not counted) for every pool but fpm_market",
            );
        }

        $pools = (string) file_get_contents(base_path('docker/pools.sh'));
        preg_match_all('/^(limit_req|limit_conn)\s+(zone=)?(\S+)/m', $pools, $m);
        $this->assertNotEmpty($m[3]);
        foreach ($m[3] as $zone) {
            $this->assertStringStartsWith('rizqim_market_', $zone);
        }
        preg_match_all('/^limit_(req|conn)_zone\s+(\S+)/m', $pools, $m);
        // pools.sh writes them from a heredoc, so the `$` is escaped there
        $this->assertSame(['\\$rizqim_market_client', '\\$rizqim_market_client', '\\$rizqim_market_all'], $m[2]);
    }

    public function test_nginx_upstreams_match_the_php_fpm_pools(): void
    {
        preg_match_all('/upstream (fpm_\w+)\s*\{\s*server ([\d.:]+);/', $this->nginxConf(), $m);
        $upstreams = array_combine($m[1], $m[2]);

        $fpm = (string) file_get_contents(base_path('docker/php-fpm.conf'));
        preg_match_all('/^\[(\w+)\]\s*$/m', $fpm, $names);
        $this->assertSame(['global', 'critical', 'market', 'default'], $names[1]);
        foreach (['critical', 'market', 'default'] as $pool) {
            $this->assertMatchesRegularExpression(
                '/^\['.$pool.'\][^\[]*^listen = '.preg_quote($upstreams['fpm_'.$pool], '/').'$/ms',
                $fpm,
                "php-fpm pool [$pool] must listen where nginx's fpm_$pool upstream points",
            );
        }

        $supervisor = (string) file_get_contents(base_path('docker/supervisord.conf'));
        $this->assertStringContainsString('command=/usr/local/bin/rizqim-pools php-fpm', $supervisor);
        $this->assertStringContainsString('command=/usr/local/bin/rizqim-pools nginx', $supervisor);
    }

    /**
     * Every registered route as [METHOD, /uri with sample parameters], HEAD left out (nginx treats it as GET).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function routes(): array
    {
        $out = [];
        /** @var RoutingRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = '/'.ltrim(preg_replace('/\{[^}]+\}/', 'x1', $route->uri()) ?? '', '/');
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $out[] = [$method, $uri];
                }
            }
        }
        $this->assertGreaterThan(500, count($out), 'the routes did not load');

        return $out;
    }

    private function pool(string $method, string $uri, string $channel = '', string $branch = ''): string
    {
        $source = $this->pathClass($uri).':'.$method.':'.$channel.':'.$branch;

        return $this->lookup('rizqim_fpm', $source);
    }

    private function pathClass(string $uri): string
    {
        return $this->lookup('rizqim_path_class', $uri);
    }

    /** nginx map semantics: exact strings first, then the regexes in order, else the default */
    private function lookup(string $map, string $value): string
    {
        $entries = $this->maps()[$map];
        $default = null;
        foreach ($entries as [$key, $result]) {
            if ($key === 'default') {
                $default = $result;
            } elseif (! str_starts_with($key, '~') && $key === $value) {
                return $result;
            }
        }
        foreach ($entries as [$key, $result]) {
            if (str_starts_with($key, '~') && preg_match('#'.str_replace('#', '\#', substr($key, 1)).'#', $value)) {
                return $result;
            }
        }
        $this->assertNotNull($default, "map \$$map has no default");

        return $default;
    }

    /** @return array<string, list<array{0: string, 1: string}>> */
    private function maps(): array
    {
        if (self::$maps !== null) {
            return self::$maps;
        }
        $maps = [];
        preg_match_all('/^map\s+(\S+|"[^"]*")\s+\$(\w+)\s*\{(.*?)^\}/ms', $this->nginxConf(), $blocks, PREG_SET_ORDER);
        foreach ($blocks as $block) {
            $entries = [];
            foreach (preg_split('/\R/', $block[3]) ?: [] as $line) {
                $line = trim((string) preg_replace('/(^|\s)#.*$/', '', $line));
                if ($line === '') {
                    continue;
                }
                $this->assertMatchesRegularExpression('/^(\S+)\s+(\S+);$/', $line, "map \${$block[2]}: unexpected line");
                preg_match('/^(\S+)\s+(\S+);$/', $line, $e);
                $entries[] = [trim($e[1], '"'), trim($e[2], '"')];
            }
            $maps[$block[2]] = $entries;
        }
        $this->assertArrayHasKey('rizqim_path_class', $maps);
        $this->assertArrayHasKey('rizqim_fpm', $maps);

        return self::$maps = $maps;
    }

    private function nginxConf(): string
    {
        return (string) file_get_contents(base_path('docker/nginx.conf'));
    }
}
