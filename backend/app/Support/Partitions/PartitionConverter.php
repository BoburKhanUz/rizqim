<?php

namespace App\Support\Partitions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md section 4): turns a plain journal table into a table partitioned by month and back.
 *
 * Two paths with one definition of the partitioned schema (DEFINITIONS):
 * - inline (the partition_journals migration, small tables only): one transaction, the table locked EXCLUSIVE (reads
 *   go on), copy, indexes, swap, old table dropped;
 * - online (`db:partitions:convert`, a database with data): `<t>_m3_new` is built beside the live table, a trigger
 *   records every changed id in `<t>_m3_changes`, rows are copied in batches (resumable: progress is max(id) copied),
 *   indexes are built, then --finish locks the live table for the last delta only and swaps; `<t>_m3_old` is kept for
 *   --rollback until --drop-old.
 *
 * Copying rows between the old and the new table changes no row a screen shows: these statements bypass the
 * change-revision hooks on purpose (raw SQL on journal tables is allowed only in app/Support/Partitions).
 *
 * Rules for FUTURE migrations on these tables: add columns on the parent only (they propagate); never CREATE INDEX
 * CONCURRENTLY on the parent (use ConcurrentIndex::createPartitioned); every UNIQUE includes the partition column; no
 * foreign key may reference these tables; never UPDATE the partition column.
 */
