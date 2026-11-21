<?php

namespace Tests\Feature\PosReleases;

use App\Auth\DeviceTokenGuard;
use App\Domain\Pos\Releases\AppVersion;
use App\Domain\Pos\Releases\PosAppReleases;
use App\Http\Controllers\Api\V1\Device\PosReleaseManifestController;
use App\Http\Controllers\Api\V1\Device\TerminalController;
use App\Models\PosShift;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ThrottleRequestsWithRedis;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\PosBilling\PosBillingFixtures;
use Tests\TestCase;

/**
 * Wave M5 S5-a, owner rule: NOTHING about app versions can stop a sale. Shift open/close, orders, bill, payment,
 * receipts, refunds, the offline queue and its sync are never gated by version. This inventory proves it on the route
 * table and on the code that reads the registry:
 *
 *  - no route, anywhere, carries a middleware that looks at versions (by name or by class);
 *  - the only routes whose action reaches the registry are GET /terminal (a notice), POST /devices/pair (setup) and
 *    the public manifest;
 *  - the registry classes are used only by the places named here (a new caller must be added here on purpose, so a
 *    review sees it);
 *  - the device guard, which every pos/* route goes through, only RECORDS the header and never refuses over it.
 *
 * Review 3 M5: the middleware a route really runs is what the router resolves (its groups, `api` included, expanded)
 * plus the kernel's global middleware, each judged by its class name AND by its source (the header name, the stored
 * app_version, the registry); and a behavioural sweep sends an app far below a minimum that already applies to every
 * till route and walks a whole sale (shift, order, bill, payment, refund, receipts, the offline sync): never a 426.
 */
class PosReleaseRouteInventoryTest extends TestCase
{
    use PosBillingFixtures;
    use PosReleaseFixtures;
    use RefreshDatabase;

    /** A version below any minimum, as an old till would send it. */
    private const ANCIENT = '0.0.1+0';

    /** What a version check would read: the header, the stored version, the registry. */
    private const VERSION_SOURCE = '/X-Rizqim-App-Version|AppVersion\b|app_version|PosAppReleases?\b|PosAppOutdatedNotice|app_outdated|update_required/';

    /** Routes whose controller action may read the registry or the version. */
    private const MAY_READ_VERSIONS = [
        'GET api/v1/terminal' => TerminalController::class . '@show',
        'POST api/v1/devices/pair' => TerminalController::class . '@pair',
        'GET api/v1/pos/releases' => PosReleaseManifestController::class . '@index',
    ];

    /**
     * @return list<LaravelRoute>
     */
    private function routes(): array
    {
        return array_values(Route::getRoutes()->getRoutes());
    }

