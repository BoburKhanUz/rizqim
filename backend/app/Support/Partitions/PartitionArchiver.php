<?php

namespace App\Support\Partitions;

use Illuminate\Support\Facades\DB;

/**
 * Wave M3 (docs/scale/M3_DESIGN.md section 5): months older than online_months leave the live tables.
 *
 * Status machine of a partition_archives row (one per partition):
 *   detached  - this class: DETACH PARTITION (lock_timeout, retried), its foreign keys dropped (a detached month must not
 *               block deleting a user or a store, nor be cascaded by it), the append-only trigger kept on audit months;
 *   uploaded  - scripts/archive-partitions.sh: pg_dump, sha256, copy made (off-site upload not yet verified, or only a
 *               local copy because S3 is not configured);
 *   verified  - the script downloaded the off-site copy back, checked sha256 and pg_restore --list;
 *   dropped   - this class, on a later run: DROP TABLE, only for `verified` rows older than drop_after_days (or, with
 *               ARCHIVE_ALLOW_LOCAL_ONLY=1, `uploaded` ones), never without a sha256 and a storage_uri;
 *   failed    - the script, with error; held - attached again by hand (skipped).
 * Tables with archive => false (notifications) are detached and dropped in one step (a `dropped` row records it).
 */
final class PartitionArchiver
{
    public function __construct(private readonly PartitionManager $manager) {}

    /**
     * Phase 3 (drops) first, then phase 1 (detaches). Each step is idempotent; a run killed half-way is finished by the
     * next one.
     *
     * @return array{dropped: list<string>, detached: list<string>, deleted: list<string>, kept: list<string>, skipped: list<string>}
     */
    public function run(): array
    {
        [$dropped, $kept] = $this->dropArchived();
        $report = ['dropped' => $dropped, 'detached' => [], 'deleted' => [], 'kept' => $kept, 'skipped' => []];
        $this->fillCounts();
        foreach (PartitionManager::tables() as $table => $config) {
            if ($this->manager->conversionState($table) !== 'none') {
                // The online conversion keeps an old or a new copy of the table: no month leaves before it is settled
                $report['skipped'][] = $table;

                continue;
            }
            foreach ($this->manager->dueForArchive($table) as $partition) {
                if ($config['archive']) {
                    $this->detach($table, $partition);
                    $report['detached'][] = $partition['name'];
                } else {
                    $this->detachAndDrop($table, $partition);
                    $report['deleted'][] = $partition['name'];
                }
            }
        }
        $this->fillCounts();

        return $report;
    }

    /**
     * @param  array{name: string, from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $partition
     */
    public function detach(string $table, array $partition): void
    {
        $name = $partition['name'];
        $this->manager->withLockRetry(function () use ($table, $name, $partition) {
            // Not CONCURRENTLY: refused while a DEFAULT partition exists. AccessExclusive on the parent for milliseconds
            DB::statement(sprintf('ALTER TABLE %s DETACH PARTITION %s', $table, $name));
            foreach (DB::select("SELECT conname FROM pg_constraint WHERE conrelid = ?::regclass AND contype = 'f'", [$name]) as $fk) {
                DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT %s', $name, $fk->conname));
            }
            // DETACH removes the cloned triggers: the month stays append-only until it is dropped (DROP fires none)
            foreach (PartitionConverter::DEFINITIONS[$table]['triggers'] as $trigger => $sql) {
                DB::statement(sprintf('CREATE TRIGGER %s ' . $sql, $trigger, $name));
            }
            $now = now();
            DB::table('partition_archives')->updateOrInsert(['partition_name' => $name], [
                'table_name' => $table,
                'range_from' => $partition['from'],
                'range_to' => $partition['to'],
                'status' => 'detached',
                'detached_at' => $now,
                'error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, (string) config('partitions.detach_lock_timeout', '3s'));
    }

    /**
     * @param  array{name: string, from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $partition
     */
    public function detachAndDrop(string $table, array $partition): void
    {
        $name = $partition['name'];
        $this->manager->withLockRetry(function () use ($table, $name, $partition) {
            DB::statement(sprintf('ALTER TABLE %s DETACH PARTITION %s', $table, $name));
            $stats = DB::selectOne(sprintf('SELECT count(*) AS n, min(id) AS min_id, max(id) AS max_id FROM %s', $name));
            DB::statement(sprintf('DROP TABLE %s', $name));
            $now = now();
            DB::table('partition_archives')->updateOrInsert(['partition_name' => $name], [
                'table_name' => $table,
                'range_from' => $partition['from'],
                'range_to' => $partition['to'],
                'status' => 'dropped',
                'rows' => (int) $stats->n,
                'min_id' => $stats->min_id,
                'max_id' => $stats->max_id,
                'detached_at' => $now,
                'dropped_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, (string) config('partitions.detach_lock_timeout', '3s'));
    }

    /**
     * Which catalogue rows may be dropped now: `verified` (an off-site copy was downloaded back and checked) at least
     * drop_after_days ago; with ARCHIVE_ALLOW_LOCAL_ONLY=1 also `uploaded` ones. Never without sha256 and storage_uri.
     *
     * @return list<object>
     */
    public function droppable(): array
    {
        $before = now()->subDays((int) config('partitions.drop_after_days', 3));

        return DB::table('partition_archives')
            ->whereNotNull('sha256')
            ->whereNotNull('storage_uri')
            ->where(function ($query) use ($before) {
                $query->where(fn ($q) => $q->where('status', 'verified')->where('verified_at', '<=', $before));
                if (config('partitions.allow_local_only')) {
                    $query->orWhere(fn ($q) => $q->where('status', 'uploaded')->where('uploaded_at', '<=', $before));
                }
            })
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @return array{0: list<string>, 1: list<string>} the dropped and the kept partition names
     */
    private function dropArchived(): array
    {
        $dropped = [];
        $kept = [];
        foreach ($this->droppable() as $row) {
            if ($this->isAttached($row->partition_name)) {
                // Attached again by hand without marking it held: never dropped from under the live table
                $kept[] = (string) $row->partition_name;

                continue;
            }
            $this->manager->withLockRetry(function () use ($row) {
                DB::statement(sprintf('DROP TABLE IF EXISTS %s', $row->partition_name));
                DB::table('partition_archives')->where('id', $row->id)->update(['status' => 'dropped', 'dropped_at' => now(), 'updated_at' => now()]);
            }, (string) config('partitions.detach_lock_timeout', '3s'));
            $dropped[] = (string) $row->partition_name;
        }

        return [$dropped, $kept];
    }

    /**
     * rows / min_id / max_id of detached months, counted outside the detach transaction (a big month takes a while).
     */
    private function fillCounts(): void
    {
        foreach (DB::table('partition_archives')->where('status', 'detached')->whereNull('rows')->get() as $row) {
            if (!$this->manager->exists($row->partition_name)) {
                continue;
            }
            $stats = DB::selectOne(sprintf('SELECT count(*) AS n, min(id) AS min_id, max(id) AS max_id FROM %s', $row->partition_name));
            DB::table('partition_archives')->where('id', $row->id)->update([
                'rows' => (int) $stats->n, 'min_id' => $stats->min_id, 'max_id' => $stats->max_id, 'updated_at' => now(),
            ]);
        }
    }

    private function isAttached(string $name): bool
    {
        return DB::selectOne('SELECT EXISTS (SELECT 1 FROM pg_inherits i JOIN pg_class c ON c.oid = i.inhrelid JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = ? AND n.nspname = current_schema()) AS e', [$name])->e;
    }
}