final class PartitionConverter
{
    /**
     * The partitioned schema of each table, and 'plain': the schema before M3 (down() and --rollback restore it).
     * Index and constraint names are the ones Laravel gave the plain tables, so later dropIndex() calls keep working.
     * '{self}' is the table itself (the old fiscal self foreign key).
     */
    public const DEFINITIONS = [
        'audit_logs' => [
            'pk' => ['audit_logs_pkey', '(id, created_at)'],
            'unique' => [],
            'indexes' => [
                'audit_logs_action_created_at_index' => '(action, created_at)',
                'audit_logs_model_index' => '(model_type, model_id)',
                'audit_logs_store_id_created_at_index' => '(store_id, created_at)',
                'audit_logs_user_id_created_at_index' => '(user_id, created_at)',
            ],
            'foreign' => [
                'audit_logs_branch_id_foreign' => 'FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL',
                'audit_logs_device_id_foreign' => 'FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL',
                'audit_logs_store_id_foreign' => 'FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL',
                'audit_logs_user_id_foreign' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
            ],
            // Migration 2026_11_01_100600: the function is unchanged; on the parent it is cloned to every partition
            'triggers' => ['audit_logs_append_only' => 'BEFORE UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION audit_logs_append_only()'],
            'not_null' => [],
            'plain' => [
                'pk' => ['audit_logs_pkey', '(id)'],
                'unique' => [],
                'indexes' => [
                    'audit_logs_action_created_at_index' => '(action, created_at)',
                    'audit_logs_model_index' => '(model_type, model_id)',
                    'audit_logs_store_id_created_at_index' => '(store_id, created_at)',
                    'audit_logs_user_id_created_at_index' => '(user_id, created_at)',
                ],
                'foreign' => [
                    'audit_logs_branch_id_foreign' => 'FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL',
                    'audit_logs_device_id_foreign' => 'FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL',
                    'audit_logs_store_id_foreign' => 'FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE SET NULL',
                    'audit_logs_user_id_foreign' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
                ],
                'nullable' => [],
            ],
        ],
        'fiscal_receipts' => [
            'pk' => ['fiscal_receipts_pkey', '(id, created_at)'],
            // Partition-local: the advisory lock of FiscalService (stage A2) keeps idempotency across months
            'unique' => [
                'fiscal_receipts_store_id_idempotency_key_unique' => '(store_id, idempotency_key, created_at)',
                'fiscal_receipts_uuid_unique' => '(uuid, created_at)',
            ],
            'indexes' => [
                'fiscal_receipts_admin_status_created_at_index' => '(status, created_at)',
                'fiscal_receipts_status_next_attempt_at_index' => '(status, next_attempt_at)',
                'fiscal_receipts_store_id_created_at_index' => '(store_id, created_at)',
                'fiscal_receipts_subject_type_subject_id_index' => '(subject_type, subject_id)',
                // The self foreign key is gone (no FK may point at a partitioned table here): its lookup stays indexed
                'fiscal_receipts_original_receipt_id_index' => '(original_receipt_id)',
            ],
            'foreign' => [
                'fiscal_receipts_branch_id_foreign' => 'FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL',
                'fiscal_receipts_device_id_foreign' => 'FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL',
                'fiscal_receipts_store_id_foreign' => 'FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE',
            ],
            'triggers' => [],
            // created_at was nullable: backfilled from sold_at / issued_at / updated_at / now while copying
            'not_null' => ['created_at' => 'coalesce(created_at, sold_at, issued_at, updated_at, (now() AT TIME ZONE \'UTC\')::timestamp(0))'],
            'plain' => [
                'pk' => ['fiscal_receipts_pkey', '(id)'],
                'unique' => [
                    'fiscal_receipts_store_id_idempotency_key_unique' => '(store_id, idempotency_key)',
                    'fiscal_receipts_uuid_unique' => '(uuid)',
                ],
                'indexes' => [
                    'fiscal_receipts_admin_status_created_at_index' => '(status, created_at)',
                    'fiscal_receipts_status_next_attempt_at_index' => '(status, next_attempt_at)',
                    'fiscal_receipts_store_id_created_at_index' => '(store_id, created_at)',
                    'fiscal_receipts_subject_type_subject_id_index' => '(subject_type, subject_id)',
                ],
                'foreign' => [
                    'fiscal_receipts_branch_id_foreign' => 'FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL',
                    'fiscal_receipts_device_id_foreign' => 'FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL',
                    'fiscal_receipts_original_receipt_id_foreign' => 'FOREIGN KEY (original_receipt_id) REFERENCES {self}(id) ON DELETE SET NULL',
                    'fiscal_receipts_store_id_foreign' => 'FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE',
                ],
                'nullable' => ['created_at'],
            ],
        ],
        'notification_logs' => [
            'pk' => ['notification_logs_pkey', '(id, sent_at)'],
            'unique' => [],
            'indexes' => [
                'notification_logs_order_id_index' => '(order_id)',
                'notification_logs_user_id_id_index' => '(user_id, id)',
                // The feed's unread count (polled every 15 s)
                'notification_logs_user_id_unread_index' => '(user_id) WHERE is_read = false',
            ],
            'foreign' => [
                'notification_logs_order_id_foreign' => 'FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL',
                'notification_logs_user_id_foreign' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
            ],
            'triggers' => [],
            'not_null' => [],
            'plain' => [
                'pk' => ['notification_logs_pkey', '(id)'],
                'unique' => [],
                'indexes' => [
                    'notification_logs_order_id_index' => '(order_id)',
                    'notification_logs_user_id_id_index' => '(user_id, id)',
                ],
                'foreign' => [
                    'notification_logs_order_id_foreign' => 'FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL',
                    'notification_logs_user_id_foreign' => 'FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL',
                ],
                'nullable' => [],
            ],
        ],
    ];

    private const NEW = '_m3_new';

    private const OLD = '_m3_old';

    private const CHANGES = '_m3_changes';

    private const CAPTURE = '_m3_capture';

    public function __construct(private readonly PartitionManager $manager) {}

    /**
     * @return array<string, mixed>
     */
    public static function definition(string $table): array
    {
        PartitionManager::table($table);

        return self::DEFINITIONS[$table] ?? throw new RuntimeException("{$table}: no partition definition");
    }

    /**
     * Exact row count up to $limit + 1 (enough to compare with the inline threshold without reading a big table).
     */
    public function countUpTo(string $table, int $limit): int
    {
        return (int) DB::selectOne(sprintf('SELECT count(*) AS n FROM (SELECT 1 FROM %s LIMIT %d) s', $table, $limit + 1))->n;
    }

