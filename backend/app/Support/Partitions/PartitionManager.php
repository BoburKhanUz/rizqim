<?php

namespace App\Support\Partitions;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md section 3): the monthly partitions of the journal tables (config/partitions.php).
 *
 * A month `audit_logs_2026_11` holds FROM '2026-11-01' TO '2026-12-01' (UTC); `audit_logs_default` catches any row of a
 * month that is not attached, so an insert inside a till transaction never fails. ensure() keeps the months attached
 * from the oldest attached one to now + months_ahead and moves rows out of DEFAULT; status() is what
 * `db:partitions:status` prints and the monitoring check reads.
 *
 * Raw SQL on partition names is allowed only in app/Support/Partitions (the rule of the design, section 4). Moving rows
 * between partitions of one table changes no row a screen shows, so it bypasses the change-revision hooks on purpose.
 */
final class PartitionManager
{
    // SQLSTATE of lock_timeout (lock_not_available) and of a deadlock: the DDL is retried
    private const RETRY_STATES = ['55P03', '40P01'];

    /**
     * @return array<string, array{column: string, online_months: int, archive: bool}>
     */
    public static function tables(): array
    {
        /** @var array<string, array{column: string, online_months: int, archive: bool}> $tables */
        $tables = config('partitions.tables', []);

        return $tables;
    }

    /**
     * @return array{column: string, online_months: int, archive: bool}
     */
    public static function table(string $table): array
    {
        $config = self::tables()[$table] ?? null;
        if ($config === null) {
            throw new RuntimeException("{$table}: bo'linadigan jadvallar ro'yxatida yo'q (config/partitions.php). Mumkin: " . implode(', ', array_keys(self::tables())));
        }

        return $config;
    }

    public static function monthName(string $base, CarbonImmutable $month): string
    {
        return $base . '_' . $month->format('Y_m');
    }

    public static function defaultName(string $base): string
    {
        return $base . '_default';
    }

    public static function monthStart(\DateTimeInterface $at): CarbonImmutable
    {
        return CarbonImmutable::instance($at)->utc()->startOfMonth();
    }

    public static function firstMonth(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', (string) config('partitions.first_month', '2026-01'), 'UTC')->startOfMonth();
    }

    /**
     * 'p' partitioned table, 'r' plain table, null: no such relation in the current schema.
     */
    public function relkind(string $relation): ?string
    {
        $row = DB::selectOne(
            'SELECT c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = ? AND n.nspname = current_schema()',
            [$relation],
        );

        return $row?->relkind;
    }

    public function isPartitioned(string $relation): bool
    {
        return $this->relkind($relation) === 'p';
    }

    public function exists(string $relation): bool
    {
        return $this->relkind($relation) !== null;
    }

