<?php
// Profiling front controller for the scale-test kit (PROF=1 ./server.sh start, fpm mode only). Loaded as php-fpm's
// auto_prepend_file (prof.ini), it serves the request itself the way public/index.php does and then exits, recording
// per request (one JSON line in $PROF_LOG): CPU time (getrusage user+sys) and wall time of each phase (autoload,
// bootstrap/app.php, each bootstrapper, each service provider's register and boot, global middleware up to the route
// match, the route's middleware + controller, send, terminate), the SQL statements (text, ms, phase) and the classes
// autoloaded. PROF=cpu records only the request's total CPU/wall and runs the real public/index.php (no instrumentation
// overhead): use it for the before/after totals, PROF=1 for the attribution. Never loaded by the image or the tests.
// The front controller is not the real one only in this: bootstrap/app.php is evaluated with Application::configure
// replaced by a subclass that times providers (eval, so that one small file is compiled per request: ~0.1 ms).

if (PHP_SAPI !== 'fpm-fcgi') { return; }
$__p = ['t0' => hrtime(true), 'r0' => getrusage()];
function __prof_cpu(): float { $r = getrusage(); return $r['ru_utime.tv_sec'] * 1e3 + $r['ru_utime.tv_usec'] / 1e3 + $r['ru_stime.tv_sec'] * 1e3 + $r['ru_stime.tv_usec'] / 1e3; }
$__p['c0'] = __prof_cpu();
$__mode = getenv('PROF') ?: '1';
$__log = getenv('PROF_LOG') ?: '/tmp/prof.jsonl';
$__base = getenv('BACKEND') ?: dirname(__DIR__, 4).'/backend';
// The path plus the names of the query parameters (GET /catalog?lat,lng and ?store_id are different requests)
parse_str($_SERVER['QUERY_STRING'] ?? '', $__q); ksort($__q);
$__uri = $_SERVER['REQUEST_METHOD'].' '.strtok($_SERVER['REQUEST_URI'] ?? '', '?').($__q ? '?'.implode(',', array_keys($__q)) : '');

if ($__mode === 'cpu') {
    register_shutdown_function(function () use ($__p, $__log, $__uri) {
        $line = ['uri' => $__uri, 'status' => http_response_code(), 'cpu' => round(__prof_cpu() - $__p['c0'], 3),
            'wall' => round((hrtime(true) - $__p['t0']) / 1e6, 3), 'files' => count(get_included_files()), 'mem' => memory_get_peak_usage()];
        // PROF_CLASSES=<file>: append the user classes this request declared (input for a preload list)
        if ($cf = getenv('PROF_CLASSES')) {
            $names = array_filter(array_merge(get_declared_classes(), get_declared_interfaces(), get_declared_traits()),
                fn ($c) => !(new ReflectionClass($c))->isInternal() && !str_starts_with($c, 'ProfApp'));
            file_put_contents($cf, implode("\n", $names)."\n", FILE_APPEND | LOCK_EX);
        }
        if (getenv('PROF_OC') && function_exists('opcache_get_status') && ($o = opcache_get_status(false))) {
            $line['oc'] = ['full' => $o['cache_full'], 'restarts' => $o['opcache_statistics']['oom_restarts'] + $o['opcache_statistics']['hash_restarts'],
                'scripts' => $o['opcache_statistics']['num_cached_scripts'], 'misses' => $o['opcache_statistics']['misses'],
                'mem_used' => $o['memory_usage']['used_memory'], 'mem_free' => $o['memory_usage']['free_memory'], 'wasted' => $o['memory_usage']['wasted_memory'],
                'interned_used' => $o['interned_strings_usage']['used_memory'] ?? null, 'interned_free' => $o['interned_strings_usage']['free_memory'] ?? null,
                'jit' => $o['jit']['on'] ?? null, 'ini' => [ini_get('opcache.memory_consumption'), ini_get('opcache.interned_strings_buffer'), ini_get('opcache.max_accelerated_files'), ini_get('opcache.validate_timestamps')]];
        }
        file_put_contents($__log, json_encode($line)."\n", FILE_APPEND | LOCK_EX);
    });
    return; // public/index.php runs as usual
}

$GLOBALS['__prof'] = ['ph' => [], 'prov' => [], 'sql' => [], 'al' => 0.0, 'al_n' => 0, 'phase' => 'boot'];
function __prof_mark(string $name): void {
    static $lastT = null, $lastC = null;
    $t = hrtime(true); $c = __prof_cpu();
    if ($lastT === null) { $lastT = $GLOBALS['__prof_t0']; $lastC = $GLOBALS['__prof_c0']; }
    $GLOBALS['__prof']['ph'][] = [$name, round(($t - $lastT) / 1e6, 3), round($c - $lastC, 3)];
    $lastT = $t; $lastC = $c;
}
$GLOBALS['__prof_t0'] = $__p['t0']; $GLOBALS['__prof_c0'] = $__p['c0'];

define('LARAVEL_START', microtime(true));
require $__base.'/vendor/autoload.php';
__prof_mark('autoload_init');