    public function refusalMessage(string $table, int $limit): string
    {
        return "{$table} jadvalida {$limit} dan ortiq qator bor: migratsiya uni joyida bo'lib chiqmaydi (jadval uzoq qulflanib qoladi). "
            . "Avval `php artisan db:partitions:convert {$table}` buyrug'ini bajaring (onlayn ishlaydi, to'xtab qolsa qayta ishga tushirsangiz davom etadi), "
            . "so'ng `php artisan db:partitions:convert {$table} --finish`, keyin `php artisan migrate` ni qayta ishga tushiring.";
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Inline (the migration)
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * The migration's up() for one table. A no-op when already partitioned; refuses above partitions.inline_max_rows.
     */
    public function convertInline(string $table): bool
    {
        if ($this->manager->isPartitioned($table)) {
            return false;
        }
        $limit = (int) config('partitions.inline_max_rows', 200000);
        if ($this->countUpTo($table, $limit) > $limit) {
            throw new RuntimeException($this->refusalMessage($table, $limit));
        }

        $this->manager->withLockRetry(function () use ($table) {
            // EXCLUSIVE: reads go on, writes wait for the whole conversion (measured: 1.3 s for 190 000 audit rows)
            DB::statement("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            $this->dropLeftovers($table);
            $this->createPartitionedTable($table, $this->startMonth($table, exact: true));
            $this->copyAll($table, $table . self::NEW);
            $this->buildPartitionedIndexes($table, perPartitionForeignKeys: false);
            $this->swapIn($table);
            DB::statement(sprintf('DROP TABLE %s', $table . self::OLD));
        }, '5s');

        return true;
    }

    /**
     * The migration's down() for one table: the plain table with the constraints it had before M3.
     */
    public function revertInline(string $table): bool
    {
        if ($this->manager->relkind($table) !== 'p') {
            return false;
        }
        $limit = (int) config('partitions.inline_max_rows', 200000);
        if ($this->countUpTo($table, $limit) > $limit) {
            throw new RuntimeException("{$table} jadvalida {$limit} dan ortiq qator bor: orqaga qaytarish faqat kichik jadvalda joyida bajariladi. Konversiyadan keyin 7 kun ichida `php artisan db:partitions:convert {$table} --rollback` ishlating.");
        }

        $this->manager->withLockRetry(function () use ($table) {
            DB::statement("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            $plain = $table . '_m3_plain';
            $definition = self::definition($table);
            DB::statement(sprintf('DROP TABLE IF EXISTS %s', $plain));
            DB::statement(sprintf('CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS INCLUDING STORAGE INCLUDING COMMENTS)', $plain, $table));
            foreach ($definition['plain']['nullable'] as $column) {
                DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL, ALTER COLUMN %s DROP DEFAULT', $plain, $column, $column));
            }
            DB::statement(sprintf('INSERT INTO %s SELECT * FROM %s', $plain, $table));
            $this->addConstraints($plain, $definition['plain'], '_m3plain');

            // Swap: the partitioned table and its partitions go (every row is in the plain copy now)
            DB::statement(sprintf('ALTER SEQUENCE %s_id_seq OWNED BY %s.id', $table, $plain));
            DB::statement(sprintf('DROP TABLE %s', $table));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $plain, $table));
            $this->renameConstraints($table, $definition['plain'], '_m3plain', '');
            foreach ($definition['triggers'] as $name => $sql) {
                DB::statement(sprintf('CREATE TRIGGER %s ' . $sql, $name, $table));
            }
        }, '5s');

        return true;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Online (db:partitions:convert)
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * 'partitioned' (done), 'swapped' (done, the old plain table kept for --rollback), 'copying' (`<t>_m3_new` is being
     * filled) or 'plain' (not started).
     */
    public function state(string $table): string
    {
        $kind = $this->manager->relkind($table);
        if ($kind === 'p') {
            return $this->manager->exists($table . self::OLD) ? 'swapped' : 'partitioned';
        }
        if ($kind === null) {
            throw new RuntimeException("{$table}: jadval topilmadi");
        }

        return $this->manager->exists($table . self::NEW) ? 'copying' : 'plain';
    }

    /**
     * Copy progress: rows copied (max id) against the live table's max id.
     *
     * @return array{copied_max_id: int, live_max_id: int, pending_changes: int}
     */
    public function progress(string $table): array
    {
        $new = $this->manager->exists($table . self::NEW) ? $table . self::NEW : null;
        $changes = $this->manager->exists($table . self::CHANGES) ? $table . self::CHANGES : null;
        $liveTable = $this->manager->relkind($table) === 'r' ? $table : ($this->manager->exists($table . self::OLD) ? $table . self::OLD : $table);

        return [
            'copied_max_id' => $new === null ? 0 : (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$new}")->m,
            'live_max_id' => (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$liveTable}")->m,
            'pending_changes' => $changes === null ? 0 : (int) DB::selectOne("SELECT count(*) AS n FROM {$changes}")->n,
        ];
    }

    /**
     * Step 1 (idempotent): the change-capture trigger on the live table, then `<t>_m3_new` with its months, DEFAULT and
     * primary key. The trigger is installed first (CREATE TRIGGER waits for open writers), so every write from then on
     * is recorded and a row committed late with a lower id is never missed.
     */
    public function prepare(string $table): void
    {
        if ($this->state($table) !== 'plain') {
            return;
        }
        $this->manager->withLockRetry(function () use ($table) {
            $this->installCapture($table);
            $this->createPartitionedTable($table, $this->startMonth($table, exact: false));
        }, (string) config('partitions.lock_timeout', '2s'));
    }

    /**
     * Step 2: batches of `WHERE id > max(id) copied ORDER BY id LIMIT batch`, one commit each. Killed at any point, the
     * next run continues from the last committed batch. $onBatch(copied, maxId) is called after each commit.
     *
     * @param  (callable(int, int): void)|null  $onBatch
     */
    public function copy(string $table, int $batch = 20000, ?callable $onBatch = null): int
    {
        $new = $table . self::NEW;
        [$columns, $select] = $this->columnLists($table);
        $total = 0;
        while (true) {
            $copied = DB::transaction(fn () => DB::affectingStatement(sprintf(
                'INSERT INTO %1$s (%2$s) SELECT %3$s FROM %4$s WHERE id > (SELECT coalesce(max(id), 0) FROM %1$s) ORDER BY id LIMIT %5$d',
                $new, $columns, $select, $table, max(1, $batch),
            )));
            if ($copied === 0) {
                return $total;
            }
            $total += $copied;
            if ($onBatch !== null) {
                $onBatch($copied, (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$new}")->m);
            }
        }
    }

    /**
     * Step 3 (idempotent): the secondary indexes and uniques on `<t>_m3_new` (nobody reads it yet: no lock on the live
     * table), foreign keys partition by partition (NOT VALID + VALIDATE: the referenced tables are never write-locked
     * for a whole scan), then on the parent (which adopts the validated ones).
     */
    public function build(string $table): void
    {
        $this->buildPartitionedIndexes($table, perPartitionForeignKeys: true);
    }

    /**
     * Rows changed since they were copied, re-copied in batches without a lock (shrinks the locked delta of --finish).
     */
    public function catchUp(string $table, int $batch = 20000): int
    {
        $total = 0;
        do {
            $done = DB::transaction(fn () => $this->applyChanges($table, $table, $table . self::NEW, $batch));
            $total += $done;
        } while ($done > 0);

        return $total;
    }

    /**
     * Step 4: under `LOCK TABLE <t> IN EXCLUSIVE MODE` (reads go on, writers wait seconds) with lock_timeout 3 s and
     * retries: the last changes and rows above the copy mark, a max(id) check, then the swap. `<t>_m3_old` is kept;
     * from now on the changes of the new table are recorded for --rollback.
     */
    public function finish(string $table, int $batch = 20000): void
    {
        if ($this->state($table) !== 'copying') {
            throw new RuntimeException("{$table}: tugatiladigan ko'chirish yo'q (holat: {$this->state($table)})");
        }
        $this->copy($table, $batch);
        $this->build($table);
        $this->manager->ensure($table, $table . self::NEW);
        $this->catchUp($table, $batch);

        $this->manager->withLockRetry(function () use ($table) {
            DB::statement("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            $new = $table . self::NEW;
            while ($this->applyChanges($table, $table, $new, 100000) > 0) {
                // drain
            }
            [$columns, $select] = $this->columnLists($table);
            DB::statement(sprintf('INSERT INTO %1$s (%2$s) SELECT %3$s FROM %4$s WHERE id > (SELECT coalesce(max(id), 0) FROM %1$s)', $new, $columns, $select, $table));
            $this->assertSameMaxId($table, $new);

            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
            $this->swapIn($table);
            DB::statement(sprintf("COMMENT ON TABLE %s IS 'M3 swapped_at=%s'", $table . self::OLD, now()->utc()->toIso8601ZuluString()));
            // --rollback copies back what is written from now on
            $this->createCaptureTrigger($table, $table . self::CHANGES);
        }, (string) config('partitions.detach_lock_timeout', '3s'));
    }

    /**
     * Within keep_old_days of --finish: the rows written to the partitioned table since the swap are copied into the
     * kept plain table, which takes the name back. The partitioned copy is dropped only after an exact count matches.
     *
     * @return array{rows_plain: int, rows_partitioned: int, dropped_partitioned: bool}
     */
    public function rollback(string $table, int $batch = 20000): array
    {
        if ($this->state($table) !== 'swapped') {
            throw new RuntimeException("{$table}: orqaga qaytarish faqat --finish dan keyin, eski jadval ({$table}_m3_old) o'chirilmaguncha mumkin");
        }
        $old = $table . self::OLD;
        $definition = self::definition($table);
        // The unlocked part first: rows changed since the swap
        do {
            $done = DB::transaction(function () use ($table, $old, $batch) {
                DB::statement(sprintf('ALTER TABLE %s DISABLE TRIGGER USER', $old));
                $n = $this->applyChanges($table, $table, $old, $batch);
                DB::statement(sprintf('ALTER TABLE %s ENABLE TRIGGER USER', $old));

                return $n;
            });
        } while ($done > 0);

        $this->manager->withLockRetry(function () use ($table, $old, $definition) {
            DB::statement("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            DB::statement(sprintf('ALTER TABLE %s DISABLE TRIGGER USER', $old));
            while ($this->applyChanges($table, $table, $old, 100000) > 0) {
                // drain
            }
            DB::statement(sprintf('ALTER TABLE %s ENABLE TRIGGER USER', $old));
            $this->assertSameMaxId($table, $old);

            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
            $this->renameConstraints($table, $definition, '', '_m3new');
            DB::statement(sprintf('ALTER SEQUENCE %s_id_seq OWNED BY %s.id', $table, $old));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $table, $table . self::NEW));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $old, $table));
            $this->renameConstraints($table, $definition['plain'], '_m3old', '');
            DB::statement(sprintf('COMMENT ON TABLE %s IS NULL', $table));
        }, (string) config('partitions.detach_lock_timeout', '3s'));

        $plain = (int) DB::selectOne("SELECT count(*) AS n FROM {$table}")->n;
        $partitioned = (int) DB::selectOne(sprintf('SELECT count(*) AS n FROM %s', $table . self::NEW))->n;
        $dropped = false;
        if ($plain === $partitioned) {
            $this->manager->withLockRetry(function () use ($table) {
                DB::statement(sprintf('DROP TABLE %s', $table . self::NEW));
                DB::statement(sprintf('DROP TABLE IF EXISTS %s', $table . self::CHANGES));
            });
            $dropped = true;
        }

        return ['rows_plain' => $plain, 'rows_partitioned' => $partitioned, 'dropped_partitioned' => $dropped];
    }

    /**
     * After keep_old_days: the kept plain table is dropped once it holds no row the partitioned table lacks (an exact
     * count of both up to the old table's last id).
     *
     * @return array{rows_old: int, rows_new: int, days_left: int, dropped: bool}
     */
    public function dropOld(string $table): array
    {
        if ($this->state($table) !== 'swapped') {
            throw new RuntimeException("{$table}: o'chiriladigan eski jadval ({$table}_m3_old) yo'q");
        }
        $old = $table . self::OLD;
        $comment = (string) (DB::selectOne(sprintf("SELECT obj_description('%s'::regclass, 'pg_class') AS c", $old))->c ?? '');
        $swappedAt = preg_match('/swapped_at=(\S+)/', $comment, $m) === 1 ? CarbonImmutable::parse($m[1]) : null;
        $keep = (int) config('partitions.keep_old_days', 7);
        $daysLeft = $swappedAt === null ? 0 : (int) max(0, ceil(now()->diffInSeconds($swappedAt->addDays($keep), false) / 86400));
        $result = ['rows_old' => 0, 'rows_new' => 0, 'days_left' => $daysLeft, 'dropped' => false];
        if ($daysLeft > 0) {
            return $result;
        }

        $maxOld = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$old}")->m;
        $result['rows_old'] = (int) DB::selectOne("SELECT count(*) AS n FROM {$old}")->n;
        $result['rows_new'] = (int) DB::selectOne("SELECT count(*) AS n FROM {$table} WHERE id <= ?", [$maxOld])->n;
        if ($result['rows_old'] !== $result['rows_new']) {
            return $result;
        }

        $this->manager->withLockRetry(function () use ($table, $old) {
            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
            DB::statement(sprintf('DROP TABLE %s', $old));
            DB::statement(sprintf('DROP TABLE IF EXISTS %s', $table . self::CHANGES));
        }, (string) config('partitions.detach_lock_timeout', '3s'));
        $result['dropped'] = true;

        return $result;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Shared steps
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * The first month to create: config first_month, or earlier when the table holds older rows (exact: min of the
     * column, for the inline path; otherwise the row with the lowest id, cheap on a big table: older stragglers land in
     * DEFAULT and ensure() gives them their month).
     */
    private function startMonth(string $table, bool $exact): CarbonImmutable
    {
        $column = PartitionManager::table($table)['column'];
        $oldest = $exact
            ? DB::selectOne("SELECT min({$column}) AS m FROM {$table}")->m
            : DB::selectOne("SELECT {$column} AS m FROM {$table} WHERE {$column} IS NOT NULL ORDER BY id LIMIT 1")?->m;
        $first = PartitionManager::firstMonth();

        return $oldest === null ? $first : min($first, PartitionManager::monthStart(CarbonImmutable::parse($oldest, 'UTC')));
    }

    /**
     * `<t>_m3_new` PARTITION BY RANGE with the months start .. now + months_ahead, DEFAULT and the primary key (under a
     * temporary name until the swap). Nobody else knows the table yet: PARTITION OF is fine here.
     */
    private function createPartitionedTable(string $table, CarbonImmutable $start): void
    {
        $new = $table . self::NEW;
        $definition = self::definition($table);
        $column = PartitionManager::table($table)['column'];
        DB::statement(sprintf('CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS INCLUDING STORAGE INCLUDING COMMENTS) PARTITION BY RANGE (%s)', $new, $table, $column));
        foreach (array_keys($definition['not_null']) as $notNull) {
            DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT CURRENT_TIMESTAMP, ALTER COLUMN %s SET NOT NULL', $new, $notNull, $notNull));
        }
        $end = PartitionManager::monthStart(now())->addMonths((int) config('partitions.months_ahead', 3));
        for ($month = $start; $month <= $end; $month = $month->addMonth()) {
            DB::statement(sprintf(
                "CREATE TABLE %s PARTITION OF %s FOR VALUES FROM ('%s') TO ('%s')",
                PartitionManager::monthName($table, $month), $new, $month->format('Y-m-d H:i:s'), $month->addMonth()->format('Y-m-d H:i:s'),
            ));
        }
        DB::statement(sprintf('CREATE TABLE %s PARTITION OF %s DEFAULT', PartitionManager::defaultName($table), $new));
        [$pkName, $pkColumns] = $definition['pk'];
        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY %s', $new, $pkName . '_m3new', $pkColumns));
    }

    private function copyAll(string $from, string $to): void
    {
        [$columns, $select] = $this->columnLists($from);
        DB::statement(sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $to, $columns, $select, $from));
    }

