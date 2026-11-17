<?php

/*
 * OPcache preload for php-fpm (docker/php.ini: opcache.preload). Runs once when the php-fpm master starts, as
 * opcache.preload_user (www-data), and declares the framework and app classes a typical request uses (the list in
 * preload-classes.txt), so no request has to autoload, link and copy them again: measured about 9 % less PHP CPU per
 * request on the scale-test kit (docs/scale/PHP_COST_M2.md). The list is generated from real requests by
 * scripts/scale-test/php/prof/preload-list.php; a class that no longer exists is skipped, a new class that is not on
 * the list is simply autoloaded as before, so a stale list only costs speed, never correctness.
 *
 * It never stops php-fpm from starting: every failure is logged and skipped. Off when PHP_PRELOAD=0, and whenever
 * opcache.validate_timestamps is on (source mounted for development: a preloaded class can not be reloaded).
 *
 * Composer's "files" (helpers) are deliberately not included here: some (ramsey/uuid, sentry) declare functions
 * without a function_exists() guard, and vendor/autoload.php includes them again on every request.
 */

(static function (): void {
    if (getenv('PHP_PRELOAD') === '0' || filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOL)) {
        return;
    }

    $base = dirname(__DIR__);
    $vendor = $base . '/vendor/composer';
    $list = __DIR__ . '/preload-classes.txt';
    if (!is_file($vendor . '/ClassLoader.php') || !is_file($list)) {
        error_log('preload: vendor/composer or docker/preload-classes.txt missing, nothing preloaded');

        return;
    }

    require_once $vendor . '/ClassLoader.php';
    $loader = new \Composer\Autoload\ClassLoader(dirname($vendor));
    $loader->addClassMap(require $vendor . '/autoload_classmap.php');
    foreach (require $vendor . '/autoload_psr4.php' as $prefix => $dirs) {
        $loader->setPsr4($prefix, $dirs);
    }
    $loader->register(true);

    $done = 0;
    $skipped = 0;
    foreach (file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $name) {
        $name = trim($name);
        if ($name === '' || $name[0] === '#') {
            continue;
        }
        try {
            if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
                $done++;
            } else {
                $skipped++;
            }
        } catch (\Throwable $e) {
            $skipped++;
            error_log('preload: skipped ' . $name . ': ' . $e->getMessage());
        }
    }

    $loader->unregister();
    error_log("preload: {$done} classes preloaded, {$skipped} skipped");
})();