    public function test_no_middleware_on_any_route_checks_the_app_version(): void
    {
        $problems = [];
        foreach ($this->routes() as $route) {
            foreach ($this->middlewareOf($route) as $name) {
                if (($problem = $this->middlewareProblem($name)) !== null) {
                    $problems[] = "{$route->uri()}: middleware {$name} {$problem}";
                }
            }
        }
        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_no_pos_money_shift_or_sync_route_has_a_version_check(): void
    {
        $pos = array_filter($this->routes(), fn (LaravelRoute $route) => str_starts_with($route->uri(), 'api/v1/pos/') || str_starts_with($route->uri(), 'api/v1/staff/'));
        // The inventory must really see the till's routes
        $this->assertGreaterThan(100, count($pos));
        $this->assertNotEmpty(array_filter($pos, fn (LaravelRoute $r) => str_contains($r->uri(), 'pos/sync')));
        $this->assertNotEmpty(array_filter($pos, fn (LaravelRoute $r) => str_contains($r->uri(), 'shifts')));
        $this->assertNotEmpty(array_filter($pos, fn (LaravelRoute $r) => str_contains($r->uri(), 'pay')));

        $problems = [];
        foreach ($pos as $route) {
            $key = implode('|', array_diff($route->methods(), ['HEAD'])) . ' ' . $route->uri();
            $action = $route->getActionName();
            // Only the public manifest may live under pos/ and read versions
            if (isset(self::MAY_READ_VERSIONS[$key])) {
                continue;
            }
            if ($route->getControllerClass() !== null && $this->sourceMentionsVersionGate((string) $route->getControllerClass(), self::VERSION_SOURCE)) {
                $problems[] = "{$key} ({$action}): its controller reads the release registry or the app version";
            }
            foreach ($this->middlewareOf($route) as $name) {
                if (($problem = $this->middlewareProblem($name)) !== null) {
                    $problems[] = "{$key}: middleware {$name} {$problem}";
                }
            }
        }
        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_only_the_known_routes_reach_the_registry(): void
    {
        $found = [];
        foreach ($this->routes() as $route) {
            $controller = $route->getControllerClass();
            if ($controller === null || !$this->sourceMentionsVersionGate($controller)) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $found["{$method} {$route->uri()}"] = $route->getActionName();
            }
        }

        // Only controllers named in MAY_READ_VERSIONS (plus the control centre's own registry endpoints) mention it
        foreach ($found as $key => $action) {
            $allowed = isset(self::MAY_READ_VERSIONS[$key]) || str_contains($key, 'api/v1/admin/pos/');
            $this->assertTrue($allowed || $this->isSameControllerAsAllowed($action), "{$key} ({$action}) reads the release registry but is not an allowed route");
        }
        foreach (self::MAY_READ_VERSIONS as $key => $action) {
            $this->assertArrayHasKey($key, $found, "{$key} should be where the registry is read");
        }
    }

    /**
     * The controller of an allowed route (TerminalController) has other actions; those must not gate anything either:
     * the registry is reached from `show` and `pair` only.
     */
    private function isSameControllerAsAllowed(string $action): bool
    {
        [$class] = explode('@', $action);
        foreach (self::MAY_READ_VERSIONS as $allowed) {
            if (str_starts_with($allowed, $class . '@')) {
                return true;
            }
        }

        return false;
    }

    public function test_the_registry_is_read_only_by_the_named_places(): void
    {
        $allowed = [
            'app/Domain/Pos/Releases/',                                  // the registry itself
            'app/Http/Controllers/Api/V1/Device/TerminalController.php',     // show (notice) and pair (setup)
            'app/Http/Controllers/Api/V1/Device/PosReleaseManifestController.php', // the public manifest
            'app/Http/Controllers/Api/V1/Admin/AdminPosReleaseController.php',     // staff
            'app/Models/PosAppRelease.php',                              // the model
            'app/Models/PosAppRelease.php',                                  // the model
            'app/Services/Admin/Pos/PosDevices.php',                     // the control centre's "latest"
            'app/Auth/DeviceTokenGuard.php',                             // records the header only (AppVersion::HEADER)
            'routes/console.php',                                        // the owner notice schedule
        ];
        $root = base_path();
        $offenders = [];
        foreach (['app', 'routes'] as $dir) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$root}/{$dir}", \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $text = (string) file_get_contents($file->getPathname());
                if (!preg_match('/PosAppReleases|PosAppRelease\b|PosAppOutdatedNotice|Pos\\\\Releases\\\\|X-Rizqim-App-Version/i', $text)) {
                    continue;
                }
                $ok = false;
                foreach ($allowed as $prefix) {
                    $ok = $ok || str_starts_with($relative, $prefix);
                }
                if (!$ok) {
                    $offenders[] = $relative;
                }
            }
        }
        $this->assertSame([], $offenders, "A new reader of the release registry: add it to this list on purpose (it must never gate a sale):\n" . implode("\n", $offenders));
    }

    public function test_the_device_guard_only_records_the_version_header(): void
    {
        $source = (string) file_get_contents((new \ReflectionClass(DeviceTokenGuard::class))->getFileName());

        // The guard names the header parser and nothing of the registry or a refusal over versions
        $this->assertStringContainsString('AppVersion::HEADER', $source);
        $this->assertDoesNotMatchRegularExpression('/PosAppReleases|426|app_outdated|update_required|abort\(/', $source);
        // The only `return null` paths are the authentication failures that existed before (no token, bad token,
        // revoked device): none sits after the version is read
        $afterHeader = substr($source, (int) strpos($source, 'AppVersion::HEADER'));
        $this->assertStringNotContainsString('return null', $afterHeader);
        $this->assertSame('X-Rizqim-App-Version', AppVersion::HEADER);
        $this->assertTrue(class_exists(PosAppReleases::class));
    }

    private function sourceMentionsVersionGate(string $class, string $pattern = '/PosAppReleases|PosAppRelease\b|AppVersion::|app_outdated|X-Rizqim-App-Version/i'): bool
    {
        if (!class_exists($class)) {
            return false;
        }
        $file = (new \ReflectionClass($class))->getFileName();

        return $file !== false && preg_match($pattern, (string) file_get_contents($file)) === 1;
    }

    /**
     * Everything that runs for a request to $route: the kernel's global middleware, then the route's own as the router
     * resolves it (groups such as `api` expanded, aliases turned into classes, parameters kept after `:`).
     *
     * @return list<string>
     */
    private function middlewareOf(LaravelRoute $route): array
    {
        $names = [];
        foreach ([...app(HttpKernel::class)->getGlobalMiddleware(), ...app('router')->gatherRouteMiddleware($route)] as $middleware) {
            $names[] = is_string($middleware) ? $middleware : get_class($middleware);
        }

        return $names;
    }

    /**
     * Why a middleware looks like a version check (its class name, or its source reading the header, the stored
     * version or the registry), or null. A throttle's parameter is not a check (`throttle:pos-releases`).
     */
    private function middlewareProblem(string $name): ?string
    {
        $class = explode(':', $name, 2)[0];
        if (preg_match('/version|release|outdated|minimum/i', $class)) {
            return '(its name)';
        }
        if (class_exists($class) && $this->sourceMentionsVersionGate($class, self::VERSION_SOURCE)) {
            return '(its source reads the app version or the registry)';
        }

        return null;
    }

    public function test_the_middleware_inventory_sees_the_api_group_and_the_global_middleware(): void
    {
        $route = collect($this->routes())->first(fn (LaravelRoute $r) => $r->uri() === 'api/v1/pos/sync');
        $this->assertNotNull($route);
        $names = $this->middlewareOf($route);
        // From the `api` group (not on the route itself) and from the kernel's global stack
        $this->assertContains(\Illuminate\Routing\Middleware\SubstituteBindings::class, $names);
        $this->assertNotEmpty(array_intersect(app(HttpKernel::class)->getGlobalMiddleware(), $names));
        // And it judges by the source, not only the name: the device guard's file reads the header
        $this->assertTrue($this->sourceMentionsVersionGate(DeviceTokenGuard::class, self::VERSION_SOURCE));
    }

    /**
     * Behavioural: a minimum that already applies (past `minimum_from`) and an app far below it. Every till-facing
     * route, called as the till (its own token) and as staff on it (a PIN session), answers anything but 426. The
     * bodies are empty and the ids made up, so most answers are 4xx: the point is that no route refuses over the
     * version. Each request runs in its own savepoint, so one failure never hides the next.
     */
    public function test_no_till_route_ever_answers_426_to_an_app_below_an_applied_minimum(): void
    {
        $this->setUpBlockingMinimum();
        $this->withoutMiddleware([ThrottleRequests::class, ThrottleRequestsWithRedis::class]);
        $sessions = ['till' => $this->till['token'], 'staff' => $this->pinSession($this->till, $this->owner, '7316')];

        $refused = [];
        $statuses = [];
        $called = 0;
        foreach ($this->routes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/v1/') || str_starts_with($uri, 'api/v1/admin/') || $uri === 'api/v1/devices/pair') {
                continue;
            }
            $path = '/' . preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use ($route) {
                $where = $route->wheres[$m[1]] ?? null;
                $id = (string) Str::uuid();

                return $where !== null && !preg_match('#^' . $where . '$#', $id) ? '1' : $id;
            }, substr($uri, strlen('api/v1/')));
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                foreach ($sessions as $as => $token) {
                    DB::beginTransaction();
                    try {
                        $status = $this->asTill($token, $method, $path)->status();
                    } finally {
                        DB::rollBack();
                    }
                    $called++;
                    $statuses[$status] = ($statuses[$status] ?? 0) + 1;
                    if ($status === 426) {
                        $refused[] = "{$method} {$uri} as {$as}";
                    }
                }
            }
        }

        $this->assertSame([], $refused, "Refused over the app version:\n" . implode("\n", $refused));
        $this->assertGreaterThan(400, $called);
        // The sweep really reached the actions (not only 401/404 from made-up paths and tokens)
        $this->assertGreaterThan(40, $statuses[200] ?? 0);
    }

    /**
     * Behavioural: a whole sale by an app below an applied minimum: shift open, order, bill, payment, receipt,
     * refund, the shift's reports and close, the offline sync. Every step succeeds.
     */
    public function test_a_whole_sale_works_for_an_app_below_an_applied_minimum(): void
    {
        $this->setUpBlockingMinimum();
        // One staff session per till: the manager (who may also approve the refund and close the shift) sells
        $manager = $this->pinSession($this->till, $this->manager, self::MANAGER_PIN);

        $this->assertSame('update_required', $this->asTill($this->till['token'], 'GET', '/terminal')->assertOk()->json('data.app_release.state'));

        $shift = $this->asTill($manager, 'POST', '/pos/shifts', ['opening_cash' => '100000'])->assertCreated()->json('data.uuid');
        $this->asTill($manager, 'GET', '/pos/shifts/current')->assertOk();
        $order = $this->posOrder([['Osh', '1', '50000']]);
        $bill = $this->asTill($manager, 'POST', "/pos/orders/{$order->uuid}/bills", ['version' => (int) $order->version, 'mode' => 'single'])
            ->assertCreated()->json('data.bills.0.uuid');
        $paid = $this->asTill($manager, 'POST', "/pos/bills/{$bill}/payments", ['tenders' => [['tender' => 'cash', 'amount' => '50000', 'tendered_amount' => '50000']]])
            ->assertOk()->assertJsonPath('data.bill.status', 'paid');
        $payment = (string) ($paid->json('data.payments.0.uuid') ?? \App\Models\Payment::query()->latest('id')->value('uuid'));
        $this->assertNotSame('', $payment);
        $this->assertNotSame(426, $this->asTill($manager, 'GET', "/pos/bills/{$bill}")->status());
        $this->asTill($manager, 'POST', '/pos/refunds', ['payment_uuid' => $payment, 'amount' => '1000', 'reason' => 'Xato', 'shift_uuid' => $shift])->assertCreated();
        $this->assertNotSame(426, $this->asTill($manager, 'GET', "/pos/shifts/{$shift}/x-report")->status());
        $this->asTill($this->till['token'], 'GET', '/pos/sync/status')->assertOk();
        $this->asTill($this->till['token'], 'POST', '/pos/sync', ['sent_at' => now()->toJSON(), 'operations' => []])->assertSuccessful();
        $close = $this->asTill($manager, 'POST', "/pos/shifts/{$shift}/close", ['counted_breakdown' => ['50000' => 2, '10000' => 4, '1000' => 9]]);
        $this->assertTrue($close->isOk(), 'shift close: ' . $close->status() . ' ' . $close->getContent());
        $this->assertNotNull(PosShift::where('uuid', $shift)->value('closed_at'));
    }

    /** A store with a till, and a cashier minimum that applies since yesterday. */
    private function setUpBlockingMinimum(): void
    {
        $this->setUpPosBilling();
        $this->release('1.2.0', ['is_minimum' => true, 'minimum_from' => Carbon::now()->subDay()]);
        $this->till['device']->forceFill(['app' => 'cashier', 'platform' => 'android', 'app_version' => self::ANCIENT])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function asTill(string $token, string $method, string $path, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($token)->withHeaders([
            'X-Rizqim-App-Version' => self::ANCIENT,
            'X-Rizqim-App' => 'cashier',
            'Idempotency-Key' => (string) Str::uuid(),
        ])->json($method, '/api/v1' . $path, $data);
    }
}
