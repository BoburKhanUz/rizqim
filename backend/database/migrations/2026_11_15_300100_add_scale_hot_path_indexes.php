<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wave M1 (scale quick wins): the indexes the load test measured on hot paths (docs/scale/RESULTS.md 6-7,
 * scripts/scale-test/sql/indexes.sql), and two redundant indexes that only cost a write per row.
 *
 * - notification_logs (user_id, id): the notification feed and badge (NotificationFeedService) filter by user_id
 *   and order by id desc; without it every poll walked the primary key backwards over the whole table.
 * - product_variants (product_id), products (store_id, status): menu loads of every till and the marketplace.
 * - order_items (voided_at) WHERE voided_at IS NOT NULL: ShiftReport's voided lines (X/Z report) scanned the
 *   branch's whole item history. Partial, so it costs nothing for the lines that are never voided.
 * - payments (order_id), notification_logs (order_id): foreign keys without an index (close/refund lookups,
 *   parent deletes). PostgreSQL does not index foreign keys itself.
 * - dropped: audit_logs_model_type_model_id_index is an exact duplicate of audit_logs_model_index;
 *   order_items_order_id_index is a prefix of order_items_order_id_client_line_id_index, which serves every
 *   order_id lookup as well.
 *
 * Built and dropped CONCURRENTLY (outside a transaction), like the other index migrations here: there is no live data
 * yet, but on a large table a plain CREATE INDEX holds a lock that blocks till writes for the whole build.
 * ConcurrentIndex::create() rebuilds an INVALID leftover of an interrupted build, so re-running the migration repairs it.
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, array{string, list<string>}> */
    private const CREATED = [
        'notification_logs_user_id_id_index' => ['notification_logs', ['user_id', 'id']],
        'product_variants_product_id_index' => ['product_variants', ['product_id']],
        'products_store_id_status_index' => ['products', ['store_id', 'status']],
        'payments_order_id_index' => ['payments', ['order_id']],
        'notification_logs_order_id_index' => ['notification_logs', ['order_id']],
    ];

    private const PARTIAL_NAME = 'order_items_voided_at_partial_index';

    /** Redundant indexes dropped here and rebuilt by down() (the migrations that made them drop them on rollback). */
    private const DROPPED = [
        'audit_logs_model_type_model_id_index' => ['audit_logs', ['model_type', 'model_id']],
        'order_items_order_id_index' => ['order_items', ['order_id']],
    ];

    public function up(): void
    {
        foreach (self::CREATED as $name => [$table, $columns]) {
            ConcurrentIndex::create($name, $table, $columns);
        }

        $this->createPartial();

        foreach (array_keys(self::DROPPED) as $name) {
            $this->dropIfExists($name);
        }
    }

    public function down(): void
    {
        foreach (self::DROPPED as $name => [$table, $columns]) {
            ConcurrentIndex::create($name, $table, $columns);
        }

        $this->dropIfExists(self::PARTIAL_NAME);

        foreach (array_reverse(self::CREATED, true) as $name => [$table]) {
            ConcurrentIndex::drop($name, $table);
        }
    }

    private function createPartial(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS '.self::PARTIAL_NAME.' ON order_items (voided_at) WHERE voided_at IS NOT NULL');

            return;
        }

        if (ConcurrentIndex::isInvalid(self::PARTIAL_NAME)) {
            DB::statement('DROP INDEX CONCURRENTLY IF EXISTS '.self::PARTIAL_NAME);
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS '.self::PARTIAL_NAME.' ON order_items (voided_at) WHERE voided_at IS NOT NULL');
    }

    private function dropIfExists(string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$name}");

            return;
        }

        DB::statement("DROP INDEX IF EXISTS {$name}");
    }
};
