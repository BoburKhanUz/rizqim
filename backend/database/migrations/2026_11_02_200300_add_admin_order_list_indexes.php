<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;

/**
 * P4-14: GET /admin/orders lists orders of every store newest first, most often narrowed to a status group (the live
 * list is the active statuses), and the SLA sources and watcher read the active statuses only; the existing order
 * indexes all start with store, branch, courier or customer. The plain time range is P4-13's orders(created_at).
 * Built CONCURRENTLY (orders is a live table).
 */
return new class extends Migration
{
    // CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction
    public $withinTransaction = false;

    /** @var array<string, array{string, list<string>}> */
    private const INDEXES = [
        'orders_admin_status_created_at_index' => ['orders', ['status', 'created_at']],
    ];

    public function up(): void
    {
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
