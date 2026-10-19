<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes on live tables for migrations that set $withinTransaction = false: on PostgreSQL they are built
 * CONCURRENTLY, so writes are never blocked; other drivers use the schema builder.
 *
 * A CREATE INDEX CONCURRENTLY that fails (a deadlock, a cancelled deploy, a duplicate in a unique index) leaves an
 * INVALID index behind: never used by queries, still updated on every write, and skipped forever by IF NOT EXISTS.
 * create() drops such a leftover first, so running it again repairs the index.
 */
final class ConcurrentIndex
{
    /**
     * @param  list<string>  $columns
     */
    public static function create(string $name, string $table, array $columns): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $name));

            return;
        }

        if (self::isInvalid($name)) {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
        }

        DB::statement(sprintf('CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s (%s)', $name, $table, implode(', ', $columns)));
    }

    public static function drop(string $name, string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));

            return;
        }

        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
    }

    /**
     * Whether an index of this name exists in the current schema but is INVALID (pg_index.indisvalid = false).
     */
    public static function isInvalid(string $name): bool
    {
        return DB::table('pg_index')
            ->join('pg_class', 'pg_class.oid', '=', 'pg_index.indexrelid')
            ->join('pg_namespace', 'pg_namespace.oid', '=', 'pg_class.relnamespace')
            ->where('pg_class.relname', $name)
            ->whereRaw('pg_namespace.nspname = current_schema()')
            ->where('pg_index.indisvalid', false)
            ->exists();
    }
}