// Time every class autoload (top level only: nested loads are inside their parent's time)
$__loaders = spl_autoload_functions();
foreach ($__loaders as $l) { spl_autoload_unregister($l); }
spl_autoload_register(function ($cls) use ($__loaders) {
    static $depth = 0;
    $t = $depth === 0 ? hrtime(true) : 0; $depth++;
    try { foreach ($__loaders as $l) { $l($cls); if (class_exists($cls, false) || interface_exists($cls, false) || trait_exists($cls, false) || enum_exists($cls, false)) break; } }
    finally { $depth--; $GLOBALS['__prof']['al_n']++; if (!class_exists($cls, false) && !interface_exists($cls, false) && !trait_exists($cls, false) && !enum_exists($cls, false)) { $GLOBALS['__prof']['miss'][] = $cls; } if ($depth === 0) $GLOBALS['__prof']['al'] += (hrtime(true) - $t) / 1e6; }
});

final class ProfApp extends Illuminate\Foundation\Application
{
    public function register($provider, $force = false)
    {
        $t = hrtime(true);
        $r = parent::register($provider, $force);
        $n = is_string($provider) ? $provider : get_class($provider);
        $GLOBALS['__prof']['prov'][$n]['reg'] = round(($GLOBALS['__prof']['prov'][$n]['reg'] ?? 0) + (hrtime(true) - $t) / 1e6, 3);
        return $r;
    }

    protected function bootProvider(Illuminate\Support\ServiceProvider $provider)
    {
        $t = hrtime(true);
        parent::bootProvider($provider);
        $n = get_class($provider);
        $GLOBALS['__prof']['prov'][$n]['boot'] = round(($GLOBALS['__prof']['prov'][$n]['boot'] ?? 0) + (hrtime(true) - $t) / 1e6, 3);
    }
}

$__src = file_get_contents($__base.'/bootstrap/app.php');
$__src = str_replace(['Application::configure(', '__DIR__'], ['\\ProfApp::configure(', var_export($__base.'/bootstrap', true)], $__src);
$app = eval('?>'.$__src);
__prof_mark('bootstrap_app_php');

$app->beforeBootstrapping(Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class, fn () => __prof_mark('kernel_make'));
foreach ([Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class, Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    Illuminate\Foundation\Bootstrap\HandleExceptions::class, Illuminate\Foundation\Bootstrap\RegisterFacades::class,
    Illuminate\Foundation\Bootstrap\RegisterProviders::class, Illuminate\Foundation\Bootstrap\BootProviders::class] as $__b) {
    $app->afterBootstrapping($__b, function ($app) use ($__b) {
        __prof_mark('bs_'.substr(strrchr($__b, '\\'), 1));
        if ($__b === Illuminate\Foundation\Bootstrap\BootProviders::class) {
            $GLOBALS['__prof']['phase'] = 'global_mw';
            // Timeline (PROF_TL=1): every object the container resolves and every statement, with its time since the start
            if (getenv('PROF_TL')) {
                $app->afterResolving(function ($obj) {
                    $GLOBALS['__prof']['tl'][] = [round((hrtime(true) - $GLOBALS['__prof_t0']) / 1e6, 3), round(__prof_cpu() - $GLOBALS['__prof_c0'], 3), 'R '.(is_object($obj) ? get_class($obj) : gettype($obj))];
                });
            }
            $app['events']->listen(Illuminate\Database\Events\QueryExecuted::class, function ($e) {
                if (getenv('PROF_TL')) { $GLOBALS['__prof']['tl'][] = [round((hrtime(true) - $GLOBALS['__prof_t0']) / 1e6, 3), round(__prof_cpu() - $GLOBALS['__prof_c0'], 3), 'Q '.round($e->time, 2).' '.substr($e->sql, 0, 90)]; }
                $GLOBALS['__prof']['sql'][] = [$GLOBALS['__prof']['phase'], round($e->time, 3), substr(preg_replace('/\s+/', ' ', $e->sql), 0, 160)];
            });
            $app['events']->listen(Illuminate\Routing\Events\RouteMatched::class, function () {
                __prof_mark('global_middleware'); $GLOBALS['__prof']['phase'] = 'route';
            });
            $app['events']->listen(Illuminate\Foundation\Http\Events\RequestHandled::class, function () {
                __prof_mark('route_mw_controller'); $GLOBALS['__prof']['phase'] = 'after';
            });
        }
    });
}
// Statements run while providers boot (before the listener above exists) are not seen; the kit's pg_stat_statements
// totals catch them. In practice none: config is cached and nothing queries at boot.

$__kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$__req = Illuminate\Http\Request::capture();
$__resp = $__kernel->handle($__req);
$__resp->send();
__prof_mark('send');
$GLOBALS['__prof']['phase'] = 'terminate';
$__kernel->terminate($__req, $__resp);
__prof_mark('terminate');

$__P = $GLOBALS['__prof'];
$__line = ['uri' => $__uri, 'route' => optional($__req->route())->uri().(str_contains($__uri, '?') ? strstr($__uri, '?') : ''), 'status' => $__resp->getStatusCode(),
    'cpu' => round(__prof_cpu() - $__p['c0'], 3), 'wall' => round((hrtime(true) - $__p['t0']) / 1e6, 3),
    'ph' => $__P['ph'], 'autoload_ms' => round($__P['al'], 3), 'autoload_n' => $__P['al_n'], 'autoload_miss' => $__P['miss'] ?? [], 'files' => count(get_included_files()),
    'prov' => $__P['prov'], 'sql' => $__P['sql'], 'tl' => $__P['tl'] ?? null, 'mem' => memory_get_peak_usage()];
file_put_contents($__log, json_encode($__line)."\n", FILE_APPEND | LOCK_EX);
exit;
