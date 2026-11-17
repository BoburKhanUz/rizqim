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

    // Ids per range of the content comparison (finish, rollback, drop-old)
    private const BUCKET = 10000;

    // The unlocked catch-up stops when this few changes are left (copied under the lock of --finish) or after so many
    // passes
    private const CATCH_UP_LEFT = 1000;

    private const CATCH_UP_PASSES = 50;

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
            // Every user trigger of the plain table follows it (read now, created after the copy: a BEFORE INSERT
            // trigger such as fiscal_receipt_keys_insert must not fire for the rows being moved)
            $triggers = $this->manager->userTriggers($table, [$table . self::CAPTURE]);
            $this->createPartitionedTable($table, $this->monthsToCreate($table));
            $this->copyAll($table, $table . self::NEW);
            $this->buildPartitionedIndexes($table, perPartitionForeignKeys: false);
            $this->swapIn($table, $triggers);
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
            $triggers = $this->manager->userTriggers($table, [$table . self::CAPTURE]);
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
            // After the copy, as in convertInline(): the triggers of the partitioned table, checked
            $this->manager->copyTriggers($triggers, $table);
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
     * is recorded and a row committed late with a lower id is never missed. Then the NOT NULL backfill (fiscal
     * created_at) is written into the live table itself, in batches, so the old and the new table agree on it (a
     * rollback brings back receipts FiscalService finds by id and created_at).
     */
    public function prepare(string $table): void
    {
        if ($this->state($table) !== 'plain') {
            return;
        }
        $months = $this->monthsToCreate($table);
        $this->manager->withLockRetry(function () use ($table, $months) {
            $this->manager->boundedLock("LOCK TABLE {$table} IN SHARE ROW EXCLUSIVE MODE");
            $this->installCapture($table);
            $this->createPartitionedTable($table, $months);
        }, (string) config('partitions.lock_timeout', '2s'));
        $this->backfillNotNull($table);
    }

    /**
     * Step 2: batches of `WHERE id > max(id) copied ORDER BY id LIMIT batch`, one commit each, up to the live table's
     * last id when the step started (rows written later reach the new table through the change log: a busy table
     * never keeps this loop running). Killed at any point, the next run continues from the last committed batch.
     * $onBatch(copied, maxId) is called after each commit.
     *
     * @param  (callable(int, int): void)|null  $onBatch
     */
    public function copy(string $table, int $batch = 20000, ?callable $onBatch = null): int
    {
        $new = $table . self::NEW;
        [$columns, $select] = $this->columnLists($table);
        $until = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$table}")->m;
        $total = 0;
        while (true) {
            $copied = DB::transaction(fn () => DB::affectingStatement(sprintf(
                'INSERT INTO %1$s (%2$s) SELECT %3$s FROM %4$s WHERE id > (SELECT coalesce(max(id), 0) FROM %1$s) AND id <= %6$d ORDER BY id LIMIT %5$d',
                $new, $columns, $select, $table, max(1, $batch), $until,
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
     * Rows changed since they were copied, re-copied in batches without a lock, until the change log is short (at most
     * CATCH_UP_LEFT entries, or after CATCH_UP_PASSES passes): what is left is copied under the lock of --finish. A
     * table written all the time never keeps this loop running.
     */
    public function catchUp(string $table, int $batch = 20000, string $target = ''): int
    {
        $target = $target === '' ? $table . self::NEW : $target;
        $total = 0;
        for ($pass = 0; $pass < self::CATCH_UP_PASSES; $pass++) {
            $done = DB::transaction(fn () => $this->applyChanges($table, $table, $target, $batch));
            $total += $done;
            if ($done === 0 || $this->pendingChanges($table) <= self::CATCH_UP_LEFT) {
                break;
            }
        }

        return $total;
    }

    /**
     * Step 4. Unlocked: the last batches, indexes, months, the catch-up and a content comparison of both tables per
     * range of ids. Then, under `LOCK TABLE <t> IN EXCLUSIVE MODE` (reads go on; it waits until every writer of the
     * table has committed, so the change log is complete): the rest of the log, rows above the copy mark, the ranges
     * that differed or were touched compared again (any difference refuses the swap and names the ids), then ACCESS
     * EXCLUSIVE for the renames. Every lock wait is bounded (lock_timeout and statement_timeout), retried, given up.
     * `<t>_m3_old` is kept; from now on the changes of the new table are recorded for --rollback.
     */
    public function finish(string $table, int $batch = 20000): void
    {
        if ($this->state($table) !== 'copying') {
            throw new RuntimeException("{$table}: tugatiladigan ko'chirish yo'q (holat: {$this->state($table)})");
        }
        $new = $table . self::NEW;
        $this->copy($table, $batch);
        $this->build($table);
        $this->manager->ensure($table, $new);
        $this->backfillNotNull($table);
        $this->catchUp($table, $batch);
        $mark = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$new}")->m;
        $differing = $this->differingBuckets($table, $new, $mark);

        $this->manager->withLockRetry(function () use ($table, $new, $mark, $differing) {
            $this->manager->boundedLock("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            $touched = [];
            while ($this->applyChanges($table, $table, $new, 100000, $touched) > 0) {
                // drain: the log is complete (no writer of the table is in flight)
            }
            [$columns, $select] = $this->columnLists($table);
            DB::statement(sprintf('INSERT INTO %1$s (%2$s) SELECT %3$s FROM %4$s WHERE id > (SELECT coalesce(max(id), 0) FROM %1$s)', $new, $columns, $select, $table));
            $this->assertSameMaxId($table, $new);
            $max = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$table}")->m;
            $recheck = array_unique(array_merge(
                $differing,
                array_map(fn (int $id) => intdiv($id, self::BUCKET), $touched),
                range(intdiv($mark, self::BUCKET), intdiv($max, self::BUCKET)),
            ));
            $still = $this->differingBuckets($table, $new, $max, $recheck);
            if ($still !== []) {
                throw new RuntimeException("{$table}: yangi jadval eskisi bilan mos emas (id: " . implode(', ', $this->differingIds($table, $new, $still, $max)) . "): almashtirish bekor qilindi, hech narsa o'zgarmadi");
            }

            $this->manager->boundedLock("LOCK TABLE {$table}, {$new} IN ACCESS EXCLUSIVE MODE");
            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
            $this->swapIn($table, $this->manager->userTriggers($table, [$table . self::CAPTURE]));
            DB::statement(sprintf("COMMENT ON TABLE %s IS 'M3 swapped_at=%s'", $table . self::OLD, now()->utc()->toIso8601ZuluString()));
            // --rollback copies back what is written from now on
            $this->createCaptureTrigger($table, $table . self::CHANGES);
        }, (string) config('partitions.detach_lock_timeout', '3s'));
    }

    /**
     * Within keep_old_days of --finish: the rows written to the partitioned table since the swap are copied into the
     * kept plain table, which takes the name back (same locking as --finish). The partitioned copy is dropped only when
     * the content of both tables is the same.
     *
     * @return array{rows_plain: int, rows_partitioned: int, dropped_partitioned: bool, differing_ids: list<int>}
     */
    public function rollback(string $table, int $batch = 20000): array
    {
        if ($this->state($table) !== 'swapped') {
            throw new RuntimeException("{$table}: orqaga qaytarish faqat --finish dan keyin, eski jadval ({$table}_m3_old) o'chirilmaguncha mumkin");
        }
        $old = $table . self::OLD;
        $definition = self::definition($table);
        // The unlocked part first: rows changed since the swap (the old table's own triggers are dropped: it is not
        // live, it gets exactly the live table's triggers at the swap)
        foreach (array_keys($this->manager->userTriggers($old)) as $stale) {
            $this->manager->withLockRetry(fn () => DB::statement(sprintf('DROP TRIGGER %s ON %s', $stale, $old)));
        }
        $this->catchUp($table, $batch, $old);

        $this->manager->withLockRetry(function () use ($table, $old, $definition) {
            $this->manager->boundedLock("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            while ($this->applyChanges($table, $table, $old, 100000) > 0) {
                // drain
            }
            $this->assertSameMaxId($table, $old);

            $this->manager->boundedLock("LOCK TABLE {$table}, {$old} IN ACCESS EXCLUSIVE MODE");
            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
            // The live table's triggers are the truth (e.g. fiscal_receipt_keys_* added after --finish): the plain
            // table gets exactly them, after its rows are in step (with them on, the keys insert trigger would refuse
            // every re-copied row, whose key row already exists)
            $triggers = $this->manager->userTriggers($table, [$table . self::CAPTURE]);
            $this->renameConstraints($table, $definition, '', '_m3new');
            DB::statement(sprintf('ALTER SEQUENCE %s_id_seq OWNED BY %s.id', $table, $old));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $table, $table . self::NEW));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $old, $table));
            $this->renameConstraints($table, $definition['plain'], '_m3old', '');
            $this->manager->copyTriggers($triggers, $table);
            DB::statement(sprintf('COMMENT ON TABLE %s IS NULL', $table));
        }, (string) config('partitions.detach_lock_timeout', '3s'));

        $new = $table . self::NEW;
        $plain = (int) DB::selectOne("SELECT count(*) AS n FROM {$table}")->n;
        $partitioned = (int) DB::selectOne("SELECT count(*) AS n FROM {$new}")->n;
        $max = max((int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$table}")->m, (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$new}")->m);
        $differing = $this->differingBuckets($table, $new, $max);
        $ids = $differing === [] ? [] : $this->differingIds($table, $new, $differing, $max);
        $dropped = false;
        if ($plain === $partitioned && $ids === []) {
            $this->manager->withLockRetry(function () use ($new, $table) {
                DB::statement(sprintf('DROP TABLE %s', $new));
                DB::statement(sprintf('DROP TABLE IF EXISTS %s', $table . self::CHANGES));
            });
            $dropped = true;
        }

        return ['rows_plain' => $plain, 'rows_partitioned' => $partitioned, 'dropped_partitioned' => $dropped, 'differing_ids' => $ids];
    }

    /**
     * After keep_old_days: the kept plain table is dropped once its content equals the partitioned table's up to its
     * last id (a checksum of every column per range of ids; on a difference nothing is dropped and the ids are named).
     *
     * @return array{rows_old: int, rows_new: int, days_left: int, dropped: bool, differing_ids: list<int>}
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
        $result = ['rows_old' => 0, 'rows_new' => 0, 'days_left' => $daysLeft, 'dropped' => false, 'differing_ids' => []];
        if ($daysLeft > 0) {
            return $result;
        }

        $maxOld = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$old}")->m;
        $result['rows_old'] = (int) DB::selectOne("SELECT count(*) AS n FROM {$old}")->n;
        $result['rows_new'] = (int) DB::selectOne("SELECT count(*) AS n FROM {$table} WHERE id <= ?", [$maxOld])->n;
        $differing = $this->differingBuckets($old, $table, $maxOld);
        if ($differing !== []) {
            $result['differing_ids'] = $this->differingIds($old, $table, $differing, $maxOld);
        }
        if ($result['rows_old'] !== $result['rows_new'] || $result['differing_ids'] !== []) {
            return $result;
        }

        $this->manager->withLockRetry(function () use ($table, $old) {
            $this->manager->boundedLock("LOCK TABLE {$table}, {$old} IN ACCESS EXCLUSIVE MODE");
            $triggers = $this->manager->userTriggers($table, [$table . self::CAPTURE]);
            DB::statement(sprintf('DROP TRIGGER IF EXISTS %s ON %s', $table . self::CAPTURE, $table));
            $this->manager->assertTriggers($triggers, $table);
        }, (string) config('partitions.detach_lock_timeout', '3s'));
        // The old table's foreign keys go one by one (each takes ACCESS EXCLUSIVE on the referenced table for an
        // instant, bounded), then the table itself, which no longer references anything
        $this->manager->dropForeignKeys($old);
        $this->manager->withLockRetry(function () use ($table, $old) {
            $this->manager->boundedLock("LOCK TABLE {$old} IN ACCESS EXCLUSIVE MODE");
            DB::statement(sprintf('DROP TABLE %s', $old));
            DB::statement(sprintf('DROP TABLE IF EXISTS %s', $table . self::CHANGES));
        }, (string) config('partitions.detach_lock_timeout', '3s'));
        $result['dropped'] = true;

        return $result;
    }

    /**
     * Ranges of BUCKET ids (id / BUCKET) whose content differs between $a and $b, up to id $max: per range the row
     * count and an md5 over the md5 of every row (all columns, as JSON) in id order. $only limits it to some ranges.
     *
     * @param  list<int>|null  $only
     * @return list<int>
     */
    public function differingBuckets(string $a, string $b, int $max, ?array $only = null): array
    {
        if ($only === []) {
            return [];
        }
        $filter = $only === null ? '' : ' AND id / ' . self::BUCKET . ' = ANY(\'{' . implode(',', array_map('intval', $only)) . '}\'::bigint[])';
        $hashes = function (string $relation) use ($max, $filter): array {
            $rows = DB::select(sprintf(
                "SELECT id / %d AS b, count(*) AS n, md5(string_agg(md5(row_to_json(x)::text), '' ORDER BY id)) AS h FROM %s x WHERE id <= %d%s GROUP BY 1",
                self::BUCKET, $relation, $max, $filter,
            ));
            $map = [];
            foreach ($rows as $row) {
                $map[(int) $row->b] = $row->n . ':' . $row->h;
            }

            return $map;
        };
        $left = $hashes($a);
        $right = $hashes($b);
        $differing = [];
        foreach (array_unique(array_merge(array_keys($left), array_keys($right))) as $bucket) {
            if (($left[$bucket] ?? null) !== ($right[$bucket] ?? null)) {
                $differing[] = (int) $bucket;
            }
        }
        sort($differing);

        return $differing;
    }

    /**
     * The first 20 ids whose row differs (or exists on one side only) in the given ranges.
     *
     * @param  list<int>  $buckets
     * @return list<int>
     */
    public function differingIds(string $a, string $b, array $buckets, int $max): array
    {
        $filter = 'id <= ' . $max . ' AND id / ' . self::BUCKET . ' = ANY(\'{' . implode(',', array_map('intval', $buckets)) . '}\'::bigint[])';

        return array_map(fn (object $row) => (int) $row->id, DB::select(sprintf(
            'SELECT coalesce(l.id, r.id) AS id FROM (SELECT id, md5(row_to_json(x)::text) AS h FROM %1$s x WHERE %3$s) l
             FULL JOIN (SELECT id, md5(row_to_json(y)::text) AS h FROM %2$s y WHERE %3$s) r ON r.id = l.id
             WHERE l.h IS DISTINCT FROM r.h ORDER BY 1 LIMIT 20',
            $a, $b, $filter,
        )));
    }

    /**
     * The NOT NULL backfill (fiscal created_at) written into the plain live table, in committed batches, or only for
     * the given ids. The change capture sees these updates: the new table gets the same values.
     *
     * @param  list<int>|null  $ids
     */
    private function backfillNotNull(string $table, ?array $ids = null): void
    {
        $notNull = self::definition($table)['not_null'];
        if ($notNull === [] || $this->manager->relkind($table) !== 'r') {
            return;
        }
        foreach ($notNull as $column => $expression) {
            if ($ids !== null) {
                if ($ids !== []) {
                    DB::affectingStatement(sprintf('UPDATE %s SET %s = %s WHERE id = ANY(?::bigint[]) AND %s IS NULL', $table, $column, $expression, $column), ['{' . implode(',', $ids) . '}']);
                }

                continue;
            }
            $max = (int) DB::selectOne("SELECT coalesce(max(id), 0) AS m FROM {$table}")->m;
            for ($from = 0; $from < $max; $from += 20000) {
                DB::transaction(fn () => DB::affectingStatement(sprintf(
                    'UPDATE %s SET %s = %s WHERE %s IS NULL AND id > %d AND id <= %d',
                    $table, $column, $expression, $column, $from, $from + 20000,
                )));
            }
        }
    }

    private function pendingChanges(string $table): int
    {
        return (int) DB::selectOne(sprintf('SELECT count(*) AS n FROM (SELECT 1 FROM %s LIMIT %d) c', $table . self::CHANGES, self::CATCH_UP_LEFT + 1))->n;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Shared steps
    // ---------------------------------------------------------------------------------------------------------------

    /**
     * The months to create: config first_month .. now + months_ahead, plus, before first_month, only the months that
     * hold rows (one row from 2019 makes one month, not 90 empty ones). Those old months are archived like any other
     * once older than online_months.
     *
     * @return list<CarbonImmutable>
     */
    private function monthsToCreate(string $table): array
    {
        $column = PartitionManager::table($table)['column'];
        $first = PartitionManager::firstMonth();
        $months = array_map(
            fn (object $row) => CarbonImmutable::parse($row->m, 'UTC'),
            DB::select(sprintf("SELECT DISTINCT to_char(date_trunc('month', %s), 'YYYY-MM-DD') AS m FROM %s WHERE %s < ? ORDER BY 1", $column, $table, $column), [$first->format('Y-m-d H:i:s')]),
        );
        $end = PartitionManager::monthStart(now())->addMonths((int) config('partitions.months_ahead', 3));
        for ($month = $first; $month <= $end; $month = $month->addMonth()) {
            $months[] = $month;
        }

        return $months;
    }

    /**
     * `<t>_m3_new` PARTITION BY RANGE with the months start .. now + months_ahead, DEFAULT and the primary key (under a
     * temporary name until the swap). Nobody else knows the table yet: PARTITION OF is fine here.
     */
    /**
     * @param  list<CarbonImmutable>  $months
     */
    private function createPartitionedTable(string $table, array $months): void
    {
        $new = $table . self::NEW;
        $definition = self::definition($table);
        $column = PartitionManager::table($table)['column'];
        DB::statement(sprintf('CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS INCLUDING STORAGE INCLUDING COMMENTS) PARTITION BY RANGE (%s)', $new, $table, $column));
        foreach (array_keys($definition['not_null']) as $notNull) {
            DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT CURRENT_TIMESTAMP, ALTER COLUMN %s SET NOT NULL', $new, $notNull, $notNull));
        }
        foreach ($months as $month) {
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
     * sequence moves to the new table, the tables trade names, the new names lose `_m3new`, and EVERY user trigger of
     * the replaced table ($triggers, read from pg_trigger) is created on the new one and checked: on a partitioned
     * parent Postgres clones them to every partition, also to months attached later.
     *
     * @param  array<string, array{definition: string, enabled: string}>  $triggers
     */
    private function swapIn(string $table, array $triggers): void
    {
        $definition = self::definition($table);
        $new = $table . self::NEW;
        $old = $table . self::OLD;
        $this->renameConstraints($table, $definition['plain'], '', '_m3old');
        DB::statement(sprintf('ALTER SEQUENCE %s_id_seq OWNED BY %s.id', $table, $new));
        DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $table, $old));
        DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $new, $table));
        $this->renameConstraints($table, $definition, '_m3new', '');
        $this->manager->copyTriggers($triggers, $table);
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
     * Takes up to $limit entries of the change log (append-only, one entry per write, by its own sequence) and brings
     * those rows of $target up to date with $source. Only the entries read are deleted: a write still in flight has
     * its own entry, invisible now, which stays and is applied by a later pass or under the lock of --finish (LOCK
     * waits for every writer to commit). Into the new partitioned table: delete + insert (no trigger there yet, no
     * foreign key points at it); into the plain table (rollback): an upsert on id (a delete would null the
     * original_receipt_id of refunds through its self foreign key) and a delete of ids $source no longer has.
     * Returns how many entries were taken; $touched collects the ids.
     *
     * @param  list<int>  $touched
     */
    private function applyChanges(string $table, string $source, string $target, int $limit, array &$touched = []): int
    {
        $changes = $table . self::CHANGES;
        $entries = DB::select(sprintf('SELECT seq, id FROM %s ORDER BY seq LIMIT %d', $changes, max(1, $limit)));
        if ($entries === []) {
            return 0;
        }
        $ids = array_values(array_unique(array_map(fn (object $row) => (int) $row->id, $entries)));
        $seqs = '{' . implode(',', array_map(fn (object $row) => (int) $row->seq, $entries)) . '}';
        $array = '{' . implode(',', $ids) . '}';
        [$columns, $select, $list] = $this->columnLists($table);
        if ($this->manager->relkind($target) === 'p') {
            // A row written without the NOT NULL value meanwhile gets it in the live table first (both agree)
            $this->backfillNotNull($source, $ids);
            DB::affectingStatement(sprintf('DELETE FROM %s WHERE id = ANY(?::bigint[])', $target), [$array]);
            DB::affectingStatement(sprintf('INSERT INTO %s (%s) SELECT %s FROM %s WHERE id = ANY(?::bigint[])', $target, $columns, $select, $source), [$array]);
        } else {
            $set = implode(', ', array_map(fn (string $column) => "{$column} = EXCLUDED.{$column}", array_values(array_filter($list, fn (string $column) => $column !== 'id'))));
            DB::affectingStatement(sprintf('DELETE FROM %1$s t WHERE t.id = ANY(?::bigint[]) AND NOT EXISTS (SELECT 1 FROM %2$s s WHERE s.id = t.id)', $target, $source), [$array]);
            DB::affectingStatement(sprintf(
                'INSERT INTO %s (%s) SELECT %s FROM %s WHERE id = ANY(?::bigint[]) ON CONFLICT (id) DO UPDATE SET %s',
                $target, $columns, $columns, $source, $set,
            ), [$array]);
        }
        DB::affectingStatement(sprintf('DELETE FROM %s WHERE seq = ANY(?::bigint[])', $changes), [$seqs]);
        array_push($touched, ...$ids);

        return count($entries);
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
        // Append-only: every write adds an entry (no ON CONFLICT: an entry already there must never swallow a later
        // change of the same row)
        DB::statement(sprintf('CREATE TABLE IF NOT EXISTS %s (seq bigserial PRIMARY KEY, id bigint NOT NULL)', $changes));
        if (!(bool) DB::selectOne("SELECT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = 'seq') AS e", [$changes])->e) {
            throw new RuntimeException("{$changes}: eski ko'rinishdagi o'zgarishlar jadvali: avval `php artisan db:partitions:convert {$table} --rollback` yoki ko'chirishni boshidan boshlang");
        }
        $this->createCaptureTrigger($table, $changes);
    }

    private function createCaptureTrigger(string $table, string $changes): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION partition_capture_changes() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    EXECUTE format('INSERT INTO %I (id) VALUES ($1)', TG_ARGV[0]) USING OLD.id;
                ELSE
                    EXECUTE format('INSERT INTO %I (id) VALUES ($1)', TG_ARGV[0]) USING NEW.id;
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
