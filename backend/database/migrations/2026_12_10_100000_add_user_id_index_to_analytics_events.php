<?php

use App\Support\ConcurrentIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // CREATE INDEX CONCURRENTLY (per partition) cannot run inside a transaction
    public $withinTransaction = false;

    private const INDEX = 'analytics_events_user_id_index';

    /**
     * A privacy request (docs/OPERATIONS.md "Maxfiylik so'rovlari"): App\Domain\Analytics\AnalyticsErasure deletes
     * one user's rows of every month (DELETE ... WHERE user_id = ?, not pruned by received_at) and the user card counts
     * them. Without this index each of those read every month of the table. Built the partitioned-journal way
     * (ConcurrentIndex::createPartitioned: an invalid index ON ONLY the parent, each month and DEFAULT built
     * CONCURRENTLY and attached, then the parent is valid and months attached later get it), so ingestion inserts go
     * on meanwhile; run again after an interruption, it repairs. One index entry per stored event. Touches no cache.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        ConcurrentIndex::createPartitioned(self::INDEX, 'analytics_events', ['user_id']);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // A partitioned index cannot be dropped CONCURRENTLY; dropping the parent index drops every month's with it
        DB::statement('DROP INDEX IF EXISTS ' . self::INDEX);
    }
};
