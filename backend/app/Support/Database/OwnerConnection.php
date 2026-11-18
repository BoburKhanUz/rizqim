<?php

namespace App\Support\Database;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

/**
 * M4 (docs/scale/M4_DESIGN.md section 5): the database role that owns the schema objects (`rizqim_owner`), apart from
 * the DML-only role the application runs as (`rizqim_app`, DB_USERNAME).
 *
 * The connection `pgsql_owner` (config/database.php) carries no credentials: `config:cache` would write them into
 * bootstrap/cache/config.php, which every php-fpm worker reads. They come from the process environment
 * (DB_OWNER_USERNAME / DB_OWNER_PASSWORD) when a schema-changing command starts, which then runs with the owner
 * connection as its default connection: the migrations (listen()) and `db:partitions:ensure` / `:archive` (run()).
 * DB_OWNER_USERNAME unset = one role, as before M4: nothing is switched and the commands use DB_USERNAME.
 */
final class OwnerConnection
{
    public const NAME = 'pgsql_owner';

    /** The migration commands: they run on the owner connection when it is configured (listen()). */
    public const COMMANDS = ['migrate', 'migrate:fresh', 'migrate:install', 'migrate:refresh', 'migrate:reset', 'migrate:rollback', 'db:wipe'];

    public static function username(): ?string
    {
        $username = trim((string) Env::get('DB_OWNER_USERNAME', ''));

        return $username === '' ? null : $username;
    }

    public static function configured(): bool
    {
        return self::username() !== null;
    }

    /**
     * Puts the owner credentials from the process environment into the connection's runtime config (never cached).
     * False when DB_OWNER_USERNAME is not set.
     */
    public static function prepare(): bool
    {
        $username = self::username();
        if ($username === null) {
            return false;
        }
        $current = config('database.connections.' . self::NAME);
        $password = (string) Env::get('DB_OWNER_PASSWORD', '');
        if (($current['username'] ?? null) !== $username || ($current['password'] ?? null) !== $password) {
            config(['database.connections.' . self::NAME . '.username' => $username, 'database.connections.' . self::NAME . '.password' => $password]);
            DB::purge(self::NAME);
        }

        return true;
    }

    /**
     * Runs $callback with the owner connection as the default connection when it is configured (the partition
     * commands), otherwise on the default connection as before. The previous default comes back afterwards and the
     * owner's session is closed (unless it was already the default: a test running as owner keeps its transaction).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        if (!self::prepare() || DB::getDefaultConnection() === self::NAME) {
            return $callback();
        }
        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection(self::NAME);
        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($previous);
            DB::disconnect(self::NAME);
        }
    }

    /**
     * Registered once (App\Providers\PartitionsServiceProvider): `php artisan migrate` (and the rest of COMMANDS) run on
     * the owner connection, from the entrypoint or by hand. Laravel dispatches these events for a real console run
     * only, not for $this->artisan() in tests: tests/TestCase.php passes --database itself.
     */
    public static function listen(): void
    {
        /** @var list<string|null> $switched per started command: the default connection to restore, null = none */
        $switched = [];
        app('events')->listen(CommandStarting::class, function (CommandStarting $event) use (&$switched) {
            $previous = null;
            if (in_array($event->command, self::COMMANDS, true) && self::prepare() && DB::getDefaultConnection() !== self::NAME) {
                $previous = DB::getDefaultConnection();
                DB::setDefaultConnection(self::NAME);
            }
            $switched[] = $previous;
        });
        app('events')->listen(CommandFinished::class, function () use (&$switched) {
            $previous = array_pop($switched);
            if ($previous !== null) {
                DB::setDefaultConnection($previous);
                DB::disconnect(self::NAME);
            }
        });
    }
}
