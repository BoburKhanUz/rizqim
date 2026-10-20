<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * scope=history of GET /api/v1/orders for a courier (courier_id) and a branch manager (branch_id): both seek by
 * (created_at, id) < cursor, newest first, which the (courier_id, status) and (store_id, created_at, id) indexes of
 * 2026_10_19_150000 cannot do for them.
 *
 * Also repairs the indexes of 2026_10_19_150000: where a CONCURRENTLY build of one failed, it is INVALID and that
 * migration (already recorded as run) would never rebuild it. ConcurrentIndex::create() drops and rebuilds only an
 * invalid one; valid ones are left as they are.
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, array{string, list<string>}> */
    private const INDEXES = [
        'orders_courier_id_created_at_id_index' => ['orders', ['courier_id', 'created_at', 'id']],
        'orders_branch_id_created_at_id_index' => ['orders', ['branch_id', 'created_at', 'id']],
    ];

    /** The indexes of 2026_10_19_150000_add_order_listing_indexes (owned, and dropped, by that migration). */
    private const REPAIRED = [
        'orders_store_id_status_created_at_index' => ['orders', ['store_id', 'status', 'created_at']],
        'orders_branch_id_status_created_at_index' => ['orders', ['branch_id', 'status', 'created_at']],
        'orders_courier_id_status_index' => ['orders', ['courier_id', 'status']],
        'orders_store_id_created_at_id_index' => ['orders', ['store_id', 'created_at', 'id']],
        'orders_user_id_created_at_id_index' => ['orders', ['user_id', 'created_at', 'id']],
        'order_items_order_id_index' => ['order_items', ['order_id']],
    ];

    public function up(): void
    {
        // Repair only: on other drivers nothing is built CONCURRENTLY, so nothing can be left invalid
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::REPAIRED as $name => [$table, $columns]) {
                ConcurrentIndex::create($name, $table, $columns);
            }
        }

        foreach (self::INDEXES as $name => [$table, $columns]) {
            ConcurrentIndex::create($name, $table, $columns);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $name => [$table]) {
            ConcurrentIndex::drop($name, $table);
        }
    }
};
