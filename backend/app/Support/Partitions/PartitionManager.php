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
    // SQLSTATE of lock_timeout (lock_not_available), of a deadlock and of statement_timeout (boundedLock): retried
    private const RETRY_STATES = ['55P03', '40P01', '57014'];

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
     * Repair missing required triggers, then attach the missing months of one table: config first_month to now +
     * months_ahead, plus every month a row in DEFAULT belongs to; rows in DEFAULT are moved into their month. Months the
     * archive took are skipped (their rows, if any arrived late, stay in DEFAULT and the status reports them).
     *
     * @return array{table: string, partitioned: bool, created: list<string>, moved: int, skipped: list<string>, repaired: list<string>}
     */
    public function ensure(string $base): array
    {
        $relation = $base;
        $report = ['table' => $base, 'partitioned' => false, 'created' => [], 'moved' => 0, 'skipped' => [], 'repaired' => []];
        $report['repaired'] = $this->repairTriggers($base);
        if (!$this->isPartitioned($relation)) {
            return $report;
        }
        $report['partitioned'] = true;
        $column = self::table($base)['column'];

        $attached = $this->partitions($relation);
        $default = self::defaultName($base);
        if (!in_array($default, array_column($attached, 'name'), true)) {
            $this->withLockRetry(function () use ($default, $relation) {
                $this->boundedLock("LOCK TABLE ONLY {$relation} IN ACCESS EXCLUSIVE MODE");
                DB::statement(sprintf('CREATE TABLE %s PARTITION OF %s DEFAULT', $default, $relation));
            });
            $report['created'][] = $default;
        }

        $attachedMonths = [];
        foreach ($attached as $partition) {
            if (!$partition['default']) {
                $attachedMonths[$partition['from']->format('Y-m')] = true;
            }
        }

        // From first_month (archived months are skipped below); months before it only when DEFAULT holds their rows:
        // never a run of empty months back to one old row
        $current = self::monthStart(now());
        $start = min(self::firstMonth(), $current);
        $end = $current->addMonths((int) config('partitions.months_ahead', 3));

        $wanted = [];
        for ($month = $start; $month <= $end; $month = $month->addMonth()) {
            $wanted[$month->format('Y-m')] = $month;
        }
        $withRows = [];
        foreach ($this->defaultMonths($default, $column) as $month) {
            $wanted[$month->format('Y-m')] = $month;
            $withRows[$month->format('Y-m')] = true;
        }
        ksort($wanted);

        $archived = $this->archivedNames($base);
        foreach ($wanted as $key => $month) {
            if (isset($attachedMonths[$key])) {
                continue;
            }
            $name = self::monthName($base, $month);
            if (in_array($name, $archived, true) || $this->exists($name)) {
                // Archived, or a detached / restored table of that name exists: never attached automatically (named
                // when it matters: rows of it wait in DEFAULT, or a table of that name is there)
                if (isset($withRows[$key]) || $this->exists($name)) {
                    $report['skipped'][] = $name;
                }

                continue;
            }
            $report['moved'] += $this->attachMonth($relation, $base, $month, $column);
            $report['created'][] = $name;
        }

        return $report;
    }

    /**
     * @return list<array{table: string, partitioned: bool, created: list<string>, moved: int, skipped: list<string>, repaired: list<string>}>
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
            // Every lock ATTACH needs, up front, bounded, in a fixed order: the referenced tables (SHARE ROW EXCLUSIVE,
            // which a till's foreign key check does not wait for), the parent (SHARE UPDATE EXCLUSIVE, inserts go on),
            // DEFAULT last (ACCESS EXCLUSIVE: an insert landing in DEFAULT, or a read of every month, waits for it)
            $referenced = $this->referencedTables($relation);
            if ($referenced !== []) {
                $this->boundedLock('LOCK TABLE ' . implode(', ', $referenced) . ' IN SHARE ROW EXCLUSIVE MODE');
            }
            $this->boundedLock("LOCK TABLE ONLY {$relation} IN SHARE UPDATE EXCLUSIVE MODE");
            if ($this->exists($default)) {
                $this->boundedLock("LOCK TABLE {$default} IN ACCESS EXCLUSIVE MODE");
            }
            DB::statement(sprintf('CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS INCLUDING CONSTRAINTS)', $name, $relation));

            $moved = 0;
            $hasDefault = $this->exists($default);
            if ($hasDefault && DB::selectOne(sprintf('SELECT EXISTS (SELECT 1 FROM %s WHERE %s >= ? AND %s < ?) AS e', $default, $column, $column), [$from, $to])->e) {
                // The cloned append-only trigger refuses DELETE: off for this move only (DDL is transactional, no
                // other session ever sees it disabled); only the enabled ones, so a disabled one stays disabled
                $enabled = array_column(DB::select("SELECT tgname FROM pg_trigger WHERE tgrelid = ?::regclass AND NOT tgisinternal AND tgenabled <> 'D'", [$default]), 'tgname');
                foreach ($enabled as $trigger) {
                    DB::statement(sprintf('ALTER TABLE %s DISABLE TRIGGER %s', $default, $trigger));
                }
                $moved = DB::affectingStatement(sprintf(
                    'WITH moved AS (DELETE FROM %1$s WHERE %3$s >= ? AND %3$s < ? RETURNING *) INSERT INTO %2$s SELECT * FROM moved',
                    $default, $name, $column,
                ), [$from, $to]);
                foreach ($enabled as $trigger) {
                    DB::statement(sprintf('ALTER TABLE %s ENABLE TRIGGER %s', $default, $trigger));
                }
            }

            DB::statement(sprintf("ALTER TABLE %s ATTACH PARTITION %s FOR VALUES FROM ('%s') TO ('%s')", $relation, $name, $from, $to));
            // Postgres clones every row trigger of the parent onto the new month: checked, never assumed
            $missing = DB::selectOne(
                'SELECT count(*) AS n FROM pg_trigger p WHERE p.tgrelid = ?::regclass AND NOT p.tgisinternal AND p.tgparentid = 0
                 AND NOT EXISTS (SELECT 1 FROM pg_trigger c WHERE c.tgrelid = ?::regclass AND c.tgparentid = p.oid)',
                [$relation, $name],
            )->n;
            if ((int) $missing > 0) {
                throw new RuntimeException("{$name}: ota jadvalning {$missing} ta triggeri yangi oyga o'tmadi: oy qo'shilmadi");
            }

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
     * Triggers the schema relies on, with what repairs them when missing (`db:partitions:ensure`, status code
     * trigger_missing). 'migration' is the migration that created them: a missing trigger is a problem only once it
     * has run. The repair creates it by 'sql' under a bounded lock (CREATE TRIGGER on a partitioned parent locks every
     * partition against writes), then runs 'backfill' in committed batches; if its 'function' is gone, the migration's
     * idempotent up() is run instead.
     */
    public const REQUIRED_TRIGGERS = [
        'audit_logs' => [
            'audit_logs_append_only' => [
                'migration' => '2026_11_01_100600_make_audit_logs_append_only',
                'function' => 'audit_logs_append_only',
                'sql' => 'CREATE TRIGGER audit_logs_append_only BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only()',
                'backfill' => null,
            ],
        ],
        'fiscal_receipts' => [
            'fiscal_receipt_keys_insert' => [
                'migration' => '2026_11_25_100300_create_fiscal_receipt_keys_table',
                'function' => 'fiscal_receipt_keys_insert',
                'sql' => 'CREATE TRIGGER fiscal_receipt_keys_insert BEFORE INSERT ON fiscal_receipts FOR EACH ROW EXECUTE FUNCTION fiscal_receipt_keys_insert()',
                // Receipts written while the trigger was missing get their key row (the migration's own backfill, set-based)
                'backfill' => 'INSERT INTO fiscal_receipt_keys (store_id, idempotency_key, uuid, receipt_id, receipt_created_at) SELECT store_id, idempotency_key, uuid, id, created_at FROM fiscal_receipts WHERE id > %d AND id <= %d ORDER BY id ON CONFLICT DO NOTHING',
            ],
            'fiscal_receipt_keys_update' => [
                'migration' => '2026_11_25_100300_create_fiscal_receipt_keys_table',
                'function' => 'fiscal_receipt_keys_update',
                'sql' => 'CREATE TRIGGER fiscal_receipt_keys_update BEFORE UPDATE OF id, store_id, idempotency_key, uuid, created_at ON fiscal_receipts FOR EACH ROW EXECUTE FUNCTION fiscal_receipt_keys_update()',
                'backfill' => null,
            ],
        ],
    ];

    /**
     * The user triggers defined on this relation itself (not internal foreign key triggers, not the clones Postgres
     * keeps on partitions), name => definition with the table written as {table}, and the enabled state.
     *
     * @param  list<string>  $exclude  trigger names to leave out (the converter's own change capture)
     * @return array<string, array{definition: string, enabled: string}>
     */
    public function userTriggers(string $relation, array $exclude = []): array
    {
        $triggers = [];
        foreach (DB::select(
            'SELECT tgname, pg_get_triggerdef(oid) AS def, tgenabled FROM pg_trigger WHERE tgrelid = ?::regclass AND NOT tgisinternal AND tgparentid = 0 ORDER BY tgname',
            [$relation],
        ) as $row) {
            if (in_array($row->tgname, $exclude, true)) {
                continue;
            }
            $definition = preg_replace('/^(CREATE (?:CONSTRAINT )?TRIGGER \S+ .+? ON )(\S+)( .*)$/s', '$1{table}$3', (string) $row->def, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException("{$relation}: trigger {$row->tgname} ta'rifini o'qib bo'lmadi, jadval almashtirilmaydi: {$row->def}");
            }
            $triggers[(string) $row->tgname] = ['definition' => (string) $definition, 'enabled' => (string) $row->tgenabled];
        }

        return $triggers;
    }

    /**
     * Re-creates triggers read by userTriggers() on $target (a same-named trigger there is replaced), with their enabled
     * state, then checks the target has exactly them: a table is never swapped in without the triggers of the one it
     * replaces (the caller's transaction rolls the swap back).
     *
     * @param  array<string, array{definition: string, enabled: string}>  $triggers
     * @param  list<string>  $exclude
     */
    public function copyTriggers(array $triggers, string $target, array $exclude = []): void
    {
        foreach ($triggers as $name => $trigger) {
            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $name, $target));
            try {
                DB::statement(str_replace('{table}', $target, $trigger['definition']));
            } catch (QueryException $e) {
                throw new RuntimeException("{$target}: {$name} triggerini yangi jadvalda yaratib bo'lmadi ({$e->getPrevious()?->getMessage()}): almashtirish bekor qilindi, hech narsa o'zgarmadi. Triggerni moslang yoki texnik mutaxassisga ko'rsating.", 0, $e);
            }
            $state = match ($trigger['enabled']) {
                'D' => 'DISABLE TRIGGER',
                'R' => 'ENABLE REPLICA TRIGGER',
                'A' => 'ENABLE ALWAYS TRIGGER',
                default => null,
            };
            if ($state !== null) {
                DB::statement(sprintf('ALTER TABLE %s %s %s', $target, $state, $name));
            }
        }
        $this->assertTriggers($triggers, $target, $exclude);
    }

    /**
     * @param  array<string, array{definition: string, enabled: string}>  $expected
     * @param  list<string>  $exclude
     */
    public function assertTriggers(array $expected, string $relation, array $exclude = []): void
    {
        $actual = $this->userTriggers($relation, $exclude);
        ksort($expected);
        if ($actual !== $expected) {
            $missing = array_keys(array_diff_key($expected, $actual));
            $extra = array_keys(array_diff_key($actual, $expected));
            throw new RuntimeException(sprintf(
                "%s: triggerlar asl jadvaldagidek emas (yo'q: %s; ortiqcha: %s; yoki ta'rifi farq qiladi): almashtirish bekor qilindi, hech narsa o'zgarmadi",
                $relation, implode(', ', $missing) ?: '-', implode(', ', $extra) ?: '-',
            ));
        }
    }

    /**
     * Required triggers (REQUIRED_TRIGGERS) that are missing or disabled on a table whose creating migration has run.
     *
     * @return list<string>
     */
    public function missingTriggers(string $base): array
    {
        $required = self::REQUIRED_TRIGGERS[$base] ?? [];
        if ($required === [] || !$this->exists($base)) {
            return [];
        }
        $present = array_column(DB::select("SELECT tgname FROM pg_trigger WHERE tgrelid = ?::regclass AND NOT tgisinternal AND tgparentid = 0 AND tgenabled <> 'D'", [$base]), 'tgname');
        $ran = $this->exists('migrations')
            ? DB::table('migrations')->whereIn('migration', array_unique(array_column($required, 'migration')))->pluck('migration')->all()
            : [];
        $missing = [];
        foreach ($required as $name => $spec) {
            if (!in_array($name, $present, true) && in_array($spec['migration'], $ran, true)) {
                $missing[] = $name;
            }
        }

        return $missing;
    }

    /**
     * Puts missing required triggers back: by their statement, or by re-running their (idempotent) migration.
     *
     * @return list<string> the repaired trigger names
     */
    public function repairTriggers(string $base): array
    {
        $missing = $this->missingTriggers($base);
        $migrations = [];
        $backfills = [];
        foreach ($missing as $name) {
            $spec = self::REQUIRED_TRIGGERS[$base][$name];
            if (!(bool) DB::selectOne('SELECT EXISTS (SELECT 1 FROM pg_proc WHERE proname = ?) AS e', [$spec['function']])->e) {
                $migrations[$spec['migration']] = true;

                continue;
            }
            $this->withLockRetry(function () use ($base, $name, $spec) {
                $this->boundedLock("LOCK TABLE {$base} IN SHARE ROW EXCLUSIVE MODE");
                $disabled = (bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_trigger WHERE tgrelid = ?::regclass AND tgname = ? AND tgparentid = 0) AS e", [$base, $name])->e;
                DB::statement($disabled ? sprintf('ALTER TABLE %s ENABLE TRIGGER %s', $base, $name) : $spec['sql']);
            });
            if ($spec['backfill'] !== null) {
                $backfills[] = $spec['backfill'];
            }
        }
        foreach ($backfills as $backfill) {
            $max = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$base}")->m;
            for ($from = 0; $from < $max; $from += 20000) {
                DB::transaction(fn () => DB::statement(sprintf($backfill, $from, $from + 20000)));
            }
        }
        foreach (array_keys($migrations) as $migration) {
            (require database_path("migrations/{$migration}.php"))->up();
        }
        $left = $this->missingTriggers($base);
        if ($left !== []) {
            throw new RuntimeException("{$base}: triggerlarni tiklab bo'lmadi: " . implode(', ', $left));
        }

        return $missing;
    }

    /**
     * A LOCK TABLE statement whose whole wait is bounded: lock_timeout applies to each lock separately (a statement
     * locking several tables, or a parent and its partitions, can otherwise wait that long for each), so the statement
     * also gets statement_timeout = the same bound. Maintenance takes its strong locks this way, up front and in a
     * fixed order (referenced tables first, the parent and DEFAULT last), so nothing waits again while they are held:
     * a till's insert queues behind it for at most about one timeout, then the step gives up and is retried later.
     */
    public function boundedLock(string $lockStatement, ?string $timeout = null): void
    {
        $timeout = preg_replace('/[^0-9a-z]/i', '', $timeout ?? (string) config('partitions.lock_timeout', '2s'));
        DB::statement("SET LOCAL lock_timeout = '{$timeout}'");
        DB::statement("SET LOCAL statement_timeout = '{$timeout}'");
        DB::statement($lockStatement);
        DB::statement('SET LOCAL statement_timeout TO DEFAULT');
    }

    /**
     * The tables the foreign keys of $relation reference, sorted (the fixed lock order).
     *
     * @return list<string>
     */
    public function referencedTables(string $relation): array
    {
        $tables = array_map(fn (object $row) => (string) $row->t, DB::select(
            "SELECT DISTINCT confrelid::regclass::text AS t FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'f' AND confrelid <> conrelid",
            [$relation],
        ));
        sort($tables);

        return $tables;
    }

    /**
     * Drops the foreign keys of a table that is no longer live (a detached month, the old plain table), one per
     * transaction: dropping a foreign key takes ACCESS EXCLUSIVE on the referenced table (users, stores...) for an
     * instant; each takes it bounded and retried, never while another strong lock is held.
     */
    public function dropForeignKeys(string $relation, ?string $timeout = null): void
    {
        $timeout ??= (string) config('partitions.detach_lock_timeout', '3s');
        foreach (DB::select("SELECT conname, confrelid::regclass::text AS ref FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'f' ORDER BY conname", [$relation]) as $fk) {
            $this->withLockRetry(function () use ($relation, $fk, $timeout) {
                $this->boundedLock(sprintf('LOCK TABLE %s IN ACCESS EXCLUSIVE MODE', $fk->ref === $relation ? $relation : "{$fk->ref}, {$relation}"), $timeout);
                DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $relation, $fk->conname));
            }, $timeout);
        }
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
     * M4: why the current connection cannot do the partitions' DDL (CREATE TABLE ... PARTITION OF, ATTACH/DETACH, DROP,
     * triggers), or null when it can: its role owns every journal table (or is a member of the owner role, or a
     * superuser) and may create tables in the schema. With split roles the DML-only application role cannot: the
     * commands then need the owner connection (App\Support\Database\OwnerConnection). Checked before any step, so a
     * wrong setup fails every run at once, not only on the day a month is due.
     */
    public function maintenanceRoleProblem(): ?string
    {
        try {
            $row = DB::selectOne(
                "SELECT current_user AS role, has_schema_privilege(current_schema(), 'CREATE') AS can_create,
                        coalesce(string_agg(c.relname, ', ' ORDER BY c.relname) FILTER (WHERE NOT pg_has_role(c.relowner, 'USAGE')), '') AS not_owned
                   FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                  WHERE n.nspname = current_schema() AND c.relname = ANY (?::text[])",
                ['{' . implode(',', array_keys(self::tables())) . '}'],
            );
        } catch (\Throwable $e) {
            return "bazaga ulanib bo'lmadi (" . mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 200) . ')';
        }
        if ($row->not_owned === '' && (bool) $row->can_create) {
            return null;
        }
        $what = $row->not_owned !== '' ? "jadvallar egasi emas ({$row->not_owned})" : "sxemada jadval yarata olmaydi";

        return "{$row->role} roli {$what}";
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
            $row = [
                'table' => $base,
                'column' => $config['column'],
                'partitioned' => $kind === 'p',
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
                $problems[] = ['table' => $base, 'code' => 'not_partitioned', 'message' => "{$base} hali oylarga bo'linmagan: `php artisan migrate` bajaring (jadval katta bo'lsa migratsiya qancha vaqt va qanday bajarishni aytadi)"];
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
            $missingTriggers = $kind === null ? [] : $this->missingTriggers($base);
            $row['missing_triggers'] = $missingTriggers;
            if ($missingTriggers !== []) {
                $problems[] = ['table' => $base, 'code' => 'trigger_missing', 'message' => "{$base}: himoya triggeri yo'q (" . implode(', ', $missingTriggers) . '): takroriy yoki o\'zgartirilgan yozuvlardan himoya ishlamayapti. `php artisan db:partitions:ensure` bajaring (triggerni tiklaydi)'];
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
