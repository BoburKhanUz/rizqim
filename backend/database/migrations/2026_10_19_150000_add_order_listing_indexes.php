<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;

/**
 * Indexes behind GET /api/v1/orders (OrderQueryService), which dashboards poll every few seconds:
 * - scope=active filters by status IN (non-terminal) per store / branch / courier;
 * - scope=history seeks by (created_at, id) < cursor, newest first, per store or customer;
 * - the eager load of order items looks them up by order_id (PostgreSQL does not index foreign keys itself).
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, array{string, list<string>}> */
    private const INDEXES = [
        'orders_store_id_status_created_at_index' => ['orders', ['store_id', 'status', 'created_at']],
        'orders_branch_id_status_created_at_index' => ['orders', ['branch_id', 'status', 'created_at']],
        'orders_courier_id_status_index' => ['orders', ['courier_id', 'status']],
        'orders_store_id_created_at_id_index' => ['orders', ['store_id', 'created_at', 'id']],
        'orders_user_id_created_at_id_index' => ['orders', ['user_id', 'created_at', 'id']],
        'order_items_order_id_index' => ['order_items', ['order_id']],
    ];

    public function up(): void
    {
        // Built without blocking writes on these live tables; an INVALID leftover of a failed build is rebuilt
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
