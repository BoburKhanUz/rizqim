<?php

namespace App\Support\Partitions;

use Illuminate\Database\QueryException;
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
     * next one. A month whose locks cannot be had within the bound is left for the next run ('failed', with the reason).
     *
     * @return array{dropped: list<string>, detached: list<string>, deleted: list<string>, kept: list<string>, skipped: list<string>, failed: array<string, string>}
     */
    public function run(): array
    {
        [$dropped, $kept] = $this->dropArchived();
        $report = ['dropped' => $dropped, 'detached' => [], 'deleted' => [], 'kept' => $kept, 'skipped' => [], 'failed' => []];
        $this->finishDetached($report);
        foreach (PartitionManager::tables() as $table => $config) {
            if ($this->manager->conversionState($table) !== 'none') {
                // The online conversion keeps an old or a new copy of the table: no month leaves before it is settled
                $report['skipped'][] = $table;

                continue;
            }
            foreach ($this->manager->dueForArchive($table) as $partition) {
                try {
                    if ($config['archive']) {
                        $this->detach($table, $partition);
                        $report['detached'][] = $partition['name'];
                    } else {
                        $this->detachAndDrop($table, $partition);
                        $report['deleted'][] = $partition['name'];
                    }
                } catch (QueryException $e) {
                    $report['failed'][$partition['name']] = (string) ($e->getPrevious()?->getMessage() ?? $e->getMessage());
                }
            }
        }
        $this->finishDetached($report);

        return $report;
    }

    /**
     * One month out of the live table. The transaction that holds the parent's lock does no waiting of its own: the
     * referenced tables (SHARE ROW EXCLUSIVE, which a till's foreign key check does not wait for) are taken first, then
     * the parent, the month and DEFAULT (ACCESS EXCLUSIVE, which a till's insert does wait for), each LOCK bounded as
     * a whole; then DETACH, the triggers put back, the catalogue row. A till's insert waits at most about one
     * detach_lock_timeout, then the step gives up (retried, then left for the next run). The month's foreign keys
     * are dropped afterwards, one per short transaction (PartitionManager::dropForeignKeys).
     *
     * @param  array{name: string, from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $partition
     */
    public function detach(string $table, array $partition): void
    {
        $this->detachOnly($table, $partition, catalogue: true);
        $this->manager->dropForeignKeys($partition['name']);
        $this->fillCounts();
    }

    /**
     * archive => false (notifications): detached the same way, its foreign keys dropped, then dropped, recorded as
     * `dropped` (no archive copy).
     *
     * @param  array{name: string, from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $partition
     */
    public function detachAndDrop(string $table, array $partition): void
    {
        $this->detachOnly($table, $partition, catalogue: false);
        $this->dropDetachedMonth($table, $partition['name'], $partition['from'], $partition['to']);
    }

    /**
     * @param  array{name: string, from: \Carbon\CarbonImmutable, to: \Carbon\CarbonImmutable}  $partition
     */
    private function detachOnly(string $table, array $partition, bool $catalogue): void
    {
        $name = $partition['name'];
        $default = PartitionManager::defaultName($table);
        $this->manager->withLockRetry(function () use ($table, $name, $partition, $catalogue, $default) {
            $referenced = $this->manager->referencedTables($table);
            if ($referenced !== []) {
                $this->manager->boundedLock('LOCK TABLE ' . implode(', ', $referenced) . ' IN SHARE ROW EXCLUSIVE MODE', $this->timeout());
            }
            $this->manager->boundedLock(sprintf('LOCK TABLE ONLY %s, %s%s IN ACCESS EXCLUSIVE MODE', $table, $name, $this->manager->exists($default) ? ", {$default}" : ''), $this->timeout());
            $triggers = $this->manager->userTriggers($table, [$table . '_m3_capture']);
            // Not CONCURRENTLY: refused while a DEFAULT partition exists
            DB::statement(sprintf('ALTER TABLE %s DETACH PARTITION %s', $table, $name));
            // DETACH removes the cloned triggers: the parent's are put back on the month (append-only audit, the fiscal
            // key guards) until it is dropped (DROP fires none)
            $this->manager->copyTriggers($triggers, $name);
            if (!$catalogue) {
                return;
            }
            $now = now();
            // A month detached again (it was `held`) starts a fresh archive record: the earlier dump, checksum and
            // verification do not cover rows added while it was attached
            DB::table('partition_archives')->updateOrInsert(['partition_name' => $name], [
                'table_name' => $table,
                'range_from' => $partition['from'],
                'range_to' => $partition['to'],
                'status' => 'detached',
                'rows' => null, 'min_id' => null, 'max_id' => null, 'bytes' => null, 'sha256' => null, 'storage_uri' => null,
                'detached_at' => $now, 'uploaded_at' => null, 'verified_at' => null, 'dropped_at' => null,
                'error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $this->timeout());
    }

    private function dropDetachedMonth(string $table, string $name, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $this->manager->dropForeignKeys($name);
        $this->manager->withLockRetry(function () use ($table, $name, $from, $to) {
            $this->manager->boundedLock("LOCK TABLE {$name} IN ACCESS EXCLUSIVE MODE", $this->timeout());
            $stats = DB::selectOne(sprintf('SELECT count(*) AS n, min(id) AS min_id, max(id) AS max_id FROM %s', $name));
            DB::statement(sprintf('DROP TABLE %s', $name));
            $now = now();
            DB::table('partition_archives')->updateOrInsert(['partition_name' => $name], [
                'table_name' => $table,
                'range_from' => $from,
                'range_to' => $to,
                'status' => 'dropped',
                'rows' => (int) $stats->n,
                'min_id' => $stats->min_id,
                'max_id' => $stats->max_id,
                'detached_at' => $now,
                'dropped_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $this->timeout());
    }

    /**
     * Steps a killed run left: detached months with foreign keys or no counts, and notification months detached but
     * not dropped (a standalone month table of an archive => false table that the catalogue does not know).
     *
     * @param  array{dropped: list<string>, detached: list<string>, deleted: list<string>, kept: list<string>, skipped: list<string>, failed: array<string, string>}  $report
     */
    private function finishDetached(array &$report): void
    {
        foreach (DB::table('partition_archives')->where('status', 'detached')->pluck('partition_name') as $name) {
            if ($this->manager->exists((string) $name)) {
                try {
                    $this->manager->dropForeignKeys((string) $name);
                } catch (QueryException $e) {
                    $report['failed'][(string) $name] = (string) ($e->getPrevious()?->getMessage() ?? $e->getMessage());
                }
            }
        }
        $this->fillCounts();
        foreach (PartitionManager::tables() as $table => $config) {
            if ($config['archive']) {
                continue;
            }
            $known = DB::table('partition_archives')->where('table_name', $table)->pluck('partition_name')->all();
            foreach (DB::select(
                "SELECT c.relname AS name FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                 WHERE n.nspname = current_schema() AND c.relkind = 'r' AND c.relname ~ ? AND NOT c.relispartition",
                ['^' . $table . '_[0-9]{4}_[0-9]{2}$'],
            ) as $orphan) {
                if (in_array($orphan->name, $known, true)) {
                    continue;
                }
                $month = \Carbon\CarbonImmutable::createFromFormat('!Y_m', substr((string) $orphan->name, strlen($table) + 1), 'UTC');
                try {
                    $this->dropDetachedMonth($table, (string) $orphan->name, $month, $month->addMonth());
                    $report['deleted'][] = (string) $orphan->name;
                } catch (QueryException $e) {
                    $report['failed'][(string) $orphan->name] = (string) ($e->getPrevious()?->getMessage() ?? $e->getMessage());
                }
            }
        }
    }

    private function timeout(): string
    {
        return (string) config('partitions.detach_lock_timeout', '3s');
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
                // A detached month has no foreign key left (finishDetached): dropping it locks nothing but itself
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
