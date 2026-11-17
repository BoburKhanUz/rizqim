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

    /**
     * M3: an index on a partitioned table (audit_logs, fiscal_receipts, notification_logs) without blocking writes.
     * CREATE INDEX CONCURRENTLY is not supported on a partitioned parent, so: an invalid index ON ONLY the parent, the
     * index built CONCURRENTLY on each partition, each one attached; once every partition has it, the parent index
     * becomes valid by itself, and months attached later get it automatically. Run again after a failure: it repairs.
     * $where: an optional partial-index predicate, e.g. "is_read = false".
     *
     * @param  list<string>  $columns
     */
    public static function createPartitioned(string $name, string $table, array $columns, ?string $where = null): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            self::create($name, $table, $columns);

            return;
        }

        $definition = '(' . implode(', ', $columns) . ')' . ($where === null ? '' : " WHERE {$where}");
        DB::statement(sprintf('CREATE INDEX IF NOT EXISTS %s ON ONLY %s %s', $name, $table, $definition));

        $partitions = DB::select(<<<'SQL'
            SELECT c.relname AS name FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid
            WHERE i.inhparent = ?::regclass ORDER BY c.relname
            SQL, [$table]);
        foreach ($partitions as $partition) {
            $child = self::partitionIndexName($name, $table, $partition->name);
            $attached = DB::selectOne(
                'SELECT EXISTS (SELECT 1 FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid WHERE i.inhparent = ?::regclass AND c.relname = ?) AS e',
                [$name, $child],
            )->e;
            if ($attached) {
                continue;
            }
            if (self::isInvalid($child)) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$child}");
            }
            DB::statement(sprintf('CREATE INDEX CONCURRENTLY IF NOT EXISTS %s ON %s %s', $child, $partition->name, $definition));
            DB::statement(sprintf('ALTER INDEX %s ATTACH PARTITION %s', $name, $child));
        }
    }

    /**
     * The name of a partition's index: `<partition>_<the index name without the table prefix>`, shortened with a hash
     * to Postgres' 63 characters.
     */
    public static function partitionIndexName(string $index, string $table, string $partition): string
    {
        $suffix = str_starts_with($index, $table . '_') ? substr($index, strlen($table) + 1) : $index;
        $name = $partition . '_' . $suffix;

        return strlen($name) <= 63 ? $name : substr($name, 0, 54) . '_' . substr(md5($name), 0, 8);
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
