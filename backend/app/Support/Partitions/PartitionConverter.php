<?php

namespace App\Support\Partitions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md section 4): turns a plain journal table into a table partitioned by month and back,
 * inline, in ONE transaction (the partition_journals migration's up() and down()): the table locked EXCLUSIVE (reads
 * go on, writes wait for the whole conversion), the partitioned copy built beside it, every row copied, the content of
 * both compared (by column name, every row), every user trigger carried over and checked, the tables swapped, the old
 * one dropped. Any difference or failure rolls everything back: nothing changes.
 *
 * Above partitions.inline_max_rows the migration refuses and says how long the conversion would lock the table; the
 * operator runs it in a quiet window with PARTITIONS_INLINE_MAX_ROWS raised (tills sell offline meanwhile). There is
 * no online converter (built, reviewed three times and removed in M3: docs/scale/M3_DESIGN.md section 4).
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
     * The partitioned schema of each table, and 'plain': the schema before M3 (down() restores it).
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

    // The partitioned copy while it is built, and the plain table while it is replaced (both inside the one transaction)
    private const NEW = '_m3_inline';

    private const OLD = '_m3_replaced';

    // Measured on the dev container (PostgreSQL 16, 4 vCPU): 190 000 rows of audit_logs or fiscal_receipts converted in
    // 1.25-1.4 s including indexes, foreign keys and the content check; the refusal's estimate uses this rate
    public const ROWS_PER_SECOND = 140000;

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
     * The inline limit: PARTITIONS_INLINE_MAX_ROWS from the process environment first (the operator's one-off override
     * for a quiet window works with a cached config too), then config partitions.inline_max_rows.
     */
    public static function inlineLimit(): int
    {
        $override = getenv('PARTITIONS_INLINE_MAX_ROWS');

        return $override !== false && ctype_digit(trim($override)) ? (int) trim($override) : (int) config('partitions.inline_max_rows', 200000);
    }

    /**
     * Exact row count up to $limit + 1 (enough to compare with the inline threshold without reading a big table).
     */
    public function countUpTo(string $table, int $limit): int
    {
        return (int) DB::selectOne(sprintf('SELECT count(*) AS n FROM (SELECT 1 FROM %s LIMIT %d) s', $table, $limit + 1))->n;
    }

    /**
     * The refusal above the limit: the honest rule, with the table's real size and the lock time it means.
     */
    public function refusalMessage(string $table): string
    {
        $rows = (int) DB::selectOne("SELECT count(*) AS n FROM {$table}")->n;
        $seconds = self::estimateSeconds($rows);
        $override = (int) (ceil($rows * 1.1 / 1000) * 1000);

        return "{$table} jadvalida {$rows} qator bor: uni oylik bo'limlarga o'tkazish shu jadvalga yozishni taxminan {$seconds} soniya to'xtatadi "
            . '(o\'qish davom etadi). Uni tinch vaqtda (kechasi) bajaring: '
            . "`PARTITIONS_INLINE_MAX_ROWS={$override} php artisan migrate`. Shu vaqt ichida kassalar oflayn rejimda sotishda davom etadi, "
            . "yozuvlar keyin yuboriladi. Jadval band bo'lsa (uzoq tranzaksiya) migratsiya kutib turmaydi: xato beradi, keyinroq qayta bajaring.";
    }

    public static function estimateSeconds(int $rows): int
    {
        return max(1, (int) ceil($rows / self::ROWS_PER_SECOND));
    }

    /**
     * The migration's up() for one table. A no-op when already partitioned; refuses above the inline limit.
     *
     * Locks, all taken up front and bounded (PartitionManager::boundedLock: given up and retried rather than queued
     * behind a long transaction): the referenced tables (SHARE ROW EXCLUSIVE: the new foreign keys are validated
     * against them; a till's foreign key check does not wait for it), the table (EXCLUSIVE: writes wait for the whole
     * conversion, about rows / ROWS_PER_SECOND seconds), then ACCESS EXCLUSIVE on the table and the referenced tables
     * just for the swap and the drop (milliseconds).
     */
    public function convertInline(string $table): bool
    {
        if ($this->manager->isPartitioned($table)) {
            return false;
        }
        $limit = self::inlineLimit();
        if ($this->countUpTo($table, $limit) > $limit) {
            throw new RuntimeException($this->refusalMessage($table));
        }

        $this->manager->withLockRetry(function () use ($table) {
            $referenced = $this->manager->referencedTables($table);
            if ($referenced !== []) {
                $this->manager->boundedLock('LOCK TABLE ' . implode(', ', $referenced) . ' IN SHARE ROW EXCLUSIVE MODE');
            }
            $this->manager->boundedLock("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            // Every user trigger of the plain table follows it (read now, created after the copy: a BEFORE INSERT
            // trigger such as fiscal_receipt_keys_insert must not fire for the rows being moved)
            $triggers = $this->manager->userTriggers($table);
            $new = $table . self::NEW;
            $this->createPartitionedTable($table, $this->monthsToCreate($table));
            [$columns, $select] = $this->columnLists($table);
            DB::statement(sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $new, $columns, $select, $table));
            $this->buildPartitionedIndexes($table);
            $this->assertSameContent($table, $new, withBackfill: true);

            $this->manager->boundedLock('LOCK TABLE ' . implode(', ', array_merge([$table], $referenced)) . ' IN ACCESS EXCLUSIVE MODE');
            $this->swapIn($table, $triggers);
            DB::statement(sprintf('DROP TABLE %s', $table . self::OLD));
        });

        return true;
    }

    /**
     * The migration's down() for one table: the plain table with the constraints it had before M3 (same locking and
     * the same content check as convertInline).
     */
    public function revertInline(string $table): bool
    {
        if ($this->manager->relkind($table) !== 'p') {
            return false;
        }
        $limit = self::inlineLimit();
        if ($this->countUpTo($table, $limit) > $limit) {
            throw new RuntimeException($this->refusalMessage($table));
        }

        $this->manager->withLockRetry(function () use ($table) {
            $referenced = $this->manager->referencedTables($table);
            if ($referenced !== []) {
                $this->manager->boundedLock('LOCK TABLE ' . implode(', ', $referenced) . ' IN SHARE ROW EXCLUSIVE MODE');
            }
            $this->manager->boundedLock("LOCK TABLE {$table} IN EXCLUSIVE MODE");
            $plain = $table . '_m3_plain';
            $definition = self::definition($table);
            $triggers = $this->manager->userTriggers($table);
            DB::statement(sprintf('DROP TABLE IF EXISTS %s', $plain));
            DB::statement(sprintf('CREATE TABLE %s (LIKE %s INCLUDING DEFAULTS INCLUDING STORAGE INCLUDING COMMENTS)', $plain, $table));
            foreach ($definition['plain']['nullable'] as $column) {
                DB::statement(sprintf('ALTER TABLE %s ALTER COLUMN %s DROP NOT NULL, ALTER COLUMN %s DROP DEFAULT', $plain, $column, $column));
            }
            [$columns] = $this->columnLists($table);
            DB::statement(sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $plain, $columns, $columns, $table));
            $this->addConstraints($plain, $definition['plain'], '_m3plain');
            $this->assertSameContent($table, $plain, withBackfill: false);

            // Swap: the partitioned table and its partitions go (every row is in the plain copy now)
            $this->manager->boundedLock('LOCK TABLE ' . implode(', ', array_merge([$table], $referenced)) . ' IN ACCESS EXCLUSIVE MODE');
            DB::statement(sprintf('ALTER SEQUENCE %s_id_seq OWNED BY %s.id', $table, $plain));
            DB::statement(sprintf('DROP TABLE %s', $table));
            DB::statement(sprintf('ALTER TABLE %s RENAME TO %s', $plain, $table));
            $this->renameConstraints($table, $definition['plain'], '_m3plain', '');
            // After the copy, as in convertInline(): the triggers of the partitioned table, checked
            $this->manager->copyTriggers($triggers, $table);
        });

        return true;
    }

    /**
     * Every row of $from is in $to with the same content: per row an md5 of the row as jsonb (keyed by column NAME, so
     * column order does not matter), aggregated in id order, and the row count. $withBackfill: the NOT NULL columns of
     * the new table (fiscal created_at) are compared with the value the copy computed for them. Inside the conversion's
     * transaction: a difference aborts it, nothing changes.
     */
    private function assertSameContent(string $from, string $to, bool $withBackfill): void
    {
        $patch = '';
        if ($withBackfill) {
            $parts = [];
            foreach (self::definition($from)['not_null'] as $column => $expression) {
                $parts[] = "'{$column}', {$expression}";
            }
            $patch = $parts === [] ? '' : ' || jsonb_build_object(' . implode(', ', $parts) . ')';
        }
        $hash = fn (string $relation, string $extra) => DB::selectOne(sprintf(
            "SELECT count(*) AS n, coalesce(md5(string_agg(md5((to_jsonb(x)%s)::text), '' ORDER BY id)), '') AS h FROM %s x",
            $extra, $relation,
        ));
        $a = $hash($from, $patch);
        $b = $hash($to, '');
        if ((int) $a->n !== (int) $b->n || $a->h !== $b->h) {
            throw new RuntimeException("{$from}: nusxa asl jadval bilan mos emas ({$a->n} / {$b->n} qator, mazmuni " . ($a->h === $b->h ? 'bir xil' : 'farq qiladi') . "): o'tkazish bekor qilindi, hech narsa o'zgarmadi");
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
     * The partitioned copy (PARTITION BY RANGE) with the given months, DEFAULT and the primary key (under a temporary
     * name until the swap). Nobody else knows the table yet: PARTITION OF is fine here.
     *
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
    private function buildPartitionedIndexes(string $table): void
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
            DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s %s', $new, $name, $sql));
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

    private function constraintExists(string $table, string $name): bool
    {
        return DB::selectOne('SELECT EXISTS (SELECT 1 FROM pg_constraint WHERE conrelid = ?::regclass AND conname = ?) AS e', [$table, $name])->e;
    }
}
