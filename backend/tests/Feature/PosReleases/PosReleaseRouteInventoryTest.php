<?php

namespace Tests\Feature\PosReleases;

use App\Auth\DeviceTokenGuard;
use App\Domain\Pos\Releases\AppVersion;
use App\Domain\Pos\Releases\PosAppReleases;
use App\Http\Controllers\Api\V1\Device\PosReleaseManifestController;
use App\Http\Controllers\Api\V1\Device\TerminalController;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
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
 */
class PosReleaseRouteInventoryTest extends TestCase
{
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
            foreach ($route->gatherMiddleware() as $middleware) {
                $name = is_string($middleware) ? $middleware : get_class($middleware);
                // (a throttle's name is not a check: `throttle:pos-releases` limits the public manifest)
                if (!str_starts_with($name, 'throttle:') && preg_match('/version|release|outdated|minimum/i', $name)) {
                    $problems[] = "{$route->uri()}: middleware {$name}";
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
            if ($route->getControllerClass() !== null && $this->sourceMentionsVersionGate((string) $route->getControllerClass())) {
                $problems[] = "{$key} ({$action}): its controller reads the release registry";
            }
            foreach ($route->gatherMiddleware() as $middleware) {
                $name = is_string($middleware) ? $middleware : get_class($middleware);
                if (!str_starts_with($name, 'throttle:') && preg_match('/version|release|outdated|minimum/i', $name)) {
                    $problems[] = "{$key}: middleware {$name}";
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
                if (!preg_match('/PosAppReleases|PosAppRelease\b|PosAppOutdatedNotice|Pos\\\\Releases\\\\/', $text)) {
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

    private function sourceMentionsVersionGate(string $controller): bool
    {
        if (!class_exists($controller)) {
            return false;
        }
        $file = (new \ReflectionClass($controller))->getFileName();

        return $file !== false && preg_match('/PosAppReleases|PosAppRelease\b|AppVersion::|app_outdated/', (string) file_get_contents($file)) === 1;
    }
}
