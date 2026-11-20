<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wave M5 S2 (review): the owner's summary of offline discounts is worked out from pos_sync_operations after every
 * sync batch and by a schedule (App\Domain\Pos\Sync\OfflineDiscounts), no longer from a cache key. Two partial indexes
 * over the `order.discount` rows only (a small share of the table, so they cost a write only when a discount comes):
 *  - (device_id, id): a till's discounts after the last summary (pending(), once per batch);
 *  - (received_at): the tills with a discount in the last days (sendOverdue(), every 10 minutes).
 * Without them both would scan every operation of the till (the unique (device_id, seq) index) or the whole table.
 *
 * Built CONCURRENTLY (outside a transaction), like the other index migrations here; an INVALID leftover of an
 * interrupted build is rebuilt, so the migration can be run again.
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, string> */
    private const INDEXES = [
        'pos_sync_operations_discount_device_index' => '(device_id, id)',
        'pos_sync_operations_discount_received_index' => '(received_at)',
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $columns) {
            $definition = "ON pos_sync_operations {$columns} WHERE type = 'order.discount'";
            if (DB::getDriverName() !== 'pgsql') {
                DB::statement("CREATE INDEX IF NOT EXISTS {$name} {$definition}");

                continue;
            }
            if (ConcurrentIndex::isInvalid($name)) {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");
            }
            DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$name} {$definition}");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement(DB::getDriverName() === 'pgsql' ? "DROP INDEX CONCURRENTLY IF EXISTS {$name}" : "DROP INDEX IF EXISTS {$name}");
        }
    }
};
