<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Wave M1 workload isolation: backend/docker/nginx.conf sends each request to the php-fpm pool its path belongs to
 * (critical: tills, KDS, terminals, payment webhooks, health; market: Mini App customer reads; analytics: the Mini App's
 * analytics batches; default: the rest).
 * This test reads the two routing maps of that file and applies them to every registered route the way nginx does
 * (regexes in order, first match wins), so a new route prefix cannot silently land in a pool nobody chose for it:
 * it fails until the prefix is assigned in nginx.conf. It also pins the rules the owner relies on: a till route is
 * never in a limited pool, no write ever goes to the market pool, checkout never depends on a market read, a caller
 * cannot pick the critical pool by the query string, one client cannot fill the market pool (M1 review R2), and the
 * analytics batches never take a worker of the default pool where checkout runs (wave P step 2 review).
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

    private const TILL = 'Bearer 12|tilltokenplaintext';

    private const CUSTOMER = 'Bearer 34|customertokenplaintext';

    /**
     * [method, uri, query string, X-Rizqim-App, Authorization, expected pool]
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>
     */
    public static function decisions(): array
    {
        $till = self::TILL;
        $customer = self::CUSTOMER;

        return [
            'health (docker)' => ['GET', '/health', '', '', '', 'fpm_critical'],
            'up' => ['GET', '/up', '', '', '', 'fpm_critical'],
            'health through the Mini App nginx' => ['GET', '/api/v1/health', '', '', '', 'fpm_critical'],
            'till payment' => ['POST', '/api/v1/pos/bills/x1/payments', '', 'cashier', $till, 'fpm_critical'],
            'till offline sync' => ['POST', '/api/v1/pos/sync', '', 'cashier', $till, 'fpm_critical'],
            'kitchen tickets' => ['GET', '/api/v1/staff/kitchen/tickets', '', 'kds', $till, 'fpm_critical'],
            'staff PIN session' => ['POST', '/api/v1/devices/0190a3b0-0000-7000-8000-000000000001/staff-session', '', 'cashier', $till, 'fpm_critical'],
            'terminal pairing' => ['POST', '/api/v1/devices/pair', '', '', '', 'fpm_critical'],
            'token refresh' => ['POST', '/api/v1/auth/refresh', '', '', $customer, 'fpm_critical'],
            'Payme webhook' => ['POST', '/api/v1/payments/payme/webhook', '', '', '', 'fpm_critical'],
            'Paylov webhook' => ['POST', '/api/v1/payments/paylov/webhook', '', '', '', 'fpm_critical'],
            'Click webhook' => ['POST', '/api/v1/payments/click/webhook', '', '', '', 'fpm_critical'],
            // The till's own requests of the paths it shares with the Mini App (pos_data catalog_repository, kitchen_setup)
            'till menu' => ['GET', '/api/v1/catalog', 'channel=pos_hall', 'cashier', $till, 'fpm_critical'],
            'till takeaway menu' => ['GET', '/api/v1/catalog', 'channel=pos_takeaway&branch_id=7', 'cashier', $till, 'fpm_critical'],
            'waiter menu, branch not sent' => ['GET', '/api/v1/catalog', 'channel=pos_hall&branch_id=', 'waiter', $till, 'fpm_critical'],
            'KDS batch products' => ['GET', '/api/v1/catalog', 'channel=pos_hall&branch_id', 'kds', $till, 'fpm_critical'],
            'till availability' => ['GET', '/api/v1/catalog/availability', 'store_id=3&branch_id=7', 'cashier', $till, 'fpm_critical'],
            // Not clearly a till: never the market (no limit can reach a till that sends something unexpected), never critical
            'POS channel without the app header' => ['GET', '/api/v1/catalog', 'channel=pos_hall', '', $customer, 'fpm_default'],
            'POS channel without a token' => ['GET', '/api/v1/catalog', 'channel=pos_hall', 'cashier', '', 'fpm_default'],
            'POS channel, garbage token' => ['GET', '/api/v1/catalog', 'channel=pos_hall', 'cashier', 'Bearer garbage', 'fpm_default'],
            'unknown app header' => ['GET', '/api/v1/catalog', 'channel=pos_hall', 'miniapp', $till, 'fpm_default'],
            'R2-4: repeated channel (PHP reads the last one)' => ['GET', '/api/v1/catalog', 'channel=pos_hall&channel=delivery', 'cashier', $till, 'fpm_default'],
            'R2-4: repeated channel, POS last' => ['GET', '/api/v1/catalog', 'channel=delivery&channel=pos_hall', '', $customer, 'fpm_default'],
            'R2-4: CHANNEL (nginx ignores the case, PHP does not)' => ['GET', '/api/v1/catalog', 'CHANNEL=pos_hall', 'cashier', $till, 'fpm_default'],
            'R2-4: encoded key' => ['GET', '/api/v1/catalog', 'ch%61nnel=pos_hall', 'cashier', $till, 'fpm_default'],
            'R2-4: extra parameter' => ['GET', '/api/v1/catalog', 'channel=pos_hall&x=1', 'cashier', $till, 'fpm_default'],
            'R2-4: customer availability with a branch' => ['GET', '/api/v1/catalog/availability', 'branch_id=1', '', $customer, 'fpm_default'],
            'R2-4: availability, branch repeated' => ['GET', '/api/v1/catalog/availability', 'branch_id=1&branch_id=', 'cashier', $till, 'fpm_default'],
            'R2-4: availability, BRANCH_ID' => ['GET', '/api/v1/catalog/availability', 'BRANCH_ID=1', 'cashier', $till, 'fpm_default'],
            'staff screen availability (partner web, no app header)' => ['GET', '/api/v1/catalog/availability', 'store_id=3&branch_id=7', '', $customer, 'fpm_default'],
            'customer catalogue' => ['GET', '/api/v1/catalog', 'store_id=5', '', $customer, 'fpm_market'],
            'customer catalogue, no query' => ['GET', '/api/v1/catalog', '', '', $customer, 'fpm_market'],
            'customer catalogue, mobile prices' => ['GET', '/api/v1/catalog', 'channel=mobile', '', $customer, 'fpm_market'],
            'customer availability' => ['GET', '/api/v1/catalog/availability', 'store_id=5', '', $customer, 'fpm_market'],
            'store list' => ['GET', '/api/v1/stores', '', '', $customer, 'fpm_market'],
            'store list, anonymous' => ['GET', '/api/v1/stores', '', '', '', 'fpm_market'],
            'own orders' => ['GET', '/api/v1/orders', '', '', $customer, 'fpm_market'],
            'delivery zones (map)' => ['GET', '/api/v1/stores/1/zones', '', '', '', 'fpm_market'],
            // R2-2: the reads checkout cannot go on without, in the default pool with the order itself
            'checkout: delivery quote' => ['GET', '/api/v1/delivery/quote', 'store=1&lat=41.3&lng=69.2&subtotal=50000', '', $customer, 'fpm_default'],
            'checkout: pickup slots' => ['GET', '/api/v1/stores/1/branches/2/pickup-slots', '', '', $customer, 'fpm_default'],
            'checkout: the cart\'s store (branches, payment methods)' => ['GET', '/api/v1/stores', 'for=checkout', '', $customer, 'fpm_default'],
            'checkout' => ['POST', '/api/v1/orders', '', '', $customer, 'fpm_default'],
            'order cancel' => ['POST', '/api/v1/orders/x1/cancel', '', '', $customer, 'fpm_default'],
            'Mini App sign-in' => ['POST', '/api/v1/auth/telegram', '', '', '', 'fpm_default'],
            'promo check at checkout' => ['POST', '/api/v1/promo/validate', '', '', $customer, 'fpm_default'],
            'own account (cashback balance)' => ['GET', '/api/v1/user', '', '', $customer, 'fpm_default'],
            // Wave P step 2: its own small pool and location (a body cap of 32 KB, limits): never a worker of the
            // default pool, where checkout runs, nor a market or critical one
            'Mini App analytics batch' => ['POST', '/api/v1/analytics/events', '', '', $customer, 'fpm_analytics'],
            'Mini App analytics batch, trailing slash (Laravel routes it the same)' => ['POST', '/api/v1/analytics/events/', '', '', $customer, 'fpm_analytics'],
            'Mini App analytics, another method' => ['GET', '/api/v1/analytics/events', '', '', $customer, 'fpm_analytics'],
            'Mini App analytics batch from a POS app' => ['POST', '/api/v1/analytics/events', '', 'cashier', $till, 'fpm_analytics'],
            'owner panel' => ['GET', '/api/v1/owner/reports/sales', '', '', $customer, 'fpm_default'],
            'admin' => ['GET', '/api/v1/admin/stores', '', '', '', 'fpm_default'],
            'unknown API path' => ['GET', '/api/v1/no-such-thing', '', '', '', 'fpm_default'],
            'a request for /index.php itself' => ['GET', '', '', '', '', 'fpm_default'],
        ];
    }

    #[DataProvider('decisions')]
    public function test_pool_decisions(string $method, string $uri, string $query, string $app, string $auth, string $pool): void
    {
        $this->assertSame($pool, $this->pool($method, $uri, $query, $app, $auth));
    }

    /**
     * R2-2: every read the Mini App cart (customer/miniapp/src/pages/Cart.tsx and the hooks/components it uses) waits
     * for before the order button works is in the default pool, where the order is placed: a market burst answers
     * 503/429 to browsing, never to checkout. Reads the cart can do without (menu prices and stock: the server checks
     * them again; geocoding: the address can be typed) stay in the market pool.
     */
    public function test_checkout_reads_are_not_in_the_market_pool(): void
    {
        $cart = (string) file_get_contents(base_path('../customer/miniapp/src/pages/Cart.tsx'));
        $this->assertStringContainsString("api.get<ApiResponse<StoreListItem[]>>('/stores', { params: { for: 'checkout' } })", $cart,
            'the cart asks for its store (branches, payment methods, order lock) with for=checkout');
        $this->assertStringContainsString("'/delivery/quote'", (string) file_get_contents(base_path('../customer/miniapp/src/hooks/useDeliveryQuote.ts')));
        $this->assertStringContainsString('/pickup-slots', (string) file_get_contents(base_path('../customer/miniapp/src/components/catalog/PickupTimePicker.tsx')));

        foreach ([
            ['GET', '/api/v1/stores', 'for=checkout'],
            ['GET', '/api/v1/delivery/quote', 'store=1&lat=41.3&lng=69.2&subtotal=1'],
            ['GET', '/api/v1/stores/x1/branches/x1/pickup-slots', ''],
            ['POST', '/api/v1/promo/validate', ''],
            ['GET', '/api/v1/user', ''],
            ['POST', '/api/v1/orders', ''],
        ] as [$method, $uri, $query]) {
            $this->assertSame('fpm_default', $this->pool($method, $uri, $query, '', self::CUSTOMER), "$method $uri?$query");
        }
    }

    /**
     * R2-4: a POST cannot be served as a GET route (X-HTTP-Method-Override or _method in the query or the body), so the
     * method nginx routes by is the method Laravel serves.
     */
    public function test_a_post_is_never_served_as_a_get_route(): void
    {
        $this->call('POST', '/api/v1/stores', [], [], [], ['HTTP_X_HTTP_METHOD_OVERRIDE' => 'GET'])->assertStatus(405);
        $this->call('POST', '/api/v1/stores?_method=GET')->assertStatus(405);
        $this->call('POST', '/api/v1/stores', ['_method' => 'GET'])->assertStatus(405);
        $this->postJson('/api/v1/stores', ['_method' => 'HEAD'])->assertStatus(405);
    }

    /**
     * R2-3: the per-client key is the client address plus the Sanctum token id (the digits before `|`), never the
     * header itself: a rotating garbage token does not get a fresh bucket of its own beyond the per-address limits, and
     * no token secret is kept in nginx's shared memory.
     */
    public function test_market_client_key_is_the_token_id_not_the_token(): void
    {
        $this->assertSame('t12', $this->lookup('rizqim_token_id', self::TILL));
        $this->assertSame('t34', $this->lookup('rizqim_token_id', 'Bearer   34|x'));
        $this->assertSame('', $this->lookup('rizqim_token_id', 'Bearer garbage'));
        $this->assertSame('', $this->lookup('rizqim_token_id', 'Bearer 99999999999999999999999|x'));
        $this->assertSame('', $this->lookup('rizqim_token_id', ''));

        $conf = $this->nginxConf();
        $this->assertMatchesRegularExpression('/map \$rizqim_fpm \$rizqim_market_client \{\s*default\s+"";\s*fpm_market\s+\$binary_remote_addr\$rizqim_token_id;\s*\}/', $conf);
        $this->assertMatchesRegularExpression('/map \$rizqim_fpm \$rizqim_market_ip \{\s*default\s+"";\s*fpm_market\s+\$binary_remote_addr;\s*\}/', $conf);
        foreach ($this->maps() as $name => $entries) {
            foreach ($entries as [, $value]) {
                $this->assertStringNotContainsString('$http_authorization', $value, "map \$$name keeps the Authorization header as a value");
            }
        }
    }

    /**
     * R2-1: a market slot is taken only by a request nginx can pass to PHP at once. A market GET that announces a body
     * is answered 400 before any limit is counted, the market location has tight body/send/read timeouts, and the
     * limits exist in the market location only.
     */
    public function test_market_location_refuses_bodies_before_its_limits(): void
    {
        $conf = $this->nginxConf();
        $this->assertSame(1, preg_match('/^    location @rizqim_market \{
(.*?)^    \}/ms', $conf, $m), 'location @rizqim_market');
        $market = $m[1];
        $body = strpos($market, 'if ($rizqim_has_body) {');
        $limits = strpos($market, 'include /etc/nginx/rizqim-pools-limits.conf;');
        $this->assertNotFalse($body);
        $this->assertNotFalse($limits);
        $this->assertMatchesRegularExpression('/if \(\$rizqim_has_body\) \{\s*return 400 /', $market);
        foreach (['client_body_timeout 5s;', 'send_timeout 10s;', 'fastcgi_read_timeout 60s;', 'fastcgi_pass fpm_market;'] as $directive) {
            $this->assertStringContainsString($directive, $market);
        }
        $this->assertSame(1, substr_count($conf, 'include /etc/nginx/rizqim-pools-limits.conf;'), 'the limits are included once, in @rizqim_market');
        $this->assertStringNotContainsString('rizqim-pools-analytics.conf', $market);

        $this->assertSame('0', $this->lookup('rizqim_has_body', ':'));
        $this->assertSame('0', $this->lookup('rizqim_has_body', '0:'));
        $this->assertSame('1', $this->lookup('rizqim_has_body', '100000:'));
        $this->assertSame('1', $this->lookup('rizqim_has_body', ':chunked'));
        $this->assertSame('1', $this->lookup('rizqim_has_body', '0:chunked'));
    }

    /**
     * Wave P step 2 review: POST /api/v1/analytics/events has its own nginx location and php-fpm pool. nginx refuses a
     * body over the controller's cap (32 KB) before any worker is taken or Laravel decodes it, buffers the body itself
     * (a slow client holds no worker), limits per client, address and in flight, and gives up quickly on a busy pool:
     * an analytics batch may be lost under load, a checkout worker is never taken.
     */
    public function test_analytics_batches_have_their_own_location_pool_and_limits(): void
    {
        $conf = $this->nginxConf();
        $this->assertSame(1, preg_match('/^    location ~ (\S+) \{
(.*?)^    \}/ms', $conf, $m), 'location ~ ... of the analytics route');
        [, $regex, $location] = $m;
        // The location and the path-class map name the same paths
        $this->assertContains([$regex, 'analytics'], array_map(fn (array $e) => [ltrim($e[0], '~'), $e[1]], $this->maps()['rizqim_path_class']));
        foreach (['/api/v1/analytics/events', '/api/v1/analytics/events/', '/api/v1/analytics/events//'] as $uri) {
            $this->assertSame(1, preg_match('#'.$regex.'#', $uri), $uri);
        }
        foreach (['/api/v1/analytics/eventsx', '/api/v1/analytics', '/api/v1/analytics/events/1', '/x/api/v1/analytics/events'] as $uri) {
            $this->assertSame(0, preg_match('#'.$regex.'#', $uri), $uri);
        }

        $cap = (int) config('analytics.limits.max_body_bytes');
        $this->assertSame(32768, $cap);
        foreach ([
            'set $rizqim_analytics 1;',
            'client_max_body_size 32k;',
            'client_body_buffer_size 32k;',
            'client_body_timeout 5s;',
            'send_timeout 10s;',
            'include /etc/nginx/rizqim-pools-analytics.conf;',
            'error_page 413 @rizqim_too_large;',
            'error_page 503 @rizqim_busy;',
            'error_page 429 @rizqim_rate_limited;',
            'fastcgi_pass fpm_analytics;',
            'fastcgi_param SCRIPT_FILENAME $document_root/index.php;',
            'fastcgi_param SCRIPT_NAME /index.php;',
            'fastcgi_param REMOTE_ADDR $realip_remote_addr;',
            'fastcgi_connect_timeout 2s;',
            'fastcgi_send_timeout 5s;',
            'fastcgi_read_timeout 10s;',
        ] as $directive) {
            $this->assertStringContainsString($directive, $location);
        }
        $this->assertStringNotContainsString('fastcgi_request_buffering off', $conf, 'nginx reads the whole body before a worker is taken');
        $this->assertStringNotContainsString('rizqim-pools-limits.conf', $location);
        $this->assertSame(1, substr_count($conf, 'include /etc/nginx/rizqim-pools-analytics.conf;'));
        // Before the php location: nginx tries regex locations in order
        $this->assertLessThan(strpos($conf, 'location ~ \.php$ {'), strpos($conf, 'location ~ '.$regex.' {'));

        // nginx's own 413 has the controller's body
        $this->assertSame(1, preg_match('/^    location @rizqim_too_large \{(.*?)^    \}/ms', $conf, $t));
        $this->assertStringContainsString('return 413 \'{"success":false,"message":"So\\\'rov hajmi juda katta.","data":{"reason":"payload_too_large"}}\';', $t[1]);

        // Its limit keys are empty (not counted) everywhere but the analytics location
        foreach (['rizqim_analytics_client', 'rizqim_analytics_ip', 'rizqim_analytics_all'] as $key) {
            $this->assertMatchesRegularExpression('/map \$rizqim_analytics \$'.$key.' \{\s*default\s+"";\s*1\s+[^;\s]+;\s*\}/', $conf);
        }
        $this->assertSame('', $this->lookup('rizqim_analytics_ip', ''));

        // The rendered limits: per client and address (rate) and in flight (connections), 503/429 as the market's
        $rendered = $this->rendered([]);
        $this->assertStringContainsString('limit_conn rizqim_analytics_all 8;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('limit_conn rizqim_analytics_ip 4;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('limit_conn rizqim_analytics_client 2;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('limit_req zone=rizqim_analytics_rate burst=10 nodelay;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('limit_req zone=rizqim_analytics_ip_rate burst=100 nodelay;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('limit_req_status 429;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('limit_conn_status 503;', $rendered['rizqim-pools-analytics.conf']);
        $this->assertStringContainsString('rate=30r/m;', $rendered['rizqim-pools-http.conf']);
        $this->assertStringContainsString('rate=600r/m;', $rendered['rizqim-pools-http.conf']);

        // The pool: small, ondemand, nice'd below the market, a short listen queue
        $fpm = (string) file_get_contents(base_path('docker/php-fpm.conf'));
        $this->assertSame(1, preg_match('/^\[analytics\]\s*$(.*?)(?=^\[|\z)/ms', $fpm, $pool));
        foreach (['pm = ondemand', 'pm.max_children = ${FPM_ANALYTICS_CHILDREN}', 'listen.backlog = ${FPM_ANALYTICS_BACKLOG}', 'process.priority = ${FPM_ANALYTICS_PRIORITY}', 'clear_env = no'] as $line) {
            $this->assertStringContainsString($line, $pool[1]);
        }
        $env = $this->poolsEnv([]);
        $this->assertSame('2', $env['FPM_ANALYTICS_CHILDREN']);
        $this->assertGreaterThan((int) $env['FPM_MARKET_PRIORITY'], (int) $env['FPM_ANALYTICS_PRIORITY']);
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, string> file name => contents of what `pools.sh render` writes
     */
    private function rendered(array $env): array
    {
        $dir = sys_get_temp_dir().'/rizqim-pools-'.bin2hex(random_bytes(4));
        mkdir($dir);
        try {
            $process = $this->poolsSh(['render', $dir], $env);
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $out = [];
            foreach (glob($dir.'/*') ?: [] as $file) {
                $out[basename($file)] = (string) file_get_contents($file);
                unlink($file);
            }

            return $out;
        } finally {
            rmdir($dir);
        }
    }

    /** R2-1, R2-5, R2-6: pools.sh refuses a configuration where one client can fill the market pool */
    public function test_pools_sh_defaults_keep_one_client_below_the_market_cap(): void
    {
        $env = $this->poolsEnv([]);
        $inflight = (int) $env['NGINX_MARKET_MAX_INFLIGHT'];
        $this->assertLessThanOrEqual(intdiv($inflight, 2), (int) $env['NGINX_MARKET_CONN_PER_CLIENT']);
        $this->assertLessThan($inflight, (int) $env['NGINX_MARKET_CONN_PER_IP']);
        $this->assertGreaterThanOrEqual((int) $env['NGINX_MARKET_CONN_PER_CLIENT'], (int) $env['NGINX_MARKET_CONN_PER_IP']);
        $this->assertGreaterThanOrEqual((int) $env['NGINX_MARKET_RATE'], (int) $env['NGINX_MARKET_IP_RATE']);

        // The same rules for the analytics pool
        $inflight = (int) $env['NGINX_ANALYTICS_MAX_INFLIGHT'];
        $this->assertLessThanOrEqual(intdiv($inflight, 2), (int) $env['NGINX_ANALYTICS_CONN_PER_CLIENT']);
        $this->assertLessThan($inflight, (int) $env['NGINX_ANALYTICS_CONN_PER_IP']);
        $this->assertGreaterThanOrEqual((int) $env['NGINX_ANALYTICS_CONN_PER_CLIENT'], (int) $env['NGINX_ANALYTICS_CONN_PER_IP']);
        $this->assertGreaterThanOrEqual((int) $env['NGINX_ANALYTICS_RATE'], (int) $env['NGINX_ANALYTICS_IP_RATE']);
        $this->assertLessThanOrEqual((int) $env['FPM_ANALYTICS_CHILDREN'] + (int) $env['FPM_ANALYTICS_BACKLOG'], $inflight);
    }

    /** @return array<string, array{0: array<string, string>, 1: string}> */
    public static function badPoolConfigs(): array
    {
        return [
            'one client can fill the market pool (the reviewer\'s 10 > 8)' => [['NGINX_MARKET_MAX_INFLIGHT' => '8', 'NGINX_MARKET_CONN_PER_CLIENT' => '10'], 'NGINX_MARKET_CONN_PER_CLIENT'],
            'one client can hold more than half of it' => [['NGINX_MARKET_MAX_INFLIGHT' => '8', 'NGINX_MARKET_CONN_PER_CLIENT' => '5', 'NGINX_MARKET_CONN_PER_IP' => '7'], 'NGINX_MARKET_CONN_PER_CLIENT'],
            'one address can fill the market pool' => [['NGINX_MARKET_MAX_INFLIGHT' => '8', 'NGINX_MARKET_CONN_PER_CLIENT' => '2', 'NGINX_MARKET_CONN_PER_IP' => '8'], 'NGINX_MARKET_CONN_PER_IP'],
            'per address below per client' => [['NGINX_MARKET_MAX_INFLIGHT' => '16', 'NGINX_MARKET_CONN_PER_CLIENT' => '4', 'NGINX_MARKET_CONN_PER_IP' => '3'], 'NGINX_MARKET_CONN_PER_IP'],
            'per address rate below per client' => [['NGINX_MARKET_RATE' => '10', 'NGINX_MARKET_IP_RATE' => '5'], 'NGINX_MARKET_IP_RATE'],
            'in flight beyond the workers and their listen queue' => [['FPM_MARKET_CHILDREN' => '2', 'FPM_MARKET_BACKLOG' => '4', 'NGINX_MARKET_MAX_INFLIGHT' => '16'], 'NGINX_MARKET_MAX_INFLIGHT'],
            'burst 0 (nginx refuses burst=0)' => [['NGINX_MARKET_BURST' => '0'], 'NGINX_MARKET_BURST'],
            'not a number' => [['FPM_MARKET_CHILDREN' => 'x'], 'FPM_MARKET_CHILDREN'],
            'R2-5: more php-fpm workers than PgBouncer server connections' => [['DB_POOLER' => 'pgbouncer', 'PGBOUNCER_POOL_SIZE' => '11'], 'PGBOUNCER_POOL_SIZE'],
            'the analytics workers count against PgBouncer too (4+4+4+2)' => [['DB_POOLER' => 'pgbouncer', 'PGBOUNCER_POOL_SIZE' => '13'], 'PGBOUNCER_POOL_SIZE'],
            'analytics: in flight beyond its workers and their listen queue' => [['FPM_ANALYTICS_CHILDREN' => '1', 'FPM_ANALYTICS_BACKLOG' => '2', 'NGINX_ANALYTICS_MAX_INFLIGHT' => '8'], 'NGINX_ANALYTICS_MAX_INFLIGHT'],
            'analytics: one client can fill its pool' => [['NGINX_ANALYTICS_MAX_INFLIGHT' => '8', 'NGINX_ANALYTICS_CONN_PER_CLIENT' => '5', 'NGINX_ANALYTICS_CONN_PER_IP' => '6'], 'NGINX_ANALYTICS_CONN_PER_CLIENT'],
            'analytics: one address can fill its pool' => [['NGINX_ANALYTICS_MAX_INFLIGHT' => '8', 'NGINX_ANALYTICS_CONN_PER_IP' => '8'], 'NGINX_ANALYTICS_CONN_PER_IP'],
            'analytics: address rate below a client\'s' => [['NGINX_ANALYTICS_RATE' => '60', 'NGINX_ANALYTICS_IP_RATE' => '30'], 'NGINX_ANALYTICS_IP_RATE'],
            'analytics: burst 0' => [['NGINX_ANALYTICS_BURST' => '0'], 'NGINX_ANALYTICS_BURST'],
            'analytics: no workers' => [['FPM_ANALYTICS_CHILDREN' => '0'], 'FPM_ANALYTICS_CHILDREN'],
            'R2-6: TLS to the pooler, which has none' => [['DB_POOLER' => 'pgbouncer', 'DB_SSLMODE' => 'require'], 'DB_SSLMODE'],
        ];
    }

    /** @param array<string, string> $env */
    #[DataProvider('badPoolConfigs')]
    public function test_pools_sh_refuses_bad_configs(array $env, string $named): void
    {
        $process = $this->poolsSh(['env'], $env);
        $this->assertNotSame(0, $process->getExitCode(), 'pools.sh accepted it');
        $this->assertStringContainsString($named, $process->getErrorOutput());
    }

    public function test_pools_sh_accepts_the_pooler_when_the_workers_fit(): void
    {
        // critical + market + default + analytics = 4 + 4 + 4 + 2
        $this->assertSame(0, $this->poolsSh(['env'], ['DB_POOLER' => 'pgbouncer', 'PGBOUNCER_POOL_SIZE' => '14'])->getExitCode());
        $this->assertSame(0, $this->poolsSh(['env'], ['DB_POOLER' => 'direct', 'PGBOUNCER_POOL_SIZE' => '1', 'DB_SSLMODE' => 'require'])->getExitCode());
        $this->assertSame(0, $this->poolsSh(['env'], ['DB_POOLER' => 'pgbouncer', 'DB_SSLMODE' => 'prefer'])->getExitCode());
    }

    /** R2-6: the entrypoint's waits are bounded: a port nobody listens on stops the start with an error */
    public function test_wait_for_gives_up(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($socket);
        $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        $started = microtime(true);
        $process = $this->poolsSh(['wait-for', 'PgBouncer', '127.0.0.1', (string) $port], ['STARTUP_WAIT_SECONDS' => '2']);
        $this->assertNotSame(0, $process->getExitCode());
        $this->assertStringContainsString('PgBouncer', $process->getErrorOutput());
        $this->assertLessThan(10, microtime(true) - $started);

        $server = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertNotFalse($server);
        $open = (int) substr((string) strrchr((string) stream_socket_get_name($server, false), ':'), 1);
        $this->assertSame(0, $this->poolsSh(['wait-for', 'Postgres', '127.0.0.1', (string) $open], ['STARTUP_WAIT_SECONDS' => '2'])->getExitCode());
        fclose($server);
    }

    /**
     * @param  list<string>  $args
     * @param  array<string, string>  $env
     */
    private function poolsSh(array $args, array $env): Process
    {
        if ((new Process(['sh', '-c', 'command -v nc']))->run() !== 0 && ($args[0] ?? '') === 'wait-for') {
            $this->markTestSkipped('nc is not installed');
        }
        // Only the variables under test: the runner's own FPM_* / NGINX_* / DB_* must not leak in
        $clean = [];
        foreach (array_keys(getenv()) as $name) {
            if (preg_match('/^(FPM_|NGINX_|PGBOUNCER_|DB_POOLER$|DB_SSLMODE$|STARTUP_WAIT_SECONDS$)/', $name)) {
                $clean[$name] = false;
            }
        }
        $process = new Process(['sh', base_path('docker/pools.sh'), ...$args], null, [...$clean, ...$env]);
        $process->setTimeout(30);
        $process->run();

        return $process;
    }

    /**
     * @param  array<string, string>  $env
     * @return array<string, string>
     */
    private function poolsEnv(array $env): array
    {
        $process = $this->poolsSh(['env'], $env);
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        preg_match_all('/^export (\w+)=(.*)$/m', $process->getOutput(), $m);

        return array_combine($m[1], $m[2]);
    }

    public function test_only_the_market_and_analytics_pools_are_limited(): void
    {
        $conf = $this->nginxConf();
        foreach (['rizqim_market_client', 'rizqim_market_ip', 'rizqim_market_all'] as $key) {
            $this->assertMatchesRegularExpression(
                '/map \$rizqim_fpm \$'.$key.' \{\s*default\s+"";\s*fpm_market\s+[^;\s]+;\s*\}/',
                $conf,
                "$key must be empty (not counted) for every pool but fpm_market",
            );
        }

        // Each location's include names only its own zones; the zones are keyed by the (empty elsewhere) keys
        $rendered = $this->rendered([]);
        $this->assertSame(['rizqim-pools-analytics.conf', 'rizqim-pools-http.conf', 'rizqim-pools-limits.conf'], array_keys($rendered));
        foreach (['rizqim-pools-limits.conf' => 'rizqim_market_', 'rizqim-pools-analytics.conf' => 'rizqim_analytics_'] as $file => $prefix) {
            preg_match_all('/^(limit_req|limit_conn)\s+(zone=)?(\S+)/m', $rendered[$file], $m);
            $this->assertNotEmpty($m[3]);
            foreach ($m[3] as $zone) {
                $this->assertStringStartsWith($prefix, $zone, $file);
            }
        }
        preg_match_all('/^limit_(req|conn)_zone\s+(\S+)/m', $rendered['rizqim-pools-http.conf'], $m);
        $this->assertSame([
            '$rizqim_market_client', '$rizqim_market_ip', '$rizqim_market_client', '$rizqim_market_ip', '$rizqim_market_all',
            '$rizqim_analytics_client', '$rizqim_analytics_ip', '$rizqim_analytics_client', '$rizqim_analytics_ip', '$rizqim_analytics_all',
        ], $m[2]);
    }

    public function test_nginx_upstreams_match_the_php_fpm_pools(): void
    {
        preg_match_all('/upstream (fpm_\w+)\s*\{\s*server ([\d.:]+);/', $this->nginxConf(), $m);
        $upstreams = array_combine($m[1], $m[2]);

        $fpm = (string) file_get_contents(base_path('docker/php-fpm.conf'));
        preg_match_all('/^\[(\w+)\]\s*$/m', $fpm, $names);
        $this->assertSame(['global', 'critical', 'market', 'default', 'analytics'], $names[1]);
        foreach (['critical', 'market', 'default', 'analytics'] as $pool) {
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

    /** The maps of nginx.conf in the order nginx evaluates them for one request */
    private function pool(string $method, string $uri, string $query = '', string $app = '', string $auth = ''): string
    {
        $class = $this->pathClass($uri);
        $queryClass = $this->lookup('rizqim_query_class', $class.'?'.$query);
        $device = $this->lookup('rizqim_pos_device', $app.':'.$auth);

        return $this->lookup('rizqim_fpm', $class.':'.$method.':'.$queryClass.':'.$device);
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
            if (! str_starts_with($key, '~')) {
                continue;
            }
            $caseless = str_starts_with($key, '~*');
            $regex = '#'.str_replace('#', '\#', substr($key, $caseless ? 2 : 1)).'#'.($caseless ? 'i' : '');
            if (preg_match($regex, $value, $captures)) {
                // A named capture used in the value (`t$rizqim_tid`); any other variable stays as written
                return (string) preg_replace_callback('/\$(\w+)/', fn (array $v) => $captures[$v[1]] ?? $v[0], $result);
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
