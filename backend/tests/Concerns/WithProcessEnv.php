<?php

namespace Tests\Concerns;

/**
 * Sets or unsets process environment variables (getenv, $_ENV, $_SERVER: what Illuminate\Support\Env reads) for the
 * length of a callback, then puts them back.
 */
trait WithProcessEnv
{
    /**
     * @param  array<string, string|null>  $vars  null = unset
     */
    protected function withEnv(array $vars, callable $callback): mixed
    {
        $saved = [];
        foreach ($vars as $name => $value) {
            $saved[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            if ($value === null) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            } else {
                putenv("{$name}={$value}");
                $_ENV[$name] = $_SERVER[$name] = $value;
            }
        }
        try {
            return $callback();
        } finally {
            foreach ($saved as $name => [$env, $envConst, $server]) {
                $env === false ? putenv($name) : putenv("{$name}={$env}");
                if ($envConst === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $envConst;
                }
                if ($server === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $server;
                }
            }
        }
    }
}
