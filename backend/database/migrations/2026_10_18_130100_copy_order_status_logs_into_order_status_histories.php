<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges order_status_logs into order_status_histories, the table every reader uses. Transitions used to be written
 * to both, so most log rows have a history twin: same order, status and user. Within such a group logs and histories
 * pair up in time order (created_at, then id); a twin takes the log's from_status, and a log without one is copied as
 * a history row that keeps the log's id in order_status_log_id (added by the previous migration).
 *
 * Set-based: one UPDATE and one INSERT, so the transaction is short and only touches rows (no table lock: checkout
 * and status changes keep writing the history meanwhile). Re-running copies nothing twice: copies are neither twins
 * nor pending logs. down() deletes only the copies.
 */
return new class extends Migration
{
    /**
     * Logs not copied yet and the histories that are not copies, each ranked within its (order, status, user) group;
     * a log and a history of the same group and rank are twins.
     */
    private const RANKED = <<<'SQL'
        WITH pending_logs AS (
            SELECT l.id, l.order_id, l.to_status AS status, l.user_id, l.from_status, l.comment, l.created_at,
                   row_number() OVER (PARTITION BY l.order_id, l.to_status, l.user_id ORDER BY l.created_at, l.id) AS rank
            FROM order_status_logs l
            WHERE NOT EXISTS (SELECT 1 FROM order_status_histories c WHERE c.order_status_log_id = l.id)
        ),
        twins AS (
            SELECT h.id, h.order_id, h.status, h.user_id,
                   row_number() OVER (PARTITION BY h.order_id, h.status, h.user_id ORDER BY h.created_at, h.id) AS rank
            FROM order_status_histories h
            WHERE h.order_status_log_id IS NULL
        )
        SQL;

    private const SAME_GROUP_AND_RANK = 'twins.order_id = pending_logs.order_id AND twins.status = pending_logs.status'
        . ' AND twins.user_id IS NOT DISTINCT FROM pending_logs.user_id AND twins.rank = pending_logs.rank';

    public function up(): void
    {
        if (!Schema::hasTable('order_status_logs') || !Schema::hasColumn('order_status_histories', 'order_status_log_id')) {
            return;
        }

        // Twins gain the log's from_status
        DB::update(self::RANKED . '
            UPDATE order_status_histories
            SET from_status = pending_logs.from_status
            FROM twins JOIN pending_logs ON ' . self::SAME_GROUP_AND_RANK . '
            WHERE order_status_histories.id = twins.id
              AND order_status_histories.from_status IS NULL
              AND pending_logs.from_status IS NOT NULL');

        // Logs without a twin become history rows
        DB::insert(self::RANKED . '
            INSERT INTO order_status_histories (order_id, user_id, from_status, status, notes, created_at, updated_at, order_status_log_id)
            SELECT pending_logs.order_id, pending_logs.user_id, pending_logs.from_status, pending_logs.status, pending_logs.comment,
                   pending_logs.created_at, pending_logs.created_at, pending_logs.id
            FROM pending_logs
            WHERE NOT EXISTS (SELECT 1 FROM twins WHERE ' . self::SAME_GROUP_AND_RANK . ')
            ORDER BY pending_logs.created_at, pending_logs.id');
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_status_histories', 'order_status_log_id')) {
            DB::table('order_status_histories')->whereNotNull('order_status_log_id')->delete();
        }
    }
};
