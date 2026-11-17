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

        $converter = new PartitionConverter(new PartitionManager());
        foreach (['fiscal_receipts', 'audit_logs', 'notification_logs'] as $table) {
            $converter->revertInline($table);
        }
    }
};
