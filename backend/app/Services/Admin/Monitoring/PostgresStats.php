<?php

namespace App\Services\Admin\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M3: what the server-level alert checks and Monitoring → Holat read from Postgres' statistics views. Every read runs
 * under `SET LOCAL statement_timeout` (monitoring.sql_statement_timeout) so a slow catalog never holds the scheduler,
 * and returns null when it could not measure (no permission, a timeout, not Postgres): "unknown", never "ok".
 *
 * Sessions of other databases on the same server are ignored except for the connection count (max_connections is
 * the server's), and the session asking is never counted.
 */
class PostgresStats
{
    /**
     * Runs $read in a transaction with a short statement_timeout; null on any failure. Inside an outer transaction
     * (tests) the previous timeout is restored, so the setting does not leak to the caller.
     *
     * @template T
     *
     * @param  callable(): T  $read
     * @return T|null
     */
    public static function bounded(callable $read, string $what = 'monitoring'): mixed
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        try {
            return DB::transaction(function () use ($read) {
                // pg_stat_activity is snapshotted once per transaction: inside a long outer one the numbers would be old
                DB::select('SELECT pg_stat_clear_snapshot()');
                $previous = (string) DB::selectOne("SELECT current_setting('statement_timeout') AS v")->v;
                // SET LOCAL (transaction-scoped, safe behind PgBouncer in transaction mode); SET takes no bindings
                DB::statement(sprintf("SET LOCAL statement_timeout = '%s'", self::duration((string) config('monitoring.sql_statement_timeout', '2s'))));
                $result = $read();
                DB::statement(sprintf("SET LOCAL statement_timeout = '%s'", self::duration($previous)));

                return $result;
            });
        } catch (Throwable $e) {
            Log::warning('Monitoring: a measurement failed', ['what' => $what, 'exception' => $e::class, 'message' => mb_substr($e->getMessage(), 0, 300)]);

            return null;
        }
    }

    /**
     * @return array{used: int, max: int, percent: float, clients: list<array{client: string, connections: int}>}|null
     */
    public function connections(): ?array
    {
        return self::bounded(function () {
            $row = DB::selectOne("SELECT count(*) AS used, current_setting('max_connections')::int AS max FROM pg_stat_activity WHERE backend_type = 'client backend'");
            $clients = DB::select("SELECT coalesce(host(client_addr), 'local') AS client, count(*) AS connections FROM pg_stat_activity WHERE backend_type = 'client backend' GROUP BY 1 ORDER BY 2 DESC LIMIT 5");
            $max = max(1, (int) $row->max);

            return [
                'used' => (int) $row->used,
                'max' => $max,
                'percent' => round(100 * (int) $row->used / $max, 1),
                'clients' => array_map(fn ($c) => ['client' => (string) $c->client, 'connections' => (int) $c->connections], $clients),
            ];
        }, 'connections');
    }

    /**
     * The longest open transactions of this database, `active` and `idle in transaction` apart (the backup tools'
     * sessions are excluded: a nightly pg_dump is one long transaction by design).
     *
     * @return array{active: list<array{pid: int, seconds: int, application: string}>, idle: list<array{pid: int, seconds: int, application: string}>}|null
     */
    public function longTransactions(): ?array
    {
        return self::bounded(function () {
            $excluded = array_values((array) config('monitoring.postgres.long_transaction_exclude', []));
            $not = $excluded === [] ? '' : ' AND NOT (coalesce(application_name, \'\') LIKE ANY (ARRAY[' . implode(',', array_fill(0, count($excluded), '?')) . ']::text[]))';
            $rows = DB::select(
                "SELECT pid, state, floor(extract(epoch FROM clock_timestamp() - xact_start))::int AS seconds, coalesce(application_name, '') AS application
                   FROM pg_stat_activity
                  WHERE datname = current_database() AND pid <> pg_backend_pid() AND backend_type = 'client backend'
                    AND xact_start IS NOT NULL AND state IN ('active', 'idle in transaction', 'idle in transaction (aborted)'){$not}
                  ORDER BY xact_start LIMIT 20",
                $excluded,
            );

            $out = ['active' => [], 'idle' => []];
            foreach ($rows as $row) {
                $out[$row->state === 'active' ? 'active' : 'idle'][] = ['pid' => (int) $row->pid, 'seconds' => (int) $row->seconds, 'application' => mb_substr((string) $row->application, 0, 60)];
            }

            return $out;
        }, 'long_transactions');
    }

    /**
     * Sessions of this database waiting on a lock, with how long they have waited.
     *
     * @return list<array{pid: int, seconds: int}>|null
     */
    public function lockWaits(): ?array
    {
        return self::bounded(fn () => array_map(
            fn ($row) => ['pid' => (int) $row->pid, 'seconds' => (int) $row->seconds],
            DB::select("SELECT pid, floor(extract(epoch FROM clock_timestamp() - coalesce(state_change, query_start)))::int AS seconds
                           FROM pg_stat_activity
                          WHERE datname = current_database() AND pid <> pg_backend_pid() AND wait_event_type = 'Lock'
                          ORDER BY 2 DESC LIMIT 50"),
        ), 'lock_waits');
    }

    /**
     * pg_stat_archiver plus the number of WAL segments waiting to be archived (`.ready` files; needs pg_monitor or
     * superuser: null when not allowed, the rest is still returned).
     *
     * @return array{archive_mode: string, last_archived_wal: string|null, last_archived_at: string|null, last_failed_wal: string|null, last_failed_at: string|null, failed_count: int, failing: bool, ready_wal: int|null}|null
     */
    public function archiver(): ?array
    {
        $state = self::bounded(function () {
            $row = DB::selectOne("SELECT current_setting('archive_mode') AS archive_mode, last_archived_wal, last_archived_time, last_failed_wal, last_failed_time, failed_count,
                    coalesce(last_failed_time > coalesce(last_archived_time, '-infinity'::timestamptz), false) AS failing
                  FROM pg_stat_archiver");

            return [
                'archive_mode' => (string) $row->archive_mode,
                'last_archived_wal' => $row->last_archived_wal,
                'last_archived_at' => self::iso($row->last_archived_time),
                'last_failed_wal' => $row->last_failed_wal,
                'last_failed_at' => self::iso($row->last_failed_time),
                'failed_count' => (int) $row->failed_count,
                'failing' => (bool) $row->failing,
                'ready_wal' => null,
            ];
        }, 'archiver');
        if ($state === null) {
            return null;
        }

        $state['ready_wal'] = self::bounded(
            fn () => (int) DB::selectOne("SELECT count(*) AS n FROM pg_ls_archive_statusdir() WHERE name LIKE '%.ready'")->n,
            'archive_status',
        );

        return $state;
    }

    /**
     * @param  list<string>  $tables
     * @return list<array{table: string, live: int, dead: int, last_vacuum_at: string|null, vacuum_age_seconds: int|null}>|null
     */
    public function tableStats(array $tables): ?array
    {
        if ($tables === []) {
            return [];
        }

        return self::bounded(fn () => array_map(fn ($row) => [
            'table' => (string) $row->relname,
            'live' => (int) $row->n_live_tup,
            'dead' => (int) $row->n_dead_tup,
            'last_vacuum_at' => self::iso($row->last_vac),
            'vacuum_age_seconds' => $row->vac_age === null ? null : (int) $row->vac_age,
        ], DB::select(
            'SELECT relname, n_live_tup, n_dead_tup, greatest(last_autovacuum, last_vacuum) AS last_vac,
                    floor(extract(epoch FROM now() - greatest(last_autovacuum, last_vacuum)))::int AS vac_age
               FROM pg_stat_user_tables WHERE schemaname = current_schema() AND relname IN (' . implode(',', array_fill(0, count($tables), '?')) . ')
              ORDER BY relname',
            $tables,
        )), 'table_stats');
    }

    /** A Postgres duration such as `2s`, `100ms` or `0`, nothing else (it goes into the SET text). */
    private static function duration(string $value): string
    {
        return preg_match('/^\d+\s*(us|ms|s|min|h|d)?$/', trim($value)) === 1 ? trim($value) : '2s';
    }

    private static function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Illuminate\Support\Carbon::parse((string) $value)->utc()->toIso8601ZuluString();
    }
}