    /**
     * The attached partitions of a partitioned table, oldest first, the DEFAULT last.
     *
     * @return list<array{name: string, from: CarbonImmutable|null, to: CarbonImmutable|null, default: bool}>
     */
    public function partitions(string $relation): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT c.relname AS name, pg_get_expr(c.relpartbound, c.oid) AS bound
            FROM pg_inherits i
            JOIN pg_class c ON c.oid = i.inhrelid
            JOIN pg_class p ON p.oid = i.inhparent
            JOIN pg_namespace n ON n.oid = p.relnamespace
            WHERE p.relname = ? AND n.nspname = current_schema()
            SQL, [$relation]);

        $months = [];
        $default = [];
        foreach ($rows as $row) {
            if ($row->bound === 'DEFAULT') {
                $default[] = ['name' => $row->name, 'from' => null, 'to' => null, 'default' => true];

                continue;
            }
            if (preg_match("/FROM \\('([^']+)'\\) TO \\('([^']+)'\\)/", (string) $row->bound, $m) !== 1) {
                continue;
            }
            $months[] = [
                'name' => $row->name,
                'from' => CarbonImmutable::parse($m[1], 'UTC'),
                'to' => CarbonImmutable::parse($m[2], 'UTC'),
                'default' => false,
            ];
        }
        usort($months, fn (array $a, array $b) => $a['from'] <=> $b['from']);

        return array_merge($months, $default);
    }

    /**
     * Partition names the archive has taken (detached, archived or dropped): ensure() never creates them again. A
     * `held` archive is attached again by hand (scripts/archive-restore.sh --attach) and counts as attached.
     *
     * @return list<string>
     */
    public function archivedNames(string $base): array
    {
        if (!$this->exists('partition_archives')) {
            return [];
        }

        return DB::table('partition_archives')->where('table_name', $base)->where('status', '!=', 'held')->pluck('partition_name')->all();
    }

    /**
     * Attach the missing months of one table: from its oldest attached month (or config first_month) to now +
     * months_ahead, plus every month a row in DEFAULT belongs to; rows in DEFAULT are moved into their month. Months the
     * archive took are skipped (their rows, if any arrived late, stay in DEFAULT and the status reports them).
     *
     * $relation is the partitioned table to work on (the online converter passes `<table>_m3_new`); partition names
     * always use the table's own name.
     *
     * @return array{table: string, partitioned: bool, created: list<string>, moved: int, skipped: list<string>}
     */
    public function ensure(string $base, ?string $relation = null): array
    {
        $relation ??= $base;
        $report = ['table' => $base, 'partitioned' => false, 'created' => [], 'moved' => 0, 'skipped' => []];
        if (!$this->isPartitioned($relation)) {
            return $report;
        }
        $report['partitioned'] = true;
        $column = self::table($base)['column'];

        $attached = $this->partitions($relation);
        $default = self::defaultName($base);
        if (!in_array($default, array_column($attached, 'name'), true)) {
            $this->withLockRetry(fn () => DB::statement(sprintf('CREATE TABLE %s PARTITION OF %s DEFAULT', $default, $relation)));
            $report['created'][] = $default;
        }

        $attachedMonths = [];
        foreach ($attached as $partition) {
            if (!$partition['default']) {
                $attachedMonths[$partition['from']->format('Y-m')] = true;
            }
        }

        $first = null;
        foreach ($attached as $partition) {
            if (!$partition['default']) {
                $first = $partition['from'];

                break;
            }
        }
        $current = self::monthStart(now());
        $start = $first ?? min(self::firstMonth(), $current);
        $end = $current->addMonths((int) config('partitions.months_ahead', 3));

        $wanted = [];
        for ($month = $start; $month <= $end; $month = $month->addMonth()) {
            $wanted[$month->format('Y-m')] = $month;
        }
        foreach ($this->defaultMonths($default, $column) as $month) {
            $wanted[$month->format('Y-m')] = $month;
        }
        ksort($wanted);

        $archived = $this->archivedNames($base);
        foreach ($wanted as $key => $month) {
            if (isset($attachedMonths[$key])) {
                continue;
            }
            $name = self::monthName($base, $month);
            if (in_array($name, $archived, true) || $this->exists($name)) {
                // Archived, or a detached / restored table of that name exists: never attached automatically
                $report['skipped'][] = $name;

                continue;
            }
            $report['moved'] += $this->attachMonth($relation, $base, $month, $column);
            $report['created'][] = $name;
        }

        return $report;
    }

    /**
     * @return list<array{table: string, partitioned: bool, created: list<string>, moved: int, skipped: list<string>}>
     */
    public function ensureAll(): array
    {
        $reports = [];
        foreach (array_keys(self::tables()) as $table) {
            $reports[] = $this->ensure($table);
        }

        return $reports;
    }

    /**
     * One month, without blocking writers: a standalone table LIKE the parent (only ShareUpdateExclusive on the parent
     * when attached), the month's rows moved out of DEFAULT in the same transaction, then ATTACH (the parent's indexes,
     * foreign keys and row triggers are created on it). Returns the rows moved out of DEFAULT.
     */
    public function attachMonth(string $relation, string $base, CarbonImmutable $month, string $column): int
    {
        $name = self::monthName($base, $month);
        $default = self::defaultName($base);
        $from = $month->format('Y-m-d H:i:s');
        $to = $month->addMonth()->format('Y-m-d H:i:s');

        return $this->withLockRetry(function () use ($relation, $name, $default, $from, $to, $column): int {
            DB::statement(sprintf('CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS INCLUDING CONSTRAINTS)', $name, $relation));

            $moved = 0;
            $hasDefault = $this->exists($default);
            if ($hasDefault && DB::selectOne(sprintf('SELECT EXISTS (SELECT 1 FROM %s WHERE %s >= ? AND %s < ?) AS e', $default, $column, $column), [$from, $to])->e) {
                // The cloned append-only trigger refuses DELETE: off for this move only (DDL is transactional, no
                // other session ever sees it disabled)
                DB::statement(sprintf('ALTER TABLE %s DISABLE TRIGGER USER', $default));
                $moved = DB::affectingStatement(sprintf(
                    'WITH moved AS (DELETE FROM %1$s WHERE %3$s >= ? AND %3$s < ? RETURNING *) INSERT INTO %2$s SELECT * FROM moved',
                    $default, $name, $column,
                ), [$from, $to]);
                DB::statement(sprintf('ALTER TABLE %s ENABLE TRIGGER USER', $default));
            }

            DB::statement(sprintf("ALTER TABLE %s ATTACH PARTITION %s FOR VALUES FROM ('%s') TO ('%s')", $relation, $name, $from, $to));

            return $moved;
        });
    }

    /**
     * Months a row in DEFAULT belongs to (DEFAULT is normally empty).
     *
     * @return list<CarbonImmutable>
     */
    private function defaultMonths(string $default, string $column): array
    {
        if (!$this->exists($default)) {
            return [];
        }

        return array_map(
            fn (object $row) => CarbonImmutable::parse($row->m, 'UTC'),
            DB::select(sprintf("SELECT DISTINCT to_char(date_trunc('month', %s), 'YYYY-MM-DD') AS m FROM %s", $column, $default)),
        );
    }

    /**
     * Months older than online_months (counting the current one), oldest first: what the archive detaches.
     *
     * @return list<array{name: string, from: CarbonImmutable, to: CarbonImmutable}>
     */
    public function dueForArchive(string $base): array
    {
        if (!$this->isPartitioned($base)) {
            return [];
        }
        $cutoff = self::monthStart(now())->subMonths(max(1, self::table($base)['online_months']) - 1);
        $due = [];
        foreach ($this->partitions($base) as $partition) {
            if (!$partition['default'] && $partition['to'] <= $cutoff) {
                $due[] = ['name' => $partition['name'], 'from' => $partition['from'], 'to' => $partition['to']];
            }
        }

        return $due;
    }

    /**
     * Runs $callback in a transaction with SET LOCAL lock_timeout; on a lock timeout or deadlock it is retried
     * (config partitions.lock_retries) after a growing pause. A till's write never waits longer than the timeout.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function withLockRetry(callable $callback, ?string $timeout = null): mixed
    {
        $timeout ??= (string) config('partitions.lock_timeout', '2s');
        $retries = max(1, (int) config('partitions.lock_retries', 3));
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($callback, $timeout) {
                    DB::statement(sprintf("SET LOCAL lock_timeout = '%s'", preg_replace('/[^0-9a-z]/i', '', $timeout)));

                    return $callback();
                });
            } catch (QueryException $e) {
                if ($attempt >= $retries || !in_array($e->getCode(), self::RETRY_STATES, true)) {
                    throw $e;
                }
                usleep(500_000 * $attempt);
            }
        }
    }

    /**
     * What `db:partitions:status --json` prints (the monitoring check reads the same array).
     *
     * @return array{generated_at: string, ok: bool, problems: list<array{table: string|null, code: string, message: string}>, tables: list<array<string, mixed>>, archives: array<string, mixed>}
     */
    public function status(): array
    {
        $problems = [];
        $tables = [];
        $current = self::monthStart(now());
        foreach (self::tables() as $base => $config) {
            $kind = $this->relkind($base);
            $conversion = $this->conversionState($base);
            $row = [
                'table' => $base,
                'column' => $config['column'],
                'partitioned' => $kind === 'p',
                'conversion' => $conversion,
                'online_months' => $config['online_months'],
                'archive' => $config['archive'],
                'partitions' => 0,
                'oldest_month' => null,
                'newest_month' => null,
                'current_month_attached' => false,
                'future_months' => 0,
                'default_rows' => 0,
                'due_for_archive' => 0,
                'bytes' => $kind === null ? 0 : (int) DB::selectOne('SELECT coalesce(sum(pg_total_relation_size(c.oid)), 0) AS b FROM pg_class c WHERE c.oid = ?::regclass OR c.oid IN (SELECT inhrelid FROM pg_inherits WHERE inhparent = ?::regclass)', [$base, $base])->b,
            ];

            if ($kind !== 'p') {
                $problems[] = ['table' => $base, 'code' => 'not_partitioned', 'message' => "{$base} hali oylarga bo'linmagan: `php artisan db:partitions:convert {$base}` bajaring"];
            } else {
                $months = [];
                foreach ($this->partitions($base) as $partition) {
                    if ($partition['default']) {
                        $row['default_rows'] = $this->countCapped($partition['name']);
                    } else {
                        $months[$partition['from']->format('Y-m')] = true;
                    }
                }
                ksort($months);
                $row['partitions'] = count($months);
                $row['oldest_month'] = array_key_first($months);
                $row['newest_month'] = array_key_last($months);
                $row['current_month_attached'] = isset($months[$current->format('Y-m')]);
                for ($month = $current->addMonth(); isset($months[$month->format('Y-m')]); $month = $month->addMonth()) {
                    $row['future_months']++;
                }
                // Archived tables: detached for the archive; notifications: detached and dropped
                $row['due_for_archive'] = count($this->dueForArchive($base));

                if (!$row['current_month_attached'] || $row['future_months'] < 2) {
                    $problems[] = ['table' => $base, 'code' => 'future_months_low', 'message' => "{$base}: oldinda {$row['future_months']} oy tayyor (kamida 2 kerak): `php artisan db:partitions:ensure` bajaring"];
                }
                if ($row['default_rows'] > 0) {
                    $problems[] = ['table' => $base, 'code' => 'default_rows', 'message' => "{$base}: {$row['default_rows']} qator zaxira (DEFAULT) bo'limda: `php artisan db:partitions:ensure` ularni o'z oyiga ko'chiradi"];
                }
            }
            if ($conversion !== 'none') {
                $problems[] = ['table' => $base, 'code' => 'conversion_pending', 'message' => match ($conversion) {
                    'copying' => "{$base}: onlayn ko'chirish tugallanmagan: `php artisan db:partitions:convert {$base}`, so'ng `--finish`",
                    default => "{$base}: eski jadval {$base}_m3_old saqlanmoqda: muddatidan keyin `php artisan db:partitions:convert {$base} --drop-old`",
                }];
            }
            $tables[] = $row;
        }

        $archives = $this->archiveSummary();
        if ($archives['failed'] > 0) {
            $problems[] = ['table' => null, 'code' => 'archive_failed', 'message' => "Arxivlashda {$archives['failed']} ta xato: partition_archives jadvalidagi error ustunini ko'ring"];
        }
        if ($archives['stale_detached'] > 0) {
            $problems[] = ['table' => null, 'code' => 'archive_stale', 'message' => "{$archives['stale_detached']} ta ajratilgan bo'lim 3 kundan beri arxivga yuklanmagan: zaxira xizmatini (backup) tekshiring"];
        }

        return [
            'generated_at' => now()->utc()->toIso8601ZuluString(),
            'ok' => $problems === [],
            'problems' => $problems,
            'tables' => $tables,
            'archives' => $archives,
        ];
    }

    /**
     * 'none', 'copying' (an online conversion is under way: `<table>_m3_new` exists) or 'swapped' (finished, the old
     * plain table `<table>_m3_old` is kept for --rollback until --drop-old).
     */
    public function conversionState(string $base): string
    {
        if ($this->exists($base . '_m3_old')) {
            return 'swapped';
        }

        return $this->exists($base . '_m3_new') ? 'copying' : 'none';
    }

    /**
     * @return array{detached: int, uploaded: int, verified: int, dropped: int, failed: int, held: int, stale_detached: int, oldest_detached_at: string|null, local_only_allowed: bool}
     */
    public function archiveSummary(): array
    {
        $summary = ['detached' => 0, 'uploaded' => 0, 'verified' => 0, 'dropped' => 0, 'failed' => 0, 'held' => 0, 'stale_detached' => 0, 'oldest_detached_at' => null, 'local_only_allowed' => (bool) config('partitions.allow_local_only')];
        if (!$this->exists('partition_archives')) {
            return $summary;
        }
        foreach (DB::table('partition_archives')->selectRaw('status, count(*) AS n')->groupBy('status')->get() as $row) {
            $summary[$row->status] = (int) $row->n;
        }
        $staleBefore = now()->subDays((int) config('partitions.drop_after_days', 3));
        $summary['stale_detached'] = DB::table('partition_archives')->where('status', 'detached')->where('detached_at', '<', $staleBefore)->count();
        $oldest = DB::table('partition_archives')->where('status', 'detached')->min('detached_at');
        $summary['oldest_detached_at'] = $oldest === null ? null : CarbonImmutable::parse($oldest, 'UTC')->toIso8601ZuluString();

        return $summary;
    }

    /**
     * count(*) that stops at 1 000 000 (DEFAULT is meant to be empty; the number only has to be readable).
     */
    private function countCapped(string $relation): int
    {
        return (int) DB::selectOne(sprintf('SELECT count(*) AS n FROM (SELECT 1 FROM %s LIMIT 1000000) s', $relation))->n;
    }
}