    /**
     * The column list of the table and the SELECT expressions that fill the new NOT NULL columns.
     *
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function columnLists(string $table): array
    {
        $columns = array_map(fn (object $row) => (string) $row->column_name, DB::select(
            'SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position',
            [$table],
        ));
        $notNull = self::definition($table)['not_null'];
        $select = array_map(fn (string $column) => $notNull[$column] ?? $column, $columns);

        return [implode(', ', $columns), implode(', ', $select), $columns];
    }

    /**
     * Uniques and indexes on the parent (built per partition), foreign keys and triggers; names carry `_m3new` until
     * the swap (index names are schema-wide; the plain table still holds the real ones).
     */
    private function buildPartitionedIndexes(string $table, bool $perPartitionForeignKeys): void
    {
        $new = $table . self::NEW;
        $definition = self::definition($table);
        foreach ($definition['unique'] as $name => $columns) {
            if (!$this->constraintExists($new, $name . '_m3new')) {
                DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s UNIQUE %s', $new, $name . '_m3new', $columns));
            }
        }
        foreach ($definition['indexes'] as $name => $columns) {
            DB::statement(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s %s', $name . '_m3new', $new, $columns));
        }
        foreach ($definition['foreign'] as $name => $sql) {
            if ($this->constraintExists($new, $name)) {
                continue;
            }
            if ($perPartitionForeignKeys) {
                foreach ($this->manager->partitions($new) as $partition) {
                    if (!$this->constraintExists($partition['name'], $name)) {
                        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s %s NOT VALID', $partition['name'], $name, $sql));
                    }
                    DB::statement(sprintf('ALTER TABLE %s VALIDATE CONSTRAINT %s', $partition['name'], $name));
                }
            }
            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s %s', $new, $name, $sql));
        }
    }

    /**
     * The swap (inside the caller's transaction, the live table locked): the plain table's names get `_m3old`, the
     * sequence moves to the new table, the tables trade names, the new names lose `_m3new`, the triggers follow.
     */
    private function swapIn(string $table): void
    {
        $definition = self::definition($table);
        $new = $table . self::NEW;
        $old = $table . self::OLD;
        $this->renameConstraints($table, $definition['plain'], '', '_m3old');
        DB::statement(sprintf('ALTER SEQUENCE %s_id_seq OWNED BY %s.id', $table, $new));
        DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $table, $old));
        DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $new, $table));
        $this->renameConstraints($table, $definition, '_m3new', '');
        foreach ($definition['triggers'] as $name => $sql) {
            DB::statement(sprintf('CREATE TRIGGER %s ' . $sql, $name, $table));
        }
    }

    /**
     * Renames the primary key, uniques and indexes of a definition on $table from name.$from to name.$to (foreign key
     * names are per table and need no rename).
     *
     * @param  array<string, mixed>  $definition
     */
    private function renameConstraints(string $table, array $definition, string $from, string $to): void
    {
        foreach (array_merge([$definition['pk'][0] => true], $definition['unique']) as $name => $_) {
            DB::statement(sprintf('ALTER TABLE %s RENAME CONSTRAINT %s TO %s', $table, $name . $from, $name . $to));
        }
        foreach (array_keys($definition['indexes']) as $name) {
            DB::statement(sprintf('ALTER INDEX %s RENAME TO %s', $name . $from, $name . $to));
        }
    }

    /**
     * Primary key, uniques, indexes and foreign keys of a plain definition on $target (names with $suffix).
     *
     * @param  array<string, mixed>  $definition
     */
    private function addConstraints(string $target, array $definition, string $suffix): void
    {
        [$pkName, $pkColumns] = $definition['pk'];
        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s PRIMARY KEY %s', $target, $pkName . $suffix, $pkColumns));
        foreach ($definition['unique'] as $name => $columns) {
            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s UNIQUE %s', $target, $name . $suffix, $columns));
        }
        foreach ($definition['indexes'] as $name => $columns) {
            DB::statement(sprintf('CREATE INDEX %s ON %s %s', $name . $suffix, $target, $columns));
        }
        foreach ($definition['foreign'] as $name => $sql) {
            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s %s', $target, $name, str_replace('{self}', $target, $sql)));
        }
    }

    /**
     * Takes up to $limit ids out of the changes table and brings those rows of $target up to date with $source: an
     * upsert on the primary key (never delete + insert: on the old fiscal table a delete would null the
     * original_receipt_id of its refunds through the self foreign key), and a delete of the ids $source no longer has.
     * Returns how many ids were taken. A row changed again meanwhile is recorded again by the trigger.
     */
    private function applyChanges(string $table, string $source, string $target, int $limit): int
    {
        $changes = $table . self::CHANGES;
        $ids = array_map(fn (object $row) => (int) $row->id, DB::select(sprintf(
            'DELETE FROM %1$s WHERE id IN (SELECT id FROM %1$s ORDER BY id LIMIT %2$d) RETURNING id',
            $changes, max(1, $limit),
        )));
        if ($ids === []) {
            return 0;
        }
        $array = '{' . implode(',', $ids) . '}';
        [$columns, $select, $list] = $this->columnLists($table);
        if ($this->manager->relkind($target) === 'p') {
            // The new partitioned table (before the swap): no trigger, no foreign key points at it; a row whose
            // backfilled created_at would differ cannot end up twice
            DB::affectingStatement(sprintf('DELETE FROM %s WHERE id = ANY(?::bigint[])', $target), [$array]);
            DB::affectingStatement(sprintf('INSERT INTO %s (%s) SELECT %s FROM %s WHERE id = ANY(?::bigint[])', $target, $columns, $select, $source), [$array]);

            return count($ids);
        }

        $set = implode(', ', array_map(fn (string $column) => "{$column} = EXCLUDED.{$column}", array_values(array_filter($list, fn (string $column) => $column !== 'id'))));
        DB::affectingStatement(sprintf('DELETE FROM %1$s t WHERE t.id = ANY(?::bigint[]) AND NOT EXISTS (SELECT 1 FROM %2$s s WHERE s.id = t.id)', $target, $source), [$array]);
        DB::affectingStatement(sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s WHERE id = ANY(?::bigint[]) ON CONFLICT (id) DO UPDATE SET %s',
            $target, $columns, $columns, $source, $set,
        ), [$array]);

        return count($ids);
    }

    private function assertSameMaxId(string $a, string $b): void
    {
        $maxA = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$a}")->m;
        $maxB = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$b}")->m;
        if ($maxA !== $maxB) {
            throw new RuntimeException("{$a} va {$b} mos emas (oxirgi id {$maxA} / {$maxB}): almashtirish bekor qilindi, hech narsa o'zgarmadi");
        }
    }

    private function installCapture(string $table): void
    {
        $changes = $table . self::CHANGES;
        DB::statement(sprintf('CREATE TABLE IF NOT EXISTS %s (id bigint PRIMARY KEY)', $changes));
        $this->createCaptureTrigger($table, $changes);
    }

    private function createCaptureTrigger(string $table, string $changes): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION partition_capture_changes() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    EXECUTE format('INSERT INTO %I (id) VALUES ($1) ON CONFLICT DO NOTHING', TG_ARGV[0]) USING OLD.id;
                ELSE
                    EXECUTE format('INSERT INTO %I (id) VALUES ($1) ON CONFLICT DO NOTHING', TG_ARGV[0]) USING NEW.id;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql;
            SQL);
        DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
        DB::statement(sprintf(
            "CREATE TRIGGER %s AFTER INSERT OR UPDATE OR DELETE ON %s FOR EACH ROW EXECUTE FUNCTION partition_capture_changes('%s')",
            $table . self::CAPTURE, $table, $changes,
        ));
    }

    /**
     * A failed earlier inline attempt rolled back completely; a leftover of an online attempt means the operator must
     * finish or roll it back first.
     */
    private function dropLeftovers(string $table): void
    {
        foreach ([self::NEW, self::OLD] as $suffix) {
            if ($this->manager->exists($table . $suffix)) {
                throw new RuntimeException("{$table}{$suffix} mavjud: onlayn konversiya tugallanmagan. Avval `php artisan db:partitions:convert {$table} --finish` (yoki --rollback) bajaring.");
            }
        }
    }

    private function constraintExists(string $table, string $name): bool
    {
        return DB::selectOne('SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?) AS e', [$table, $name])->e;
    }
}
