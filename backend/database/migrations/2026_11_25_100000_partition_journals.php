<?php

use App\Support\Partitions\PartitionConverter;
use App\Support\Partitions\PartitionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Each table is converted in its own transaction (App\Support\Partitions\PartitionConverter::convertInline)
    public $withinTransaction = false;

    /**
     * Wave M3 (docs/scale/M3_DESIGN.md section 4): notification_logs (by sent_at), audit_logs and fiscal_receipts (by
     * created_at) become tables partitioned by month, with a DEFAULT partition and months from config
     * partitions.first_month to now + 3. Primary keys become (id, <column>); fiscal_receipts' uniques become
     * partition-local, its self foreign key is dropped and created_at is backfilled and NOT NULL.
     *
     * Inline only while a table is small (config partitions.inline_max_rows): a bigger one makes this migration stop
     * with a message telling the operator to run `php artisan db:partitions:convert <table>` (online) first. A table
     * already partitioned (by that command) is skipped.
     *
     * Rules for FUTURE migrations on these three tables: add columns on the parent only (they propagate to every
     * partition); never CREATE INDEX CONCURRENTLY on the parent (use App\Support\ConcurrentIndex::createPartitioned);
     * every UNIQUE includes the partition column; no foreign key may reference these tables; never UPDATE the partition
     * column; raw SQL on a partition name only in app/Support/Partitions.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $converter = new PartitionConverter(new PartitionManager());
        foreach (['notification_logs', 'audit_logs', 'fiscal_receipts'] as $table) {
            $converter->convertInline($table);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Months detached from these tables (waiting for the archive, or held) would be left behind as orphan tables
        // and their rows missing from the plain tables: refused until they are archived and dropped, or attached again
        $manager = new PartitionManager();
        $open = [];
        if ($manager->exists('partition_archives')) {
            $open = DB::table('partition_archives')->where('status', '!=', 'dropped')->orderBy('partition_name')->pluck('partition_name')->all();
        }
        foreach (array_keys(PartitionManager::tables()) as $table) {
            foreach (DB::select("SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = current_schema() AND c.relkind = 'r' AND NOT c.relispartition AND c.relname ~ ?", ['^' . $table . '_[0-9]{4}_[0-9]{2}$']) as $row) {
                $open[] = $row->relname;
            }
        }
        $open = array_values(array_unique($open));
        if ($open !== []) {
            throw new \RuntimeException("Orqaga qaytarish rad etildi: jadvaldan ajratilgan oylar bor (" . implode(', ', $open) . "). Ularning qatorlari oddiy jadvalga qaytmaydi: avval arxivlab o'chiring yoki qayta ulang.");
        }

        $converter = new PartitionConverter($manager);
        foreach (['fiscal_receipts', 'audit_logs', 'notification_logs'] as $table) {
            $converter->revertInline($table);
        }
    }
};
